<?php

use App\Logging\MaskPhoneNumberProcessor;
use App\Models\Driver;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\WhatsAppMessage;
use App\Services\PhoneNormalizer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Monolog\Level;
use Monolog\LogRecord;

test('security headers middleware adds HSTS, nosniff, frame options, referrer policy and CSP', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(200);
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($response->headers->get('Content-Security-Policy'))
        ->toBe("default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self';");
});

test('login endpoint enforces rate limiting after 5 attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ratelimit@example.com',
            'password' => 'wrong-pass',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ratelimit@example.com',
        'password' => 'wrong-pass',
    ]);

    $response->assertStatus(429);
});

test('exports endpoint enforces rate limiting', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)->postJson('/api/v1/exports', [
            'type' => 'drivers',
        ]);
    }

    $response = $this->actingAs($user)->postJson('/api/v1/exports', [
        'type' => 'drivers',
    ]);

    $response->assertStatus(429);
});

test('Cloud WhatsApp Webhook strictly rejects unsigned or wrongly signed requests with 403', function () {
    Config::set('whatsapp.provider', 'cloud');
    Config::set('whatsapp.cloud_api.app_secret', 'test_app_secret_123');

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [],
    ];

    // 1. Missing signature
    $resMissing = $this->postJson('/webhooks/whatsapp', $payload);
    $resMissing->assertStatus(403);

    // 2. Invalid signature
    $resInvalid = $this->withHeaders([
        'X-Hub-Signature-256' => 'sha256=invalid_hash',
    ])->postJson('/webhooks/whatsapp', $payload);
    $resInvalid->assertStatus(403);

    // 3. Valid signature
    $content = json_encode($payload);
    $validSig = 'sha256='.hash_hmac('sha256', $content, 'test_app_secret_123');

    $resValid = $this->call(
        'POST',
        '/webhooks/whatsapp',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Hub-Signature-256' => $validSig,
        ],
        $content
    );

    $resValid->assertStatus(200);
});

test('PhoneNormalizer correctly masks phone numbers keeping prefix and last 3-4 digits', function () {
    expect(PhoneNormalizer::mask('+201012345678'))->toBe('+201*****5678')
        ->and(PhoneNormalizer::mask('01123456789'))->toBe('011****6789')
        ->and(PhoneNormalizer::mask(''))->toBe('')
        ->and(PhoneNormalizer::mask(null))->toBe('');
});

test('MaskPhoneNumberProcessor sanitizes phone numbers in log messages and context arrays', function () {
    $processor = new MaskPhoneNumberProcessor;

    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: Level::Info,
        message: 'Dispatched order offer to +201012345678 successfully',
        context: [
            'recipient' => '+201198765432',
            'details' => [
                'phone' => '+201211112222',
                'name' => 'Driver Ali',
            ],
        ]
    );

    $processed = $processor($record);

    expect((string) $processed->message)->toContain('+201*****5678')
        ->and((string) $processed->message)->not->toContain('12345678')
        ->and($processed->context['recipient'])->toBe('+201*****5432')
        ->and($processed->context['details']['phone'])->toBe('+201*****2222');
});

test('app:prune-retention-data deletes old webhook events and clears old message payloads', function () {
    $driver = Driver::factory()->create();

    // 1. Old webhook event (4 months old)
    $oldEvent = WebhookEvent::create([
        'provider' => 'whatsapp',
        'external_id' => 'evt_old_'.uniqid(),
        'payload' => ['sample' => 'old_event_data'],
    ]);
    DB::table('webhook_events')->where('id', $oldEvent->id)->update([
        'created_at' => now()->subMonths(4),
    ]);

    // 2. Recent webhook event (10 days old)
    $recentEvent = WebhookEvent::create([
        'provider' => 'whatsapp',
        'external_id' => 'evt_recent_'.uniqid(),
        'payload' => ['sample' => 'recent_event_data'],
    ]);
    DB::table('webhook_events')->where('id', $recentEvent->id)->update([
        'created_at' => now()->subDays(10),
    ]);

    // 3. Old WhatsApp message with payload
    $oldMsg = WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => 'out',
        'type' => 'template',
        'payload' => ['heavy_json' => str_repeat('X', 500)],
        'body' => 'Important audit message',
        'status' => 'delivered',
    ]);
    DB::table('whatsapp_messages')->where('id', $oldMsg->id)->update([
        'created_at' => now()->subMonths(4),
    ]);

    // 4. Recent WhatsApp message with payload
    $recentMsg = WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => 'out',
        'type' => 'template',
        'payload' => ['recent_json' => 'keep_this'],
        'body' => 'Recent message',
        'status' => 'sent',
    ]);

    // Run pruning command with 3 months retention
    $exitCode = Artisan::call('app:prune-retention-data', ['--months' => 3]);
    expect($exitCode)->toBe(0);

    // Assert old webhook event was hard deleted
    expect(WebhookEvent::find($oldEvent->id))->toBeNull()
        ->and(WebhookEvent::find($recentEvent->id))->not->toBeNull();

    // Assert old message payload was nulled while metadata was retained
    $oldMsgFresh = WhatsAppMessage::find($oldMsg->id);
    expect($oldMsgFresh)->not->toBeNull()
        ->and($oldMsgFresh->payload)->toBeNull()
        ->and($oldMsgFresh->body)->toBe('Important audit message');

    // Assert recent message payload is untouched
    $recentMsgFresh = WhatsAppMessage::find($recentMsg->id);
    expect($recentMsgFresh->payload)->not->toBeNull();
});

test('app:backup-db generates encrypted backup with APP_KEY when requested', function () {
    $backupDir = storage_path('app/backups');
    File::ensureDirectoryExists($backupDir);

    $filename = 'test_enc_backup_'.uniqid().'.sql';
    $exitCode = Artisan::call('app:backup-db', [
        '--filename' => $filename,
        '--encrypt' => true,
    ]);

    expect($exitCode)->toBe(0);

    $encryptedPath = $backupDir.'/'.$filename.'.enc';
    expect(File::exists($encryptedPath))->toBeTrue();
    expect(File::exists($backupDir.'/'.$filename))->toBeFalse();

    // Verify it decrypts with Crypt to valid SQL content
    $encryptedContent = File::get($encryptedPath);
    $decrypted = Crypt::decrypt($encryptedContent);
    expect($decrypted)->toContain('Fast CRM Database Backup');

    // Clean up
    @File::delete($encryptedPath);
});

test('app:backup-db replicates backup to secondary disk and admin can download latest backup', function () {
    Storage::fake('secondary_disk');
    $backupDir = storage_path('app/backups');
    File::ensureDirectoryExists($backupDir);

    $filename = 'test_replicate_'.uniqid().'.sql';
    $exitCode = Artisan::call('app:backup-db', [
        '--filename' => $filename,
        '--secondary-disk' => 'secondary_disk',
    ]);

    expect($exitCode)->toBe(0);
    expect(Storage::disk('secondary_disk')->exists('backups/'.$filename))->toBeTrue();

    // Authenticated admin can download latest backup
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $response = $this->get('/api/v1/admin/health/backups/latest');
    $response->assertOk();
    $response->assertHeader('content-disposition');

    // Clean up local file
    @File::delete($backupDir.'/'.$filename);
});
