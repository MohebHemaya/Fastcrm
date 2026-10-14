<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * List all available operational reports.
     */
    public function list(): JsonResponse
    {
        return response()->json([
            'data' => $this->reportService->getAvailableReports(),
            'meta' => null,
            'message' => 'تم استرجاع قائمة التقارير المتاحة بنجاح.',
        ]);
    }

    /**
     * Get paginated table rows, columns, and real KPI metrics for a specific report.
     */
    public function show(Request $request, string $reportType): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'in:in,out'],
            'group_id' => ['nullable', 'integer'],
            'question_id' => ['nullable', 'integer'],
            'is_skipped' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = $validated;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 25);

        try {
            $result = $this->reportService->getReportData($reportType, $filters, $page, $perPage);

            return response()->json([
                'data' => $result['rows'],
                'columns' => $result['columns'],
                'summary' => $result['summary'],
                'meta' => $result['meta'],
                'message' => 'تم استرجاع بيانات التقرير بنجاح.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Stream an Excel (.xlsx) file download directly to the client.
     */
    public function download(Request $request, string $reportType): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'in:in,out'],
            'group_id' => ['nullable', 'integer'],
            'question_id' => ['nullable', 'integer'],
            'is_skipped' => ['nullable', 'boolean'],
        ]);

        $filters = $validated;

        try {
            return $this->reportService->streamReportDownload($reportType, $filters);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Executive Overview KPIs.
     */
    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $data = $this->reportService->getOverviewReport(
            startDate: is_string($startDate) ? $startDate : null,
            endDate: is_string($endDate) ? $endDate : null
        );

        return response()->json([
            'data' => $data,
            'meta' => null,
            'message' => 'تم استرجاع تقرير النظرة العامة بنجاح.',
        ]);
    }

    /**
     * Funnel report.
     */
    public function funnel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $data = $this->reportService->getFunnelReport(
            startDate: is_string($startDate) ? $startDate : null,
            endDate: is_string($endDate) ? $endDate : null
        );

        return response()->json([
            'data' => $data,
            'meta' => null,
            'message' => 'تم استرجاع تقرير مسار الاستبيان بنجاح.',
        ]);
    }

    /**
     * WhatsApp report.
     */
    public function whatsapp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $data = $this->reportService->getWhatsAppReport(
            startDate: is_string($startDate) ? $startDate : null,
            endDate: is_string($endDate) ? $endDate : null
        );

        return response()->json([
            'data' => $data,
            'meta' => null,
            'message' => 'تم استرجاع تقرير أداء الواتساب بنجاح.',
        ]);
    }

    /**
     * Fulfillment report.
     */
    public function fulfillment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $data = $this->reportService->getFulfillmentReport(
            startDate: is_string($startDate) ? $startDate : null,
            endDate: is_string($endDate) ? $endDate : null
        );

        return response()->json([
            'data' => $data,
            'meta' => null,
            'message' => 'تم استرجاع تقرير إنجاز أوامر الشغل بنجاح.',
        ]);
    }
}
