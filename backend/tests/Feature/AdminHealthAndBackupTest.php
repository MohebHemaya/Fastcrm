<?php

use App\Mail\HealthAlertMail;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

test('GET /api/v1/admin/health requires authentication', function () {
    $this->getJson('/api/v1/admin/health')->assertStatus(401);
});

test('GET /api/v1/admin/health returns full metrics and detects stale scheduler heartbeat', function () {
    $user = User::factory()->create();

    // 1. Fresh heartbeat (1 minute old)
    Cache::forever('last_scheduler_run_at', now()->subMinute()->toIso8601String());

    $response = $this->actingAs($user)->getJson('/api/v1/admin/health');
    $response->assertStatus(200)
        ->assertJsonPath('data.scheduler.status', 'ok')
        ->assertJsonPath('data.scheduler.is_stale', false)
        ->assertJsonPath('data.database.status', 'ok');

    // 2. Stale heartbeat (7 minutes old)
    Cache::forever('last_scheduler_run_at', now()->subMinutes(7)->toIso8601String());

    $responseStale = $this->actingAs($user)->getJson('/api/v1/admin/health');
    $responseStale->assertStatus(200)
        ->assertJsonPath('data.scheduler.status', 'warning')
        ->assertJsonPath('data.scheduler.is_stale', true)
        ->assertJsonPath('data.status', 'warning');
});

test('failed jobs can be inspected, retried, and deleted via health admin endpoints', function () {
    $user = User::factory()->create();

    // Insert a dummy failed job into failed_jobs table
    $uuid = (string) str()->uuid();
    $id = DB::table('failed_jobs')->insertGetId([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendWhatsAppMessageJob']),
        'exception' => "RuntimeException: Meta API 500 error in file.php:12\nStack trace: ...",
        'failed_at' => now(),
    ]);

    // Inspect list
    $res = $this->actingAs($user)->getJson('/api/v1/admin/health');
    $res->assertStatus(200)
        ->assertJsonPath('data.failed_jobs.count', 1);

    // Delete failed job
    $delRes = $this->actingAs($user)->deleteJson("/api/v1/admin/health/failed-jobs/{$id}");
    $delRes->assertStatus(200);

    expect(DB::table('failed_jobs')->where('id', $id)->first())->toBeNull();
});

test('app:check-health-alerts dispatches alert email when heartbeat is stale or messages failed', function () {
    Mail::fake();

    // Stale heartbeat
    Cache::forever('last_scheduler_run_at', now()->subMinutes(10)->toIso8601String());
    Cache::forget('health_alert_last_sent');

    $exitCode = Artisan::call('app:check-health-alerts', ['--force' => true]);
    expect($exitCode)->toBe(0);

    Mail::assertSent(HealthAlertMail::class, function ($mail) {
        return count($mail->warnings) > 0;
    });
});

test('app:backup-db generates a valid backup and prunes old backups according to retention', function () {
    $backupDir = storage_path('app/backups');

    $exitCode = Artisan::call('app:backup-db', [
        '--keep' => 3,
        '--filename' => 'test_backup_run.sql',
    ]);

    expect($exitCode)->toBe(0);
    $backupFile = $backupDir.'/test_backup_run.sql';

    expect(file_exists($backupFile))->toBeTrue()
        ->and(filesize($backupFile))->toBeGreaterThan(1000);

    $content = file_get_contents($backupFile);
    expect($content)->toContain('SET FOREIGN_KEY_CHECKS=0;')
        ->and($content)->toContain('START TRANSACTION;')
        ->and($content)->toContain('COMMIT;');

    // Test retention pruning
    for ($i = 1; $i <= 5; $i++) {
        touch($backupDir."/dummy_old_backup_{$i}.sql", time() - (86400 * (10 - $i)));
    }

    Artisan::call('app:backup-db', ['--keep' => 3]);

    $remaining = File::glob($backupDir.'/*.sql');
    expect(count($remaining))->toBeLessThanOrEqual(3);

    // Clean up test files
    foreach (File::glob($backupDir.'/*.sql') as $f) {
        @unlink($f);
    }
});
