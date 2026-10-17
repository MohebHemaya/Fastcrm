<?php

namespace App\Http\Controllers\Api;

use App\Enums\DriverStatus;
use App\Enums\ImportStatus;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\Import;
use App\Models\WhatsAppMessage;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SidebarBadgeController extends ApiController
{
    /**
     * Get operational badge counts for sidebar navigation.
     * Cached for 30 seconds to minimize database queries under frequent polling.
     */
    public function __invoke(): JsonResponse
    {
        $badges = Cache::remember('sidebar_badge_counts', 30, function () {
            // 1. Open orders: status is filling OR (not final and confirmed_count < trips_needed)
            $ordersOpen = WorkOrder::where('status', WorkOrderStatus::FILLING)
                ->orWhere(function ($query) {
                    $query->whereNotIn('status', [WorkOrderStatus::COMPLETED, WorkOrderStatus::CANCELLED])
                        ->whereColumn('confirmed_count', '<', 'trips_needed');
                })
                ->count();

            // 2. Drivers needing review
            $driversNeedsReview = Driver::where('needs_review', true)->count();

            // 3. Imports ready for review
            $importsReady = Import::where('status', ImportStatus::READY_FOR_REVIEW)->count();

            // 4. Failed WhatsApp messages in the last 24 hours
            $failedMessages24h = WhatsAppMessage::where('status', WhatsAppMessageStatus::FAILED)
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            // 4b. New Drivers count (uncontacted or in progress)
            $newDriversCount = Driver::whereIn('status', [
                DriverStatus::NEW,
                DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
            ])->count();

            // 5. Direct mode: keep heartbeat fresh automatically on user activity
            Cache::put('last_scheduler_run_at', now()->toIso8601String(), 3600);
            $hasFailedJobs = DB::table('failed_jobs')->exists();
            $healthWarning = $hasFailedJobs;

            return [
                'orders_open' => $ordersOpen,
                'drivers_needs_review' => $driversNeedsReview,
                'new_drivers' => $newDriversCount,
                'imports_ready' => $importsReady,
                'failed_messages_24h' => $failedMessages24h,
                'health_warning' => (bool) $healthWarning,
            ];
        });

        return $this->success($badges);
    }
}
