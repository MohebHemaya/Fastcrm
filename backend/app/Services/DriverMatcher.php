<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\Question;
use App\Models\WorkOrder;
use App\Models\WorkOrderFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DriverMatcher
{
    /**
     * Build the query for matching drivers against work order filters and criteria.
     *
     * @param  iterable<mixed>  $filters
     * @return Builder<Driver>
     */
    public function buildQuery(
        iterable $filters = [],
        bool $excludeSameDayConfirmed = false,
        Carbon|string|null $scheduledAt = null,
        ?int $currentWorkOrderId = null,
        ?float $minAvgRating = null,
        ?int $maxNoShows = null,
        ?int $lastTripWithinDays = null,
        bool $includeUnrated = false,
        ?int $targetGroupId = null,
        ?string $driverExperience = null,
        ?string $phone = null
    ): Builder {
        // 1. Only drivers in ACTIVE status are eligible (never new, invited, opted_out, blocked, etc.)
        $query = Driver::query()->where('status', DriverStatus::ACTIVE);

        // 2. Filter exclusions for same-day confirmed drivers if requested
        if ($excludeSameDayConfirmed && $scheduledAt !== null) {
            $scheduledDate = $scheduledAt instanceof Carbon
                ? $scheduledAt->toDateString()
                : Carbon::parse($scheduledAt)->toDateString();

            $query->whereDoesntHave('workOrderRecipients', function (Builder $q) use ($scheduledDate, $currentWorkOrderId) {
                $q->where('status', WorkOrderRecipientStatus::CONFIRMED)
                    ->whereHas('workOrder', function (Builder $woQuery) use ($scheduledDate, $currentWorkOrderId) {
                        $woQuery->whereDate('scheduled_at', $scheduledDate)
                            ->where('status', '!=', WorkOrderStatus::CANCELLED);
                        if ($currentWorkOrderId) {
                            $woQuery->where('id', '!=', $currentWorkOrderId);
                        }
                    });
            });
        }

        // 2b. System metric filters: max_no_shows, min_avg_rating, last_trip_within_days
        if ($maxNoShows !== null) {
            $query->where(function (Builder $q) use ($maxNoShows) {
                $q->whereDoesntHave('metrics')
                    ->orWhereHas('metrics', function (Builder $mq) use ($maxNoShows) {
                        $mq->where('no_shows', '<=', $maxNoShows);
                    });
            });
        }

        if ($minAvgRating !== null) {
            if ($includeUnrated) {
                $query->where(function (Builder $q) use ($minAvgRating) {
                    $q->whereDoesntHave('metrics')
                        ->orWhereHas('metrics', function (Builder $mq) use ($minAvgRating) {
                            $mq->whereNull('avg_rating')
                                ->orWhere('avg_rating', '>=', $minAvgRating);
                        });
                });
            } else {
                $query->whereHas('metrics', function (Builder $mq) use ($minAvgRating) {
                    $mq->whereNotNull('avg_rating')
                        ->where('avg_rating', '>=', $minAvgRating);
                });
            }
        }

        if ($lastTripWithinDays !== null) {
            $cutoff = Carbon::now()->subDays($lastTripWithinDays);
            $query->whereHas('metrics', function (Builder $mq) use ($cutoff) {
                $mq->whereNotNull('last_trip_at')
                    ->where('last_trip_at', '>=', $cutoff);
            });
        }

        // 2c. Target WhatsApp Group filter
        if ($targetGroupId !== null) {
            $query->whereHas('whatsappGroups', function (Builder $gq) use ($targetGroupId) {
                $gq->where('whatsapp_groups.id', $targetGroupId);
            });
        }

        // 2d. Driver Experience filter: all | new_only | experienced_only
        if ($driverExperience === 'new_only') {
            $query->where(function (Builder $eq) {
                $eq->whereDoesntHave('metrics')
                    ->orWhereHas('metrics', function (Builder $mq) {
                        $mq->where('trips_completed', 0);
                    });
            });
        } elseif ($driverExperience === 'experienced_only') {
            $query->whereHas('metrics', function (Builder $mq) {
                $mq->where('trips_completed', '>', 0);
            });
        }

        // 2e. Phone number filter
        if ($phone !== null && trim($phone) !== '') {
            $cleaned = trim($phone);
            $query->where('phone', 'like', "%{$cleaned}%");
        }

        // 3. Group filters by question_id (AND between different questions, OR between options of same question)
        $groupedFilters = [];
        foreach ($filters as $f) {
            if ($f instanceof WorkOrderFilter) {
                $qId = $f->question_id;
                $operator = $f->operator->value;
                $value = $f->value;
            } else {
                $qId = (int) ($f['question_id'] ?? 0);
                $operator = is_object($f['operator'] ?? null) ? $f['operator']->value : (string) ($f['operator'] ?? 'in');
                $value = $f['value'] ?? null;
            }

            if ($qId > 0) {
                $groupedFilters[$qId][] = [
                    'operator' => $operator,
                    'value' => $value,
                ];
            }
        }

        if (empty($groupedFilters)) {
            return $query;
        }

        // Validate that each question has is_filter = true
        $questionIds = array_keys($groupedFilters);
        $filterQuestions = Question::whereIn('id', $questionIds)
            ->where('is_filter', true)
            ->get()
            ->keyBy('id');

        foreach ($groupedFilters as $qId => $filterSpecs) {
            $question = $filterQuestions->get($qId);
            if (! $question) {
                // If question is not marked is_filter = true, exclude it from query filters
                continue;
            }

            // AND between questions: this question must match
            // Inside the same question: OR between multiple filter rules or options
            $query->where(function (Builder $questionGroup) use ($question, $filterSpecs) {
                foreach ($filterSpecs as $idx => $spec) {
                    $op = $spec['operator'];
                    $val = $spec['value'];

                    $normalizedOp = match ($op) {
                        'gte', '>=' => '>=',
                        'lte', '<=' => '<=',
                        'eq', '=' => '=',
                        default => 'in',
                    };

                    $method = ($idx === 0) ? 'where' : 'orWhere';
                    $questionGroup->{$method}(function ($sub) use ($question, $val, $normalizedOp) {
                        /** @var Driver|Builder<Driver> $sub */
                        if ($question->key === 'whatsapp_group') {
                            $this->applyWhatsAppGroupFilter($sub, $normalizedOp, $val);
                        } elseif ($question->key === 'driver_experience') {
                            $this->applyDriverExperienceFilter($sub, $normalizedOp, $val);
                        } else {
                            $sub->answeredWith($question, $normalizedOp, $val);
                        }
                    });
                }
            });
        }

        return $query;
    }

    /**
     * Filter drivers by WhatsApp group membership via criteria.
     *
     * @param  Builder<Driver>  $query
     */
    protected function applyWhatsAppGroupFilter(Builder $query, string $operator, mixed $value): void
    {
        $raw = is_array($value) ? $value : (is_string($value) && str_contains($value, ',') ? explode(',', $value) : [$value]);
        $ids = array_values(array_filter(array_map('intval', $raw), fn (int $id) => $id > 0));

        if (empty($ids)) {
            return;
        }

        if ($operator === '=' && count($ids) === 1) {
            $query->whereHas('whatsappGroups', function (Builder $gq) use ($ids) {
                $gq->where('whatsapp_groups.id', $ids[0]);
            });
        } else {
            $query->whereHas('whatsappGroups', function (Builder $gq) use ($ids) {
                $gq->whereIn('whatsapp_groups.id', $ids);
            });
        }
    }

    /**
     * Filter drivers by fleet experience metrics via criteria.
     *
     * @param  Builder<Driver>  $query
     */
    protected function applyDriverExperienceFilter(Builder $query, string $operator, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $hasNew = in_array('new_only', $values, true);
        $hasExp = in_array('experienced_only', $values, true);

        // If both selected or neither, all drivers match
        if (($hasNew && $hasExp) || (! $hasNew && ! $hasExp)) {
            return;
        }

        if ($hasNew) {
            $query->where(function (Builder $eq) {
                $eq->whereDoesntHave('metrics')
                    ->orWhereHas('metrics', function (Builder $mq) {
                        $mq->where('trips_completed', 0);
                    });
            });
        } elseif ($hasExp) {
            $query->whereHas('metrics', function (Builder $mq) {
                $mq->where('trips_completed', '>', 0);
            });
        }
    }

    /**
     * Match drivers for an existing WorkOrder model.
     *
     * @return Builder<Driver>
     */
    public function matchForWorkOrder(WorkOrder $workOrder): Builder
    {
        return $this->buildQuery(
            filters: $workOrder->filters,
            excludeSameDayConfirmed: (bool) $workOrder->exclude_same_day_confirmed,
            scheduledAt: $workOrder->scheduled_at,
            currentWorkOrderId: $workOrder->id,
            minAvgRating: $workOrder->min_avg_rating,
            maxNoShows: $workOrder->max_no_shows,
            lastTripWithinDays: $workOrder->last_trip_within_days,
            includeUnrated: (bool) $workOrder->include_unrated,
            targetGroupId: $workOrder->target_group_id,
            driverExperience: $workOrder->driver_experience
        );
    }
}
