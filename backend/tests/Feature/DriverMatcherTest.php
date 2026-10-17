<?php

use App\Enums\DriverStatus;
use App\Enums\QuestionType;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Driver;
use App\Models\DriverAnswer;
use App\Models\DriverMetric;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\User;
use App\Models\WhatsAppGroup;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\DriverMatcher;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->matcher = app(DriverMatcher::class);

    // Filterable single-choice question: vehicle_type
    $this->qVehicle = Question::create([
        'key' => 'vehicle_type',
        'sort_order' => 1,
        'is_active' => true,
        'is_required' => true,
        'is_filter' => true,
        'type' => QuestionType::SINGLE_CHOICE,
    ]);

    $this->vVehicle = QuestionVersion::create([
        'question_id' => $this->qVehicle->id,
        'version' => 1,
        'text_ar' => 'نوع المركبة',
        'type' => QuestionType::SINGLE_CHOICE,
        'options_snapshot' => [
            ['value' => 'pickup', 'label_ar' => 'ربع نقل'],
            ['value' => 'van', 'label_ar' => 'فان'],
            ['value' => 'jumbo', 'label_ar' => 'جامبو'],
        ],
    ]);

    $this->optPickup = QuestionOption::create([
        'question_id' => $this->qVehicle->id,
        'value' => 'pickup',
        'label_ar' => 'ربع نقل',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->optVan = QuestionOption::create([
        'question_id' => $this->qVehicle->id,
        'value' => 'van',
        'label_ar' => 'فان',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $this->optJumbo = QuestionOption::create([
        'question_id' => $this->qVehicle->id,
        'value' => 'jumbo',
        'label_ar' => 'جامبو',
        'sort_order' => 3,
        'is_active' => true,
    ]);

    // Filterable numeric question: experience_years
    $this->qExp = Question::create([
        'key' => 'experience_years',
        'sort_order' => 2,
        'is_active' => true,
        'is_required' => false,
        'is_filter' => true,
        'type' => QuestionType::NUMBER,
    ]);

    $this->vExp = QuestionVersion::create([
        'question_id' => $this->qExp->id,
        'version' => 1,
        'text_ar' => 'سنوات الخبرة',
        'type' => QuestionType::NUMBER,
    ]);

    // Non-filterable question: favourite_color
    $this->qColor = Question::create([
        'key' => 'favourite_color',
        'sort_order' => 3,
        'is_active' => true,
        'is_required' => false,
        'is_filter' => false,
        'type' => QuestionType::TEXT,
    ]);

    $this->vColor = QuestionVersion::create([
        'question_id' => $this->qColor->id,
        'version' => 1,
        'text_ar' => 'اللون المفضل',
        'type' => QuestionType::TEXT,
    ]);
});

test('DriverMatcher only matches active drivers and excludes suspended, new, or blacklisted drivers', function () {
    $activeDriver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $newDriver = Driver::factory()->create(['status' => DriverStatus::NEW]);
    $suspendedDriver = Driver::factory()->create(['status' => DriverStatus::SUSPENDED]);
    $blacklistedDriver = Driver::factory()->create(['status' => DriverStatus::BLOCKED]);

    // Give all 4 drivers the same answer: pickup
    foreach ([$activeDriver, $newDriver, $suspendedDriver, $blacklistedDriver] as $driver) {
        DriverAnswer::create([
            'driver_id' => $driver->id,
            'question_id' => $this->qVehicle->id,
            'question_version_id' => $this->vVehicle->id,
            'option_id' => $this->optPickup->id,
            'is_current' => true,
            'is_skipped' => false,
            'answered_at' => now(),
        ]);
    }

    $matched = $this->matcher->buildQuery([
        [
            'question_id' => $this->qVehicle->id,
            'operator' => 'eq',
            'value' => 'pickup',
        ],
    ])->pluck('id')->all();

    expect($matched)->toBe([$activeDriver->id]);
});

test('DriverMatcher ignores questions where is_filter is false', function () {
    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    DriverAnswer::create([
        'driver_id' => $driver1->id,
        'question_id' => $this->qColor->id,
        'question_version_id' => $this->vColor->id,
        'value_text' => 'blue',
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    // Query with non-filter question is ignored by matcher
    $matched = $this->matcher->buildQuery([
        [
            'question_id' => $this->qColor->id,
            'operator' => 'eq',
            'value' => 'blue',
        ],
    ])->pluck('id')->all();

    // Since non-filter question is ignored, all active drivers match
    expect($matched)->toContain($driver1->id, $driver2->id);
});

test('DriverMatcher applies AND between questions and OR between options of same question', function () {
    // Driver 1: pickup, 5 years exp
    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverAnswer::create([
        'driver_id' => $driver1->id,
        'question_id' => $this->qVehicle->id,
        'question_version_id' => $this->vVehicle->id,
        'option_id' => $this->optPickup->id,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);
    DriverAnswer::create([
        'driver_id' => $driver1->id,
        'question_id' => $this->qExp->id,
        'question_version_id' => $this->vExp->id,
        'value_number' => 5,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    // Driver 2: van, 4 years exp
    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverAnswer::create([
        'driver_id' => $driver2->id,
        'question_id' => $this->qVehicle->id,
        'question_version_id' => $this->vVehicle->id,
        'option_id' => $this->optVan->id,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);
    DriverAnswer::create([
        'driver_id' => $driver2->id,
        'question_id' => $this->qExp->id,
        'question_version_id' => $this->vExp->id,
        'value_number' => 4,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    // Driver 3: jumbo, 5 years exp
    $driver3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverAnswer::create([
        'driver_id' => $driver3->id,
        'question_id' => $this->qVehicle->id,
        'question_version_id' => $this->vVehicle->id,
        'option_id' => $this->optJumbo->id,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);
    DriverAnswer::create([
        'driver_id' => $driver3->id,
        'question_id' => $this->qExp->id,
        'question_version_id' => $this->vExp->id,
        'value_number' => 5,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    // Driver 4: pickup, 2 years exp
    $driver4 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverAnswer::create([
        'driver_id' => $driver4->id,
        'question_id' => $this->qVehicle->id,
        'question_version_id' => $this->vVehicle->id,
        'option_id' => $this->optPickup->id,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);
    DriverAnswer::create([
        'driver_id' => $driver4->id,
        'question_id' => $this->qExp->id,
        'question_version_id' => $this->vExp->id,
        'value_number' => 2,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    // Filter: vehicle_type IN ['pickup', 'van'] AND experience_years >= 3
    $filters = [
        [
            'question_id' => $this->qVehicle->id,
            'operator' => 'in',
            'value' => ['pickup', 'van'],
        ],
        [
            'question_id' => $this->qExp->id,
            'operator' => 'gte',
            'value' => 3,
        ],
    ];

    $matched = $this->matcher->buildQuery($filters)->pluck('id')->all();

    // Driver 1 (pickup, 5) and Driver 2 (van, 4) match.
    // Driver 3 (jumbo) fails vehicle filter.
    // Driver 4 (2 years) fails exp filter.
    expect($matched)->toContain($driver1->id, $driver2->id)
        ->not->toContain($driver3->id)
        ->not->toContain($driver4->id);
});

test('DriverMatcher handles numeric gte and lte correctly', function () {
    $d1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    foreach ([[$d1, 2], [$d2, 5], [$d3, 10]] as [$driver, $exp]) {
        DriverAnswer::create([
            'driver_id' => $driver->id,
            'question_id' => $this->qExp->id,
            'question_version_id' => $this->vExp->id,
            'value_number' => $exp,
            'is_current' => true,
            'is_skipped' => false,
            'answered_at' => now(),
        ]);
    }

    $lteMatched = $this->matcher->buildQuery([
        ['question_id' => $this->qExp->id, 'operator' => 'lte', 'value' => 5],
    ])->pluck('id')->all();

    expect($lteMatched)->toContain($d1->id, $d2->id)
        ->not->toContain($d3->id);

    $gteMatched = $this->matcher->buildQuery([
        ['question_id' => $this->qExp->id, 'operator' => 'gte', 'value' => 6],
    ])->pluck('id')->all();

    expect($gteMatched)->toBe([$d3->id]);
});

test('Missing or skipped answers never match in DriverMatcher', function () {
    $driverNoAnswer = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driverSkipped = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    DriverAnswer::create([
        'driver_id' => $driverSkipped->id,
        'question_id' => $this->qVehicle->id,
        'question_version_id' => $this->vVehicle->id,
        'is_current' => true,
        'is_skipped' => true,
        'answered_at' => now(),
    ]);

    $matched = $this->matcher->buildQuery([
        ['question_id' => $this->qVehicle->id, 'operator' => 'eq', 'value' => 'pickup'],
    ])->pluck('id')->all();

    expect($matched)->toBeEmpty();
});

test('DriverMatcher excludes drivers confirmed on another order on the same date when flag is set', function () {
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    DriverAnswer::create([
        'driver_id' => $driver->id,
        'question_id' => $this->qVehicle->id,
        'question_version_id' => $this->vVehicle->id,
        'option_id' => $this->optPickup->id,
        'is_current' => true,
        'is_skipped' => false,
        'answered_at' => now(),
    ]);

    // Work order 1: scheduled on 2026-10-15
    $order1 = WorkOrder::factory()->create([
        'scheduled_at' => '2026-10-15 10:00:00',
        'status' => WorkOrderStatus::FILLING,
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order1->id,
        'driver_id' => $driver->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
    ]);

    $filters = [
        ['question_id' => $this->qVehicle->id, 'operator' => 'eq', 'value' => 'pickup'],
    ];

    // Same date, excludeSameDayConfirmed = true -> should exclude driver
    $matchedSameDate = $this->matcher->buildQuery(
        filters: $filters,
        excludeSameDayConfirmed: true,
        scheduledAt: '2026-10-15 14:00:00'
    )->pluck('id')->all();

    expect($matchedSameDate)->not->toContain($driver->id);

    // Same date, excludeSameDayConfirmed = false -> should NOT exclude driver
    $matchedAllowed = $this->matcher->buildQuery(
        filters: $filters,
        excludeSameDayConfirmed: false,
        scheduledAt: '2026-10-15 14:00:00'
    )->pluck('id')->all();

    expect($matchedAllowed)->toContain($driver->id);

    // Different date, excludeSameDayConfirmed = true -> should NOT exclude driver
    $matchedDiffDate = $this->matcher->buildQuery(
        filters: $filters,
        excludeSameDayConfirmed: true,
        scheduledAt: '2026-10-16 10:00:00'
    )->pluck('id')->all();

    expect($matchedDiffDate)->toContain($driver->id);

    // If order1 was CANCELLED -> should NOT exclude driver
    $order1->update(['status' => WorkOrderStatus::CANCELLED]);

    $matchedCancelledOrder = $this->matcher->buildQuery(
        filters: $filters,
        excludeSameDayConfirmed: true,
        scheduledAt: '2026-10-15 14:00:00'
    )->pluck('id')->all();

    expect($matchedCancelledOrder)->toContain($driver->id);
});

test('POST /work-orders/match-preview returns count and first 20 drivers with no N+1 queries', function () {
    $user = User::factory()->create();

    // Create 25 active drivers with pickup answer
    for ($i = 0; $i < 25; $i++) {
        $d = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
        DriverAnswer::create([
            'driver_id' => $d->id,
            'question_id' => $this->qVehicle->id,
            'question_version_id' => $this->vVehicle->id,
            'option_id' => $this->optPickup->id,
            'is_current' => true,
            'is_skipped' => false,
            'answered_at' => now(),
        ]);
    }

    DB::enableQueryLog();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/work-orders/match-preview', [
        'filters' => [
            [
                'question_id' => $this->qVehicle->id,
                'operator' => 'eq',
                'value' => 'pickup',
            ],
        ],
    ]);

    $queriesCount = count(DB::getQueryLog());

    $response->assertOk()
        ->assertJsonPath('data.count', 25)
        ->assertJsonCount(20, 'data.drivers');

    // Ensure query count is constant regardless of driver count (eager loaded relationships, no N+1)
    expect($queriesCount)->toBeLessThan(12);
});

test('POST /work-orders/match-preview rejects non-filter question with 422', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/work-orders/match-preview', [
        'filters' => [
            [
                'question_id' => $this->qColor->id, // is_filter = false
                'operator' => 'eq',
                'value' => 'blue',
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('filters.0.question_id');
});

test('DriverMatcher matches drivers using whatsapp_group filter with eq and in', function () {
    $group1 = WhatsAppGroup::factory()->create(['name' => 'جروب القاهرة']);
    $group2 = WhatsAppGroup::factory()->create(['name' => 'جروب الإسكندرية']);
    $group3 = WhatsAppGroup::factory()->create(['name' => 'جروب الدلتا']);

    $qGroup = Question::where('key', 'whatsapp_group')->firstOrFail();

    $driver1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driver1->whatsappGroups()->attach($group1->id);

    $driver2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driver2->whatsappGroups()->attach($group2->id);

    $driver3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $driver3->whatsappGroups()->attach($group3->id);

    // Test single group match (eq)
    $matched = $this->matcher->buildQuery([
        [
            'question_id' => $qGroup->id,
            'operator' => 'eq',
            'value' => (string) $group1->id,
        ],
    ])->pluck('id')->all();

    expect($matched)->toContain($driver1->id)
        ->and($matched)->not->toContain($driver2->id)
        ->and($matched)->not->toContain($driver3->id);

    // Test multi-group match (in)
    $matchedMulti = $this->matcher->buildQuery([
        [
            'question_id' => $qGroup->id,
            'operator' => 'in',
            'value' => [(string) $group1->id, (string) $group2->id],
        ],
    ])->pluck('id')->all();

    expect($matchedMulti)->toContain($driver1->id)
        ->and($matchedMulti)->toContain($driver2->id)
        ->and($matchedMulti)->not->toContain($driver3->id);
});

test('DriverMatcher matches drivers using driver_experience filter with new_only and experienced_only', function () {
    $qExp = Question::where('key', 'driver_experience')->firstOrFail();

    // New driver (0 trips)
    $newDriver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverMetric::create([
        'driver_id' => $newDriver->id,
        'trips_completed' => 0,
    ]);

    // Experienced driver (5 trips)
    $expDriver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverMetric::create([
        'driver_id' => $expDriver->id,
        'trips_completed' => 5,
    ]);

    // Match new_only
    $matchedNew = $this->matcher->buildQuery([
        [
            'question_id' => $qExp->id,
            'operator' => 'eq',
            'value' => 'new_only',
        ],
    ])->pluck('id')->all();

    expect($matchedNew)->toContain($newDriver->id)
        ->and($matchedNew)->not->toContain($expDriver->id);

    // Match experienced_only
    $matchedExp = $this->matcher->buildQuery([
        [
            'question_id' => $qExp->id,
            'operator' => 'eq',
            'value' => 'experienced_only',
        ],
    ])->pluck('id')->all();

    expect($matchedExp)->toContain($expDriver->id)
        ->and($matchedExp)->not->toContain($newDriver->id);
});
