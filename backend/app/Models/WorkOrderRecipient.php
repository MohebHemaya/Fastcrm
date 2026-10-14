<?php

namespace App\Models;

use App\Enums\WorkOrderOutcome;
use App\Enums\WorkOrderRecipientStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $work_order_id
 * @property int $driver_id
 * @property WorkOrderRecipientStatus $status
 * @property int|null $sequence_number
 * @property int $stage_batch
 * @property Carbon|null $sent_at
 * @property Carbon|null $responded_at
 * @property WorkOrderOutcome|null $outcome
 * @property int|null $rating
 * @property string|null $outcome_notes
 * @property Carbon|null $outcome_recorded_at
 * @property int|null $outcome_recorded_by
 * @property string|null $cancellation_reason
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $reminder_sent_at
 * @property WorkOrder|null $workOrder
 * @property Driver|null $driver
 * @property User|null $outcomeRecorder
 */
class WorkOrderRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'driver_id',
        'status',
        'sequence_number',
        'stage_batch',
        'sent_at',
        'responded_at',
        'outcome',
        'rating',
        'outcome_notes',
        'outcome_recorded_at',
        'outcome_recorded_by',
        'cancellation_reason',
        'cancelled_at',
        'reminder_sent_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkOrderRecipientStatus::class,
            'outcome' => WorkOrderOutcome::class,
            'sequence_number' => 'integer',
            'stage_batch' => 'integer',
            'rating' => 'integer',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'outcome_recorded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function outcomeRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outcome_recorded_by');
    }
}
