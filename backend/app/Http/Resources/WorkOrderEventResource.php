<?php

namespace App\Http\Resources;

use App\Models\WorkOrderEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrderEvent
 */
class WorkOrderEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'driver_id' => $this->driver_id,
            'driver_name' => $this->driver?->name,
            'action' => $this->action,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
