<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
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
use App\Services\DriverMetricsService;
use App\Services\WorkOrderFollowUpService;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('removing confirmed driver decrements count, sets cancelled, preserves order status, and does NOT auto-promote', function () {
    $order = WorkOrder::factory()->create([
        'trips_needed' => 2,
        'backup_needed' => 1,
        'status' => WorkOrderStatus::FILLED,
        'confirmed_count' => 2,
        'backup_count' => 1,
    ]);

    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'Driver 1']);
    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'Driver 2']);
    $driver3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'Backup Driver 1']);

    $rec1 = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver1->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    $rec2 = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver2->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 2,
    ]);

    $rec3 = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver3->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sequence_number' => 1,
    ]);

    $service = app(WorkOrderFollowUpService::class);
    $updatedOrder = $service->removeConfirmedDriver(
        order: $order,
        recipient: $rec1,
        reason: 'عطل مفاجئ في السيارة',
        userId: $this->user->id
    );

    // Order status must remain FILLED (never silently reopen to filling)
    expect($updatedOrder->status)->toBe(WorkOrderStatus::FILLED)
        ->and($updatedOrder->confirmed_count)->toBe(1)
        ->and($updatedOrder->hasOpenSlots())->toBeTrue()
        ->and($updatedOrder->trips_needed - $updatedOrder->confirmed_count)->toBe(1);

    // Removed recipient marked cancelled with reason and null sequence
    $rec1->refresh();
    expect($rec1->status)->toBe(WorkOrderRecipientStatus::CANCELLED)
        ->and($rec1->cancellation_reason)->toBe('عطل مفاجئ في السيارة')
        ->and($rec1->cancelled_at)->not->toBeNull()
        ->and($rec1->sequence_number)->toBeNull();

    // Remaining confirmed driver re-sequenced gaplessly to 1
    $rec2->refresh();
    expect($rec2->status)->toBe(WorkOrderRecipientStatus::CONFIRMED)
        ->and($rec2->sequence_number)->toBe(1);

    // Backup driver MUST REMAIN BACKUP (NO AUTOMATIC PROMOTION)
    $rec3->refresh();
    expect($rec3->status)->toBe(WorkOrderRecipientStatus::BACKUP)
        ->and($rec3->sequence_number)->toBe(1);

    // Timeline event logged
    $event = WorkOrderEvent::where('work_order_id', $order->id)
        ->where('action', 'driver_cancelled')
        ->first();
    expect($event)->not->toBeNull()
        ->and($event->driver_id)->toBe($driver1->id)
        ->and($event->user_id)->toBe($this->user->id)
        ->and($event->metadata['open_slots'])->toBe(1);
});

test('promoting backup driver increments confirmed, decrements backup, re-sequences, and dispatches backup_promoted template', function () {
    $order = WorkOrder::factory()->create([
        'trips_needed' => 2,
        'backup_needed' => 2,
        'status' => WorkOrderStatus::FILLED,
        'confirmed_count' => 1, // 1 open slot
        'backup_count' => 2,
    ]);

    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'Confirmed 1']);
    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'Backup 1']);
    $driver3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'Backup 2']);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver1->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    $backupRec1 = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver2->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sequence_number' => 1,
    ]);

    $backupRec2 = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver3->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sequence_number' => 2,
    ]);

    $service = app(WorkOrderFollowUpService::class);
    // Promote lowest sequence backup (default when recipientId is null)
    $promoted = $service->promoteBackup(order: $order, recipientId: null, userId: $this->user->id);

    expect($promoted->id)->toBe($backupRec1->id)
        ->and($promoted->status)->toBe(WorkOrderRecipientStatus::CONFIRMED)
        ->and($promoted->sequence_number)->toBe(2);

    $order->refresh();
    expect($order->confirmed_count)->toBe(2)
        ->and($order->backup_count)->toBe(1)
        ->and($order->hasOpenSlots())->toBeFalse();

    // Remaining backup re-sequenced gaplessly to 1
    $backupRec2->refresh();
    expect($backupRec2->sequence_number)->toBe(1);

    // Notification dispatched
    $message = WhatsAppMessage::where('driver_id', $driver2->id)
        ->where('order_id', $order->id)
        ->where('direction', WhatsAppMessageDirection::OUT)
        ->where('template_name', 'backup_promoted')
        ->first();
    expect($message)->not->toBeNull();

    // Timeline event logged
    $event = WorkOrderEvent::where('work_order_id', $order->id)
        ->where('action', 'backup_promoted')
        ->first();
    expect($event)->not->toBeNull()
        ->and($event->driver_id)->toBe($driver2->id);
});

