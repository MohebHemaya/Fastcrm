<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Get dashboard summary aggregations.
     */
    public function index(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $data = $this->dashboardService->getSummary(
            startDate: is_string($startDate) ? $startDate : null,
            endDate: is_string($endDate) ? $endDate : null
        );

        return response()->json([
            'data' => $data,
            'meta' => [
                'cached' => true,
                'version' => $this->dashboardService->getCacheVersion(),
            ],
            'message' => 'تم استرجاع بيانات لوحة التحكم بنجاح.',
        ]);
    }

    /**
     * Get group performance breakdown table.
     */
    public function groupPerformance(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $sortBy = (string) $request->query('sort_by', 'imported');
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $groups = $this->dashboardService->getGroupPerformance(
            startDate: is_string($startDate) ? $startDate : null,
            endDate: is_string($endDate) ? $endDate : null
        );

        // In-memory sort of group performance results
        $allowedSorts = ['name', 'imported', 'invited', 'interested', 'active', 'declined', 'opted_out', 'conversion_rate'];
        if (in_array($sortBy, $allowedSorts, true)) {
            usort($groups, function ($a, $b) use ($sortBy, $sortDir) {
                if ($sortBy === 'name') {
                    $cmp = strcmp($a['name'], $b['name']);
                } else {
                    $cmp = ($a[$sortBy] ?? 0) <=> ($b[$sortBy] ?? 0);
                }

                return $sortDir === 'asc' ? $cmp : -$cmp;
            });
        }

        return response()->json([
            'data' => $groups,
            'meta' => [
                'total_groups' => count($groups),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'message' => 'تم استرجاع تقرير أداء المجموعات بنجاح.',
        ]);
    }

    /**
     * Bust dashboard cache manually.
     */
    public function bustCache(): JsonResponse
    {
        $this->dashboardService->bustCache();

        return response()->json([
            'data' => null,
            'meta' => ['version' => $this->dashboardService->getCacheVersion()],
            'message' => 'تم تحديث التخزين المؤقت للوحة التحكم بنجاح.',
        ]);
    }
}
