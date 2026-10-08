<?php

use App\Enums\DriverStatus;
use App\Models\Driver;
use App\Models\DriverMetric;
use App\Services\DriverMatcher;
use Carbon\Carbon;

test('DriverMatcher min_avg_rating excludes unrated drivers by default and includes them when include_unrated is true', function () {
    $matcher = app(DriverMatcher::class);

    // Driver 1: rated 4.5
    $d1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverMetric::create(['driver_id' => $d1->id, 'avg_rating' => 4.5, 'ratings_count' => 5]);

    // Driver 2: rated 3.5
    $d2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverMetric::create(['driver_id' => $d2->id, 'avg_rating' => 3.5, 'ratings_count' => 2]);

    // Driver 3: unrated (has metric row but null avg_rating)
    $d3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverMetric::create(['driver_id' => $d3->id, 'avg_rating' => null]);

    // Driver 4: new driver with no driver_metrics record at all
    $d4 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    // Query with min_avg_rating = 4.0 and include_unrated = false (default)
    $matchedStrict = $matcher->buildQuery(
        minAvgRating: 4.0,
        includeUnrated: false
    )->pluck('id')->all();

    expect($matchedStrict)->toContain($d1->id)
        ->and($matchedStrict)->not->toContain($d2->id)
        ->and($matchedStrict)->not->toContain($d3->id)
        ->and($matchedStrict)->not->toContain($d4->id);

    // Query with min_avg_rating = 4.0 and include_unrated = true
    $matchedPermissive = $matcher->buildQuery(
        minAvgRating: 4.0,
        includeUnrated: true
    )->pluck('id')->all();

    expect($matchedPermissive)->toContain($d1->id)
        ->and($matchedPermissive)->toContain($d3->id)
        ->and($matchedPermissive)->toContain($d4->id)
        ->and($matchedPermissive)->not->toContain($d2->id); // 3.5 still rejected
});

test('DriverMatcher max_no_shows treats drivers with no history as eligible (0 no-shows)', function () {
    $matcher = app(DriverMatcher::class);

    // Driver 1: 0 no-shows
    $d1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverMetric::create(['driver_id' => $d1->id, 'no_shows' => 0]);

    // Driver 2: 2 no-shows
    $d2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverMetric::create(['driver_id' => $d2->id, 'no_shows' => 2]);

    // Driver 3: no history / no metrics row
    $d3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $matched = $matcher->buildQuery(maxNoShows: 1)->pluck('id')->all();

    expect($matched)->toContain($d1->id)
        ->and($matched)->toContain($d3->id) // Eligible (0 no-shows)
        ->and($matched)->not->toContain($d2->id); // 2 > 1 rejected
});

test('DriverMatcher last_trip_within_days matches only drivers active within specified window', function () {
    $matcher = app(DriverMatcher::class);

    // Driver 1: last trip 5 days ago
    $d1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverMetric::create(['driver_id' => $d1->id, 'last_trip_at' => Carbon::now()->subDays(5)]);

    // Driver 2: last trip 40 days ago
    $d2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    DriverMetric::create(['driver_id' => $d2->id, 'last_trip_at' => Carbon::now()->subDays(40)]);

    // Driver 3: never took a trip (null)
    $d3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);

    $matched = $matcher->buildQuery(lastTripWithinDays: 30)->pluck('id')->all();

    expect($matched)->toContain($d1->id)
        ->and($matched)->not->toContain($d2->id)
        ->and($matched)->not->toContain($d3->id);
});

test('DriverMatcher target_group_id and driver_experience filter properly', function () {
    $matcher = app(DriverMatcher::class);

    $groupA = \App\Models\WhatsappGroup::factory()->create(['name' => 'Group Tenth']);
    $groupB = \App\Models\WhatsappGroup::factory()->create(['name' => 'Group October']);

    // Driver 1: in Group A, experienced (5 trips)
    $d1 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d1->whatsappGroups()->attach($groupA->id, ['first_seen_at' => now()]);
    DriverMetric::create(['driver_id' => $d1->id, 'trips_completed' => 5]);

    // Driver 2: in Group A, new (0 trips)
    $d2 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d2->whatsappGroups()->attach($groupA->id, ['first_seen_at' => now()]);
    DriverMetric::create(['driver_id' => $d2->id, 'trips_completed' => 0]);

    // Driver 3: in Group B, experienced (10 trips)
    $d3 = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $d3->whatsappGroups()->attach($groupB->id, ['first_seen_at' => now()]);
    DriverMetric::create(['driver_id' => $d3->id, 'trips_completed' => 10]);

    // Filter Group A only
    $matchedGroupA = $matcher->buildQuery(targetGroupId: $groupA->id)->pluck('id')->all();
    expect($matchedGroupA)->toContain($d1->id)
        ->and($matchedGroupA)->toContain($d2->id)
        ->and($matchedGroupA)->not->toContain($d3->id);

    // Filter Group A + new_only
    $matchedGroupANew = $matcher->buildQuery(targetGroupId: $groupA->id, driverExperience: 'new_only')->pluck('id')->all();
    expect($matchedGroupANew)->toContain($d2->id)
        ->and($matchedGroupANew)->not->toContain($d1->id)
        ->and($matchedGroupANew)->not->toContain($d3->id);

    // Filter Group A + experienced_only
    $matchedGroupAExp = $matcher->buildQuery(targetGroupId: $groupA->id, driverExperience: 'experienced_only')->pluck('id')->all();
    expect($matchedGroupAExp)->toContain($d1->id)
        ->and($matchedGroupAExp)->not->toContain($d2->id)
        ->and($matchedGroupAExp)->not->toContain($d3->id);
});
