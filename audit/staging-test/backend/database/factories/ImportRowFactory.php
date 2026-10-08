<?php

namespace Database\Factories;

use App\Enums\ImportRowClassification;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRow>
 */
class ImportRowFactory extends Factory
{
    protected $model = ImportRow::class;

    public function definition(): array
    {
        $phone = '+2010'.fake()->numerify('########');

        return [
            'import_id' => Import::factory(),
            'row_number' => fake()->numberBetween(1, 1000),
            'raw_phone' => $phone,
            'group_name' => 'جروب القاهرة',
            'normalized_phone' => $phone,
            'classification' => ImportRowClassification::NEW,
            'error' => null,
            'driver_id' => null,
            'created_at' => now(),
        ];
    }
}
