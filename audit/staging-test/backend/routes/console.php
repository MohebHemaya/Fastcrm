<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('app:heartbeat', function () {
    Cache::forever('last_scheduler_run_at', now()->toIso8601String());
    $this->info('Scheduler heartbeat recorded at: '.now()->toIso8601String());
})->purpose('Record scheduler heartbeat timestamp in cache');

Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('app:heartbeat')
    ->everyMinute();

Schedule::command('drivers:expire-invites')
    ->hourly();

Schedule::command('questionnaire:check-timeouts')
    ->everyFiveMinutes();

Schedule::command('work-orders:reconcile-notifications')
    ->everyFiveMinutes();

Schedule::command('work-orders:send-reminders')
    ->everyFifteenMinutes();

Schedule::command('exports:cleanup')
    ->daily();

Schedule::command('app:prune-retention-data')
    ->daily();

Schedule::command('app:backup-db --keep=7')
    ->daily();

Schedule::command('app:check-health-alerts')
    ->everyFiveMinutes();
