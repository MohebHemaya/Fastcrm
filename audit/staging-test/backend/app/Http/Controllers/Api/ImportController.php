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
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends ApiController
{
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

        // Dispatch background parse job
        ParseImportJob::dispatch($import);

        return $this->success(
            new ImportResource($import),
            'تم رفع الملف بنجاح، وجاري معالجة وفحص الأرقام.',
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

        CommitImportJob::dispatch($import, $request->user());

        return $this->success(
            new ImportResource($import),
            'تم بدء عملية اعتماد وإضافة السائقين بنجاح.'
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
            'تم إلغاء الاستيراد بنجاح.'
        );
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
