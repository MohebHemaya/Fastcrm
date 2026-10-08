<?php

namespace Database\Factories;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            'code' => WorkOrder::generateCode(),
            'from_location' => fake()->city(),
            'to_location' => fake()->city(),
            'scheduled_at' => now()->addDays(2)->setHour(9)->setMinute(0),
            'cargo_type' => 'بضائع عامة',
            'pay_amount' => fake()->randomFloat(2, 500, 3000),
            'notes' => 'مطلوب الالتزام بالمواعيد',
            'trips_needed' => 10,
            'backup_needed' => 5,
            'status' => WorkOrderStatus::DRAFT,
            'confirmed_count' => 0,
            'backup_count' => 0,
            'sent_count' => 0,
            'send_strategy' => 'all',
            'exclude_same_day_confirmed' => false,
            'created_by' => User::factory(),
        ];
    }

    public function filling(): static
    {
        return $this->state(fn () => [
            'status' => WorkOrderStatus::FILLING,
        ]);
    }

    public function filled(): static
    {
        return $this->state(fn () => [
            'status' => WorkOrderStatus::FILLED,
            'confirmed_count' => 10,
            'backup_count' => 5,
            'filled_at' => now(),
        ]);
    }
}
