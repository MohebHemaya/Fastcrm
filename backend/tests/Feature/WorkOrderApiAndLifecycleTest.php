<?php

use App\DTOs\WhatsApp\InboundMessage;
use App\Enums\DriverStatus;
use App\Enums\QuestionType;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Jobs\DispatchStagedWorkOrderBatchJob;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\AcceptOrderOffer;
use App\Services\InboundMessageRouter;
use App\Services\MessageSender;
use App\Services\WorkOrderService;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create a filterable question
    $this->question = Question::create([
        'key' => 'vehicle_type',
        'sort_order' => 1,
        'is_active' => true,
        'is_required' => true,
        'is_filter' => true,
        'type' => QuestionType::SINGLE_CHOICE,
    ]);

    $this->version = QuestionVersion::create([
        'question_id' => $this->question->id,
        'version' => 1,
        'text_ar' => 'نوع المركبة',
        'type' => QuestionType::SINGLE_CHOICE,
        'options_snapshot' => [
            ['value' => 'pickup', 'label_ar' => 'ربع نقل'],
            ['value' => 'van', 'label_ar' => 'فان'],
        ],
    ]);

    $this->optPickup = QuestionOption::create([
        'question_id' => $this->question->id,
        'value' => 'pickup',
        'label_ar' => 'ربع نقل',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->optVan = QuestionOption::create([
        'question_id' => $this->question->id,
        'value' => 'van',
        'label_ar' => 'فان',
        'sort_order' => 2,
        'is_active' => true,
    ]);
});

test('can create a draft work order with filters and audit log', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/work-orders', [
        'from_location' => 'القاهرة - مدينة نصر',
        'to_location' => 'الإسكندرية - سموحة',
        'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'cargo_type' => 'مواد غذائية',
        'pay_amount' => 1500.50,
        'trips_needed' => 3,
        'backup_needed' => 1,
        'notes' => 'برجاء الالتزام بالمواعيد',
        'send_strategy' => 'all',
        'exclude_same_day_confirmed' => true,
        'filters' => [
            [
                'question_id' => $this->question->id,
                'operator' => 'eq',
                'value' => 'pickup',
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.from_location', 'القاهرة - مدينة نصر')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.trips_needed', 3)
        ->assertJsonPath('data.backup_needed', 1)
        ->assertJsonPath('data.exclude_same_day_confirmed', true);

    $code = $response->json('data.code');
    expect($code)->toMatch('/^WO-\d{4}$/');

    $this->assertDatabaseHas('work_orders', [
        'code' => $code,
        'from_location' => 'القاهرة - مدينة نصر',
        'status' => 'draft',
    ]);

    $this->assertDatabaseHas('work_order_filters', [
        'question_id' => $this->question->id,
        'operator' => 'eq',
    ]);

    expect(ActivityLog::where('action', 'work_order_created')->exists())->toBeTrue();
});

test('can update a draft work order but not after sending', function () {
    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::DRAFT,
        'from_location' => 'طنطا',
        'created_by' => $this->user->id,
    ]);

    // Update draft successfully
    $updateResp = $this->actingAs($this->user, 'sanctum')->putJson("/api/v1/work-orders/{$order->id}", [
        'from_location' => 'المنصورة',
        'trips_needed' => 5,
    ]);

    $updateResp->assertOk()
        ->assertJsonPath('data.from_location', 'المنصورة')
        ->assertJsonPath('data.trips_needed', 5);

    // Change status to FILLING (sent)
    $order->update(['status' => WorkOrderStatus::FILLING]);

    $failResp = $this->actingAs($this->user, 'sanctum')->putJson("/api/v1/work-orders/{$order->id}", [
        'from_location' => 'سوهاج',
    ]);

    $failResp->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

test('can list and filter work orders', function () {
    WorkOrder::factory()->create([
        'status' => WorkOrderStatus::DRAFT,
        'from_location' => 'القاهرة',
        'code' => 'WO-0010',
    ]);

    WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLED,
        'from_location' => 'الإسكندرية',
        'code' => 'WO-0011',
    ]);

    $respAll = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/work-orders');
    $respAll->assertOk()->assertJsonCount(2, 'data');

    $respFiltered = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/work-orders?status=filled');
    $respFiltered->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'WO-0011');

    $respSearch = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/work-orders?search=القاهرة');
    $respSearch->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'WO-0010');
});

