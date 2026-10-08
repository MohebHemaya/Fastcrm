<?php

namespace App\Models;

use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $code
 * @property string $from_location
 * @property string $to_location
 * @property Carbon $scheduled_at
 * @property string|null $cargo_type
 * @property float|null $pay_amount
 * @property string|null $notes
 * @property int $trips_needed
 * @property int $backup_needed
 * @property WorkOrderStatus $status
 * @property int $confirmed_count
 * @property int $backup_count
 * @property int $sent_count
 * @property Carbon|null $filled_at
 * @property string $send_strategy
 * @property int|null $batch_size
 * @property int|null $batch_interval_minutes
 * @property bool $exclude_same_day_confirmed
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User|null $creator
 */
class WorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'from_location',
        'to_location',
        'scheduled_at',
        'cargo_type',
        'pay_amount',
        'notes',
        'trips_needed',
        'backup_needed',
        'status',
        'confirmed_count',
        'backup_count',
        'sent_count',
        'filled_at',
        'send_strategy',
        'batch_size',
        'batch_interval_minutes',
        'exclude_same_day_confirmed',
        'min_avg_rating',
        'max_no_shows',
        'last_trip_within_days',
        'include_unrated',
        'cancellation_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'filled_at' => 'datetime',
            'pay_amount' => 'decimal:2',
            'status' => WorkOrderStatus::class,
            'exclude_same_day_confirmed' => 'boolean',
            'include_unrated' => 'boolean',
            'min_avg_rating' => 'float',
            'max_no_shows' => 'integer',
            'last_trip_within_days' => 'integer',
            'trips_needed' => 'integer',
            'backup_needed' => 'integer',
            'confirmed_count' => 'integer',
            'backup_count' => 'integer',
            'sent_count' => 'integer',
            'batch_size' => 'integer',
            'batch_interval_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WorkOrderFilter, $this>
     */
    public function filters(): HasMany
    {
        return $this->hasMany(WorkOrderFilter::class);
    }

    /**
     * @return HasMany<WorkOrderRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(WorkOrderRecipient::class);
    }

    /**
     * @return HasMany<WorkOrderRecipient, $this>
     */
    public function confirmedRecipients(): HasMany
    {
        return $this->hasMany(WorkOrderRecipient::class)
            ->where('status', WorkOrderRecipientStatus::CONFIRMED)
            ->orderBy('sequence_number', 'asc');
    }

    /**
     * @return HasMany<WorkOrderRecipient, $this>
     */
    public function backupRecipients(): HasMany
    {
        return $this->hasMany(WorkOrderRecipient::class)
            ->where('status', WorkOrderRecipientStatus::BACKUP)
            ->orderBy('sequence_number', 'asc');
    }

    /**
     * @return HasMany<WorkOrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(WorkOrderEvent::class)->orderBy('id', 'desc');
    }

    /**
     * Check if work order has open/unfilled confirmed slots.
     */
    public function hasOpenSlots(): bool
    {
        return $this->confirmed_count < $this->trips_needed;
    }

    public function getHasOpenSlotsAttribute(): bool
    {
        return $this->hasOpenSlots();
    }

    public function getOpenSlotsCountAttribute(): int
    {
        return max(0, $this->trips_needed - $this->confirmed_count);
    }

    /**
     * Generate the next sequential code: WO-0001, WO-0002, etc.
     */
    public static function generateCode(): string
    {
        $lastOrder = DB::table('work_orders')
            ->select('code')
            ->orderByDesc('id')
            ->first();

        if (! $lastOrder || ! preg_match('/WO-(\d+)/', $lastOrder->code, $matches)) {
            return 'WO-0001';
        }

        $nextNum = ((int) $matches[1]) + 1;

        return sprintf('WO-%04d', $nextNum);
    }
}
