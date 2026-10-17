<?php

namespace App\Http\Controllers\Api;

use App\Models\WebhookEvent;
use App\Models\WhatsAppMessage;
use App\Services\Audit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminHealthController extends ApiController
{
    /**
     * Get comprehensive system health metrics for admin dashboard.
     */
    public function systemHealth(): JsonResponse
    {
        // 1. Scheduler Heartbeat
        $lastHeartbeatIso = Cache::get('last_scheduler_run_at');
        $schedulerStatus = 'ok';
        $ageSeconds = 0;
        $ageMinutes = 0;

        if ($lastHeartbeatIso) {
            $ageSeconds = max(0, (int) now()->timestamp - (int) Carbon::parse((string) $lastHeartbeatIso)->timestamp);
            $ageMinutes = round($ageSeconds / 60, 1);
            if ($ageMinutes > 5) {
                $schedulerStatus = 'warning';
            }
        } else {
            $schedulerStatus = 'warning';
        }

        // 2. Queue Backlog and Oldest Job
        $queueBacklog = DB::table('jobs')->count();
        $oldestJobCreatedAt = DB::table('jobs')->min('created_at');
        $oldestJobAgeSeconds = $oldestJobCreatedAt ? max(0, time() - (int) $oldestJobCreatedAt) : null;

        // 3. Failed Jobs
        $failedJobsCount = DB::table('failed_jobs')->count();
        $recentFailedJobs = DB::table('failed_jobs')
            ->latest('failed_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $payload = json_decode((string) $row->payload, true);
                $displayName = $payload['displayName'] ?? ($payload['job'] ?? 'Unknown Job');

                // Extract first 2 lines of exception
                $lines = explode("\n", (string) $row->exception);
                $exceptionPreview = implode(' ', array_slice(array_map('trim', $lines), 0, 2));

                return [
                    'id' => $row->id,
                    'uuid' => $row->uuid,
                    'name' => $displayName,
                    'queue' => $row->queue,
                    'failed_at' => $row->failed_at,
                    'exception_preview' => mb_substr($exceptionPreview, 0, 150),
                ];
            });

        // 4. Last Webhook Received
        $lastWebhookAt = WebhookEvent::latest('created_at')->value('created_at');
        $lastWebhookAgeMinutes = $lastWebhookAt
            ? round(now()->diffInSeconds(Carbon::parse($lastWebhookAt)) / 60, 1)
            : null;

        // 5. WhatsApp Provider & Failed Messages
        $providerMode = config('whatsapp.provider', 'fake');
        $graphVersion = config('whatsapp.cloud_api.version', 'v25.0');
        $failedMessages24h = WhatsAppMessage::where('status', 'failed')
            ->where('updated_at', '>=', now()->subHours(24))
            ->count();

        // 6. Database Connectivity
        $dbOk = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $dbOk = false;
        }

        // 7. Backups
        $backupDir = storage_path('app/backups');
        $backupsCount = 0;
        $lastBackupAt = null;

        if (File::isDirectory($backupDir)) {
            $files = File::files($backupDir);
            $backupsCount = count($files);
            if ($backupsCount > 0) {
                usort($files, fn ($a, $b) => $b->getMTime() <=> $a->getMTime());
                $lastBackupAt = Carbon::createFromTimestamp($files[0]->getMTime())->toIso8601String();
            }
        }

        $overallStatus = ($dbOk && $schedulerStatus === 'ok') ? 'healthy' : 'warning';

        return $this->success([
            'status' => $overallStatus,
            'checked_at' => now()->toIso8601String(),
            'database' => [
                'status' => $dbOk ? 'ok' : 'fail',
                'driver' => config('database.default'),
            ],
            'scheduler' => [
                'status' => $schedulerStatus,
                'last_heartbeat_at' => $lastHeartbeatIso,
                'age_seconds' => $ageSeconds,
                'age_minutes' => $ageMinutes,
                'is_stale' => $schedulerStatus === 'warning',
                'warning_threshold_minutes' => 5,
            ],
            'queue' => [
                'backlog_size' => $queueBacklog,
                'oldest_job_age_seconds' => $oldestJobAgeSeconds,
                'status' => ($queueBacklog > 500) ? 'warning' : 'ok',
            ],
            'failed_jobs' => [
                'count' => $failedJobsCount,
                'recent' => $recentFailedJobs,
            ],
            'webhook' => [
                'last_received_at' => $lastWebhookAt ? Carbon::parse($lastWebhookAt)->toIso8601String() : null,
                'age_minutes' => $lastWebhookAgeMinutes,
            ],
            'whatsapp' => [
                'provider' => $providerMode,
                'graph_version' => $graphVersion,
                'failed_messages_24h' => $failedMessages24h,
            ],
            'backups' => [
                'count' => $backupsCount,
                'last_backup_at' => $lastBackupAt,
            ],
        ], 'تم جلب تقرير حالة النظام بنجاح');
    }

    /**
     * Retry a specific failed job.
     */
    public function retryFailedJob(int|string $id): JsonResponse
    {
        $job = DB::table('failed_jobs')
            ->where('id', $id)
            ->orWhere('uuid', $id)
            ->first();

        if (! $job) {
            return $this->error('المهمة الفاشلة غير موجودة.', 404);
        }

        Artisan::call('queue:retry', ['id' => [$job->uuid]]);

        return $this->success(null, 'تمت إعادة محاولة المهمة بنجاح.');
    }

    /**
     * Delete a specific failed job.
     */
    public function deleteFailedJob(int|string $id): JsonResponse
    {
        $job = DB::table('failed_jobs')
            ->where('id', $id)
            ->orWhere('uuid', $id)
            ->first();

        if (! $job) {
            return $this->error('المهمة الفاشلة غير موجودة.', 404);
        }

        Artisan::call('queue:forget', ['id' => $job->uuid]);

        return $this->success(null, 'تم حذف المهمة الفاشلة بنجاح.');
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAllFailedJobs(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();
        if ($count === 0) {
            return $this->success(null, 'لا توجد مهام فاشلة لإعادة المحاولة.');
        }

        Artisan::call('queue:retry', ['id' => ['all']]);

        return $this->success(null, "تمت إعادة محاولة {$count} مهمة بنجاح.");
    }

    /**
     * Flush all failed jobs.
     */
    public function flushFailedJobs(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();
        Artisan::call('queue:flush');

        return $this->success(null, "تم مسح {$count} مهمة فاشلة بنجاح.");
    }

    /**
     * Download the latest database backup.
     */
    public function downloadLatestBackup(): BinaryFileResponse|JsonResponse
    {
        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            return $this->error('لا توجد نسخ احتياطية متوفرة.', 404);
        }

        $files = File::files($backupDir);
        $backupFiles = array_filter($files, function ($file) {
            $ext = strtolower($file->getExtension());

            return in_array($ext, ['sql', 'enc', 'gz', 'zip'], true);
        });

        if (empty($backupFiles)) {
            return $this->error('لا توجد نسخ احتياطية متوفرة.', 404);
        }

        usort($backupFiles, fn ($a, $b) => $b->getMTime() <=> $a->getMTime());
        $latest = reset($backupFiles);

        Audit::log('database_backup_downloaded', null, [
            'filename' => $latest->getFilename(),
            'size' => $latest->getSize(),
            'user_id' => auth()->id(),
        ]);

        return response()->download($latest->getPathname(), $latest->getFilename(), [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
