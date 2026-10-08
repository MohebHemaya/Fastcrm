<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    /**
     * Get dashboard summary cards and metrics.
     *
     * @return array<string, mixed>
     */
    public function getSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $version = $this->getCacheVersion();
        $dateKey = md5(($startDate ?? 'all').'_'.($endDate ?? 'all'));
        $cacheKey = "dashboard_summary_{$version}_{$dateKey}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($startDate, $endDate) {
            return $this->computeSummary($startDate, $endDate);
        });
    }

    /**
     * Get group performance table data per WhatsApp group.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGroupPerformance(?string $startDate = null, ?string $endDate = null): array
    {
        $version = $this->getCacheVersion();
        $dateKey = md5(($startDate ?? 'all').'_'.($endDate ?? 'all'));
        $cacheKey = "dashboard_group_perf_{$version}_{$dateKey}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($startDate, $endDate) {
            return $this->computeGroupPerformance($startDate, $endDate);
        });
    }

    protected static bool $bustScheduled = false;

    protected static bool $trailingBustRequired = false;

    protected static float $latestMutationTime = 0.0;

    protected static int $lastBustTime = 0;

    /**
     * Bust dashboard aggregation cache (debounced, trailing-aware, and afterCommit-aware).
     */
    public function bustCache(bool $force = false): void
    {
        $now = microtime(true);
        static::$latestMutationTime = $now;
        static::$trailingBustRequired = true;

        if ($force) {
            $this->applyInvalidation($now);

            return;
        }

        if (static::$bustScheduled) {
            return;
        }

        $lastInvalidated = (float) Cache::get('dashboard_last_invalidated_at', 0);
        if (($now - $lastInvalidated) < 3.0) {
            $this->scheduleTrailingMutationSync();

            return;
        }

        static::$bustScheduled = true;

        $doInvalidate = function () {
            static::$bustScheduled = false;
            $this->applyInvalidation(microtime(true));
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($doInvalidate);
        } else {
            if (app()->runningInConsole()) {
                $doInvalidate();
            } else {
                app()->terminating($doInvalidate);
            }
        }
    }

    /**
     * Apply cache version invalidation and record timestamps.
     */
    protected function applyInvalidation(float $timestamp): void
    {
        static::$bustScheduled = false;
        static::$trailingBustRequired = false;
        static::$lastBustTime = (int) $timestamp;
        Cache::forever('dashboard_last_invalidated_at', $timestamp);
        Cache::forever('dashboard_latest_mutation_at', $timestamp);
        Cache::increment('dashboard_cache_version');
    }

    /**
     * Ensure the latest mutation timestamp is synced to cache for trailing check.
     */
    protected function scheduleTrailingMutationSync(): void
    {
        $sync = function () {
            if (static::$trailingBustRequired && static::$latestMutationTime > 0) {
                Cache::forever('dashboard_latest_mutation_at', static::$latestMutationTime);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($sync);
        } elseif (! app()->runningInConsole()) {
            app()->terminating($sync);
        } else {
            $sync();
        }
    }

    /**
     * Reset bust debounce state (useful for tests).
     */
    public static function resetDebounce(): void
    {
        static::$bustScheduled = false;
        static::$trailingBustRequired = false;
        static::$latestMutationTime = 0.0;
        static::$lastBustTime = 0;
    }

    /**
     * Get current cache version integer (with trailing invalidation check).
     */
    public function getCacheVersion(): int
    {
        $lastInvalidated = (float) Cache::get('dashboard_last_invalidated_at', 0);
        $latestMutation = (float) Cache::get('dashboard_latest_mutation_at', 0);

        // Trailing invalidation: if changes were made within debounce window,
        // once the 3.0s window ends, bump the version on read.
        if ($latestMutation > $lastInvalidated && (microtime(true) - $lastInvalidated) >= 3.0) {
            $this->applyInvalidation(microtime(true));
        }

        return (int) Cache::get('dashboard_cache_version', 1);
    }

    /**
     * Compute summary metrics in SQL.
     *
     * @return array<string, mixed>
     */
    private function computeSummary(?string $startDate = null, ?string $endDate = null): array
    {
        // 1. Drivers by status
        $driverQuery = Driver::query();
        if ($startDate && $endDate) {
            $driverQuery->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }

        $rawDriverCounts = (clone $driverQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $driversByStatus = [];
        $totalDrivers = 0;
        foreach (DriverStatus::cases() as $case) {
            $val = $case->value;
            $cnt = (int) ($rawDriverCounts[$val] ?? 0);
            $driversByStatus[$val] = [
                'status' => $val,
                'label' => $case->label(),
                'count' => $cnt,
            ];
            $totalDrivers += $cnt;
        }

        $activeDriversCount = (int) ($rawDriverCounts[DriverStatus::ACTIVE->value] ?? 0);
        $optedOutDriversCount = (int) ($rawDriverCounts[DriverStatus::OPTED_OUT->value] ?? 0);

        // 2. Orders this month (or in date range) by status
        $monthStart = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $monthEnd = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfMonth();

        $rawOrderCounts = WorkOrder::whereBetween('created_at', [$monthStart, $monthEnd])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $ordersByStatus = [];
        $totalOrdersThisMonth = 0;
        foreach (WorkOrderStatus::cases() as $case) {
            $val = $case->value;
            $cnt = (int) ($rawOrderCounts[$val] ?? 0);
            $ordersByStatus[$val] = [
                'status' => $val,
                'label' => $case->label(),
                'count' => $cnt,
            ];
            $totalOrdersThisMonth += $cnt;
        }

        // 3. Offer acceptance rate
        $recipientQuery = DB::table('work_order_recipients')->whereNotNull('sent_at');
        if ($startDate && $endDate) {
            $recipientQuery->whereBetween('sent_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }

        $recipientStats = $recipientQuery->select(
            DB::raw('COUNT(*) as total_sent'),
            DB::raw("SUM(CASE WHEN status IN ('confirmed', 'backup') OR responded_at IS NOT NULL THEN 1 ELSE 0 END) as accepted_count")
        )->first();

        $totalOffersSent = $recipientStats ? (int) $recipientStats->total_sent : 0;
        $acceptedOffersCount = $recipientStats ? (int) $recipientStats->accepted_count : 0;
        $acceptanceRate = $totalOffersSent > 0
            ? round(($acceptedOffersCount / $totalOffersSent) * 100, 1)
            : 0.0;

        // 4. Average time-to-fill (minutes) for filled orders
        $fillOrderQuery = DB::table('work_orders')->whereIn('status', [
            WorkOrderStatus::FILLED->value,
            WorkOrderStatus::IN_PROGRESS->value,
            WorkOrderStatus::COMPLETED->value,
        ])->whereNotNull('filled_at');

        if ($startDate && $endDate) {
            $fillOrderQuery->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }

        $avgTimeQuery = $fillOrderQuery->select(
            DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, filled_at)) as avg_minutes'),
            DB::raw('COUNT(*) as filled_orders_count')
        )->first();

        $avgFillMinutes = ($avgTimeQuery && $avgTimeQuery->avg_minutes !== null) ? round((float) $avgTimeQuery->avg_minutes, 1) : 0.0;
        $filledOrdersCount = $avgTimeQuery ? (int) $avgTimeQuery->filled_orders_count : 0;

        // 5. Monthly orders chart trend (last 6 months)
        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();
        $monthlyTrendRaw = DB::table('work_orders')
            ->where('created_at', '>=', $sixMonthsAgo)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            )
            ->groupBy('ym')
            ->orderBy('ym', 'asc')
            ->get();

        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $ym = now()->subMonths($i)->format('Y-m');
            $arabicMonthName = now()->subMonths($i)->translatedFormat('M Y');
            $found = $monthlyTrendRaw->firstWhere('ym', $ym);
            $monthlyTrend[] = [
                'month' => $ym,
                'name' => $arabicMonthName,
                'total' => $found ? (int) $found->total : 0,
                'completed' => $found ? (int) $found->completed : 0,
                'cancelled' => $found ? (int) $found->cancelled : 0,
            ];
        }

        // 6. Flat representations for frontend compatibility
        $driversByStatusSimple = [];
        foreach (DriverStatus::cases() as $case) {
            $val = $case->value;
            $driversByStatusSimple[$val] = (int) ($rawDriverCounts[$val] ?? 0);
        }

        $ordersByStatusSimple = [];
        foreach (WorkOrderStatus::cases() as $case) {
            $val = $case->value;
            $ordersByStatusSimple[$val] = (int) ($rawOrderCounts[$val] ?? 0);
        }

        $monthlyTrendsFrontend = array_map(function ($item) {
            return [
                'month' => $item['month'],
                'label' => $item['name'],
                'total_orders' => $item['total'],
                'completed_orders' => $item['completed'],
            ];
        }, $monthlyTrend);

        return [
            // Backend nested contract (preserves existing backend API tests)
            'drivers_summary' => [
                'total_drivers' => $totalDrivers,
                'active_drivers' => $activeDriversCount,
                'opted_out_drivers' => $optedOutDriversCount,
                'by_status' => $driversByStatus,
            ],
            'orders_summary' => [
                'total_orders' => $totalOrdersThisMonth,
                'by_status' => $ordersByStatus,
                'period_start' => $monthStart->toDateString(),
                'period_end' => $monthEnd->toDateString(),
            ],
            'dispatch_metrics' => [
                'total_offers_sent' => $totalOffersSent,
                'accepted_offers_count' => $acceptedOffersCount,
                'acceptance_rate' => $acceptanceRate,
                'avg_time_to_fill_minutes' => $avgFillMinutes,
                'filled_orders_count' => $filledOrdersCount,
            ],
            'monthly_trend' => $monthlyTrend,

            // Frontend flat contract (matches DashboardSummary type)
            'total_drivers' => $totalDrivers,
            'active_drivers_count' => $activeDriversCount,
            'opted_out_drivers_count' => $optedOutDriversCount,
            'drivers_by_status' => $driversByStatusSimple,
            'orders_this_month' => [
                'total' => $totalOrdersThisMonth,
                'by_status' => $ordersByStatusSimple,
            ],
            'offer_acceptance_rate' => $acceptanceRate,
            'avg_time_to_fill_minutes' => $avgFillMinutes,
            'monthly_trends' => $monthlyTrendsFrontend,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],

            'cached_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Compute Group Performance table in SQL.
     *
     * @return array<int, array<string, mixed>>
     */
    private function computeGroupPerformance(?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table('whatsapp_groups as g')
            ->leftJoin('driver_group as dg', 'g.id', '=', 'dg.group_id')
            ->leftJoin('drivers as d', function ($join) {
                $join->on('dg.driver_id', '=', 'd.id')
                    ->whereNull('d.deleted_at');
            });

        if ($startDate && $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('dg.first_seen_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ])->orWhereNull('dg.first_seen_at');
            });
        }

        $results = $query->select(
            'g.id',
            'g.name',
            DB::raw('COUNT(DISTINCT dg.driver_id) as imported'),
            DB::raw("SUM(CASE WHEN d.status = 'invited' THEN 1 ELSE 0 END) as invited"),
            DB::raw("SUM(CASE WHEN d.status = 'interested' THEN 1 ELSE 0 END) as interested"),
            DB::raw("SUM(CASE WHEN d.status = 'active' THEN 1 ELSE 0 END) as active"),
            DB::raw("SUM(CASE WHEN d.status = 'declined' THEN 1 ELSE 0 END) as declined"),
            DB::raw("SUM(CASE WHEN d.status = 'opted_out' THEN 1 ELSE 0 END) as opted_out")
        )
            ->groupBy('g.id', 'g.name')
            ->orderBy('imported', 'desc')
            ->orderBy('g.name', 'asc')
            ->get();

        return $results->map(function ($row) {
            $imported = (int) $row->imported;
            $active = (int) $row->active;
            $conversionRate = $imported > 0 ? round(($active / $imported) * 100, 1) : 0.0;

            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'imported' => $imported,
                'invited' => (int) $row->invited,
                'interested' => (int) $row->interested,
                'active' => $active,
                'declined' => (int) $row->declined,
                'opted_out' => (int) $row->opted_out,
                'conversion_rate' => $conversionRate,
            ];
        })->all();
    }
}
