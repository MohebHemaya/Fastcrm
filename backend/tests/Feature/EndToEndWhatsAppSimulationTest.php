<?php

use App\Contracts\WhatsAppProvider;
use App\DTOs\WhatsApp\InboundMessage;
use App\DTOs\WhatsApp\StatusUpdate;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Driver;
use App\Models\WhatsAppMessage;
use App\Services\InboundMessageRouter;
use App\Services\InviteDriversService;
use App\Services\MessageSender;
use App\Services\WebhookStatusUpdater;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
});

test('Full WhatsApp Lifecycle E2E: Import/New -> Invite -> Delivered -> Read -> Reply Yes -> Interested -> Opt-out -> Blocked', function () {
    $provider = app(WhatsAppProvider::class);
    $sender = app(MessageSender::class);
    $inviteService = app(InviteDriversService::class);
    $inboundRouter = app(InboundMessageRouter::class);
    $statusUpdater = app(WebhookStatusUpdater::class);

    // 1. Driver created with status NEW
    $driver = Driver::factory()->create([
        'phone' => '+201099887766',
        'name' => 'كابتن حسام',
        'status' => DriverStatus::NEW,
    ]);
    expect($driver->status)->toBe(DriverStatus::NEW);

    // 2. Admin invites driver (queue is faked so message stays QUEUED initially)
    Queue::fake([SendWhatsAppMessageJob::class]);
    $inviteResult = $inviteService->invite([$driver->id], false);
    expect($inviteResult['invited_count'])->toBe(1);

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::INVITED);

    // 3. Outgoing invite message queued in DB
    $inviteMsg = WhatsAppMessage::where('driver_id', $driver->id)
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->latest()
        ->firstOrFail();
    expect($inviteMsg->status)->toBe(WhatsAppMessageStatus::QUEUED);

    // 4. SendWhatsAppMessageJob processes queue
    $sendJob = new SendWhatsAppMessageJob($inviteMsg->id);
    $sendJob->handle($provider);

    $inviteMsg->refresh();
    expect($inviteMsg->status)->toBe(WhatsAppMessageStatus::SENT)
        ->and($inviteMsg->provider_message_id)->not->toBeNull();

    // 5. Provider delivers webhook status: DELIVERED
    $statusUpdater->handle(new StatusUpdate($inviteMsg->provider_message_id, 'delivered'));
    $inviteMsg->refresh();
    expect($inviteMsg->status)->toBe(WhatsAppMessageStatus::DELIVERED);

    // 6. Provider delivers webhook status: READ
    $statusUpdater->handle(new StatusUpdate($inviteMsg->provider_message_id, 'read'));
    $inviteMsg->refresh();
    expect($inviteMsg->status)->toBe(WhatsAppMessageStatus::READ);

    // 7. Driver replies: "نعم، مهتم" (button click or text)
    $replyInbound = new InboundMessage(
        from: $driver->phone,
        type: 'button',
        text: 'نعم، مهتم',
        payloadId: 'invite_yes',
        providerMessageId: 'sim_reply_yes_01',
        timestamp: now()->timestamp,
    );
    $inboundRouter->handle($replyInbound);

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::INTERESTED);

    // 8. Confirmation reply text was sent to driver
    $confirmReply = WhatsAppMessage::where('driver_id', $driver->id)
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->latest('id')
        ->firstOrFail();
    expect($confirmReply->body)->toContain('شكرًا لك! تم تسجيل اهتمامك');

    // 9. Driver later decides to opt out: sends "إيقاف"
    $optOutInbound = new InboundMessage(
        from: $driver->phone,
        type: 'text',
        text: 'إيقاف',
        payloadId: null,
        providerMessageId: 'sim_optout_01',
        timestamp: now()->timestamp,
    );
    $inboundRouter->handle($optOutInbound);

    $driver->refresh();
    expect($driver->status)->toBe(DriverStatus::OPTED_OUT)
        ->and($driver->opted_out_at)->not->toBeNull();

    // 10. Opt-out confirmation message was dispatched
    $finalOptOutMsg = WhatsAppMessage::where('driver_id', $driver->id)
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->latest('id')
        ->firstOrFail();
    expect($finalOptOutMsg->body)->toContain('تم إلغاء اشتراكك بنجاح');

    // 11. Any subsequent attempt to message this driver is blocked
    expect(fn () => $sender->sendText($driver, 'عرض رحلة جديدة'))
        ->toThrow(RuntimeException::class, 'لا يمكن إرسال رسائل للسائق');
});
