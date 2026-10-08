<?php

namespace App\Http\Resources;

use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WhatsAppMessage
 */
class WhatsAppMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'driver_name' => $this->driver->name,
            'driver_phone' => $this->driver->phone,
            'direction' => $this->direction->value,
            'direction_label' => $this->direction->value === 'out' ? 'صادرة' : 'واردة',
            'type' => $this->type->value,
            'template_name' => $this->template_name,
            'body' => $this->body,
            'payload' => $this->payload,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'provider_message_id' => $this->provider_message_id,
            'order_id' => $this->order_id,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
