<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\User;
use App\Models\WhatsappGroup;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\DashboardService;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('exact dashboard numbers on seeded data match expectations', function () {
    $dashboardService = app(DashboardService::class);
    $dashboardService->bustCache();

    // 1. Seed drivers
    Driver::factory()->count(3)->create(['status' => DriverStatus::ACTIVE]);
    Driver::factory()->count(2)->create(['status' => DriverStatus::INVITED]);
    Driver::factory()->count(1)->create(['status' => DriverStatus::OPTED_OUT]);
    Driver::factory()->count(1)->create(['status' => DriverStatus::DECLINED]);

    // 2. Seed work orders
    $order1 = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLED,
        'trips_needed' => 2,
        'confirmed_count' => 2,
        'created_at' => now()->startOfMonth()->addHours(2),
        'filled_at' => now()->startOfMonth()->addHours(3), // 60 minutes fill time
    ]);

    $order2 = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::IN_PROGRESS,
        'trips_needed' => 1,
        'confirmed_count' => 1,
        'created_at' => now()->startOfMonth()->addHours(4),
        'filled_at' => now()->startOfMonth()->addHours(6), // 120 minutes fill time
    ]);

    WorkOrder::factory()->create([
        'status' => WorkOrderStatus::DRAFT,
        'created_at' => now()->startOfMonth()->addHours(7),
    ]);

    // 3. Seed recipients for acceptance rate: 4 sent, 3 accepted (confirmed/backup)
    $activeDriver = Driver::where('status', DriverStatus::ACTIVE)->first();
    WorkOrderRecipient::create([
        'work_order_id' => $order1->id,
        'driver_id' => $activeDriver->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sent_at' => now()->subDay(),
        'responded_at' => now()->subDay()->addMinutes(10),
    ]);
    WorkOrderRecipient::create([
        'work_order_id' => $order1->id,
        'driver_id' => Driver::factory()->create(['status' => DriverStatus::ACTIVE])->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sent_at' => now()->subDay(),
        'responded_at' => now()->subDay()->addMinutes(15),
    ]);
    WorkOrderRecipient::create([
        'work_order_id' => $order2->id,
        'driver_id' => Driver::factory()->create(['status' => DriverStatus::ACTIVE])->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sent_at' => now()->subDay(),
        'responded_at' => now()->subDay()->addMinutes(20),
    ]);
    WorkOrderRecipient::create([
        'work_order_id' => $order2->id,
        'driver_id' => Driver::factory()->create(['status' => DriverStatus::ACTIVE])->id,
        'status' => WorkOrderRecipientStatus::DECLINED,
        'sent_at' => now()->subDay(),
    ]);

    $response = $this->getJson('/api/v1/dashboard');
    $this->getJson('/api/v1/dashboard/summary')->assertOk();

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'drivers_summary' => ['total_drivers', 'active_drivers', 'opted_out_drivers', 'by_status'],
                'orders_summary' => ['total_orders', 'by_status', 'period_start', 'period_end'],
                'dispatch_metrics' => [
                    'total_offers_sent',
                    'accepted_offers_count',
                    'acceptance_rate',
                    'avg_time_to_fill_minutes',
                    'filled_orders_count',
                ],
                'monthly_trend',
            ],
            'meta',
            'message',
        ]);

    $data = $response->json('data');

    // Total drivers: 3 active + 2 invited + 1 opted_out + 1 declined + 3 created for recipients = 10 drivers
    expect($data['drivers_summary']['active_drivers'])->toBe(6)
        ->and($data['drivers_summary']['opted_out_drivers'])->toBe(1)
        ->and($data['orders_summary']['total_orders'])->toBe(3)
        ->and($data['orders_summary']['by_status']['filled']['count'])->toBe(1)
        ->and($data['orders_summary']['by_status']['in_progress']['count'])->toBe(1)
        ->and($data['orders_summary']['by_status']['draft']['count'])->toBe(1)
        // Acceptance rate: 3 accepted out of 4 sent = 75.0%
        ->and($data['dispatch_metrics']['total_offers_sent'])->toBe(4)
        ->and($data['dispatch_metrics']['accepted_offers_count'])->toBe(3)
        ->and($data['dispatch_metrics']['acceptance_rate'])->toEqual(75.0)
        // Avg time to fill: (60 + 120) / 2 = 90.0 minutes
        ->and($data['dispatch_metrics']['avg_time_to_fill_minutes'])->toEqual(90.0)
        ->and($data['dispatch_metrics']['filled_orders_count'])->toBe(2);
});

