<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WorkOrderOutcome;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\DriverMetric;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderRecipient;
use App\Services\WorkOrderFollowUpService;
use App\Services\WorkOrderService;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
});

test('Phase 6 E2E Scenario: fill 10+5 -> cancel confirmed -> promote backup -> complete order -> record outcomes -> verify driver_metrics', function () {
    $prefix = 'E2E6_'.uniqid();

    // 1. Create an admin user for actions
    $admin = User::factory()->create([
        'name' => 'مشرف العمليات',
        'email' => "admin_{$prefix}@test.com",
    ]);

    // 2. Create 25 active drivers
    /** @var list<Driver> $drivers */
    $drivers = [];
    for ($i = 1; $i <= 25; $i++) {
        $phone = '+2011'.str_pad((string) (abs(crc32($prefix."_{$i}")) % 100000000), 8, '0', STR_PAD_LEFT);
        $drivers[] = Driver::factory()->create([
            'phone' => $phone,
            'name' => "سائق كابتن {$i}",
            'status' => DriverStatus::ACTIVE,
        ]);
    }

    expect(count($drivers))->toBe(25);

    // 3. Create a work order with 10 trips needed + 5 backup needed
    /** @var WorkOrder $order */
    $order = WorkOrder::create([
        'code' => 'WO-P6-'.substr(md5($prefix), 0, 6),
        'from_location' => 'مستودعات السادس من أكتوبر',
        'to_location' => 'ميناء الإسكندرية',
        'scheduled_at' => now()->addDays(2),
        'cargo_type' => 'بضائع مجمدة',
        'pay_amount' => 1800,
        'trips_needed' => 10,
        'backup_needed' => 5,
        'status' => WorkOrderStatus::DRAFT,
        'send_strategy' => 'all',
        'confirmed_count' => 0,
        'backup_count' => 0,
        'sent_count' => 0,
    ]);

    // 4. Send offers to all 25 drivers
    $workOrderService = app(WorkOrderService::class);
    $sentOrder = $workOrderService->send($order);

    expect($sentOrder->status)->toBe(WorkOrderStatus::FILLING)
        ->and($sentOrder->sent_count)->toBe(25);

    // 5. Simulate inbound acceptances from all 25 drivers sequentially
    // First 10 get confirmed, next 5 get backup, next 10 get too_late
    foreach ($drivers as $driver) {
        Artisan::call('whatsapp:simulate-inbound', [
            'phone' => $driver->phone,
            '--button' => "order_accept:{$order->id}:{$driver->id}",
        ]);
    }

    $order->refresh();
    expect($order->status)->toBe(WorkOrderStatus::FILLED)
        ->and($order->confirmed_count)->toBe(10)
        ->and($order->backup_count)->toBe(5)
        ->and($order->has_open_slots)->toBeFalse();

    // 6. Admin cancels one confirmed driver (e.g. driver #3)
    $confirmedToCancel = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::CONFIRMED)
        ->where('sequence_number', 3)
        ->firstOrFail();

    $followUpService = app(WorkOrderFollowUpService::class);
    $followUpService->removeConfirmedDriver(
        order: $order,
        recipient: $confirmedToCancel,
        reason: 'عطل مفاجئ في مبرد السيارة',
        userId: $admin->id
    );

    $order->refresh();
    $confirmedToCancel->refresh();

    // Rule: NO automatic promotion, ever!
    // Status stays filled, confirmed_count is decremented, has_open_slots is true
    expect($order->status)->toBe(WorkOrderStatus::FILLED)
        ->and($order->confirmed_count)->toBe(9)
        ->and($order->backup_count)->toBe(5)
        ->and($order->has_open_slots)->toBeTrue()
        ->and($order->open_slots_count)->toBe(1)
        ->and($confirmedToCancel->status)->toBe(WorkOrderRecipientStatus::CANCELLED)
        ->and($confirmedToCancel->cancellation_reason)->toBe('عطل مفاجئ في مبرد السيارة');

    // Verify cancellation event recorded in work_order_events
    $cancelEvent = WorkOrderEvent::where('work_order_id', $order->id)
        ->where('action', 'driver_cancelled')
        ->first();
    expect($cancelEvent)->not->toBeNull()
        ->and($cancelEvent->driver_id)->toBe($confirmedToCancel->driver_id)
        ->and($cancelEvent->user_id)->toBe($admin->id);

    // 7. Admin manually chooses backup #1 and promotes to confirmed
    $backupToPromote = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::BACKUP)
        ->orderBy('sequence_number')
        ->firstOrFail();

    $promoted = $followUpService->promoteBackup(
        order: $order,
        recipientId: $backupToPromote->id,
        userId: $admin->id
    );

    $order->refresh();
    $backupToPromote->refresh();

    expect($order->confirmed_count)->toBe(10)
        ->and($order->backup_count)->toBe(4)
        ->and($order->has_open_slots)->toBeFalse()
        ->and($backupToPromote->status)->toBe(WorkOrderRecipientStatus::CONFIRMED)
        ->and($backupToPromote->sequence_number)->toBe(10); // Assigned next sequence

    // Verify remaining backups are gapless 1..4
    $remainingBackups = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::BACKUP)
        ->orderBy('sequence_number')
        ->get();
    expect($remainingBackups)->toHaveCount(4)
        ->and($remainingBackups->pluck('sequence_number')->all())->toBe([1, 2, 3, 4]);

    // Verify backup_promoted message was sent
    $promotedMsg = WhatsAppMessage::where('order_id', $order->id)
        ->where('driver_id', $backupToPromote->driver_id)
        ->where('template_name', 'backup_promoted')
        ->first();
    expect($promotedMsg)->not->toBeNull();

    // Verify event logged
    $promoteEvent = WorkOrderEvent::where('work_order_id', $order->id)
        ->where('action', 'backup_promoted')
        ->first();
    expect($promoteEvent)->not->toBeNull()
        ->and($promoteEvent->driver_id)->toBe($backupToPromote->driver_id);

    // 8. Order Lifecycle: advance from filled -> in_progress -> completed
    $order = $followUpService->changeStatus($order, WorkOrderStatus::IN_PROGRESS, null, $admin->id);
    expect($order->status)->toBe(WorkOrderStatus::IN_PROGRESS);

    $order = $followUpService->changeStatus($order, WorkOrderStatus::COMPLETED, null, $admin->id);
    expect($order->status)->toBe(WorkOrderStatus::COMPLETED);

    // 9. Admin records outcomes for all 10 confirmed drivers
    $confirmedDrivers = WorkOrderRecipient::where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::CONFIRMED)
        ->orderBy('sequence_number')
        ->get();

    expect($confirmedDrivers)->toHaveCount(10);

    // Outcome distribution:
    // Drivers 0..6 (7 drivers): attended with rating 5
    // Driver 7: late with rating 4 and note "تأخر 20 دقيقة"
    // Driver 8: no_show (no rating)
    // Driver 9: cancelled_late (no rating)
    $outcomeData = [];
    foreach ($confirmedDrivers as $idx => $recipient) {
        if ($idx < 7) {
            $outcomeData[] = [
                'recipient_id' => $recipient->id,
                'outcome' => WorkOrderOutcome::ATTENDED->value,
                'rating' => 5,
                'notes' => 'حضور متميز والتزام بالميعاد',
            ];
        } elseif ($idx === 7) {
            $outcomeData[] = [
                'recipient_id' => $recipient->id,
                'outcome' => WorkOrderOutcome::LATE->value,
                'rating' => 4,
                'notes' => 'تأخر 20 دقيقة عن موعد التحميل',
            ];
        } elseif ($idx === 8) {
            $outcomeData[] = [
                'recipient_id' => $recipient->id,
                'outcome' => WorkOrderOutcome::NO_SHOW->value,
                'rating' => null,
                'notes' => 'لم يحضر ولم يرد على الاتصال',
            ];
        } else {
            $outcomeData[] = [
                'recipient_id' => $recipient->id,
                'outcome' => WorkOrderOutcome::CANCELLED_LATE->value,
                'rating' => null,
                'notes' => 'اعتذر قبل موعد الرحلة بساعة',
            ];
        }
    }

    $followUpService->recordOutcomes($order, $outcomeData, $admin->id);

    // 10. Verify Driver Metrics were updated accurately for each driver
    // Driver 0 (Attended with 5 stars)
    $metric0 = DriverMetric::where('driver_id', $confirmedDrivers[0]->driver_id)->first();
    expect($metric0)->not->toBeNull()
        ->and($metric0->trips_completed)->toBe(1)
        ->and((float) $metric0->avg_rating)->toBe(5.0)
        ->and($metric0->ratings_count)->toBe(1)
        ->and($metric0->no_shows)->toBe(0)
        ->and($metric0->late_count)->toBe(0)
        ->and($metric0->cancellations)->toBe(0)
        ->and($metric0->last_trip_at)->not->toBeNull();

    // Driver 7 (Late with 4 stars)
    $metric7 = DriverMetric::where('driver_id', $confirmedDrivers[7]->driver_id)->first();
    expect($metric7)->not->toBeNull()
        ->and($metric7->trips_completed)->toBe(1)
        ->and((float) $metric7->avg_rating)->toBe(4.0)
        ->and($metric7->ratings_count)->toBe(1)
        ->and($metric7->late_count)->toBe(1)
        ->and($metric7->no_shows)->toBe(0)
        ->and($metric7->cancellations)->toBe(0);

    // Driver 8 (No show)
    $metric8 = DriverMetric::where('driver_id', $confirmedDrivers[8]->driver_id)->first();
    expect($metric8)->not->toBeNull()
        ->and($metric8->trips_completed)->toBe(0)
        ->and($metric8->no_shows)->toBe(1)
        ->and($metric8->avg_rating)->toBeNull()
        ->and($metric8->ratings_count)->toBe(0);

    // Driver 9 (Cancelled late)
    $metric9 = DriverMetric::where('driver_id', $confirmedDrivers[9]->driver_id)->first();
    expect($metric9)->not->toBeNull()
        ->and($metric9->trips_completed)->toBe(0)
        ->and($metric9->cancellations)->toBe(1)
        ->and($metric9->no_shows)->toBe(0);

    // 11. Verify Timeline Events for all operations
    $timelineActions = WorkOrderEvent::where('work_order_id', $order->id)
        ->pluck('action')
        ->all();

    expect($timelineActions)->toContain('driver_cancelled')
        ->and($timelineActions)->toContain('backup_promoted')
        ->and($timelineActions)->toContain('status_in_progress')
        ->and($timelineActions)->toContain('status_completed')
        ->and($timelineActions)->toContain('outcome_recorded');
});
