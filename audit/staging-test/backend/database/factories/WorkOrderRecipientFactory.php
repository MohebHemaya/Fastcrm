<?php

namespace Database\Factories;

use App\Enums\WorkOrderRecipientStatus;
use App\Models\Driver;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderRecipient>
 */
class WorkOrderRecipientFactory extends Factory
{
    protected $model = WorkOrderRecipient::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'driver_id' => Driver::factory(),
            'status' => WorkOrderRecipientStatus::PENDING,
            'sequence_number' => null,
            'stage_batch' => 1,
            'sent_at' => null,
            'responded_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => WorkOrderRecipientStatus::SENT,
            'sent_at' => now(),
        ]);
    }
}
