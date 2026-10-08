<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $driver_id
 * @property int $question_id
 * @property int $question_version_id
 * @property int|null $option_id
 * @property string|null $value_text
 * @property float|null $value_number
 * @property bool $is_current
 * @property bool $is_skipped
 * @property Carbon $answered_at
 * @property Driver $driver
 * @property Question $question
 * @property QuestionVersion $version
 * @property QuestionOption|null $option
 */
class DriverAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'question_id',
        'question_version_id',
        'option_id',
        'value_text',
        'value_number',
        'is_current',
        'is_skipped',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'is_skipped' => 'boolean',
            'value_number' => 'decimal:2',
            'answered_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'question_version_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'option_id');
    }

    /**
     * Get a displayable value string in Arabic.
     */
    public function getDisplayValueAttribute(): string
    {
        if ($this->is_skipped) {
            return 'تم التخطي';
        }

        if ($this->option) {
            return $this->option->label_ar;
        }

        if ($this->value_number !== null) {
            return (string) $this->value_number;
        }

        return $this->value_text ?? '';
    }
}
