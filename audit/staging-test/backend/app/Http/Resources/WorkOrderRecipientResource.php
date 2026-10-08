<?php

namespace App\Http\Resources;

use App\Models\WorkOrderRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrderRecipient
 */
class WorkOrderRecipientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'driver_id' => $this->driver_id,
            'driver_name' => $this->driver?->name ?: 'كابتن',
            'driver_phone' => $this->driver?->phone,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'sequence_number' => $this->sequence_number,
            'stage_batch' => $this->stage_batch,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'outcome' => $this->outcome?->value,
            'outcome_label' => $this->outcome?->label(),
            'rating' => $this->rating,
            'outcome_notes' => $this->outcome_notes,
            'outcome_recorded_at' => $this->outcome_recorded_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'reminder_sent_at' => $this->reminder_sent_at?->toIso8601String(),
        ];
    }
}
