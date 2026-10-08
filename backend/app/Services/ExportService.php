<?php

namespace App\Services;

use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Jobs\ProcessExportJob;
use App\Models\Export;

class ExportService
{
    /**
     * Create an export job and dispatch it to the queue.
     *
     * @param  array<string, mixed>  $params
     */
    public function queueExport(
        ExportType $type,
        array $params = [],
        ?int $userId = null,
        bool $sync = false
    ): Export {
        $export = Export::create([
            'user_id' => $userId,
            'type' => $type,
            'params' => $params,
            'status' => ExportStatus::QUEUED,
            'path' => null,
            'filename' => null,
            'error' => null,
            'expires_at' => now()->addDays(7),
        ]);

        if ($sync) {
            ProcessExportJob::dispatchSync($export->id);
            $export->refresh();
        } else {
            ProcessExportJob::dispatch($export->id);
        }

        return $export;
    }
}
