<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WorkOrderOutcome;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\DriverMetric;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderRecipient;
use App\Services\WorkOrderFollowUpService;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('recording outcomes after completed calculates driver metrics and creates timeline event', function () {
    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::COMPLETED,
        'scheduled_at' => now()->subDay(),
    ]);

    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'Driver 1']);
    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE, 'name' => 'Driver 2']);

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

    $service = app(WorkOrderFollowUpService::class);
    $service->recordOutcomes($order, [
        [
            'recipient_id' => $rec1->id,
            'outcome' => WorkOrderOutcome::ATTENDED->value,
            'rating' => 5,
            'notes' => 'سائق ممتاز والتزام كامل',
        ],
        [
            'recipient_id' => $rec2->id,
            'outcome' => WorkOrderOutcome::NO_SHOW->value,
            'notes' => 'لم يحضر ولم يرد على الهاتف',
        ],
    ], $this->user->id);

    // Verify recipient 1 outcome and rating
    $rec1->refresh();
    expect($rec1->outcome)->toBe(WorkOrderOutcome::ATTENDED)
        ->and($rec1->rating)->toBe(5)
        ->and($rec1->outcome_notes)->toBe('سائق ممتاز والتزام كامل')
        ->and($rec1->outcome_recorded_at)->not->toBeNull()
        ->and($rec1->outcome_recorded_by)->toBe($this->user->id);

    // Verify recipient 2 outcome
    $rec2->refresh();
    expect($rec2->outcome)->toBe(WorkOrderOutcome::NO_SHOW)
        ->and($rec2->rating)->toBeNull();

    // Verify DriverMetric for driver 1 (via listener)
    $metric1 = DriverMetric::where('driver_id', $driver1->id)->first();
    expect($metric1)->not->toBeNull()
        ->and($metric1->trips_completed)->toBe(1)
        ->and($metric1->late_count)->toBe(0)
        ->and($metric1->no_shows)->toBe(0)
        ->and($metric1->cancellations)->toBe(0)
        ->and((float) $metric1->avg_rating)->toBe(5.0)
        ->and($metric1->ratings_count)->toBe(1)
        ->and($metric1->last_trip_at)->not->toBeNull();

    // Verify DriverMetric for driver 2 (via listener)
    $metric2 = DriverMetric::where('driver_id', $driver2->id)->first();
    expect($metric2)->not->toBeNull()
        ->and($metric2->trips_completed)->toBe(0)
        ->and($metric2->no_shows)->toBe(1)
        ->and($metric2->avg_rating)->toBeNull()
        ->and($metric2->last_trip_at)->toBeNull();

    // Verify timeline events recorded
    $events = WorkOrderEvent::where('work_order_id', $order->id)
        ->where('action', 'outcome_recorded')
        ->get();
    expect($events)->toHaveCount(2);
});

test('editing an outcome recalculates driver metrics accurately without drift', function () {
    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::COMPLETED,
        'scheduled_at' => now()->subDay(),
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $rec = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    $service = app(WorkOrderFollowUpService::class);

    // Initially recorded as no_show
    $service->recordOutcomes($order, [
        [
            'recipient_id' => $rec->id,
            'outcome' => WorkOrderOutcome::NO_SHOW->value,
        ],
    ], $this->user->id);

    $metric = DriverMetric::where('driver_id', $driver->id)->first();
    expect($metric->no_shows)->toBe(1)
        ->and($metric->trips_completed)->toBe(0);

    // Later corrected to attended with 4 rating
    $service->recordOutcomes($order, [
        [
            'recipient_id' => $rec->id,
            'outcome' => WorkOrderOutcome::ATTENDED->value,
            'rating' => 4,
            'notes' => 'تم التصحيح بعد التواصل مع المشرف',
        ],
    ], $this->user->id);

    $metric->refresh();
    expect($metric->no_shows)->toBe(0)
        ->and($metric->trips_completed)->toBe(1)
        ->and((float) $metric->avg_rating)->toBe(4.0)
        ->and($metric->ratings_count)->toBe(1);
});

test('cannot record outcomes if order is not completed', function () {
    $order = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLED,
    ]);

    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $rec = WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    $service = app(WorkOrderFollowUpService::class);

    expect(fn () => $service->recordOutcomes($order, [
        ['recipient_id' => $rec->id, 'outcome' => 'attended'],
    ], $this->user->id))->toThrow(ValidationException::class);
});

test('driver list API returns metrics and supports sorting by metrics', function () {
    $driverA = Driver::factory()->create(['name' => 'Driver A', 'status' => DriverStatus::ACTIVE]);
    $driverB = Driver::factory()->create(['name' => 'Driver B', 'status' => DriverStatus::ACTIVE]);

    DriverMetric::create([
        'driver_id' => $driverA->id,
        'trips_completed' => 10,
        'avg_rating' => 4.8,
        'no_shows' => 0,
    ]);

    DriverMetric::create([
        'driver_id' => $driverB->id,
        'trips_completed' => 2,
        'avg_rating' => 3.2,
        'no_shows' => 3,
    ]);

    // Sort by avg_rating desc
    $response = $this->getJson('/api/v1/drivers?sort_by=avg_rating&sort_dir=desc');
    $response->assertOk();

    $data = $response->json('data');
    expect($data[0]['id'])->toBe($driverA->id)
        ->and($data[0]['metrics']['avg_rating'])->toBe(4.8)
        ->and($data[1]['id'])->toBe($driverB->id)
        ->and($data[1]['metrics']['avg_rating'])->toBe(3.2);

    // Sort by no_shows desc
    $response2 = $this->getJson('/api/v1/drivers?sort_by=no_shows&sort_dir=desc');
    $response2->assertOk();
    $data2 = $response2->json('data');
    expect($data2[0]['id'])->toBe($driverB->id)
        ->and($data2[0]['metrics']['no_shows'])->toBe(3);
});