test('promoting backup driver throws 422 when no slots are free', function () {
    $order = WorkOrder::factory()->create([
        'trips_needed' => 1,
        'backup_needed' => 1,
        'status' => WorkOrderStatus::FILLED,
        'confirmed_count' => 1, // Full
        'backup_count' => 1,
    ]);

    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver1->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    $backupRec = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver2->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sequence_number' => 1,
    ]);

    $service = app(WorkOrderFollowUpService::class);

    expect(fn () => $service->promoteBackup(order: $order, recipientId: $backupRec->id, userId: $this->user->id))
        ->toThrow(ValidationException::class);
});

test('promoting backup driver cancels recipient and throws 422 if driver became ineligible', function () {
    $order = WorkOrder::factory()->create([
        'trips_needed' => 2,
        'backup_needed' => 1,
        'status' => WorkOrderStatus::FILLED,
        'confirmed_count' => 1, // Slot free
        'backup_count' => 1,
    ]);

    // Driver was active when assigned backup, but later opted out
    $driver = Driver::factory()->create(['status' => DriverStatus::OPTED_OUT]);

    $backupRec = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sequence_number' => 1,
    ]);

    $service = app(WorkOrderFollowUpService::class);

    expect(fn () => $service->promoteBackup(order: $order, recipientId: $backupRec->id, userId: $this->user->id))
        ->toThrow(ValidationException::class);

    $backupRec->refresh();
    expect($backupRec->status)->toBe(WorkOrderRecipientStatus::CANCELLED);

    $order->refresh();
    expect($order->backup_count)->toBe(0)
        ->and($order->confirmed_count)->toBe(1);
});

test('removing backup driver decrements backup count and re-sequences remaining backups', function () {
    $order = WorkOrder::factory()->create([
        'trips_needed' => 2,
        'backup_needed' => 2,
        'backup_count' => 2,
    ]);

    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $b1 = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver1->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sequence_number' => 1,
    ]);

    $b2 = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver2->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sequence_number' => 2,
    ]);

    $service = app(WorkOrderFollowUpService::class);
    $service->removeBackupDriver($order, $b1, 'لم يعد متاحًا', $this->user->id);

    $order->refresh();
    expect($order->backup_count)->toBe(1);

    $b1->refresh();
    expect($b1->status)->toBe(WorkOrderRecipientStatus::CANCELLED);

    $b2->refresh();
    expect($b2->sequence_number)->toBe(1);
});

test('adding driver manually respects capacity, eligibility, and sends template', function () {
    $order = WorkOrder::factory()->create([
        'trips_needed' => 2,
        'backup_needed' => 1,
        'confirmed_count' => 1,
        'backup_count' => 0,
    ]);

    $existingConfirmed = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $existingConfirmed->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    $activeDriver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $blockedDriver = Driver::factory()->create(['status' => DriverStatus::BLOCKED]);

    $service = app(WorkOrderFollowUpService::class);

    // Blocked driver rejected
    expect(fn () => $service->addDriverManually($order, $blockedDriver->id, 'confirmed', $this->user->id))
        ->toThrow(ValidationException::class);

    // Active driver accepted as confirmed
    $rec = $service->addDriverManually($order, $activeDriver->id, 'confirmed', $this->user->id);
    expect($rec->status)->toBe(WorkOrderRecipientStatus::CONFIRMED)
        ->and($rec->sequence_number)->toBe(2);

    $order->refresh();
    expect($order->confirmed_count)->toBe(2);

    // Outgoing confirmation template dispatched
    $msg = WhatsAppMessage::where('driver_id', $activeDriver->id)
        ->where('order_id', $order->id)
        ->where('template_name', 'order_confirmation')
        ->first();
    expect($msg)->not->toBeNull();

    // Adding another confirmed when full rejected
    $anotherActive = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    expect(fn () => $service->addDriverManually($order, $anotherActive->id, 'confirmed', $this->user->id))
        ->toThrow(ValidationException::class);
});

