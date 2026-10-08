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
        bool $includeUnrated = false
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
                        $sub->answeredWith($question, $normalizedOp, $val);
                    });
                }
            });
        }

        return $query;
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
            includeUnrated: (bool) $workOrder->include_unrated
        );
    }
}
