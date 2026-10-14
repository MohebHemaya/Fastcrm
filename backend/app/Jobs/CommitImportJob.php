<?php

namespace App\Jobs;

use App\Enums\DriverStatus;
use App\Enums\ImportRowClassification;
use App\Enums\ImportStatus;
use App\Events\ImportProgressUpdated;
use App\Models\Driver;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\User;
use App\Models\WhatsappGroup;
use App\Services\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class CommitImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public Import $import,
        public ?User $user = null
    ) {}

    public function handle(): void
    {
        // Idempotency: if already completed, do nothing
        if ($this->import->status === ImportStatus::COMPLETED) {
            return;
        }

        $this->import->update([
            'status' => ImportStatus::COMMITTING,
            'error' => null,
        ]);

        $totalRows = ImportRow::where('import_id', $this->import->id)
            ->where('classification', '!=', ImportRowClassification::INVALID->value)
            ->count();
        $processed = 0;

        try {
            broadcast(new ImportProgressUpdated(
                jobId: (string) $this->import->id,
                processed: 0,
                total: $totalRows,
                completed: false,
                status: 'processing'
            ));
        } catch (Throwable) {}

        try {
            DB::transaction(function () use ($totalRows, &$processed) {
                ImportRow::where('import_id', $this->import->id)
                    ->where('classification', '!=', ImportRowClassification::INVALID->value)
                    ->chunkById(500, function ($rows) use ($totalRows, &$processed) {
                        // 1. Resolve WhatsApp groups in chunk
                        $groupNames = $rows->pluck('group_name')->filter()->unique()->values()->all();
                        $groupMap = [];

                        foreach ($groupNames as $name) {
                            $trimmed = trim($name);
                            if ($trimmed !== '') {
                                $group = WhatsappGroup::firstOrCreate(['name' => $trimmed]);
                                $groupMap[$name] = $group->id;
                                $groupMap[$trimmed] = $group->id;
                            }
                        }

                        // 2. Process drivers and group attachments
                        $pivotInserts = [];
                        $now = now();

                        foreach ($rows as $row) {
                            if (! $row->normalized_phone) {
                                continue;
                            }

                            if ($row->classification === ImportRowClassification::NEW) {
                                $driver = Driver::firstOrCreate(
                                    ['phone' => $row->normalized_phone],
                                    ['status' => DriverStatus::NEW]
                                );
                            } else {
                                $driver = Driver::where('phone', $row->normalized_phone)->first();
                            }

                            if (! $driver) {
                                continue;
                            }

                            if ($row->driver_id !== $driver->id) {
                                $row->update(['driver_id' => $driver->id]);
                            }

                            $groupId = $groupMap[$row->group_name] ?? null;
                            if ($groupId) {
                                $pivotInserts[] = [
                                    'driver_id' => $driver->id,
                                    'group_id' => $groupId,
                                    'import_id' => $this->import->id,
                                    'first_seen_at' => $now,
                                ];
                            }
                        }

                        if (! empty($pivotInserts)) {
                            DB::table('driver_group')->insertOrIgnore($pivotInserts);
                        }

                        $processed += $rows->count();
                    });

                $this->import->update([
                    'status' => ImportStatus::COMPLETED,
                ]);

                Audit::log('import_committed', $this->import, [
                    'counts' => $this->import->counts,
                    'user_id' => $this->user?->id,
                ]);
            });

            try {
                broadcast(new ImportProgressUpdated(
                    jobId: (string) $this->import->id,
                    processed: $totalRows,
                    total: $totalRows,
                    completed: true,
                    status: 'completed'
                ));
            } catch (Throwable) {}
        } catch (Throwable $e) {
            $this->import->update([
                'status' => ImportStatus::FAILED,
                'error' => 'حدث خطأ أثناء اعتماد الاستيراد: '.$e->getMessage(),
            ]);

            try {
                broadcast(new ImportProgressUpdated(
                    jobId: (string) $this->import->id,
                    processed: $processed,
                    total: $totalRows,
                    completed: true,
                    status: 'failed',
                    error: $e->getMessage()
                ));
            } catch (Throwable) {}
        }
    }
}
