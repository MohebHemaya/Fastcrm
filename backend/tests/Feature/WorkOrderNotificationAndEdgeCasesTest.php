<?php

use App\DTOs\WhatsApp\InboundMessage;
use App\Enums\DriverStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\WhatsAppMessage;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\AcceptOrderOffer;
use App\Services\InboundMessageRouter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('free text reply only accepts when driver has exactly ONE open offer', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'phone' => '+201011112222', 'name' => 'أحمد']);

    $order1 = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLING,
        'trips_needed' => 1,
        'backup_needed' => 0,
    ]);

    $recipient1 = WorkOrderRecipient::create([
        'work_order_id' => $order1->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::SENT,
    ]);

    $router = app(InboundMessageRouter::class);

    // Free text reply "أوافق" with 1 open offer -> accepted
    $inbound1 = new InboundMessage(
        from: '+201011112222',
        type: 'text',
        text: 'أوافق',
        payloadId: null,
        providerMessageId: 'text_msg_single_1',
        timestamp: time()
    );

    $router->handle($inbound1);

    $recipient1->refresh();
    expect($recipient1->status)->toBe(WorkOrderRecipientStatus::CONFIRMED)
        ->and($recipient1->sequence_number)->toBe(1);
});

test('free text reply does NOT guess when driver has 2 or more open offers and asks to tap button', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'phone' => '+201033334444', 'name' => 'محمود']);

    $order1 = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLING,
        'trips_needed' => 1,
        'backup_needed' => 0,
    ]);
    $order2 = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLING,
        'trips_needed' => 1,
        'backup_needed' => 0,
    ]);

    $recipient1 = WorkOrderRecipient::create([
        'work_order_id' => $order1->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::SENT,
    ]);
    $recipient2 = WorkOrderRecipient::create([
        'work_order_id' => $order2->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::SENT,
    ]);

    $router = app(InboundMessageRouter::class);

    // Free text reply "أوافق" with 2 open offers
    $inbound = new InboundMessage(
        from: '+201033334444',
        type: 'text',
        text: 'أوافق',
        payloadId: null,
        providerMessageId: 'text_msg_multi_1',
        timestamp: time()
    );

    $router->handle($inbound);

    // Both offers remain untouched in SENT status
    $recipient1->refresh();
    $recipient2->refresh();
    expect($recipient1->status)->toBe(WorkOrderRecipientStatus::SENT)
        ->and($recipient2->status)->toBe(WorkOrderRecipientStatus::SENT);

    // Guidance text message was sent to driver asking them to tap button
    $guidanceMsg = WhatsAppMessage::where('driver_id', $driver->id)
        ->where('body', 'like', '%لديك أكثر من عرض رحلة متاح حاليًا%')
        ->first();
    expect($guidanceMsg)->not->toBeNull();

    // Now, tapping button for Order 2 specifically works!
    $buttonInbound = new InboundMessage(
        from: '+201033334444',
        type: 'button',
        text: 'أوافق',
        payloadId: "order_accept:{$order2->id}:{$driver->id}",
        providerMessageId: 'btn_msg_specific_2',
        timestamp: time()
    );

    $router->handle($buttonInbound);

    $recipient2->refresh();
    expect($recipient2->status)->toBe(WorkOrderRecipientStatus::CONFIRMED)
        ->and($recipient2->sequence_number)->toBe(1);
});

test('AcceptOrderOffer re-checks driver eligibility and cancels offer if driver became opted_out, blocked, or suspended', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLING,
        'trips_needed' => 1,
        'backup_needed' => 0,
    ]);

    $recipient = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::SENT,
    ]);

    // Driver gets blocked or opted out before accepting
    $driver->update(['status' => DriverStatus::BLOCKED]);

    $acceptService = app(AcceptOrderOffer::class);
    $result = $acceptService->handle($order->id, $driver->id, 'accept');

    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('cancelled');

    $recipient->refresh();
    expect($recipient->status)->toBe(WorkOrderRecipientStatus::CANCELLED);

    // Capacity is not consumed
    $order->refresh();
    expect($order->confirmed_count)->toBe(0);

    // No confirmation message is dispatched
    $outgoingMsg = WhatsAppMessage::where('driver_id', $driver->id)
        ->where('template_name', 'order_confirmation')
        ->first();
    expect($outgoingMsg)->toBeNull();
});

test('acceptance transaction survives simulated deadlock and retries successfully', function () {
    // Commit outer test transaction so DB::transaction attempts logic is active
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }

    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLING,
        'trips_needed' => 1,
        'backup_needed' => 0,
    ]);

    $recipient = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::SENT,
    ]);

    while (DB::transactionLevel() > 0) {
        DB::commit();
    }

    $attempts = 0;

    // Simulate deadlock on attempt 1, succeeds on attempt 2
    $result = DB::transaction(function () use (&$attempts, $order, $driver) {
        $attempts++;
        if ($attempts === 1) {
            // Throw a PDO deadlock exception (SQLSTATE 40001 / error code 1213)
            $pdoException = new PDOException('Deadlock found when trying to get lock; try restarting transaction', '40001');
            throw new QueryException('mysql', 'SELECT * FROM work_orders FOR UPDATE', [], $pdoException);
        }

        return app(AcceptOrderOffer::class)->handle($order->id, $driver->id, 'accept');
    }, attempts: 3);

    $recipient->refresh();

    // Clean up
    $recipient->delete();
    $order->delete();
    $driver->delete();

    if (DB::transactionLevel() === 0) {
        DB::beginTransaction();
    }

    expect($attempts)->toBe(2)
        ->and($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('confirmed')
        ->and($recipient->status)->toBe(WorkOrderRecipientStatus::CONFIRMED)
        ->and($recipient->sequence_number)->toBe(1);
});

test('work-orders:reconcile-notifications finds missing notifications and sends them idempotently without duplicates', function () {
    $driverConfirmed = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'خالد']);
    $driverBackup = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'عمر']);
    $driverTooLate = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'سعيد']);

    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLED,
        'trips_needed' => 1,
        'backup_needed' => 1,
        'confirmed_count' => 1,
        'backup_count' => 1,
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driverConfirmed->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driverBackup->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sequence_number' => 1,
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driverTooLate->id,
        'status' => WorkOrderRecipientStatus::TOO_LATE,
    ]);

    // Initial state: no outgoing messages exist for these recipients
    expect(WhatsAppMessage::where('order_id', $order->id)->count())->toBe(0);

    // 1. Run reconciliation command
    $this->artisan('work-orders:reconcile-notifications')
        ->expectsOutput('Reconciled 3 missing notifications.')
        ->assertSuccessful();

    // Verify all 3 messages were sent with correct templates
    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driverConfirmed->id,
        'order_id' => $order->id,
        'template_name' => 'order_confirmation',
    ]);
    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driverBackup->id,
        'order_id' => $order->id,
        'template_name' => 'order_backup_notice',
    ]);
    $this->assertDatabaseHas('whatsapp_messages', [
        'driver_id' => $driverTooLate->id,
        'order_id' => $order->id,
        'template_name' => 'order_filled',
    ]);

    expect(WhatsAppMessage::where('order_id', $order->id)->count())->toBe(3);

    // 2. Run reconciliation command again -> IDEMPOTENT, 0 reconciled, no duplicates!
    $this->artisan('work-orders:reconcile-notifications')
        ->expectsOutput('Reconciled 0 missing notifications.')
        ->assertSuccessful();

    expect(WhatsAppMessage::where('order_id', $order->id)->count())->toBe(3);
});
