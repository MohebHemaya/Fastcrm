<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('queue:work is executed by scheduler and processes queued jobs from database', function () {
    // 1. Configure database queue connection
    config(['queue.default' => 'database']);
    Cache::forget('test_job_processed');

    // 2. Clear any leftover jobs
    DB::table('jobs')->truncate();

    // 3. Dispatch a job to the database queue
    dispatch(function () {
        Cache::put('test_job_processed', true, 60);
    });

    // Verify job is waiting in the database table
    expect(DB::table('jobs')->count())->toBe(1);

    // 4. Trigger the queue:work command registered in the scheduler
    $schedule = app()->make(Schedule::class);
    $queueEvent = collect($schedule->events())->first(function ($event) {
        return str_contains($event->command, 'queue:work');
    });

    expect($queueEvent)->not->toBeNull();

    // Run the scheduled queue worker command
    Artisan::call('queue:work', [
        'connection' => 'database',
        '--stop-when-empty' => true,
        '--max-time' => 10,
    ]);

    // 5. Prove that the job was processed and removed from database
    expect(DB::table('jobs')->count())->toBe(0);
    expect(Cache::get('test_job_processed'))->toBe(true);
});
