<?php

use App\Contracts\WhatsAppProvider;
use App\DTOs\WhatsApp\InboundMessage;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Models\Driver;
use App\Models\Question;
use App\Models\WhatsAppMessage;
use App\Services\InboundMessageRouter;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
    Question::query()->delete();
    Cache::flush();
});

test('Inbound message is persisted to whatsapp_messages table with direction in', function () {
    $driver = Driver::factory()->create([
        'phone' => '+201012345678',
        'status' => DriverStatus::ACTIVE,
    ]);

    $router = app(InboundMessageRouter::class);
    $inbound = new InboundMessage(
        from: '+201012345678',
        type: 'text',
        text: 'رسالة عادية',
        payloadId: null,
        providerMessageId: 'inbound_persisted_01',
        timestamp: now()->timestamp,
    );

    $router->handle($inbound);

    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::IN->value,
        'provider_message_id' => 'inbound_persisted_01',
        'body' => 'رسالة عادية',
        'status' => WhatsAppMessageStatus::DELIVERED->value,
    ]);
});

test('Opt-out keyword matrix transitions driver to opted_out and sends one confirmation', function (string $keyword) {
    $driver = Driver::factory()->create([
        'phone' => '+201011112222',
        'status' => DriverStatus::INVITED,
    ]);

    $router = app(InboundMessageRouter::class);
    $inbound = new InboundMessage(
        from: '+201011112222',
        type: 'text',
        text: $keyword,
        payloadId: null,
        providerMessageId: 'in_opt_'.md5($keyword),
        timestamp: now()->timestamp,
    );

    $router->handle($inbound);

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::OPTED_OUT)
        ->and($driver->opted_out_at)->not->toBeNull();

    // Check confirmation message was created
    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT->value,
    ]);
})->with([
    'إيقاف',
    'ايقاف',
    ' إيقاف ',
    'إِيقَافٌ', // with tashkeel
    'stop',
    'STOP',
    'إلغاء',
    'الغاء',
    'إِلْغَاء', // with tashkeel
    'unsubscribe',
    'وقف',
    'انهاء',
    'إنهاء',
    'اوقف',
    'قف',
    'خروج',
]);

test('Normal sentences containing ambiguous words do NOT opt the driver out', function (string $sentence) {
    $driver = Driver::factory()->create([
        'phone' => '+201099998888',
        'status' => DriverStatus::INVITED,
    ]);

    $router = app(InboundMessageRouter::class);
    $inbound = new InboundMessage(
        from: '+201099998888',
        type: 'text',
        text: $sentence,
        payloadId: null,
        providerMessageId: 'in_false_opt_'.md5($sentence),
        timestamp: now()->timestamp,
    );

    $router->handle($inbound);

    $driver->refresh();
    expect($driver->status)->not->toBe(DriverStatus::OPTED_OUT)
        ->and($driver->status)->toBe(DriverStatus::INVITED)
        ->and($driver->opted_out_at)->toBeNull();
})->with([
    'عربيتي واقفة في الورشة',
    'الشغل وقف النهارده',
    'مش هقدر انهي الشغل',
    'لو سمحت انا مش عايز الغاء',
    'الساعة واقفة',
    'المحطة عند إشارة قف',
    'خروج السيارة من الجراج بعد قليل',
]);

test('Driver in INVITED status replying YES transitions to INTERESTED and receives text', function () {
    $driver = Driver::factory()->create([
        'phone' => '+201033334444',
        'status' => DriverStatus::INVITED,
    ]);

    $router = app(InboundMessageRouter::class);
    $inbound = new InboundMessage(
        from: '+201033334444',
        type: 'button',
        text: 'نعم، مهتم',
        payloadId: 'invite_yes',
        providerMessageId: 'in_yes_001',
        timestamp: now()->timestamp,
    );

    $router->handle($inbound);

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::INTERESTED);

    // Assert reply text sent
    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT->value,
        'body' => 'شكرًا لك! تم تسجيل اهتمامك وسنتواصل معك قريبًا بالتفاصيل.',
    ]);
});

test('Driver in INVITED status replying NO transitions to DECLINED', function () {
    $driver = Driver::factory()->create([
        'phone' => '+201055556666',
        'status' => DriverStatus::INVITED,
    ]);

    $router = app(InboundMessageRouter::class);
    $inbound = new InboundMessage(
        from: '+201055556666',
        type: 'button',
        text: 'لا، شكرًا',
        payloadId: 'invite_no',
        providerMessageId: 'in_no_001',
        timestamp: now()->timestamp,
    );

    $router->handle($inbound);

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::DECLINED);
});

test('Unknown input sends polite fallback buttons once and subsequent messages within 1 hour are throttled', function () {
    $driver = Driver::factory()->create([
        'phone' => '+201077778888',
        'status' => DriverStatus::ACTIVE,
    ]);

    $router = app(InboundMessageRouter::class);

    // 1st unknown message -> fallback is sent
    $inbound1 = new InboundMessage(
        from: '+201077778888',
        type: 'text',
        text: 'كلام غير مفهوم 1',
        payloadId: null,
        providerMessageId: 'in_unknown_01',
        timestamp: now()->timestamp,
    );
    $router->handle($inbound1);

    $outCountAfterFirst = WhatsAppMessage::where('driver_id', $driver->id)
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->count();

    expect($outCountAfterFirst)->toBe(1);

    // 2nd unknown message immediately after -> throttled (no loop!)
    $inbound2 = new InboundMessage(
        from: '+201077778888',
        type: 'text',
        text: 'كلام غير مفهوم 2',
        payloadId: null,
        providerMessageId: 'in_unknown_02',
        timestamp: now()->timestamp,
    );
    $router->handle($inbound2);

    $outCountAfterSecond = WhatsAppMessage::where('driver_id', $driver->id)
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->count();

    expect($outCountAfterSecond)->toBe(1); // Still 1! Throttled!
});