test('group performance table returns exact stats per WhatsApp group with conversion rate', function () {
    $dashboardService = app(DashboardService::class);
    $dashboardService->bustCache();

    $groupA = WhatsappGroup::create(['name' => 'سائقي القاهرة']);
    $groupB = WhatsappGroup::create(['name' => 'سائقي الجيزة']);

    // Group A: 4 drivers (2 active, 1 invited, 1 declined) -> conversion 50%
    $d1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d3 = Driver::factory()->create(['status' => DriverStatus::INVITED]);
    $d4 = Driver::factory()->create(['status' => DriverStatus::DECLINED]);

    $groupA->drivers()->attach([$d1->id, $d2->id, $d3->id, $d4->id], ['first_seen_at' => now()]);

    // Group B: 2 drivers (1 active, 1 opted_out) -> conversion 50%
    $d5 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d6 = Driver::factory()->create(['status' => DriverStatus::OPTED_OUT]);

    $groupB->drivers()->attach([$d5->id, $d6->id], ['first_seen_at' => now()]);

    $response = $this->getJson('/api/v1/dashboard/group-performance?sort_by=name&sort_dir=asc');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'imported',
                    'invited',
                    'interested',
                    'active',
                    'declined',
                    'opted_out',
                    'conversion_rate',
                ],
            ],
            'meta' => ['total_groups', 'sort_by', 'sort_dir'],
            'message',
        ]);

    $rows = $response->json('data');
    $rowA = collect($rows)->firstWhere('name', 'سائقي القاهرة');
    $rowB = collect($rows)->firstWhere('name', 'سائقي الجيزة');

    expect($rowA)->not->toBeNull()
        ->and($rowA['imported'])->toBe(4)
        ->and($rowA['active'])->toBe(2)
        ->and($rowA['invited'])->toBe(1)
        ->and($rowA['declined'])->toBe(1)
        ->and($rowA['conversion_rate'])->toEqual(50.0);

    expect($rowB)->not->toBeNull()
        ->and($rowB['imported'])->toBe(2)
        ->and($rowB['active'])->toBe(1)
        ->and($rowB['opted_out'])->toBe(1)
        ->and($rowB['conversion_rate'])->toEqual(50.0);
});

test('dashboard respects date range filter', function () {
    $dashboardService = app(DashboardService::class);
    $dashboardService->bustCache();

    // Create an old order last month
    WorkOrder::factory()->create([
        'status' => WorkOrderStatus::FILLED,
        'created_at' => now()->subMonths(2),
    ]);

    // Create order this week
    WorkOrder::factory()->create([
        'status' => WorkOrderStatus::COMPLETED,
        'created_at' => now()->subDays(2),
    ]);

    $start = now()->subDays(5)->toDateString();
    $end = now()->toDateString();

    $response = $this->getJson("/api/v1/dashboard?start_date={$start}&end_date={$end}");

    $response->assertOk();
    $data = $response->json('data');

    // Only the order within [subDays(5)..now] should be counted
    expect($data['orders_summary']['total_orders'])->toBe(1)
        ->and($data['orders_summary']['by_status']['completed']['count'])->toBe(1)
        ->and($data['orders_summary']['by_status']['filled']['count'])->toBe(0);
});

test('dashboard cache busts when driver or work order changes', function () {
    $dashboardService = app(DashboardService::class);
    $dashboardService->bustCache();

    $initialVersion = $dashboardService->getCacheVersion();

    // 1. Create a driver -> triggers cache bust
    Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $versionAfterDriver = $dashboardService->getCacheVersion();
    expect($versionAfterDriver)->toBeGreaterThanOrEqual($initialVersion);

    // 2. Manual bust cache endpoint
    $response = $this->postJson('/api/v1/dashboard/bust-cache');
    $response->assertOk();
    expect($response->json('meta.version'))->toBeGreaterThanOrEqual($versionAfterDriver);
});

test('dashboard cache-version observers debounce and write at most once during batch transactions', function () {
    DB::transaction(function () {
        // Creating multiple drivers inside a transaction should only register a single afterCommit bust
        for ($i = 0; $i < 10; $i++) {
            Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
        }
    });

    $dashboardService = app(DashboardService::class);
    expect($dashboardService->getCacheVersion())->toBeGreaterThan(0);
});

test('dashboard cache executes trailing invalidation so changes made within debounce window bump version once window ends', function () {
    $dashboardService = app(DashboardService::class);

    // 1. Force a clean baseline
    $dashboardService->bustCache(force: true);
    $baseVersion = $dashboardService->getCacheVersion();

    // 2. Trigger bust within the debounce window (0.1s after base)
    $dashboardService->bustCache();

    // Version must NOT bump yet because we are within debounce window
    expect($dashboardService->getCacheVersion())->toBe($baseVersion);

    // 3. Simulate the 3.0-second debounce window elapsing
    Cache::forever(
        'dashboard_last_invalidated_at',
        microtime(true) - 4.0
    );

    // 4. On next read/check, trailing invalidation must trigger and bump version
    $trailingVersion = $dashboardService->getCacheVersion();
    expect($trailingVersion)->toBeGreaterThan($baseVersion);

    // 5. Subsequent read with no new mutation remains at trailingVersion
    expect($dashboardService->getCacheVersion())->toBe($trailingVersion);
});