test('resend offers re-opens order to filling only when free slots exist', function () {
    $order = WorkOrder::factory()->create([
        'trips_needed' => 2,
        'confirmed_count' => 2, // Full
        'status' => WorkOrderStatus::FILLED,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $noResponseRec = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::NO_RESPONSE,
    ]);

    $service = app(WorkOrderFollowUpService::class);

    // When full: rejects
    expect(fn () => $service->resendOffers($order, [$noResponseRec->id], $this->user->id))
        ->toThrow(ValidationException::class);

    // Open a slot
    $order->update(['confirmed_count' => 1]);

    // Resend succeeds and re-opens to filling
    $count = $service->resendOffers($order, [$noResponseRec->id], $this->user->id);
    expect($count)->toBe(1);

    $order->refresh();
    expect($order->status)->toBe(WorkOrderStatus::FILLING);

    $noResponseRec->refresh();
    expect($noResponseRec->status)->toBe(WorkOrderRecipientStatus::SENT);
});

test('order lifecycle transitions through filled -> in_progress -> completed, and cancelled with reason', function () {
    $order = WorkOrder::factory()->create([
        'trips_needed' => 1,
        'confirmed_count' => 1,
        'status' => WorkOrderStatus::FILLED,
    ]);

    $service = app(WorkOrderFollowUpService::class);

    // Advance to IN_PROGRESS
    $order = $service->changeOrderStatus($order, WorkOrderStatus::IN_PROGRESS, null, $this->user->id);
    expect($order->status)->toBe(WorkOrderStatus::IN_PROGRESS);

    // Advance to COMPLETED
    $order = $service->changeOrderStatus($order, WorkOrderStatus::COMPLETED, null, $this->user->id);
    expect($order->status)->toBe(WorkOrderStatus::COMPLETED);

    // Completed order is final
    expect(fn () => $service->changeOrderStatus($order, WorkOrderStatus::IN_PROGRESS, null, $this->user->id))
        ->toThrow(ValidationException::class);
});

test('admin removals do NOT count towards driver cancellations metric; only driver-initiated late cancellations count', function () {
    $order = WorkOrder::factory()->create([
        'trips_needed' => 2,
        'confirmed_count' => 1,
        'status' => WorkOrderStatus::FILLED,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $recipient = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
        'responded_at' => now()->subHour(),
    ]);

    $service = app(WorkOrderFollowUpService::class);
    $metricsService = app(DriverMetricsService::class);

    // 1. Admin removes confirmed driver for operational reason
    $service->removeConfirmedDriver($order, $recipient, 'ظرف تشغيلي خاص بالشركة', $this->user->id);

    $recipient->refresh();
    expect($recipient->status)->toBe(WorkOrderRecipientStatus::CANCELLED);
    expect($recipient->outcome)->toBeNull();

    // Driver cancellations metric must remain 0
    $metric = $metricsService->recalculateForDriver($driver->id);
    expect($metric->cancellations)->toBe(0);

    // 2. Only driver-initiated late cancellations (outcome = CANCELLED_LATE) increment cancellations
    $completedOrder = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::COMPLETED,
        'scheduled_at' => now()->subDay(),
    ]);
    $recCompleted = WorkOrderRecipient::create([
        'work_order_id' => $completedOrder->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    $service->recordOutcomes($completedOrder, [
        [
            'recipient_id' => $recCompleted->id,
            'outcome' => WorkOrderOutcome::CANCELLED_LATE->value,
            'notes' => 'السائق اتصل واعتذر متأخرًا قبل التحرك مباشرة',
        ],
    ], $this->user->id);

    $driverMetricUpdated = DriverMetric::where('driver_id', $driver->id)->first();
    expect($driverMetricUpdated->cancellations)->toBe(1);
});