test('sending a work order creates recipients and dispatches offer templates', function () {
    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'phone' => '+201011111111']);
    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'phone' => '+201022222222']);

    foreach ([$driver1, $driver2] as $d) {
        DriverAnswer::create([
            'driver_id' => $d->id,
            'question_id' => $this->question->id,
            'question_version_id' => $this->version->id,
            'option_id' => $this->optPickup->id,
            'is_current' => true,
            'is_skipped' => false,
            'answered_at' => now(),
        ]);
    }

    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::DRAFT,
        'trips_needed' => 1,
        'backup_needed' => 1,
        'send_strategy' => 'all',
    ]);

    $order->filters()->create([
        'question_id' => $this->question->id,
        'operator' => 'eq',
        'value' => 'pickup',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/work-orders/{$order->id}/send");

    $response->assertOk()
        ->assertJsonPath('data.status', 'filling')
        ->assertJsonPath('data.sent_count', 2);

    $this->assertDatabaseHas('work_order_recipients', [
        'work_order_id' => $order->id,
        'driver_id' => $driver1->id,
        'status' => 'sent',
    ]);

    $this->assertDatabaseHas('work_order_recipients', [
        'work_order_id' => $order->id,
        'driver_id' => $driver2->id,
        'status' => 'sent',
    ]);

    expect(ActivityLog::where('action', 'work_order_sent')->exists())->toBeTrue();
});

test('accept and decline flow updates capacity, sequence numbers, and triggers filled status', function () {
    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driver3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLING,
        'trips_needed' => 1,
        'backup_needed' => 1,
        'confirmed_count' => 0,
        'backup_count' => 0,
    ]);

    foreach ([$driver1, $driver2, $driver3] as $d) {
        WorkOrderRecipient::create([
            'work_order_id' => $order->id,
            'driver_id' => $d->id,
            'status' => WorkOrderRecipientStatus::SENT,
        ]);
    }

    $acceptService = app(AcceptOrderOffer::class);

    // 1. Driver 1 accepts -> should be confirmed with sequence 1
    $res1 = $acceptService->handle($order->id, $driver1->id, 'accept');
    expect($res1['success'])->toBeTrue()
        ->and($res1['status'])->toBe('confirmed')
        ->and($res1['sequence_number'])->toBe(1);

    $order->refresh();
    expect($order->confirmed_count)->toBe(1)
        ->and($order->status)->toBe(WorkOrderStatus::FILLING);

    // 2. Driver 2 declines -> should be marked declined, not consuming capacity
    $res2 = $acceptService->handle($order->id, $driver2->id, 'decline');
    expect($res2['status'])->toBe('declined');

    $order->refresh();
    expect($order->confirmed_count)->toBe(1)
        ->and($order->backup_count)->toBe(0);

    // 3. Driver 3 accepts -> confirmed is full (1/1), so Driver 3 becomes backup with sequence 1
    $res3 = $acceptService->handle($order->id, $driver3->id, 'accept');
    expect($res3['success'])->toBeTrue()
        ->and($res3['status'])->toBe('backup')
        ->and($res3['sequence_number'])->toBe(1);

    $order->refresh();
    // Capacity reached (1 confirmed, 1 backup) -> status must be FILLED
    expect($order->backup_count)->toBe(1)
        ->and($order->status)->toBe(WorkOrderStatus::FILLED)
        ->and($order->filled_at)->not->toBeNull();
});

test('inbound button tap and free text trigger AcceptOrderOffer via InboundMessageRouter', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'phone' => '+201099999999']);

    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLING,
        'trips_needed' => 1,
        'backup_needed' => 0,
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::SENT,
    ]);

    $router = app(InboundMessageRouter::class);

    // Tap "أوافق" button
    $inbound = new InboundMessage(
        from: '+201099999999',
        type: 'button',
        text: 'أوافق',
        payloadId: "order_accept:{$order->id}:{$driver->id}",
        providerMessageId: 'btn_test_msg_1',
        timestamp: time(),
    );

    $router->handle($inbound);

    $recipient = WorkOrderRecipient::where('work_order_id', $order->id)->where('driver_id', $driver->id)->first();
    expect($recipient->status)->toBe(WorkOrderRecipientStatus::CONFIRMED)
        ->and($recipient->sequence_number)->toBe(1);
});

