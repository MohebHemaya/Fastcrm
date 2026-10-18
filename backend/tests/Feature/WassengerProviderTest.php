<?php

use App\Contracts\WhatsAppProvider;
use App\DTOs\WhatsApp\InboundMessage;
use App\DTOs\WhatsApp\StatusUpdate;
use App\Exceptions\WhatsAppPermanentException;
use App\WhatsApp\WassengerProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

test('WassengerProvider sends direct text message via /messages API', function () {
    config([
        'whatsapp.wassenger.token' => 'test_wassenger_token',
        'whatsapp.wassenger.device_id' => 'device_xyz',
        'whatsapp.wassenger.base_url' => 'https://api.wassenger.com/v1',
    ]);

    Http::fake([
        'https://api.wassenger.com/v1/messages' => function (Illuminate\Http\Client\Request $request) {
            expect($request->hasHeader('Token', 'test_wassenger_token'))->toBeTrue();
            expect($request['phone'])->toBe('+201278842350');
            expect($request['message'])->toBe('مرحبًا بك في فاست اكسبريس');
            expect($request['device'])->toBe('device_xyz');

            return Http::response([
                'id' => 'msg_w101',
                'status' => 'queued',
            ], 201);
        },
    ]);

    $provider = new WassengerProvider;
    $msgId = $provider->sendText('01278842350', 'مرحبًا بك في فاست اكسبريس');

    expect($msgId)->toBe('msg_w101');
});

test('WassengerProvider sends native WhatsApp Poll with options for buttons', function () {
    config([
        'whatsapp.wassenger.token' => 'test_wassenger_token',
        'whatsapp.wassenger.device_id' => 'device_xyz',
        'whatsapp.wassenger.base_url' => 'https://api.wassenger.com/v1',
    ]);

    Http::fake([
        'https://api.wassenger.com/v1/messages' => function (Illuminate\Http\Client\Request $request) {
            expect($request['phone'])->toBe('+201278842350');
            expect($request['poll']['name'])->toContain('عرض رحلة #1042');
            expect($request['poll']['options'])->toBe(['قبول الرحلة', 'اعتذار']);
            expect($request['poll']['multiple'])->toBeFalse();

            return Http::response([
                'id' => 'poll_msg_202',
                'status' => 'queued',
            ], 201);
        },
    ]);

    $provider = new WassengerProvider;
    $msgId = $provider->sendButtons('01278842350', 'عرض رحلة #1042 - العاشر ⬅️ الإسكندرية', [
        ['id' => 'accept', 'title' => 'قبول الرحلة'],
        ['id' => 'decline', 'title' => 'اعتذار'],
    ]);

    expect($msgId)->toBe('poll_msg_202');
});

test('WassengerProvider sends image with caption', function () {
    config([
        'whatsapp.wassenger.token' => 'test_wassenger_token',
        'whatsapp.wassenger.device_id' => 'device_xyz',
        'whatsapp.wassenger.base_url' => 'https://api.wassenger.com/v1',
    ]);

    Http::fake([
        'https://api.wassenger.com/v1/messages' => function (Illuminate\Http\Client\Request $request) {
            expect($request['phone'])->toBe('+201278842350');
            expect($request['media']['url'])->toBe('https://example.com/logo.png');
            expect($request['message'])->toBe('شعار فاست اكسبريس');

            return Http::response([
                'id' => 'img_msg_303',
                'status' => 'queued',
            ], 201);
        },
    ]);

    $provider = new WassengerProvider;
    $msgId = $provider->sendImage('01278842350', 'https://example.com/logo.png', 'شعار فاست اكسبريس');

    expect($msgId)->toBe('img_msg_303');
});

test('WassengerProvider parses inbound message webhook (message:in:new)', function () {
    $provider = new WassengerProvider;

    $payload = [
        'event' => 'message:in:new',
        'time' => 1718801234,
        'data' => [
            'id' => 'in_msg_999',
            'chat' => [
                'contact' => [
                    'phone' => '+201278842350',
                ],
            ],
            'type' => 'text',
            'text' => 'أنا جاهز ومتاح للرحلة',
            'fromMe' => false,
        ],
    ];

    $request = Request::create('/webhooks/whatsapp', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $events = $provider->parseWebhook($request);

    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(InboundMessage::class);
    expect($events[0]->from)->toBe('+201278842350');
    expect($events[0]->text)->toBe('أنا جاهز ومتاح للرحلة');
    expect($events[0]->type)->toBe('text');
});

test('WassengerProvider parses poll vote webhook (message:update with poll)', function () {
    $provider = new WassengerProvider;

    $payload = [
        'event' => 'message:update',
        'data' => [
            'id' => 'poll_12345',
            'type' => 'poll',
            'poll' => [
                'name' => 'عرض رحلة #1042',
                'votes' => [
                    [
                        'option' => '🟢 قبول الرحلة والتحرك الآن',
                        'sender' => '201278842350@c.us',
                        'timestamp' => 1718805000,
                    ],
                ],
            ],
        ],
    ];

    $request = Request::create('/webhooks/whatsapp', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $events = $provider->parseWebhook($request);

    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(InboundMessage::class);
    expect($events[0]->from)->toBe('+201278842350');
    expect($events[0]->type)->toBe('button');
    expect($events[0]->text)->toBe('🟢 قبول الرحلة والتحرك الآن');
    expect($events[0]->payloadId)->toBe('🟢 قبول الرحلة والتحرك الآن');
});

test('WassengerProvider parses message status update (message:out:ack)', function () {
    $provider = new WassengerProvider;

    $payload = [
        'event' => 'message:out:ack',
        'data' => [
            'id' => 'out_msg_777',
            'ack' => 3,
            'status' => 'read',
        ],
    ];

    $request = Request::create('/webhooks/whatsapp', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $events = $provider->parseWebhook($request);

    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(StatusUpdate::class);
    expect($events[0]->providerMessageId)->toBe('out_msg_777');
    expect($events[0]->status)->toBe('read');
});

test('WassengerProvider handles API error and throws WhatsAppPermanentException', function () {
    config([
        'whatsapp.wassenger.token' => 'invalid_token',
        'whatsapp.wassenger.base_url' => 'https://api.wassenger.com/v1',
    ]);

    Http::fake([
        'https://api.wassenger.com/v1/messages' => Http::response([
            'message' => 'Unauthorized token',
        ], 401),
    ]);

    $provider = new WassengerProvider;
    $provider->sendText('01278842350', 'تجربة خطأ');
})->throws(WhatsAppPermanentException::class);

test('WassengerProvider resolves correctly from Laravel container when configured', function () {
    config(['whatsapp.provider' => 'wassenger']);
    $resolved = app(WhatsAppProvider::class);

    expect($resolved)->toBeInstanceOf(WassengerProvider::class);
});
