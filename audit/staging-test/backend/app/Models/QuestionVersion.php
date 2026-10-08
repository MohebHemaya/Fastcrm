<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $question_id
 * @property int $version
 * @property string $text_ar
 * @property QuestionType $type
 * @property array|null $options_snapshot
 * @property Carbon|null $created_at
 */
class QuestionVersion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'question_id',
        'version',
        'text_ar',
        'type',
        'options_snapshot',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'version' => 'integer',
            'options_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return HasMany<DriverAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(DriverAnswer::class, 'question_version_id');
    }
}
