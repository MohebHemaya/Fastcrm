<?php

namespace App\Models;

use App\Services\Audit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property User|null $user
 */
class ActivityLog extends Model
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
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'properties',
        'ip',
        'user_agent',
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
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Record an audit activity log entry.
     */
    public static function record(
        string $action,
        string $description = '',
        mixed $subjectType = null,
        ?int $subjectId = null,
        array $properties = []
    ): ?self {
        $subject = null;
        if ($subjectType && $subjectId && class_exists($subjectType)) {
            $subject = $subjectType::find($subjectId);
        }
        if ($description !== '') {
            $properties['description'] = $description;
        }

        return Audit::log($action, $subject, $properties);
    }

    /**
     * Associated user who performed the activity.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
