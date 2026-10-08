<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Driver;
use App\Models\WhatsAppMessage;
use App\Services\MessageSender;
use App\WhatsApp\FakeWhatsAppProvider;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
});

test('Fake provider round trip: sending template dispatches job and marks sent', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::NEW]);
    $sender = app(MessageSender::class);

    $msg = $sender->sendTemplate(
        driver: $driver,
        templateName: 'driver_invitation',
        lang: 'ar',
        params: [$driver->name],
        buttons: [['id' => 'invite_yes', 'title' => 'نعم، مهتم']]
    );

    expect($msg)->toBeInstanceOf(WhatsAppMessage::class)
        ->and($msg->direction)->toBe(WhatsAppMessageDirection::OUT)
        ->and($msg->type)->toBe(WhatsAppMessageType::TEMPLATE)
        ->and($msg->status)->toBe(WhatsAppMessageStatus::QUEUED);

    // Process job synchronously
    $job = new SendWhatsAppMessageJob($msg->id);
    $job->handle(app(WhatsAppProvider::class));

    $msg->refresh();
    expect($msg->status)->toBe(WhatsAppMessageStatus::SENT)
        ->and($msg->provider_message_id)->not->toBeNull()
        ->and($msg->sent_at)->not->toBeNull();
});

test('MessageSender blocks opted_out drivers with exception', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::OPTED_OUT]);
    $sender = app(MessageSender::class);

    expect(fn () => $sender->sendText($driver, 'رسالة تجريبية'))
        ->toThrow(RuntimeException::class, 'لا يمكن إرسال رسائل للسائق');
});

test('MessageSender blocks blocked drivers with exception', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::BLOCKED]);
    $sender = app(MessageSender::class);

    expect(fn () => $sender->sendTemplate($driver, 'driver_invitation', 'ar'))
        ->toThrow(RuntimeException::class, 'لا يمكن إرسال رسائل للسائق');
});

test('24-hour window logic correctly detects free-form messaging eligibility', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $sender = app(MessageSender::class);

    // No inbound messages yet -> false
    expect($sender->isWithinFreeFormWindow($driver))->toBeFalse();

    // Inbound message 2 hours ago -> true
    WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::IN,
        'provider_message_id' => 'in_123',
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'السلام عليكم',
        'status' => WhatsAppMessageStatus::DELIVERED,
        'created_at' => now()->subHours(2),
    ]);

    expect($sender->isWithinFreeFormWindow($driver))->toBeTrue();

    // Inbound message older than 25 hours -> false
    WhatsAppMessage::where('driver_id', $driver->id)->update(['created_at' => now()->subHours(26)]);
    expect($sender->isWithinFreeFormWindow($driver))->toBeFalse();
});

test('SendWhatsAppMessageJob is idempotent and skips if already sent', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::NEW]);
    $msg = WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'provider_message_id' => 'existing_provider_id_999',
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'رسالة مرسلة بالفعل',
        'status' => WhatsAppMessageStatus::SENT,
        'sent_at' => now()->subMinute(),
    ]);

    $job = new SendWhatsAppMessageJob($msg->id);
    $job->handle(app(WhatsAppProvider::class));

    $msg->refresh();
    expect($msg->provider_message_id)->toBe('existing_provider_id_999');
});

test('sendOptOutConfirmation bypasses opted_out status check for final confirmation', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::OPTED_OUT]);
    $sender = app(MessageSender::class);

    $msg = $sender->sendOptOutConfirmation($driver, 'تم إلغاء الاشتراك بنجاح.');

    expect($msg)->toBeInstanceOf(WhatsAppMessage::class)
        ->and($msg->status)->toBe(WhatsAppMessageStatus::QUEUED)
        ->and($msg->payload['is_opt_out_confirmation'])->toBeTrue();

    // Job processes without being skipped
    $job = new SendWhatsAppMessageJob($msg->id);
    $job->handle(app(WhatsAppProvider::class));

    $msg->refresh();
    expect($msg->status)->toBe(WhatsAppMessageStatus::SENT);
});
