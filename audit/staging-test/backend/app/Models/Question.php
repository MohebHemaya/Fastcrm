<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $key
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_required
 * @property bool $is_filter
 * @property QuestionType $type
 * @property int|null $current_version_id
 * @property QuestionVersion|null $currentVersion
 * @property Collection<int, QuestionOption> $options
 * @property Collection<int, QuestionVersion> $versions
 */
class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'sort_order',
        'is_active',
        'is_required',
        'is_filter',
        'type',
        'current_version_id',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_required' => 'boolean',
            'is_filter' => 'boolean',
            'type' => QuestionType::class,
        ];
    }

    /**
     * @return BelongsTo<QuestionVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'current_version_id');
    }

    public function getTextAttribute(): string
    {
        return $this->currentVersion ? $this->currentVersion->text_ar : $this->key;
    }

    /**
     * @return HasMany<QuestionVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(QuestionVersion::class)->orderBy('version', 'desc');
    }

    /**
     * @return HasMany<QuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order', 'asc');
    }

    /**
     * @return HasMany<QuestionOption, $this>
     */
    public function activeOptions(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->where('is_active', true)->orderBy('sort_order', 'asc');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(DriverAnswer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    public function scopeFilterOnly(Builder $query): Builder
    {
        return $query->where('is_filter', true);
    }

    /**
     * Create a new version if text, type, or options have changed.
     *
     * @param  array<array{value: string, label_ar: string, sort_order?: int, is_active?: bool}>  $optionsData
     */
    public function recordVersion(string $textAr, QuestionType $type, array $optionsData = []): QuestionVersion
    {
        return DB::transaction(function () use ($textAr, $type, $optionsData) {
            $latestVersion = $this->versions()->first();
            $nextVersionNumber = $latestVersion ? $latestVersion->version + 1 : 1;

            // Format snapshot
            $snapshot = array_map(function ($opt, $idx) {
                return [
                    'value' => (string) ($opt['value'] ?? ''),
                    'label_ar' => (string) ($opt['label_ar'] ?? ''),
                    'sort_order' => (int) ($opt['sort_order'] ?? $idx),
                    'is_active' => (bool) ($opt['is_active'] ?? true),
                ];
            }, $optionsData, array_keys($optionsData));

            $newVersion = QuestionVersion::create([
                'question_id' => $this->id,
                'version' => $nextVersionNumber,
                'text_ar' => $textAr,
                'type' => $type,
                'options_snapshot' => empty($snapshot) ? null : $snapshot,
                'created_at' => now(),
            ]);

            // Sync options in question_options table: deactivate removed, update existing, insert new
            if (! empty($optionsData)) {
                $incomingValues = [];
                foreach ($optionsData as $idx => $opt) {
                    $val = (string) $opt['value'];
                    $incomingValues[] = $val;

                    QuestionOption::updateOrCreate(
                        ['question_id' => $this->id, 'value' => $val],
                        [
                            'label_ar' => (string) $opt['label_ar'],
                            'sort_order' => (int) ($opt['sort_order'] ?? $idx),
                            'is_active' => (bool) ($opt['is_active'] ?? true),
                        ]
                    );
                }

                // Deactivate options not in incoming values (never hard delete)
                $this->options()->whereNotIn('value', $incomingValues)->update(['is_active' => false]);
            }

            $this->update([
                'type' => $type,
                'current_version_id' => $newVersion->id,
            ]);

            return $newVersion;
        });
    }
}
