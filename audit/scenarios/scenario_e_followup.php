<?php
// AUDIT Scenario E: Post-Fill Follow-Up, Rejection, Swap & Rating Engine
// Target DB: driver_system_audit

require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Driver;
use App\Models\DriverMetric;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Models\WorkOrderEvent;
use App\Enums\DriverStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderOutcome;
use App\Services\WorkOrderFollowUpService;
use App\Services\DriverMatcher;
use App\Services\DriverMetricsService;

config(['database.connections.mysql.database' => 'driver_system_audit']);
DB::purge('mysql');
DB::reconnect('mysql');

echo "====================================================================\n";
echo "STAGE 5 SCENARIO E: POST-FILL FOLLOW-UP & RATINGS AUDIT\n";
echo "Database: driver_system_audit\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

$results = [];
function recordStep(string $step, string $description, bool $passed, string $details): void {
    global $results;
    $results[] = compact('step', 'description', 'passed', 'details');
    echo sprintf("[%s] %-55s -> %s\n  %s\n\n",
        $step, $description, $passed ? "PASS" : "FAIL", $details);
}

// Clean relevant tables
DB::statement('SET FOREIGN_KEY_CHECKS=0');
WorkOrderEvent::truncate();
WorkOrderRecipient::truncate();
WorkOrder::truncate();
DriverMetric::truncate();
DB::table('driver_status_histories')->truncate();
Driver::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1');

$followUp = app(WorkOrderFollowUpService::class);
$matcher = app(DriverMatcher::class);
$metricsService = app(DriverMetricsService::class);

// Create drivers
$d1 = Driver::create(['phone' => '+201155550001', 'name' => 'D1 Confirmed', 'status' => DriverStatus::ACTIVE]);
$d2 = Driver::create(['phone' => '+201155550002', 'name' => 'D2 Confirmed', 'status' => DriverStatus::ACTIVE]);
$d3 = Driver::create(['phone' => '+201155550003', 'name' => 'D3 Backup', 'status' => DriverStatus::ACTIVE]);
$d4 = Driver::create(['phone' => '+201155550004', 'name' => 'D4 Manual', 'status' => DriverStatus::ACTIVE]);

// Create order: trips_needed=2, backup_needed=1
$order = WorkOrder::create([
    'code' => 'WO-FOLLOWUP-01',
    'from_location' => 'مدينة نصر',
    'to_location' => 'العاشر من رمضان',
    'scheduled_at' => now()->addDays(2),
    'trips_needed' => 2,
    'backup_needed' => 1,
    'confirmed_count' => 2,
    'backup_count' => 1,
    'status' => WorkOrderStatus::FILLED,
]);

$r1 = WorkOrderRecipient::create([
    'work_order_id' => $order->id,
    'driver_id' => $d1->id,
    'status' => WorkOrderRecipientStatus::CONFIRMED,
    'sequence_number' => 1,
    'responded_at' => now(),
]);
$r2 = WorkOrderRecipient::create([
    'work_order_id' => $order->id,
    'driver_id' => $d2->id,
    'status' => WorkOrderRecipientStatus::CONFIRMED,
    'sequence_number' => 2,
    'responded_at' => now(),
]);
$r3 = WorkOrderRecipient::create([
    'work_order_id' => $order->id,
    'driver_id' => $d3->id,
    'status' => WorkOrderRecipientStatus::BACKUP,
    'sequence_number' => 1,
    'responded_at' => now(),
]);

// -----------------------------------------------------------------------------
// STEP 1: Remove Confirmed Driver -> Open Slot Created, NO Auto-Promote
// -----------------------------------------------------------------------------
$followUp->removeConfirmedDriver($order, $r1, 'عطل مفاجئ في السيارة');
$order->refresh();
$r1->refresh();
$r3->refresh();

// D1 removed -> cancelled, order confirmed_count = 1, D3 remains BACKUP (not auto-promoted)
$step1Passed = (
    $order->confirmed_count === 1 &&
    $r1->status === WorkOrderRecipientStatus::CANCELLED &&
    $r3->status === WorkOrderRecipientStatus::BACKUP
);
recordStep('STEP_1', 'Remove confirmed driver -> slot opens, NO auto-promote of backup', $step1Passed,
    "Confirmed count: {$order->confirmed_count}/2, D1 status: {$r1->status->value}, D3 backup status: {$r3->status->value}");

// -----------------------------------------------------------------------------
// STEP 2: Promote Backup Driver into Open Slot
// -----------------------------------------------------------------------------
$promotedRecipient = $followUp->promoteBackup($order, $r3->id);
$order->refresh();
$r3->refresh();

