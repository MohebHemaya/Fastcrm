<?php

namespace App\Console\Commands;

use App\Mail\HealthAlertMail;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckHealthAlertsCommand extends Command
{
    protected $signature = 'app:check-health-alerts
                            {--force : Force sending email alert even if disabled or throttled}';

    protected $description = 'Check scheduler heartbeat and failed message count and send email alerts if needed';

    public function handle(): int
    {
        $enabled = (bool) config('app.health_alerts.enabled', false) || $this->option('force');
        $maxMinutes = (int) config('app.health_alerts.max_heartbeat_minutes', 5);
        $maxFailedMessages = (int) config('app.health_alerts.max_failed_messages', 20);

        $warnings = [];

        // 1. Check Scheduler Heartbeat
        $lastHeartbeatIso = Cache::get('last_scheduler_run_at');
        $heartbeatAgeMinutes = 999.0;

        if ($lastHeartbeatIso) {
            $lastRun = Carbon::parse($lastHeartbeatIso);
            $heartbeatAgeMinutes = round(abs((int) now()->diffInSeconds($lastRun)) / 60, 1);
        }

        if ($heartbeatAgeMinutes > $maxMinutes) {
            $warnings[] = "مجدول المهام (Cron) متوقف منذ {$heartbeatAgeMinutes} دقيقة (الحد الأقصى المسموح: {$maxMinutes} دقائق).";
        }

        // 2. Check Failed WhatsApp Messages (24h)
        $failedCount = WhatsAppMessage::where('status', 'failed')
            ->where('updated_at', '>=', now()->subHours(24))
            ->count();

        if ($failedCount >= $maxFailedMessages) {
            $warnings[] = "عدد رسائل واتساب الفاشلة خلال الـ 24 ساعة الماضية بلغ {$failedCount} رسالة (الحد الأقصى: {$maxFailedMessages}).";
        }

        if (empty($warnings)) {
            $this->info('System health check passed: no critical alert conditions detected.');

            return self::SUCCESS;
        }

        $this->warn('Health alert conditions triggered:');
        foreach ($warnings as $w) {
            $this->line(" - {$w}");
        }

        if (! $enabled) {
            $this->info('Alert emails are disabled (ALERT_EMAILS_ENABLED=false). Skipping email dispatch.');

            return self::SUCCESS;
        }

        // Check throttle: at most 1 email every 60 minutes unless forced
        $throttleKey = 'health_alert_last_sent';
        if (! $this->option('force') && Cache::has($throttleKey)) {
            $this->info('Alert email was already sent recently (throttled). Skipping.');

            return self::SUCCESS;
        }

        $recipient = config('app.health_alerts.recipient') ?: config('mail.from.address', 'admin@example.com');

        try {
            Mail::to($recipient)->send(new HealthAlertMail($warnings, [
                'scheduler' => [
                    'status' => 'warning',
                    'age_minutes' => $heartbeatAgeMinutes,
                ],
                'whatsapp' => [
                    'failed_messages_24h' => $failedCount,
                ],
            ]));

            Cache::put($throttleKey, now()->toIso8601String(), 3600);
            $this->info("Alert email successfully dispatched to {$recipient}.");

            Log::warning('[HealthMonitoring] Alert email dispatched', [
                'recipient' => $recipient,
                'warnings' => $warnings,
            ]);
        } catch (\Throwable $e) {
            $this->error('Failed to send alert email: '.$e->getMessage());
            Log::error('[HealthMonitoring] Alert email dispatch error: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
