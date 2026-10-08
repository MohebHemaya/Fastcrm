<?php

namespace App\Http\Resources;

use App\Models\ImportRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportRow
 */
class ImportRowResource extends JsonResource
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
            'row_number' => $this->row_number,
            'raw_phone' => $this->raw_phone,
            'group_name' => $this->group_name,
            'normalized_phone' => $this->normalized_phone,
            'classification' => [
                'value' => $this->classification->value,
                'label' => $this->classification->label(),
            ],
            'error' => $this->error,
            'driver_id' => $this->driver_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
