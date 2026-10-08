<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_name' => 'drivers.xlsx',
            'stored_path' => 'imports/drivers.xlsx',
            'status' => ImportStatus::READY_FOR_REVIEW,
            'counts' => [
                'total' => 10,
                'new' => 7,
                'duplicate_in_file' => 1,
                'existing' => 1,
                'active' => 1,
                'invalid' => 0,
            ],
            'error' => null,
        ];
    }
}
