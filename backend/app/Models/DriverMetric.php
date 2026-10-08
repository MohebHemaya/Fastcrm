<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverMetric extends Model
{
    use HasFactory;

    protected $table = 'driver_metrics';

    protected $fillable = [
        'driver_id',
        'trips_completed',
        'cancellations',
        'no_shows',
        'late_count',
        'avg_rating',
        'ratings_count',
        'last_trip_at',
    ];

    protected $casts = [
        'trips_completed' => 'integer',
        'cancellations' => 'integer',
        'no_shows' => 'integer',
        'late_count' => 'integer',
        'avg_rating' => 'float',
        'ratings_count' => 'integer',
        'last_trip_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
