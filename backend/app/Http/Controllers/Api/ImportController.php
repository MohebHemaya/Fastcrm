<?php

namespace App\Http\Controllers\Api;

use App\Enums\ImportRowClassification;
use App\Enums\ImportStatus;
use App\Http\Requests\UploadImportRequest;
use App\Http\Resources\ImportResource;
use App\Http\Resources\ImportRowResource;
use App\Jobs\CommitImportJob;
use App\Jobs\ParseImportJob;
use App\Models\Import;
use App\Services\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends ApiController
{
    /**
     * List recent imports with optional status filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Import::with('user')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $paginated = $query->paginate($perPage);

        return $this->paginated(
            ImportResource::collection($paginated),
            $paginated
        );
    }

    /**
     * Upload an Excel/CSV file to start the import process.
     */
    public function upload(UploadImportRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $storedPath = $file->store('imports', 'local');

        $import = Import::create([
            'user_id' => $request->user()->id,
            'original_name' => $originalName,
            'stored_path' => $storedPath,
            'status' => ImportStatus::UPLOADED,
            'counts' => [
                'total' => 0,
                'new' => 0,
                'duplicate_in_file' => 0,
                'existing' => 0,
                'active' => 0,
                'invalid' => 0,
            ],
            'error' => null,
        ]);

        Audit::log('import_uploaded', $import, [
            'original_name' => $originalName,
            'size' => $file->getSize(),
        ]);

        // Process parsing immediately & synchronously for instant results!
        ParseImportJob::dispatchSync($import);
        $import->refresh();

        return $this->success(
            new ImportResource($import),
            'تم رفع وفحص ملف الاستيراد بنجاح.',
            201
        );
    }

    /**
     * Show import details and summary counts.
     */
    public function show(Import $import): JsonResponse
    {
        $import->load('user');

        return $this->success(new ImportResource($import));
    }

    /**
     * Get paginated rows of an import with filters (preview tabs).
     */
    public function rows(Request $request, Import $import): JsonResponse
    {
        $query = $import->rows();

        if ($request->filled('classification')) {
            $query->where('classification', $request->query('classification'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('raw_phone', 'like', "%{$search}%")
                    ->orWhere('normalized_phone', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $paginated = $query->orderBy('row_number', 'asc')->paginate($perPage);

        return $this->paginated(
            ImportRowResource::collection($paginated),
            $paginated
        );
    }

    /**
     * Commit the import to create drivers and associate WhatsApp groups.
     */
    public function commit(Request $request, Import $import): JsonResponse
    {
        if ($import->status !== ImportStatus::READY_FOR_REVIEW) {
            return $this->error('لا يمكن اعتماد هذا الاستيراد إلا عندما يكون جاهزاً للمراجعة.', 422);
        }

        // Process committing synchronously for instant completion!
        CommitImportJob::dispatchSync($import, $request->user());
        $import->refresh();

        return $this->success(
            new ImportResource($import),
            'تم اعتماد واستيراد السائقين بنجاح.'
        );
    }

    /**
     * Cancel an import before it is committed.
     */
    public function cancel(Import $import): JsonResponse
    {
        if ($import->status === ImportStatus::COMMITTING || $import->status === ImportStatus::COMPLETED) {
            return $this->error('لا يمكن إلغاء استيراد قيد التنفيذ أو مكتمل.', 422);
        }

        $import->update([
            'status' => ImportStatus::CANCELLED,
        ]);

        Audit::log('import_cancelled', $import);

        return $this->success(
            new ImportResource($import),
            'تم إلغاء عملية الاستيراد.'
        );
    }

    /**
     * Delete an import and its associated staged rows.
     */
    public function destroy(Import $import): JsonResponse
    {
        if ($import->status === ImportStatus::COMMITTING) {
            return $this->error('لا يمكن حذف استيراد قيد التنفيذ حالياً.', 422);
        }

        $import->rows()->delete();

        if ($import->stored_path && Storage::disk('local')->exists($import->stored_path)) {
            Storage::disk('local')->delete($import->stored_path);
        }

        $import->delete();

        return $this->success(null, 'تم حذف عملية الاستيراد بنجاح.');
    }

    /**
     * Download sample Excel/CSV template.
     */
    public function template(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="drivers_import_template.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Arabic support in Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, ['رقم الهاتف (Phone)', 'اسم المجموعة (Group Name)']);

            // Sample rows
            fputcsv($handle, ['01012345678', 'جروب المعادي']);
            fputcsv($handle, ['01198765432', 'جروب التجمع الخامس']);
            fputcsv($handle, ['01234567890', 'جروب أكتوبر']);
            fputcsv($handle, ['01555555555', 'جروب مدينة نصر']);

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export invalid rows as CSV for correction.
     */
    public function exportInvalid(Import $import): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"import_{$import->id}_invalid_rows.csv\"",
        ];

        return response()->stream(function () use ($import) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['رقم السطر', 'الرقم المدخل', 'المجموعة', 'سبب الخطأ']);

            $import->rows()
                ->where('classification', ImportRowClassification::INVALID->value)
                ->orderBy('row_number', 'asc')
                ->chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->row_number,
                            $row->raw_phone,
                            $row->group_name,
                            $row->error,
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}
