<?php

namespace App\Services;

use App\Enums\WorkOrderOutcome;
use App\Models\DriverMetric;
use App\Models\WorkOrderRecipient;
use Illuminate\Support\Facades\DB;

class DriverMetricsService
{
    /**
     * Recalculate metrics for a given driver from work order outcomes.
     */
    public function recalculateForDriver(int $driverId): DriverMetric
    {
        $recipients = WorkOrderRecipient::where('driver_id', $driverId)
            ->whereNotNull('outcome')
            ->get();

        $tripsCompleted = 0;
        $lateCount = 0;
        $noShows = 0;
        $cancellations = 0;
        $ratings = [];

        foreach ($recipients as $recipient) {
            $outcome = $recipient->outcome;
            if (! $outcome) {
                continue;
            }

            if ($outcome === WorkOrderOutcome::ATTENDED) {
                $tripsCompleted++;
            } elseif ($outcome === WorkOrderOutcome::LATE) {
                $tripsCompleted++;
                $lateCount++;
            } elseif ($outcome === WorkOrderOutcome::NO_SHOW) {
                $noShows++;
            } elseif ($outcome === WorkOrderOutcome::CANCELLED_LATE) {
                $cancellations++;
            }

            if ($recipient->rating !== null && $recipient->rating >= 1 && $recipient->rating <= 5) {
                $ratings[] = (int) $recipient->rating;
            }
        }

        $ratingsCount = count($ratings);
        $avgRating = $ratingsCount > 0 ? round(array_sum($ratings) / $ratingsCount, 2) : null;

        // Find last trip date where driver attended or was late
        $lastTripAt = DB::table('work_order_recipients')
            ->join('work_orders', 'work_order_recipients.work_order_id', '=', 'work_orders.id')
            ->where('work_order_recipients.driver_id', $driverId)
            ->whereIn('work_order_recipients.outcome', [
                WorkOrderOutcome::ATTENDED->value,
                WorkOrderOutcome::LATE->value,
            ])
            ->max('work_orders.scheduled_at');

        return DriverMetric::updateOrCreate(
            ['driver_id' => $driverId],
            [
                'trips_completed' => $tripsCompleted,
                'cancellations' => $cancellations,
                'no_shows' => $noShows,
                'late_count' => $lateCount,
                'avg_rating' => $avgRating,
                'ratings_count' => $ratingsCount,
                'last_trip_at' => $lastTripAt,
            ]
        );
    }
}