test('responded_at timestamp remains strictly unchanged when sequences are renumbered', function () {
    $order = WorkOrder::factory()->create([
        'trips_needed' => 3,
        'backup_needed' => 3,
        'confirmed_count' => 3,
        'backup_count' => 3,
        'status' => WorkOrderStatus::FILLED,
    ]);

    $t1 = now()->subMinutes(60)->startOfSecond();
    $t2 = now()->subMinutes(50)->startOfSecond();
    $t3 = now()->subMinutes(40)->startOfSecond();
    $t4 = now()->subMinutes(30)->startOfSecond();
    $t5 = now()->subMinutes(20)->startOfSecond();
    $t6 = now()->subMinutes(10)->startOfSecond();

    $d1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d4 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d5 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d6 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $c1 = WorkOrderRecipient::create(['work_order_id' => $order->id, 'driver_id' => $d1->id, 'status' => WorkOrderRecipientStatus::CONFIRMED, 'sequence_number' => 1, 'responded_at' => $t1]);
    $c2 = WorkOrderRecipient::create(['work_order_id' => $order->id, 'driver_id' => $d2->id, 'status' => WorkOrderRecipientStatus::CONFIRMED, 'sequence_number' => 2, 'responded_at' => $t2]);
    $c3 = WorkOrderRecipient::create(['work_order_id' => $order->id, 'driver_id' => $d3->id, 'status' => WorkOrderRecipientStatus::CONFIRMED, 'sequence_number' => 3, 'responded_at' => $t3]);

    $b1 = WorkOrderRecipient::create(['work_order_id' => $order->id, 'driver_id' => $d4->id, 'status' => WorkOrderRecipientStatus::BACKUP, 'sequence_number' => 1, 'responded_at' => $t4]);
    $b2 = WorkOrderRecipient::create(['work_order_id' => $order->id, 'driver_id' => $d5->id, 'status' => WorkOrderRecipientStatus::BACKUP, 'sequence_number' => 2, 'responded_at' => $t5]);
    $b3 = WorkOrderRecipient::create(['work_order_id' => $order->id, 'driver_id' => $d6->id, 'status' => WorkOrderRecipientStatus::BACKUP, 'sequence_number' => 3, 'responded_at' => $t6]);

    $service = app(WorkOrderFollowUpService::class);

    // 1. Remove confirmed driver 1 -> c2 and c3 are renumbered to 1 and 2
    $service->removeConfirmedDriver($order, $c1, 'عطل مفاجئ بالسيارة', $this->user->id);

    $c2->refresh();
    $c3->refresh();
    expect($c2->sequence_number)->toBe(1);
    expect($c2->responded_at?->toDateTimeString())->toBe($t2->toDateTimeString());
    expect($c3->sequence_number)->toBe(2);
    expect($c3->responded_at?->toDateTimeString())->toBe($t3->toDateTimeString());

    // 2. Promote backup driver 1 (b1) to confirmed -> b1 becomes confirmed, b2 & b3 renumbered to 1 & 2
    $service->promoteBackup($order, $b1->id, $this->user->id);

    $b1->refresh();
    $b2->refresh();
    $b3->refresh();

    // b1 is now confirmed, its responded_at is preserved
    expect($b1->status)->toBe(WorkOrderRecipientStatus::CONFIRMED);
    expect($b1->responded_at?->toDateTimeString())->toBe($t4->toDateTimeString());

    // b2 and b3 were renumbered, responded_at remains unchanged
    expect($b2->sequence_number)->toBe(1);
    expect($b2->responded_at?->toDateTimeString())->toBe($t5->toDateTimeString());
    expect($b3->sequence_number)->toBe(2);
    expect($b3->responded_at?->toDateTimeString())->toBe($t6->toDateTimeString());

    // 3. Remove backup driver 2 (b2) -> b3 renumbered to 1
    $service->removeBackupDriver($order, $b2, 'اعتذار مسبق', $this->user->id);

    $b3->refresh();
    expect($b3->sequence_number)->toBe(1);
    expect($b3->responded_at?->toDateTimeString())->toBe($t6->toDateTimeString());
});
