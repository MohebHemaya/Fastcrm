<?php

namespace App\Http\Resources;

use App\Models\WorkOrder;
use App\Models\WorkOrderFilter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrder
 */
class WorkOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'from_location' => $this->from_location,
            'to_location' => $this->to_location,
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'cargo_type' => $this->cargo_type,
            'pay_amount' => $this->pay_amount,
            'notes' => $this->notes,
            'trips_needed' => $this->trips_needed,
            'backup_needed' => $this->backup_needed,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'confirmed_count' => $this->confirmed_count,
            'backup_count' => $this->backup_count,
            'sent_count' => $this->sent_count,
            'has_open_slots' => $this->hasOpenSlots(),
            'open_slots_count' => max(0, $this->trips_needed - $this->confirmed_count),
            'min_avg_rating' => $this->min_avg_rating,
            'max_no_shows' => $this->max_no_shows,
            'last_trip_within_days' => $this->last_trip_within_days,
            'include_unrated' => (bool) $this->include_unrated,
            'cancellation_reason' => $this->cancellation_reason,
            'filled_at' => $this->filled_at?->toIso8601String(),
            'send_strategy' => $this->send_strategy,
            'batch_size' => $this->batch_size,
            'batch_interval_minutes' => $this->batch_interval_minutes,
            'exclude_same_day_confirmed' => $this->exclude_same_day_confirmed,
            'created_at' => $this->created_at->toIso8601String(),
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'filters' => $this->whenLoaded('filters', fn () => $this->filters->map(fn (WorkOrderFilter $f) => [
                'id' => $f->id,
                'question_id' => $f->question_id,
                'question_key' => $f->question?->key,
                'question_text' => $f->question?->currentVersion?->text_ar,
                'operator' => $f->operator->value,
                'value' => $f->value,
            ])),
        ];
    }
}
