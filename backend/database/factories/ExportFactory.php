<?php

namespace Database\Factories;

use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Models\Export;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    protected $model = Export::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => ExportType::DRIVERS,
            'params' => [],
            'status' => ExportStatus::QUEUED,
            'path' => null,
            'filename' => 'drivers_export.xlsx',
            'error' => null,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExportStatus::DONE,
            'path' => 'exports/sample_file.xlsx',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExportStatus::FAILED,
            'error' => 'An unexpected error occurred during export.',
        ]);
    }
}
