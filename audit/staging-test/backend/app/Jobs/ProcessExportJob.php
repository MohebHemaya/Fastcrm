<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Exports\DriversExport;
use App\Exports\GroupPerformanceExport;
use App\Exports\StreamingDriversExport;
use App\Exports\WorkOrderExport;
use App\Models\Driver;
use App\Models\Export;
use App\Models\WorkOrder;
use App\Services\DriverProfilePdfService;
use App\Services\WorkOrderPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600; // 10 minutes for large datasets

    public function __construct(
        public int $exportId
    ) {}

    public function handle(): void
    {
        $export = Export::find($this->exportId);
        if (! $export) {
            return;
        }

        $export->update([
            'status' => ExportStatus::PROCESSING,
            'error' => null,
        ]);

        try {
            $params = $export->params ?? [];
            $timestamp = now()->format('Ymd_His');
            $type = $export->type;

            switch ($type) {
                case ExportType::DRIVERS:
                    $filename = "drivers_export_{$timestamp}.xlsx";
                    $path = "exports/{$filename}";
                    $exportHelper = new DriversExport($params);
                    if ($exportHelper->query()->count() > 1000) {
                        $streamingExporter = new StreamingDriversExport($params);
                        $streamingExporter->exportToDisk('local', $path);
                    } else {
                        Excel::store($exportHelper, $path, 'local');
                    }
                    break;

                case ExportType::WORK_ORDER:
                    $workOrderId = (int) ($params['work_order_id'] ?? 0);
                    $workOrder = WorkOrder::findOrFail($workOrderId);
                    $filename = "order_{$workOrder->code}_{$timestamp}.xlsx";
                    $path = "exports/{$filename}";
                    Excel::store(new WorkOrderExport($workOrder), $path, 'local');
                    break;

                case ExportType::GROUP_PERFORMANCE:
                    $filename = "group_performance_{$timestamp}.xlsx";
                    $path = "exports/{$filename}";
                    Excel::store(new GroupPerformanceExport($params), $path, 'local');
                    break;

                case ExportType::ORDER_PDF:
                    $workOrderId = (int) ($params['work_order_id'] ?? 0);
                    $workOrder = WorkOrder::findOrFail($workOrderId);
                    $filename = "order_{$workOrder->code}_{$timestamp}.pdf";
                    $path = "exports/{$filename}";
                    $pdfContent = app(WorkOrderPdfService::class)->generatePdf($workOrder);
                    Storage::disk('local')->put($path, $pdfContent);
                    break;

                case ExportType::DRIVER_PROFILE_PDF:
                    $driverId = (int) ($params['driver_id'] ?? 0);
                    $driver = Driver::findOrFail($driverId);
                    $filename = "driver_{$driver->id}_{$timestamp}.pdf";
                    $path = "exports/{$filename}";
                    $pdfContent = app(DriverProfilePdfService::class)->generatePdf($driver);
                    Storage::disk('local')->put($path, $pdfContent);
                    break;

                default:
                    throw new \InvalidArgumentException("Unknown export type: {$type->value}");
            }

            $export->update([
                'status' => ExportStatus::DONE,
                'path' => $path,
                'filename' => $filename,
                'expires_at' => now()->addDays(7),
            ]);
        } catch (\Throwable $e) {
            Log::error("[ProcessExportJob] Export {$export->id} failed: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            $export->update([
                'status' => ExportStatus::FAILED,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
