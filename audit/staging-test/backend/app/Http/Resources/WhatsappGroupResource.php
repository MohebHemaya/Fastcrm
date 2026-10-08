<?php

namespace App\Http\Resources;

use App\Models\WhatsappGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WhatsappGroup
 */
class WhatsappGroupResource extends JsonResource
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
            'name' => $this->name,
            'drivers_count' => $this->drivers_count ?? 0,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
