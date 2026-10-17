<?php

use App\Contracts\WhatsAppProvider;
use App\DTOs\WhatsApp\InboundMessage;
use App\DTOs\WhatsApp\StatusUpdate;
use App\Exceptions\WhatsAppPermanentException;
use App\Exceptions\WhatsAppRetryableException;
use App\WhatsApp\UltraMsgProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

test('UltraMsgProvider sends direct text message via messages/chat API', function () {
    config([
        'whatsapp.ultramsg.token' => 'ultramsg_secret_token_123',
        'whatsapp.ultramsg.instance_id' => 'instance194026',
        'whatsapp.ultramsg.base_url' => 'https://api.ultramsg.com',
    ]);

    Http::fake([
        'https://api.ultramsg.com/instance194026/messages/chat' => function (Illuminate\Http\Client\Request $request) {
            expect($request['token'])->toBe('ultramsg_secret_token_123');
            expect($request['to'])->toBe('+201278842350');
            expect($request['body'])->toBe('مرحبًا يا كابتن من UltraMsg');

            return Http::response([
                'sent' => 'true',
                'message' => 'ok',
                'id' => 101,
            ], 200);
        },
    ]);

    $provider = new UltraMsgProvider;
    $msgId = $provider->sendText('+201278842350', 'مرحبًا يا كابتن من UltraMsg');

    expect($msgId)->toBe('101');
});

test('UltraMsgProvider formats buttons into clear numbered options', function () {
    config([
        'whatsapp.ultramsg.token' => 'token_abc',
        'whatsapp.ultramsg.instance_id' => 'instance194026',
    ]);

    Http::fake([
        'https://api.ultramsg.com/instance194026/messages/chat' => function (Illuminate\Http\Client\Request $request) {
            expect($request['body'])->toContain('متاح رحلة جديدة')
                ->and($request['body'])->toContain('1️⃣ قبول')
                ->and($request['body'])->toContain('2️⃣ اعتذار');

            return Http::response(['sent' => 'true', 'message' => 'ok', 'id' => 102], 200);
        },
    ]);

    $provider = new UltraMsgProvider;
    $msgId = $provider->sendButtons('+201278842350', 'متاح رحلة جديدة', [
        ['id' => '1', 'title' => 'قبول'],
        ['id' => '2', 'title' => 'اعتذار'],
    ]);

    expect($msgId)->toBe('102');
});

test('UltraMsgProvider formats list into readable message', function () {
    config([
        'whatsapp.ultramsg.token' => 'token_xyz',
        'whatsapp.ultramsg.instance_id' => 'instance194026',
    ]);

    Http::fake([
        'https://api.ultramsg.com/instance194026/messages/chat' => function (Illuminate\Http\Client\Request $request) {
            expect($request['body'])->toContain('قائمة الشفتات')
                ->and($request['body'])->toContain('1️⃣ صباحي')
                ->and($request['body'])->toContain('2️⃣ مسائي');

            return Http::response(['sent' => 'true', 'message' => 'ok', 'id' => 103], 200);
        },
    ]);

    $provider = new UltraMsgProvider;
    $msgId = $provider->sendList('+201278842350', 'قائمة الشفتات', 'اختر شفت', [
        ['id' => 'morn', 'title' => 'صباحي', 'description' => 'من 8 ص إلى 4 م'],
        ['id' => 'eve', 'title' => 'مسائي', 'description' => 'من 4 م إلى 12 ص'],
    ]);

    expect($msgId)->toBe('103');
});

test('UltraMsgProvider throws WhatsAppRetryableException on 429 rate limit', function () {
    config([
        'whatsapp.ultramsg.token' => 'token_rate',
        'whatsapp.ultramsg.instance_id' => 'instance194026',
    ]);

    Http::fake([
        'https://api.ultramsg.com/instance194026/messages/chat' => Http::response([
            'error' => 'Rate limit exceeded',
        ], 429),
    ]);

    $provider = new UltraMsgProvider;
    expect(fn () => $provider->sendText('+201278842350', 'تجربة'))
        ->toThrow(WhatsAppRetryableException::class);
});

test('UltraMsgProvider throws WhatsAppPermanentException on UltraMsg error payload', function () {
    config([
        'whatsapp.ultramsg.token' => 'invalid_token',
        'whatsapp.ultramsg.instance_id' => 'instance194026',
    ]);

    Http::fake([
        'https://api.ultramsg.com/instance194026/messages/chat' => Http::response([
            'error' => 'Invalid token or instance',
        ], 200),
    ]);

    $provider = new UltraMsgProvider;
    expect(fn () => $provider->sendText('+201278842350', 'تجربة'))
        ->toThrow(WhatsAppPermanentException::class);
});

test('UltraMsgProvider parses inbound message_received webhook correctly', function () {
    $provider = new UltraMsgProvider;

    $payload = [
        'event_type' => 'message_received',
        'instanceId' => 'instance194026',
        'data' => [
            'id' => 'msg_ultra_777',
            'from' => '201278842350@c.us',
            'to' => '123456789@c.us',
            'body' => 'أنا متاح للعمل الآن',
            'fromMe' => false,
            'time' => 1718000000,
        ],
    ];

    $request = Request::create('/webhooks/whatsapp', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $events = $provider->parseWebhook($request);

    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(InboundMessage::class);
    expect($events[0]->from)->toBe('+201278842350');
    expect($events[0]->text)->toBe('أنا متاح للعمل الآن');
    expect($events[0]->providerMessageId)->toBe('msg_ultra_777');
});

test('UltraMsgProvider parses message_ack webhook into StatusUpdate', function () {
    $provider = new UltraMsgProvider;

    $payload = [
        'event_type' => 'message_ack',
        'instanceId' => 'instance194026',
        'data' => [
            'id' => 'msg_ultra_777',
            'ack' => 'delivered',
        ],
    ];

    $request = Request::create('/webhooks/whatsapp', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $events = $provider->parseWebhook($request);

    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(StatusUpdate::class);
    expect($events[0]->providerMessageId)->toBe('msg_ultra_777');
    expect($events[0]->status)->toBe('delivered');
});

test('UltraMsgProvider verifies webhook token', function () {
    config(['whatsapp.verify_token' => 'ultra_secret_123']);
    $provider = new UltraMsgProvider;

    $validRequest = Request::create('/webhooks/whatsapp?token=ultra_secret_123&hub_challenge=ch_999');
    expect($provider->verifyWebhook($validRequest))->toBe('ch_999');

    $invalidRequest = Request::create('/webhooks/whatsapp?token=wrong_token');
    expect(fn () => $provider->verifyWebhook($invalidRequest))
        ->toThrow(ValidationException::class);
});

test('AppServiceProvider resolves UltraMsgProvider when configured', function () {
    config(['whatsapp.provider' => 'ultramsg']);

    $resolved = app(WhatsAppProvider::class);
    expect($resolved)->toBeInstanceOf(UltraMsgProvider::class);
});
