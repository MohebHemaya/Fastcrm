<?php

namespace App\Models;

use App\Enums\DriverStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $phone
 * @property string|null $name
 * @property DriverStatus $status
 * @property Carbon|null $opted_out_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Collection<int, WhatsappGroup> $whatsappGroups
 * @property Collection<int, DriverStatusHistory> $statusHistories
 *
 * @method static Builder<Driver> answeredWith(\App\Models\Question|int|string $question, string $operator = '=', mixed $value = null)
 * @method Builder<Driver> answeredWith(\App\Models\Question|int|string $question, string $operator = '=', mixed $value = null)
 */
class Driver extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'name',
        'status',
        'opted_out_at',
        'notes',
        'questionnaire_retries',
        'questionnaire_last_sent_at',
        'questionnaire_reminder_sent_at',
        'needs_review',
        'review_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DriverStatus::class,
            'opted_out_at' => 'datetime',
            'questionnaire_retries' => 'integer',
            'questionnaire_last_sent_at' => 'datetime',
            'questionnaire_reminder_sent_at' => 'datetime',
            'needs_review' => 'boolean',
        ];
    }

    /**
     * WhatsApp groups this driver belongs to.
     *
     * @return BelongsToMany<WhatsappGroup, $this>
     */
    public function whatsappGroups(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappGroup::class, 'driver_group', 'driver_id', 'group_id')
            ->withPivot(['first_seen_at', 'import_id']);
    }

    /**
     * Status transition history records.
     *
     * @return HasMany<DriverStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(DriverStatusHistory::class)->orderBy('id', 'desc');
    }

    /**
     * Associated import rows.
     *
     * @return HasMany<ImportRow, $this>
     */
    public function importRows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /**
     * Work order recipient assignments for this driver.
     *
     * @return HasMany<WorkOrderRecipient, $this>
     */
    public function workOrderRecipients(): HasMany
    {
        return $this->hasMany(WorkOrderRecipient::class);
    }

    /**
     * Reliability and performance metrics.
     *
     * @return HasOne<DriverMetric, $this>
     */
    public function metrics(): HasOne
    {
        return $this->hasOne(DriverMetric::class);
    }

    /**
     * WhatsApp messages sent to or received from this driver.
     *
     * @return HasMany<WhatsAppMessage, $this>
     */
    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class)->latest();
    }

    /**
     * Alias for whatsappMessages.
     *
     * @return HasMany<WhatsAppMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->whatsappMessages();
    }

    /**
     * The most recent inbound message from this driver (for 24h window check).
     *
     * @return HasMany<WhatsAppMessage, $this>
     */
    public function inboundMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class)
            ->where('direction', 'in')
            ->latest();
    }

    /**
     * All answers recorded for this driver (including history).
     *
     * @return HasMany<DriverAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(DriverAnswer::class)->latest('answered_at');
    }

    /**
     * Current active answers for this driver.
     *
     * @return HasMany<DriverAnswer, $this>
     */
    public function currentAnswers(): HasMany
    {
        return $this->hasMany(DriverAnswer::class)
            ->where('is_current', true)
            ->with(['question', 'version', 'option']);
    }

    /**
     * Scope to find drivers who answered a specific question matching given criteria.
     * Only matches un-skipped, current (is_current = true) answers.
     * Missing or skipped answers never match.
     * Historical answers remain queryable even if the option is subsequently deactivated.
     *
     * @param  Builder<Driver>  $query
     * @param  Question|int|string  $question  Question model, id, or slug key
     * @param  string  $operator  Operator (=, !=, >, >=, <, <=, in, like)
     * @param  mixed  $value  Target value (option_id, option slug value, number, or text)
     * @return Builder<Driver>
     */
    public function scopeAnsweredWith(Builder $query, Question|int|string $question, string $operator = '=', mixed $value = null): Builder
    {
        return $query->whereHas('answers', function (Builder $q) use ($question, $operator, $value) {
            $q->where('is_current', true)
                ->where('is_skipped', false);

            if ($question instanceof Question) {
                $q->where('question_id', $question->id);
            } elseif (is_numeric($question)) {
                $q->where('question_id', (int) $question);
            } else {
                $q->whereHas('question', fn (Builder $qQ) => $qQ->where('key', $question));
            }

            $op = strtolower(trim($operator));

            if ($op === 'in') {
                $values = is_array($value) ? $value : [$value];
                $q->where(function (Builder $sub) use ($values) {
                    $numericValues = array_values(array_filter($values, 'is_numeric'));
                    if (! empty($numericValues)) {
                        $sub->whereIn('option_id', $numericValues)
                            ->orWhereIn('value_number', $numericValues);
                    }

                    $stringValues = array_values(array_map('strval', $values));
                    $lowerValues = array_map('strtolower', $stringValues);
                    $hasYes = count(array_intersect($lowerValues, ['yes', 'نعم', 'true', '1'])) > 0;
                    $hasNo = count(array_intersect($lowerValues, ['no', 'لا', 'false', '0'])) > 0;

                    if ($hasYes) {
                        $stringValues = array_unique(array_merge($stringValues, ['نعم', 'yes', 'true', '1']));
                    }
                    if ($hasNo) {
                        $stringValues = array_unique(array_merge($stringValues, ['لا', 'no', 'false', '0']));
                    }

                    $sub->orWhereIn('value_text', $stringValues)
                        ->orWhereHas('option', fn (Builder $qOpt) => $qOpt->whereIn('value', $stringValues)->orWhereIn('label_ar', $stringValues));
                });
            } else {
                $lowVal = is_string($value) ? strtolower(trim($value)) : (is_bool($value) ? ($value ? 'yes' : 'no') : '');
                if (in_array($lowVal, ['yes', 'نعم', 'true'], true)) {
                    $q->where(function (Builder $sub) {
                        $sub->whereIn('value_text', ['نعم', 'yes', '1', 'true'])
                            ->orWhere('value_number', 1);
                    });
                } elseif (in_array($lowVal, ['no', 'لا', 'false'], true)) {
                    $q->where(function (Builder $sub) {
                        $sub->whereIn('value_text', ['لا', 'no', '0', 'false'])
                            ->orWhere('value_number', 0);
                    });
                } else {
                    $q->where(function (Builder $sub) use ($op, $value) {
                        if (is_numeric($value)) {
                            $sub->where('value_number', $op, $value)
                                ->orWhere('option_id', $op, (int) $value);
                        }

                        if (is_string($value)) {
                            $sub->orWhere('value_text', $op, $value)
                                ->orWhereHas('option', fn (Builder $qOpt) => $qOpt->where('value', $op, $value)->orWhere('label_ar', $op, $value));
                        }
                    });
                }
            }
        });
    }
}