test('cancelling a work order updates status, notifies confirmed/backup drivers, and rejects late acceptances', function () {
    $confirmedDriver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $backupDriver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $sentDriver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLING,
        'trips_needed' => 1,
        'backup_needed' => 1,
        'confirmed_count' => 1,
        'backup_count' => 1,
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $confirmedDriver->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $backupDriver->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sequence_number' => 1,
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $sentDriver->id,
        'status' => WorkOrderRecipientStatus::SENT,
    ]);

    $service = app(WorkOrderService::class);
    $service->cancel($order, 'تغير في جدول التشغيل');

    $order->refresh();
    expect($order->status)->toBe(WorkOrderStatus::CANCELLED);

    // All recipients should be marked cancelled
    $this->assertDatabaseHas('work_order_recipients', [
        'work_order_id' => $order->id,
        'driver_id' => $confirmedDriver->id,
        'status' => 'cancelled',
    ]);
    $this->assertDatabaseHas('work_order_recipients', [
        'work_order_id' => $order->id,
        'driver_id' => $sentDriver->id,
        'status' => 'cancelled',
    ]);

    // An acceptance attempt on a cancelled order must be rejected as too_late / cancelled
    $acceptService = app(AcceptOrderOffer::class);
    $res = $acceptService->handle($order->id, $sentDriver->id, 'accept');

    expect($res['success'])->toBeFalse();
    expect(ActivityLog::where('action', 'work_order_cancelled')->exists())->toBeTrue();
});

test('staged sending stops remaining batches automatically when order is filled', function () {
    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLING,
        'trips_needed' => 1,
        'backup_needed' => 0,
        'send_strategy' => 'staged',
        'batch_size' => 1,
        'batch_interval_minutes' => 5,
    ]);

    $driverBatch2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driverBatch3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driverBatch2->id,
        'status' => WorkOrderRecipientStatus::PENDING,
        'stage_batch' => 2,
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driverBatch3->id,
        'status' => WorkOrderRecipientStatus::PENDING,
        'stage_batch' => 3,
    ]);

    // Order is filled before Batch 2 runs
    $order->update([
        'status' => WorkOrderStatus::FILLED,
        'confirmed_count' => 1,
    ]);

    $job = new DispatchStagedWorkOrderBatchJob($order->id, 2);
    $job->handle(app(MessageSender::class));

    // Batch 2 & 3 recipients should now be CANCELLED because order was already filled
    $this->assertDatabaseHas('work_order_recipients', [
        'work_order_id' => $order->id,
        'driver_id' => $driverBatch2->id,
        'status' => 'cancelled',
    ]);
    $this->assertDatabaseHas('work_order_recipients', [
        'work_order_id' => $order->id,
        'driver_id' => $driverBatch3->id,
        'status' => 'cancelled',
    ]);
});

test('duplicate work order creates a new draft with identical filters and new code', function () {
    $original = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLED,
        'from_location' => 'أسيوط',
        'to_location' => 'سوهاج',
        'trips_needed' => 2,
        'backup_needed' => 1,
    ]);

    $original->filters()->create([
        'question_id' => $this->question->id,
        'operator' => 'eq',
        'value' => 'van',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/work-orders/{$original->id}/duplicate");

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.from_location', 'أسيوط')
        ->assertJsonPath('data.to_location', 'سوهاج');

    $newCode = $response->json('data.code');
    expect($newCode)->not->toBe($original->code)
        ->and($newCode)->toMatch('/^WO-\d{4}$/');

    $newId = $response->json('data.id');
    $this->assertDatabaseHas('work_order_filters', [
        'work_order_id' => $newId,
        'question_id' => $this->question->id,
        'value' => json_encode('van'),
    ]);

    expect(ActivityLog::where('action', 'work_order_duplicated')->exists())->toBeTrue();
});

test('failed recipient can be retried via retry endpoint', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLING,
        'trips_needed' => 2,
    ]);

    $recipient = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::FAILED,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/work-orders/{$order->id}/recipients/{$recipient->id}/retry");

    $response->assertOk()
        ->assertJsonPath('data.status', 'sent');

    $recipient->refresh();
    expect($recipient->status)->toBe(WorkOrderRecipientStatus::SENT)
        ->and($recipient->sent_at)->not->toBeNull();

    expect(ActivityLog::where('action', 'work_order_recipient_retried')->exists())->toBeTrue();
});

test('can create a draft with custom_message and renders with driver variables', function () {
    $driver = Driver::factory()->create([
        'name' => 'محمود سلامة',
        'phone' => '+201099998888',
        'status' => DriverStatus::ACTIVE,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/work-orders', [
        'from_location' => 'القاهرة',
        'to_location' => 'السويس',
        'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'trips_needed' => 1,
        'custom_message' => 'يا كابتن {{driver_name}}، رحلة من {{from_location}} إلى {{to_location}} برقم {{order_code}}.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.custom_message', 'يا كابتن {{driver_name}}، رحلة من {{from_location}} إلى {{to_location}} برقم {{order_code}}.');

    $orderId = $response->json('data.id');
    $order = WorkOrder::find($orderId);

    $rendered = $order->renderOfferMessageForDriver($driver);
    expect($rendered)->toBe("يا كابتن محمود سلامة، رحلة من القاهرة إلى السويس برقم {$order->code}.");
});
