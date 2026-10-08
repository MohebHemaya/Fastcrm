<?php

namespace App\Models;

use App\Enums\ExportStatus;
use App\Enums\ExportType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property ExportType $type
 * @property array<string, mixed>|null $params
 * @property ExportStatus $status
 * @property string|null $path
 * @property string|null $filename
 * @property string|null $error
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Export extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'params',
        'status',
        'path',
        'filename',
        'error',
        'expires_at',
    ];

    protected $casts = [
        'type' => ExportType::class,
        'status' => ExportStatus::class,
        'params' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::DONE && ! $this->isExpired() && ! empty($this->path);
    }
}
