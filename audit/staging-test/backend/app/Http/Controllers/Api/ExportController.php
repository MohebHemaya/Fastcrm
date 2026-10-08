<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExportResource;
use App\Jobs\ProcessExportJob;
use App\Models\Driver;
use App\Models\Export;
use App\Models\WorkOrder;
use App\Services\DriverProfilePdfService;
use App\Services\ExportService;
use App\Services\WorkOrderPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService,
        protected WorkOrderPdfService $workOrderPdfService,
        protected DriverProfilePdfService $driverProfilePdfService
    ) {}

    /**
     * List recent exports for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $exports = Export::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'data' => ExportResource::collection($exports),
            'meta' => ['total' => count($exports)],
            'message' => 'تم استرجاع قائمة ملفات التصدير بنجاح.',
        ]);
    }

    /**
     * Create and dispatch a new export.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:drivers,work_order,group_performance,order_pdf,driver_profile_pdf'],
            'params' => ['nullable', 'array'],
            'sync' => ['nullable', 'boolean'],
        ]);

        $type = ExportType::from($validated['type']);
        $params = $validated['params'] ?? [];
        $isSync = (bool) ($validated['sync'] ?? false);

        $export = $this->exportService->queueExport(
            type: $type,
            params: $params,
            userId: $request->user()?->id,
            sync: $isSync
        );

        return response()->json([
            'data' => new ExportResource($export),
            'meta' => null,
            'message' => $isSync ? 'تم تجهيز ملف التصدير بنجاح.' : 'تمت إضافة طلب التصدير إلى قائمة الانتظار.',
        ], 202);
    }

    /**
     * Show export status.
     */
    public function show(Export $export): JsonResponse
    {
        return response()->json([
            'data' => new ExportResource($export),
            'meta' => null,
            'message' => 'تم استرجاع حالة التصدير.',
        ]);
    }

    /**
     * Authenticated download of exported file (requires ownership).
     */
    public function download(Request $request, Export $export): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'يجب تسجيل الدخول أولاً.',
            ], 401);
        }

        if ($export->user_id && $export->user_id !== $user->id) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'غير مصرح لك بتحميل هذا الملف.',
            ], 403);
        }

        if ($export->status !== ExportStatus::DONE) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'ملف التصدير غير جاهز للتحميل بعد.',
            ], 400);
        }

        if ($export->isExpired()) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'انتهت صلاحية ملف التصدير (تُحذف الملفات تلقائيًا بعد 7 أيام).',
            ], 410);
        }

        if (empty($export->path) || str_contains($export->path, '..')) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'مسار الملف غير صالح.',
            ], 404);
        }

        if (! Storage::disk('local')->exists($export->path)) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'ملف التصدير غير موجود على الخادم أو تم حذفه.',
            ], 404);
        }

        $filename = $export->filename ?: basename($export->path);

        return Storage::disk('local')->download($export->path, $filename);
    }

    /**
     * Retry a failed export.
     */
    public function retry(Export $export): JsonResponse
    {
        if ($export->status !== ExportStatus::FAILED) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'يمكن إعادة محاولة التصدير للمهام الفاشلة فقط.',
            ], 400);
        }

        $export->update([
            'status' => ExportStatus::QUEUED,
            'error' => null,
        ]);

        ProcessExportJob::dispatch($export->id);

        return response()->json([
            'data' => new ExportResource($export->refresh()),
            'meta' => null,
            'message' => 'تمت إعادة إضافة مهمة التصدير إلى قائمة الانتظار.',
        ]);
    }

    /**
     * Direct Work Order PDF download.
     */
    public function directOrderPdf(WorkOrder $workOrder): StreamedResponse
    {
        $pdfContent = $this->workOrderPdfService->generatePdf($workOrder);
        $filename = "order_{$workOrder->code}.pdf";

        return response()->streamDownload(function () use ($pdfContent) {
            echo $pdfContent;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Direct Driver Profile PDF download.
     */
    public function directDriverPdf(Driver $driver): StreamedResponse
    {
        $pdfContent = $this->driverProfilePdfService->generatePdf($driver);
        $filename = "driver_{$driver->id}.pdf";

        return response()->streamDownload(function () use ($pdfContent) {
            echo $pdfContent;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
