<?php

use App\Enums\DriverStatus;
use App\Enums\ImportStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\Import;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Cache::flush();

    $this->user = User::create([
        'name' => 'مدير النظام',
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
        'is_active' => true,
    ]);
});

test('unauthenticated request to sidebar badges is rejected', function () {
    $response = $this->getJson('/api/v1/sidebar-badges');
    $response->assertStatus(401);
});

test('authenticated user receives accurate sidebar badges and cache works', function () {
    // 1. Create a work order filling (open)
    WorkOrder::create([
        'code' => 'WO-TEST-001',
        'from_location' => 'القاهرة',
        'to_location' => 'الإسكندرية',
        'scheduled_at' => now()->addDay(),
        'trips_needed' => 5,
        'confirmed_count' => 2,
        'status' => WorkOrderStatus::FILLING,
        'created_by' => $this->user->id,
    ]);

    // 2. Create another work order: published, trips_needed > confirmed_count
    WorkOrder::create([
        'code' => 'WO-TEST-002',
        'from_location' => 'الجيزة',
        'to_location' => 'طنطا',
        'scheduled_at' => now()->addDay(),
        'trips_needed' => 3,
        'confirmed_count' => 1,
        'status' => WorkOrderStatus::SENDING,
        'created_by' => $this->user->id,
    ]);

    // 3. Create a completed work order (should NOT count)
    WorkOrder::create([
        'code' => 'WO-TEST-003',
        'from_location' => 'السويس',
        'to_location' => 'الإسماعيلية',
        'scheduled_at' => now()->subDay(),
        'trips_needed' => 2,
        'confirmed_count' => 2,
        'status' => WorkOrderStatus::COMPLETED,
        'created_by' => $this->user->id,
    ]);

    // 4. Create drivers
    $driver1 = Driver::create([
        'name' => 'سائق 1',
        'phone' => '+201000000001',
        'status' => DriverStatus::ACTIVE,
        'needs_review' => true,
    ]);
    $driver2 = Driver::create([
        'name' => 'سائق 2',
        'phone' => '+201000000002',
        'status' => DriverStatus::ACTIVE,
        'needs_review' => false,
    ]);

    // 5. Create imports ready for review
    Import::create([
        'original_name' => 'drivers.xlsx',
        'stored_path' => 'imports/drivers.xlsx',
        'status' => ImportStatus::READY_FOR_REVIEW,
        'user_id' => $this->user->id,
    ]);

    // 6. Create failed WhatsApp message within 24h
    WhatsAppMessage::create([
        'driver_id' => $driver1->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'رسالة فاشلة',
        'status' => WhatsAppMessageStatus::FAILED,
        'error_message' => 'Delivery failed',
        'created_at' => Carbon::now()->subHours(2),
    ]);

    // 7. Old failed WhatsApp message (>24h ago, should NOT count)
    $oldMsg = WhatsAppMessage::create([
        'driver_id' => $driver2->id,
        'direction' => WhatsAppMessageDirection::OUT,
        'type' => WhatsAppMessageType::TEXT,
        'body' => 'رسالة فاشلة قديمة',
        'status' => WhatsAppMessageStatus::FAILED,
        'error_message' => 'Delivery failed',
    ]);
    DB::table('whatsapp_messages')
        ->where('id', $oldMsg->id)
        ->update(['created_at' => Carbon::now()->subHours(30)]);

    // Set scheduler heartbeat as fresh
    Cache::put('last_scheduler_run_at', now()->toDateTimeString(), 300);

    $response = $this->actingAs($this->user)->getJson('/api/v1/sidebar-badges');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'orders_open',
                'drivers_needs_review',
                'imports_ready',
                'failed_messages_24h',
                'health_warning',
            ],
        ]);

    $data = $response->json('data');
    expect($data['orders_open'])->toBe(2);
    expect($data['drivers_needs_review'])->toBe(1);
    expect($data['imports_ready'])->toBe(1);
    expect($data['failed_messages_24h'])->toBe(1);
    expect($data['health_warning'])->toBe(false);

    // Verify caching: create another driver with needs_review = true
    Driver::create([
        'name' => 'سائق 3',
        'phone' => '+201000000003',
        'status' => DriverStatus::ACTIVE,
        'needs_review' => true,
    ]);

    // Since it's cached for 30 seconds, response should still return 1
    $cachedResponse = $this->actingAs($this->user)->getJson('/api/v1/sidebar-badges');
    expect($cachedResponse->json('data.drivers_needs_review'))->toBe(1);

    // Clear cache, then count should become 2
    Cache::forget('sidebar_badge_counts');
    $freshResponse = $this->actingAs($this->user)->getJson('/api/v1/sidebar-badges');
    expect($freshResponse->json('data.drivers_needs_review'))->toBe(2);
});
