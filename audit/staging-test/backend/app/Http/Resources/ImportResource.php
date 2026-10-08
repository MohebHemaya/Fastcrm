<?php

namespace App\Http\Resources;

use App\Models\Import;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Import
 */
class ImportResource extends JsonResource
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
            'original_name' => $this->original_name,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'counts' => $this->counts ?? [
                'total' => 0,
                'new' => 0,
                'duplicate_in_file' => 0,
                'existing' => 0,
                'active' => 0,
                'invalid' => 0,
            ],
            'error' => $this->error,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
