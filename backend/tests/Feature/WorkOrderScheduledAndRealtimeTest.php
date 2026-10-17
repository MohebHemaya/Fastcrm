<?php

namespace Tests\Feature;

use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Events\WorkOrderUpdated;
use App\Models\Driver;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WorkOrderScheduledAndRealtimeTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_work_order_without_locations_and_date(): void
    {
        $payload = [
            'trips_needed' => 3,
            'backup_needed' => 1,
            'cargo_type' => 'بضائع عامة',
            'scheduled_dispatch_at' => now()->addHours(2)->toIso8601String(),
            'offer_expires_in_minutes' => 45,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/work-orders', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('work_orders', [
            'trips_needed' => 3,
            'backup_needed' => 1,
            'from_location' => null,
            'to_location' => null,
            'scheduled_at' => null,
            'offer_expires_in_minutes' => 45,
        ]);
    }

    public function test_work_order_updated_event_broadcasts_on_private_channels(): void
    {
        Event::fake([WorkOrderUpdated::class]);

        $order = WorkOrder::create([
            'code' => 'WO-TEST-BROADCAST',
            'trips_needed' => 2,
            'backup_needed' => 0,
            'status' => WorkOrderStatus::FILLING,
            'confirmed_count' => 2,
            'created_by' => $this->user->id,
        ]);

        event(new WorkOrderUpdated(
            workOrderId: $order->id,
            orderCode: $order->code,
            status: WorkOrderStatus::FILLED->value,
            confirmedCount: 2,
            backupCount: 0,
            tripsNeeded: 2,
            backupNeeded: 0,
            isFilled: true,
            notificationMessage: 'اكتمل عدد السائقين المطلوب للرحلة بنجاح!'
        ));

        Event::assertDispatched(WorkOrderUpdated::class, function ($event) use ($order) {
            $channels = $event->broadcastOn();
            $this->assertCount(2, $channels);
            $this->assertEquals('private-work-orders', $channels[0]->name);
            $this->assertEquals("private-work-order.{$order->id}", $channels[1]->name);

            $payload = $event->broadcastWith();
            $this->assertEquals(2, $payload['confirmed_count']);
            $this->assertTrue($payload['is_filled']);
            $this->assertEquals('filled', $payload['status']);

            return true;
        });
    }

    public function test_process_schedules_command_expires_recipients_past_deadline(): void
    {
        $order = WorkOrder::create([
            'code' => 'WO-TEST-EXPIRE',
            'trips_needed' => 1,
            'backup_needed' => 0,
            'status' => WorkOrderStatus::FILLING,
            'offer_expires_in_minutes' => 30,
            'created_by' => $this->user->id,
        ]);

        $driver = Driver::factory()->create(['phone' => '+201099999999']);

        $recipient = WorkOrderRecipient::create([
            'work_order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => WorkOrderRecipientStatus::SENT,
            'sent_at' => now()->subMinutes(35),
            'expires_at' => now()->subMinutes(5),
            'stage_batch' => 1,
        ]);

        $this->artisan('work-orders:process-schedules')
            ->assertSuccessful();

        $recipient->refresh();
        $this->assertEquals(WorkOrderRecipientStatus::NO_RESPONSE, $recipient->status);
    }
}
