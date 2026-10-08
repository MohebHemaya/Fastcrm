<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\WhatsAppMessage;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
});

test('work-orders:send-reminders dispatches trip_reminder idempotently and never duplicates', function () {
    // 1. Configure setting: 24 hours before
    Setting::updateOrCreate(
        ['key' => 'order_reminder_hours_before'],
        ['value' => '24', 'type' => 'number', 'group' => 'work_orders']
    );

    // 2. Order 1: scheduled in 10 hours (within 24h window), FILLED
    $order1 = WorkOrder::factory()->create([
        'scheduled_at' => now()->addHours(10),
        'status' => WorkOrderStatus::FILLED,
        'from_location' => 'ميدان الرماية',
    ]);

    // 3. Order 2: scheduled in 48 hours (outside 24h window), FILLED
    $order2 = WorkOrder::factory()->create([
        'scheduled_at' => now()->addHours(48),
        'status' => WorkOrderStatus::FILLED,
        'from_location' => 'مدينة نصر',
    ]);

    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'Driver 1']);
    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'Driver 2']);

    // Recipient 1 on Order 1: confirmed, no reminder sent yet
    $rec1 = WorkOrderRecipient::create([
        'work_order_id' => $order1->id,
        'driver_id' => $driver1->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    // Recipient 2 on Order 2: confirmed, but outside window
    $rec2 = WorkOrderRecipient::create([
        'work_order_id' => $order2->id,
        'driver_id' => $driver2->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    // First run of command: should send reminder to Recipient 1 only
    $exitCode = Artisan::call('work-orders:send-reminders');
    expect($exitCode)->toBe(0);

    $rec1->refresh();
    expect($rec1->reminder_sent_at)->not->toBeNull();

    $rec2->refresh();
    expect($rec2->reminder_sent_at)->toBeNull();

    $messagesCount = WhatsAppMessage::where('template_name', 'trip_reminder')
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->count();
    expect($messagesCount)->toBe(1);

    // Second run: IDEMPOTENCY TEST - must find 0 and send 0
    Artisan::call('work-orders:send-reminders');
    $output = Artisan::output();
    expect($output)->toContain('Dispatched 0 trip reminder notifications.');

    $messagesCountSecondRun = WhatsAppMessage::where('template_name', 'trip_reminder')
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->count();
    expect($messagesCountSecondRun)->toBe(1); // Still exactly 1, no duplicates
});
