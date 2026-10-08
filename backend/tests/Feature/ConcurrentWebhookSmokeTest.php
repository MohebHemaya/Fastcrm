<?php

use Illuminate\Support\Facades\Artisan;

test('concurrent webhook smoke test processes 100 requests with exactly 8 intentional duplicates and 0 deadlocks', function () {
    $exitCode = Artisan::call('app:smoke-concurrent-webhooks', [
        '--concurrency' => 10,
        '--total' => 100,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('PASSED: Exactly 92 new events and 8 intentional duplicates processed with 0 deadlocks and 0 errors.')
        ->and($output)->toContain('8 intentional duplicates');
});
