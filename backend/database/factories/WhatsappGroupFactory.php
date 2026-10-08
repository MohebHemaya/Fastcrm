<?php

namespace Database\Factories;

use App\Models\WhatsappGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappGroup>
 */
class WhatsappGroupFactory extends Factory
{
    protected $model = WhatsappGroup::class;

    public function definition(): array
    {
        return [
            'name' => 'جروب '.fake()->unique()->city(),
        ];
    }
}
