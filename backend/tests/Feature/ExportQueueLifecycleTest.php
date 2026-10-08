<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\ExportStatus;
use App\Jobs\ProcessExportJob;
use App\Models\Driver;
use App\Models\Export;
use App\Models\User;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Storage::fake('local');
});

test('export queue flow: queued -> processing -> done -> authenticated download', function () {
    Queue::fake([ProcessExportJob::class]);

    Driver::factory()->count(5)->create(['status' => DriverStatus::ACTIVE]);

    // 1. Dispatch export via API
    $response = $this->postJson('/api/v1/exports', [
        'type' => 'drivers',
        'params' => ['status' => 'active'],
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.type', 'drivers')
        ->assertJsonPath('data.status', 'queued');

    $exportId = $response->json('data.id');
    Queue::assertPushed(ProcessExportJob::class, fn ($job) => $job->exportId === $exportId);

    // 2. Execute the job synchronously to simulate worker execution
    $job = new ProcessExportJob($exportId);
    $job->handle();

    $export = Export::find($exportId);
    expect($export->status)->toBe(ExportStatus::DONE)
        ->and($export->path)->not->toBeNull()
        ->and($export->filename)->not->toBeNull()
        ->and(Storage::disk('local')->exists($export->path))->toBeTrue();

    // 3. Status endpoint shows done
    $statusResponse = $this->getJson("/api/v1/exports/{$exportId}");
    $statusResponse->assertOk()
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.is_downloadable', true);

    // 4. Download file as authenticated user
    $downloadResponse = $this->get("/api/v1/exports/{$exportId}/download");
    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('content-disposition');
});

test('unauthenticated request to download export is rejected with 401', function () {
    $export = Export::factory()->done()->create();
    Storage::disk('local')->put($export->path, 'mock content');

    // Make request without authentication
    auth()->logout();
    $response = $this->getJson("/api/v1/exports/{$export->id}/download");
    $response->assertStatus(401);
});

test('downloading non-ready or expired export returns proper HTTP error codes', function () {
    // 1. Pending export returns 400
    $pendingExport = Export::factory()->create([
        'user_id' => $this->user->id,
        'status' => ExportStatus::QUEUED,
    ]);
    $res1 = $this->getJson("/api/v1/exports/{$pendingExport->id}/download");
    $res1->assertStatus(400);

    // 2. Expired export returns 410
    $expiredExport = Export::factory()->done()->create([
        'user_id' => $this->user->id,
        'expires_at' => now()->subDay(),
    ]);
    Storage::disk('local')->put($expiredExport->path, 'mock content');
    $res2 = $this->getJson("/api/v1/exports/{$expiredExport->id}/download");
    $res2->assertStatus(410);

    // 3. Missing file on disk returns 404
    $missingFileExport = Export::factory()->done()->create([
        'user_id' => $this->user->id,
        'path' => 'exports/non_existent.xlsx',
        'expires_at' => now()->addDays(2),
    ]);
    $res3 = $this->getJson("/api/v1/exports/{$missingFileExport->id}/download");
    $res3->assertStatus(404);
});

test('scheduled command exports:cleanup deletes expired files and records older than 7 days', function () {
    // 1. Fresh export - should NOT be deleted
    $freshExport = Export::factory()->done()->create([
        'path' => 'exports/fresh.xlsx',
        'expires_at' => now()->addDays(5),
    ]);
    Storage::disk('local')->put($freshExport->path, 'fresh file content');

    // 2. Expired export - SHOULD be deleted
    $expiredExport = Export::factory()->done()->create([
        'path' => 'exports/expired.xlsx',
        'expires_at' => now()->subMinutes(10),
    ]);
    Storage::disk('local')->put($expiredExport->path, 'expired file content');

    expect(Storage::disk('local')->exists($expiredExport->path))->toBeTrue();

    // Run cleanup command
    Artisan::call('exports:cleanup');

    // Expired record and file removed
    expect(Export::find($expiredExport->id))->toBeNull();
    expect(Storage::disk('local')->exists($expiredExport->path))->toBeFalse();

    // Fresh record and file preserved
    expect(Export::find($freshExport->id))->not->toBeNull();
    expect(Storage::disk('local')->exists($freshExport->path))->toBeTrue();
});

test('downloading another users export is rejected with 403 Forbidden', function () {
    $otherUser = User::factory()->create();
    $export = Export::factory()->done()->create([
        'user_id' => $otherUser->id,
    ]);
    Storage::disk('local')->put($export->path, 'secret export content');

    // Request as current user ($this->user), who does not own $export
    $response = $this->getJson("/api/v1/exports/{$export->id}/download");
    $response->assertStatus(403)
        ->assertJsonPath('message', 'غير مصرح لك بتحميل هذا الملف.');
});

test('export files strictly reside outside the public directory', function () {
    $export = Export::factory()->done()->create([
        'path' => 'exports/sample_private.xlsx',
    ]);
    Storage::disk('local')->put($export->path, 'private content');

    $fullPath = Storage::disk('local')->path($export->path);
    $publicPath = public_path();

    expect(str_starts_with($fullPath, $publicPath))->toBeFalse();
});
