<?php

namespace App\Http\Resources;

use App\Models\Driver;
use App\Models\WhatsappGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Driver
 */
class DriverResource extends JsonResource
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
            'phone' => $this->phone,
            'name' => $this->name,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'opted_out_at' => $this->opted_out_at?->toIso8601String(),
            'notes' => $this->notes,
            'groups' => $this->relationLoaded('whatsappGroups') ? $this->whatsappGroups->map(function (WhatsappGroup $group): array {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'first_seen_at' => data_get($group->pivot, 'first_seen_at'),
                ];
            }) : [],
            'status_histories' => $this->relationLoaded('statusHistories')
                ? DriverStatusHistoryResource::collection($this->statusHistories)
                : [],
            'metrics' => $this->relationLoaded('metrics') && $this->metrics ? [
                'trips_completed' => $this->metrics->trips_completed,
                'cancellations' => $this->metrics->cancellations,
                'no_shows' => $this->metrics->no_shows,
                'late_count' => $this->metrics->late_count,
                'avg_rating' => $this->metrics->avg_rating,
                'ratings_count' => $this->metrics->ratings_count,
                'last_trip_at' => $this->metrics->last_trip_at?->toIso8601String(),
            ] : null,
            'answers_count' => $this->relationLoaded('currentAnswers') ? $this->currentAnswers->count() : ($this->current_answers_count ?? 0),
            'is_new_to_fleet' => $this->relationLoaded('metrics') ? (($this->metrics ? $this->metrics->trips_completed : 0) === 0) : true,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
