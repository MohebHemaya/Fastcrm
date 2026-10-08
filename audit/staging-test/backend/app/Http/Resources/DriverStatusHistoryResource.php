<?php

namespace App\Http\Resources;

use App\Models\DriverStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DriverStatusHistory
 */
class DriverStatusHistoryResource extends JsonResource
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
            'from' => [
                'value' => $this->from->value,
                'label' => $this->from->label(),
            ],
            'to' => [
                'value' => $this->to->value,
                'label' => $this->to->label(),
            ],
            'reason' => $this->reason,
            'changed_by' => $this->changer ? [
                'id' => $this->changer->id,
                'name' => $this->changer->name,
            ] : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
