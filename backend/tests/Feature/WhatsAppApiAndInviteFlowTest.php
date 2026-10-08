<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Driver;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
    $this->user = User::factory()->create();
});

test('estimate invite calculates count and duration accurately', function () {
    Driver::factory()->count(15)->create(['status' => DriverStatus::NEW]);
    Driver::factory()->count(5)->create(['status' => DriverStatus::ACTIVE]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/drivers/invite-estimate', [
            'all_new' => true,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'count' => 15,
            ],
        ]);
});

test('bulk invite transitions new drivers to invited and dispatches messages', function () {
    $drivers = Driver::factory()->count(5)->create(['status' => DriverStatus::NEW]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/drivers/invite', [
            'driver_ids' => $drivers->pluck('id')->toArray(),
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'invited_count' => 5,
            ],
        ]);

    foreach ($drivers as $driver) {
        $driver->refresh();
        expect($driver->status)->toBe(DriverStatus::INVITED);

        $this->assertDatabaseHas('whatsapp_messages', [
            'driver_id' => $driver->id,
            'direction' => WhatsAppMessageDirection::OUT->value,
            'template_name' => 'driver_invitation',
        ]);
    }
});

test('driver messages endpoint returns chat history in chronological order', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $msg1 = WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'الرسالة الأولى',
        'status' => WhatsAppMessageStatus::SENT,
        'created_at' => now()->subMinutes(10),
    ]);

    $msg2 = WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::IN,
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'الرسالة الثانية واردة',
        'status' => WhatsAppMessageStatus::DELIVERED,
        'created_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/drivers/{$driver->id}/messages");

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.body', 'الرسالة الأولى')
        ->assertJsonPath('data.1.body', 'الرسالة الثانية واردة');
});

test('global whatsapp messages endpoint lists messages and status counters', function () {
    $driver = Driver::factory()->create();

    WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'رسالة 1',
        'status' => WhatsAppMessageStatus::SENT,
        'created_at' => now(),
    ]);

    WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'رسالة 2',
        'status' => WhatsAppMessageStatus::FAILED,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/whatsapp/messages');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'meta' => [
                'counts' => ['total', 'queued', 'sent', 'delivered', 'read', 'failed'],
            ],
        ])
        ->assertJsonPath('meta.counts.total', 2)
        ->assertJsonPath('meta.counts.failed', 1);
});

test('retry endpoint re-queues a failed outgoing message', function () {
    Queue::fake([SendWhatsAppMessageJob::class]);

    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $msg = WhatsAppMessage::create([
        'driver_id' => $driver->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'رسالة فشلت وتحتاج إعادة محاولة',
        'status' => WhatsAppMessageStatus::FAILED,
        'error_code' => '131026',
        'error_message' => 'Previous failure',
        'provider_message_id' => 'failed_id_01',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/whatsapp/messages/{$msg->id}/retry");

    Queue::assertPushed(SendWhatsAppMessageJob::class);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.error_code', null);

    $msg->refresh();
    expect($msg->status)->toBe(WhatsAppMessageStatus::QUEUED)
        ->and($msg->error_code)->toBeNull()
        ->and($msg->provider_message_id)->toBeNull();
});

test('drivers:expire-invites command transitions expired invited drivers to no_response', function () {
    config(['whatsapp.no_response_days' => 3]);

    $expiredDriver = Driver::factory()->create([
        'status' => DriverStatus::INVITED,
        'updated_at' => now()->subDays(4),
    ]);

    $recentDriver = Driver::factory()->create([
        'status' => DriverStatus::INVITED,
        'updated_at' => now()->subDay(),
    ]);

    $this->artisan('drivers:expire-invites')
        ->assertSuccessful();

    $expiredDriver->refresh();
    $recentDriver->refresh();

    expect($expiredDriver->status)->toBe(DriverStatus::NO_RESPONSE)
        ->and($recentDriver->status)->toBe(DriverStatus::INVITED);
});
