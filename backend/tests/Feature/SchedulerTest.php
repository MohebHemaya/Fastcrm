<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

test('scheduler registers queue work and heartbeat commands', function () {
    $schedule = app()->make(Schedule::class);
    $events = collect($schedule->events());

    $commands = $events->map(function ($event) {
        return $event->command;
    })->filter()->values();

    $hasQueue = $commands->contains(function ($cmd) {
        return str_contains($cmd, 'queue:work') && str_contains($cmd, '--stop-when-empty');
    });

    $hasHeartbeat = $commands->contains(function ($cmd) {
        return str_contains($cmd, 'app:heartbeat');
    });

    expect($hasQueue)->toBeTrue()
        ->and($hasHeartbeat)->toBeTrue();
});

test('heartbeat command records timestamp in cache', function () {
    Cache::forget('last_scheduler_run_at');

    Artisan::call('app:heartbeat');

    $timestamp = Cache::get('last_scheduler_run_at');
    expect($timestamp)->not->toBeNull();
});
