<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Exceptions\WhatsAppPermanentException;
use App\Exceptions\WhatsAppRetryableException;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Driver;
use App\Models\WhatsAppMessage;
use App\WhatsApp\CloudApiProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

test('SendWhatsAppMessageJob handles permanent error without rethrowing', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::NEW]);
    $msg = WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'اختبار خطأ دائم',
        'status' => WhatsAppMessageStatus::QUEUED,
    ]);

    $mockProvider = Mockery::mock(WhatsAppProvider::class);
    $mockProvider->shouldReceive('sendText')
        ->once()
        ->andThrow(new WhatsAppPermanentException('Invalid recipient phone number', 131026));

    $job = new SendWhatsAppMessageJob($msg->id);
    $job->handle($mockProvider);

    $msg->refresh();
    expect($msg->status)->toBe(WhatsAppMessageStatus::FAILED)
        ->and($msg->error_code)->toBe('131026')
        ->and($msg->error_message)->toContain('Invalid recipient phone number');
});

test('SendWhatsAppMessageJob rethrows on retryable error to trigger queue retry', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::NEW]);
    $msg = WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'اختبار خطأ قابل للإعادة',
        'status' => WhatsAppMessageStatus::QUEUED,
    ]);

    $mockProvider = Mockery::mock(WhatsAppProvider::class);
    $mockProvider->shouldReceive('sendText')
        ->once()
        ->andThrow(new WhatsAppRetryableException('Rate limit exceeded', 131048));

    $job = new SendWhatsAppMessageJob($msg->id);

    expect(fn () => $job->handle($mockProvider))
        ->toThrow(WhatsAppRetryableException::class);

    $msg->refresh();
    expect($msg->status)->toBe(WhatsAppMessageStatus::FAILED)
        ->and($msg->error_code)->toBe('131048');
});

test('CloudApiProvider sends template, text, and buttons via HTTP client', function () {
    config([
        'whatsapp.cloud_api.token' => 'mock_token',
        'whatsapp.cloud_api.phone_number_id' => '123456789',
        'whatsapp.cloud_api.version' => 'v25.0',
    ]);

    Http::fake([
        'https://graph.facebook.com/v25.0/123456789/messages' => Http::response([
            'messages' => [['id' => 'wamid.HBgLMDExMQ==']],
        ], 200),
    ]);

    $provider = new CloudApiProvider;

    // 1. sendTemplate
    $tplId = $provider->sendTemplate('+201012345678', 'driver_invitation', 'ar', ['أحمد'], [
        ['id' => 'yes', 'title' => 'نعم'],
    ]);
    expect($tplId)->toBe('wamid.HBgLMDExMQ==');

    // 2. sendText
    $txtId = $provider->sendText('+201012345678', 'مرحبًا بك');
    expect($txtId)->toBe('wamid.HBgLMDExMQ==');

    // 3. sendButtons
    $btnId = $provider->sendButtons('+201012345678', 'اختر الخيار:', [
        ['id' => 'opt_1', 'title' => 'خيار 1'],
    ]);
    expect($btnId)->toBe('wamid.HBgLMDExMQ==');
});

test('CloudApiProvider uses configurable WHATSAPP_GRAPH_VERSION in request URL', function () {
    config([
        'whatsapp.cloud_api.token' => 'custom_token',
        'whatsapp.cloud_api.phone_number_id' => '987654321',
        'whatsapp.cloud_api.version' => 'v26.0',
    ]);

    Http::fake([
        'https://graph.facebook.com/v26.0/987654321/messages' => Http::response([
            'messages' => [['id' => 'wamid.DYNAMIC_VERSION_OK']],
        ], 200),
    ]);

    $provider = new CloudApiProvider;
    $msgId = $provider->sendText('+201012345678', 'تجربة إصدار مخصص');

    expect($msgId)->toBe('wamid.DYNAMIC_VERSION_OK');

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://graph.facebook.com/v26.0/987654321/messages');
    });
});

test('CloudApiProvider maps 4xx responses to WhatsAppPermanentException', function () {
    config([
        'whatsapp.cloud_api.token' => 'mock_token',
        'whatsapp.cloud_api.phone_number_id' => '123456789',
        'whatsapp.cloud_api.version' => 'v25.0',
    ]);

    Http::fake([
        'https://graph.facebook.com/v25.0/123456789/messages' => Http::response([
            'error' => [
                'message' => 'Recipient not found',
                'code' => 100,
                'error_subcode' => 131026,
            ],
        ], 400),
    ]);

    $provider = new CloudApiProvider;

    expect(fn () => $provider->sendText('+201000000000', 'مرحبًا'))
        ->toThrow(WhatsAppPermanentException::class);
});

test('CloudApiProvider maps 429 and 5xx responses to WhatsAppRetryableException', function () {
    config([
        'whatsapp.cloud_api.token' => 'mock_token',
        'whatsapp.cloud_api.phone_number_id' => '123456789',
        'whatsapp.cloud_api.version' => 'v25.0',
    ]);

    Http::fake([
        'https://graph.facebook.com/v25.0/123456789/messages' => Http::response([
            'error' => [
                'message' => 'Rate limit hit',
                'code' => 429,
                'error_subcode' => 131048,
            ],
        ], 429),
    ]);

    $provider = new CloudApiProvider;

    expect(fn () => $provider->sendText('+201000000000', 'مرحبًا'))
        ->toThrow(WhatsAppRetryableException::class);
});

test('CloudApiProvider verifies webhook signature correctly', function () {
    $secret = 'test_app_secret_abc123';
    config([
        'whatsapp.cloud_api.app_secret' => $secret,
    ]);

    $payloadContent = json_encode(['entry' => []]);
    $validSignature = 'sha256='.hash_hmac('sha256', $payloadContent, $secret);

    $request = Request::create('/webhooks/whatsapp', 'POST', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $validSignature,
        'CONTENT_TYPE' => 'application/json',
    ], $payloadContent);

    $provider = new CloudApiProvider;

    // Valid signature does not throw
    $events = $provider->parseWebhook($request);
    expect($events)->toBeArray();

    // Invalid signature throws ValidationException
    $invalidRequest = Request::create('/webhooks/whatsapp', 'POST', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid_hash',
        'CONTENT_TYPE' => 'application/json',
    ], $payloadContent);

    expect(fn () => $provider->parseWebhook($invalidRequest))
        ->toThrow(ValidationException::class);
});
