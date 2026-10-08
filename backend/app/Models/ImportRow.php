<?php

namespace App\Models;

use App\Enums\ImportRowClassification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $import_id
 * @property int $row_number
 * @property string|null $raw_phone
 * @property string|null $group_name
 * @property string|null $normalized_phone
 * @property ImportRowClassification $classification
 * @property string|null $error
 * @property int|null $driver_id
 * @property Carbon|null $created_at
 * @property Import $import
 * @property Driver|null $driver
 */
class ImportRow extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     * Only created_at is used.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'import_id',
        'row_number',
        'raw_phone',
        'group_name',
        'normalized_phone',
        'classification',
        'error',
        'driver_id',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => ImportRowClassification::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * Associated import.
     *
     * @return BelongsTo<Import, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /**
     * Associated driver if matched/committed.
     *
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
