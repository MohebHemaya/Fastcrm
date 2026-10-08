<?php

namespace Database\Factories;

use App\Enums\DriverStatus;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'phone' => '+2010'.fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'status' => DriverStatus::NEW,
            'opted_out_at' => null,
            'notes' => fake()->sentence(),
            'questionnaire_retries' => 0,
            'needs_review' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DriverStatus::ACTIVE,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DriverStatus::BLOCKED,
        ]);
    }
}
