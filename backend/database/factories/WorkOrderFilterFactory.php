<?php

namespace Database\Factories;

use App\Enums\WorkOrderFilterOperator;
use App\Models\Question;
use App\Models\WorkOrder;
use App\Models\WorkOrderFilter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderFilter>
 */
class WorkOrderFilterFactory extends Factory
{
    protected $model = WorkOrderFilter::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'question_id' => Question::factory(),
            'operator' => WorkOrderFilterOperator::IN,
            'value' => ['pickup'],
        ];
    }
}
