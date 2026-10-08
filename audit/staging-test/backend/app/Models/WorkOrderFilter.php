<?php

namespace App\Models;

use App\Enums\WorkOrderFilterOperator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $work_order_id
 * @property int $question_id
 * @property WorkOrderFilterOperator $operator
 * @property mixed $value
 * @property WorkOrder $workOrder
 * @property Question $question
 */
class WorkOrderFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'question_id',
        'operator',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'operator' => WorkOrderFilterOperator::class,
            'value' => 'json',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
