<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\WhatsAppMessage;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\WorkOrderService;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
});

test('Phase 5 E2E Scenario: 50 recipients, 10+5 order, simulated inbound acceptances, verify 10 confirmed, 5 backup, 35 too_late and messages', function () {
    $prefix = 'E2E5_'.uniqid();

    // 1. Create 50 active drivers
    /** @var list<Driver> $drivers */
    $drivers = [];
    for ($i = 1; $i <= 50; $i++) {
        $phone = '+2015'.str_pad((string) (abs(crc32($prefix."_{$i}")) % 100000000), 8, '0', STR_PAD_LEFT);
        $drivers[] = Driver::factory()->create([
            'phone' => $phone,
            'name' => "Driver E2E {$i}",
            'status' => DriverStatus::ACTIVE,
        ]);
    }

    expect(count($drivers))->toBe(50);

    // 2. Admin creates a 10 trips + 5 backup work order in draft
    /** @var WorkOrder $order */
    $order = WorkOrder::create([
        'code' => 'WO-SIM-'.substr(md5($prefix), 0, 6),
        'from_location' => 'مستودعات السادس من أكتوبر',
        'to_location' => 'ميناء دمياط',
        'scheduled_at' => now()->addDays(2),
        'cargo_type' => 'حاويات مبردة',
        'pay_amount' => 2500,
        'trips_needed' => 10,
        'backup_needed' => 5,
        'status' => WorkOrderStatus::DRAFT,
        'send_strategy' => 'all',
        'confirmed_count' => 0,
        'backup_count' => 0,
        'sent_count' => 0,
    ]);

    // 3. Send offers to all 50 matching drivers
    $workOrderService = app(WorkOrderService::class);
    $sentOrder = $workOrderService->send($order);

    expect($sentOrder->status)->toBe(WorkOrderStatus::FILLING)
        ->and($sentOrder->sent_count)->toBe(50);

    // Verify 50 offer messages sent via Fake provider
    $offerMessagesCount = WhatsAppMessage::where('order_id', $order->id)
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->where('template_name', 'order_offer')
        ->count();
    expect($offerMessagesCount)->toBe(50);

    // Verify 50 recipients created in status SENT
    $sentRecipientsCount = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::SENT)
        ->count();
    expect($sentRecipientsCount)->toBe(50);

    // 4. Simulate inbound acceptance from each of the 50 drivers sequentially
    // Drivers 1 to 40 click the "order_accept" button payload;
    // Drivers 41 to 50 reply with Arabic text "أوافق" (tested with 1 open offer rule)
    foreach ($drivers as $index => $driver) {
        if ($index < 40) {
            // Button click simulation via command
            Artisan::call('whatsapp:simulate-inbound', [
                'phone' => $driver->phone,
                '--button' => "order_accept:{$order->id}:{$driver->id}",
            ]);
        } else {
            // Free-text reply simulation via command
            Artisan::call('whatsapp:simulate-inbound', [
                'phone' => $driver->phone,
                'text' => 'أوافق',
            ]);
        }
    }

    // 5. Refresh Order and verify final state
    $order->refresh();

    expect($order->status)->toBe(WorkOrderStatus::FILLED)
        ->and($order->confirmed_count)->toBe(10)
        ->and($order->backup_count)->toBe(5)
        ->and($order->filled_at)->not->toBeNull();

    // 6. Verify Recipients counts
    $confirmedRecipients = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::CONFIRMED)
        ->orderBy('sequence_number')
        ->get();
    expect($confirmedRecipients)->toHaveCount(10);

    // Verify gapless sequence numbers 1..10
    $confirmedSeqs = $confirmedRecipients->pluck('sequence_number')->all();
    expect($confirmedSeqs)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

    $backupRecipients = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::BACKUP)
        ->orderBy('sequence_number')
        ->get();
    expect($backupRecipients)->toHaveCount(5);

    // Verify gapless sequence numbers 1..5
    $backupSeqs = $backupRecipients->pluck('sequence_number')->all();
    expect($backupSeqs)->toBe([1, 2, 3, 4, 5]);

    $tooLateRecipients = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::TOO_LATE)
        ->get();
    expect($tooLateRecipients)->toHaveCount(35);

    // 7. Verify Outgoing WhatsApp Messages
    // Confirmation messages: 10
    $confirmationMessages = WhatsAppMessage::where('order_id', $order->id)
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->where('template_name', 'order_confirmation')
        ->count();
    expect($confirmationMessages)->toBe(10);

    // Backup notices: 5
    $backupMessages = WhatsAppMessage::where('order_id', $order->id)
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->where('template_name', 'order_backup_notice')
        ->count();
    expect($backupMessages)->toBe(5);

    // Order filled / too late notices: 35
    $tooLateMessages = WhatsAppMessage::where('order_id', $order->id)
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->where('template_name', 'order_filled')
        ->count();
    expect($tooLateMessages)->toBe(35);

    // 8. Reconcile notifications test
    // Run reconciliation command: should find 0 missing notifications
    $exitCode = Artisan::call('work-orders:reconcile-notifications');
    expect($exitCode)->toBe(0);

    $reconcileOutput = Artisan::output();
    expect($reconcileOutput)->toContain('Reconciled 0 missing notifications.');
});
