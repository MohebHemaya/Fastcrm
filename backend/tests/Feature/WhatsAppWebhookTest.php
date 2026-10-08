<?php

use App\Contracts\WhatsAppProvider;
use App\DTOs\WhatsApp\StatusUpdate;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Jobs\ProcessWebhookEventJob;
use App\Models\Driver;
use App\Models\WebhookEvent;
use App\Models\WhatsAppMessage;
use App\Services\WebhookStatusUpdater;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['whatsapp.verify_token' => 'test_token_123']);
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
});

test('GET /webhooks/whatsapp verifies hub token and returns challenge', function () {
    $response = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=test_token_123&hub_challenge=challenge_code_999');

    $response->assertStatus(200)
        ->assertSee('challenge_code_999');
});

test('GET /webhooks/whatsapp with invalid token returns 403', function () {
    $response = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=123');

    $response->assertStatus(403);
});

test('POST /webhooks/whatsapp stores event, dispatches job, and returns 200 immediately', function () {
    Queue::fake([ProcessWebhookEventJob::class]);

    $payload = [
        'messages' => [
            [
                'id' => 'msg_provider_001',
                'from' => '+201012345678',
                'type' => 'text',
                'text' => 'مرحبًا',
                'timestamp' => now()->timestamp,
            ],
        ],
    ];

    $response = $this->postJson('/webhooks/whatsapp', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'received',
        ]);

    $this->assertDatabaseHas('webhook_events', [
        'external_id' => 'msg_provider_001',
    ]);

    Queue::assertPushed(ProcessWebhookEventJob::class);
});

test('duplicate webhook event is ignored and returns 200 without redispatching', function () {
    Queue::fake([ProcessWebhookEventJob::class]);

    WebhookEvent::create([
        'provider' => 'fake',
        'external_id' => 'msg_provider_dup',
        'payload' => ['test' => true],
    ]);

    $payload = [
        'messages' => [
            [
                'id' => 'msg_provider_dup',
                'from' => '+201012345678',
                'text' => 'مكرر',
                'timestamp' => now()->timestamp,
            ],
        ],
    ];

    $response = $this->postJson('/webhooks/whatsapp', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'duplicate_ignored',
        ]);

    Queue::assertNotPushed(ProcessWebhookEventJob::class);
});

test('status updates strictly advance message lifecycle in correct order', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $msg = WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'provider_message_id' => 'wamid_123',
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'رسالة تجريبية',
        'status' => WhatsAppMessageStatus::SENT,
        'sent_at' => now()->subMinute(),
    ]);

    $updater = app(WebhookStatusUpdater::class);

    // 1. Advance to DELIVERED
    $updater->handle(new StatusUpdate('wamid_123', 'delivered'));
    $msg->refresh();
    expect($msg->status)->toBe(WhatsAppMessageStatus::DELIVERED)
        ->and($msg->delivered_at)->not->toBeNull();

    // 2. Advance to READ
    $updater->handle(new StatusUpdate('wamid_123', 'read'));
    $msg->refresh();
    expect($msg->status)->toBe(WhatsAppMessageStatus::READ)
        ->and($msg->read_at)->not->toBeNull();

    // 3. Regression attempt (read -> delivered) must be IGNORED
    $updater->handle(new StatusUpdate('wamid_123', 'delivered'));
    $msg->refresh();
    expect($msg->status)->toBe(WhatsAppMessageStatus::READ);

    // 4. Another regression attempt (read -> sent) must be IGNORED
    $updater->handle(new StatusUpdate('wamid_123', 'sent'));
    $msg->refresh();
    expect($msg->status)->toBe(WhatsAppMessageStatus::READ);
});

test('failed status update records error code and sets failed status', function () {
    $driver = Driver::factory()->create();
    $msg = WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'provider_message_id' => 'wamid_fail_001',
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'رسالة ستفشل',
        'status' => WhatsAppMessageStatus::SENT,
        'sent_at' => now()->subMinute(),
    ]);

    $updater = app(WebhookStatusUpdater::class);
    $updater->handle(new StatusUpdate('wamid_fail_001', 'failed', '131026'));

    $msg->refresh();
    expect($msg->status)->toBe(WhatsAppMessageStatus::FAILED)
        ->and($msg->error_code)->toBe('131026')
        ->and($msg->error_message)->toContain('131026');
});

test('Removed duplicate webhook route under /api/v1/webhooks/whatsapp returns 404', function () {
    // Both GET and POST to duplicate /api/v1/webhooks/whatsapp must return 404 Not Found
    $getResp = $this->getJson('/api/v1/webhooks/whatsapp');
    $getResp->assertStatus(404);

    $postResp = $this->postJson('/api/v1/webhooks/whatsapp', [
        'messages' => [['id' => 'dummy_id']],
    ]);
    $postResp->assertStatus(404);
});

test('Public webhook endpoint /webhooks/whatsapp works without CSRF or Sanctum authentication', function () {
    // GET challenge verification without cookies/headers
    $getResp = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=test_token_123&hub_challenge=valid_challenge');
    $getResp->assertStatus(200)->assertSee('valid_challenge');

    // POST webhook event without CSRF or session cookies
    Queue::fake([ProcessWebhookEventJob::class]);
    $postResp = $this->postJson('/webhooks/whatsapp', [
        'messages' => [
            [
                'id' => 'no_csrf_event_01',
                'from' => '+201011112222',
                'type' => 'text',
                'text' => 'تجربة بدون سي إس آر إف',
                'timestamp' => now()->timestamp,
            ],
        ],
    ]);
    $postResp->assertStatus(200)->assertJson(['status' => 'received']);
});