$step2Passed = (
    $order->confirmed_count === 2 &&
    $order->backup_count === 0 &&
    $r3->status === WorkOrderRecipientStatus::CONFIRMED &&
    $r3->sequence_number === 2
);
recordStep('STEP_2', 'Promote backup driver with capacity check & gapless sequence', $step2Passed,
    "Confirmed count: {$order->confirmed_count}/2, Backup count: {$order->backup_count}, D3 status: {$r3->status->value}, D3 seq: {$r3->sequence_number}");

// -----------------------------------------------------------------------------
// STEP 3: Add Driver Manually as Backup
// -----------------------------------------------------------------------------
$manualRecipient = $followUp->addDriverManually($order, $d4->id, 'backup');
$order->refresh();

$step3Passed = (
    $order->backup_count === 1 &&
    $manualRecipient->status === WorkOrderRecipientStatus::BACKUP &&
    $manualRecipient->sequence_number === 1
);
recordStep('STEP_3', 'Add driver manually as backup', $step3Passed,
    "Backup count: {$order->backup_count}/1, D4 status: {$manualRecipient->status->value}, D4 seq: {$manualRecipient->sequence_number}");

// -----------------------------------------------------------------------------
// STEP 4: Status Lifecycle: FILLED -> IN_PROGRESS -> COMPLETED
// -----------------------------------------------------------------------------
$followUp->changeOrderStatus($order, WorkOrderStatus::IN_PROGRESS);
$order->refresh();
$status1 = $order->status;

$followUp->changeOrderStatus($order, WorkOrderStatus::COMPLETED);
$order->refresh();
$status2 = $order->status;

$step4Passed = ($status1 === WorkOrderStatus::IN_PROGRESS && $status2 === WorkOrderStatus::COMPLETED);
recordStep('STEP_4', 'Status lifecycle transition (FILLED -> IN_PROGRESS -> COMPLETED)', $step4Passed,
    "Intermediate status: {$status1->value}, Final status: {$status2->value}");

// -----------------------------------------------------------------------------
// STEP 5: Record Outcomes & Recalculate Driver Metrics
// -----------------------------------------------------------------------------
// D2 completed OK with 5-star rating, D3 was NO_SHOW
$followUp->recordOutcomes($order, [
    [
        'recipient_id' => $r2->id,
        'outcome' => 'attended',
        'rating' => 5,
        'notes' => 'سائق ممتاز وملتزم جدا',
    ],
    [
        'recipient_id' => $r3->id,
        'outcome' => 'no_show',
        'rating' => null,
        'notes' => 'لم يحضر في موعد الرحلة',
    ],
]);

// Recalculate metrics for both drivers
$metricsService->recalculateForDriver($d2->id);
$metricsService->recalculateForDriver($d3->id);

$m2 = DriverMetric::where('driver_id', $d2->id)->first();
$m3 = DriverMetric::where('driver_id', $d3->id)->first();

$step5Passed = (
    $m2 !== null &&
    (float)$m2->avg_rating === 5.0 &&
    $m2->trips_completed >= 1 &&
    $m3 !== null &&
    $m3->no_shows >= 1
);
recordStep('STEP_5', 'Record outcomes & recalculate driver metrics (rating + no_shows)', $step5Passed,
    "D2 avg_rating: {$m2?->avg_rating}, trips_completed: {$m2?->trips_completed} | D3 no_shows: {$m3?->no_shows}");

// -----------------------------------------------------------------------------
// STEP 6: System Filters in Matcher (min_avg_rating and max_no_shows)
// -----------------------------------------------------------------------------
// Query 1: min_avg_rating = 4.0 -> D2 matches, D3 (unrated or no_show) does not
$matchedHighRating = $matcher->buildQuery(
    minAvgRating: 4.0,
    includeUnrated: false
)->pluck('id')->all();

// Query 2: max_no_shows = 0 -> D2 matches (0 no shows), D3 excluded (1 no show)
$matchedNoShows = $matcher->buildQuery(
    maxNoShows: 0
)->pluck('id')->all();

$step6Passed = (
    in_array($d2->id, $matchedHighRating, true) &&
    !in_array($d3->id, $matchedHighRating, true) &&
    in_array($d2->id, $matchedNoShows, true) &&
    !in_array($d3->id, $matchedNoShows, true)
);
recordStep('STEP_6', 'System filters (min_avg_rating and max_no_shows) in Matcher', $step6Passed,
    "Min rating 4.0: " . implode(',', $matchedHighRating) . " (D2 present, D3 excluded) | Max no-shows 0: " . implode(',', $matchedNoShows) . " (D3 excluded)");

// -----------------------------------------------------------------------------
// FINAL SUMMARY
// -----------------------------------------------------------------------------
$allPassed = !in_array(false, array_column($results, 'passed'), true);
echo "====================================================================\n";
echo "SCENARIO E AUDIT RESULT: " . ($allPassed ? "100% PASS" : "FAIL") . "\n";
echo "====================================================================\n";
exit($allPassed ? 0 : 1);
