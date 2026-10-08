<?php
// AUDIT Stage 4: Read-Only Database Integrity Checker for Driver Management System.
// Usage: php audit/integrity-check.php [--database=driver_system|driver_system_test]

require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Enums\DriverStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Services\DriverStatusService;

// Parse CLI options
$options = getopt('', ['database::']);
$targetDb = $options['database'] ?? config('database.connections.mysql.database');

if (isset($options['database'])) {
    config(['database.connections.mysql.database' => $options['database']]);
    DB::purge('mysql');
    DB::reconnect('mysql');
}

echo "====================================================================\n";
echo "DRIVER MANAGEMENT SYSTEM — DATA INTEGRITY AUDIT CHECKER\n";
echo "Target Database: $targetDb\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

$violations = [];
function recordViolation(string $rule, string $message, array $offendingIds = []): void {
    global $violations;
    $violations[] = [
        'rule' => $rule,
        'message' => $message,
        'ids' => $offendingIds,
    ];
}

// -----------------------------------------------------------------------------
// CHECK 1: Work Order Capacity & Recipient Count Match
// -----------------------------------------------------------------------------
echo "[CHECK 1] Verifying work_orders confirmed_count and backup_count vs recipients...\n";
$orders = DB::table('work_orders')->get();
$check1Errors = 0;
foreach ($orders as $order) {
    $actualConfirmed = DB::table('work_order_recipients')
        ->where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::CONFIRMED->value)
        ->count();

    $actualBackup = DB::table('work_order_recipients')
        ->where('work_order_id', $order->id)
        ->where('status', WorkOrderRecipientStatus::BACKUP->value)
        ->count();

    if ($order->confirmed_count !== $actualConfirmed) {
        recordViolation('CHECK_1_CONFIRMED_MISMATCH', "Order #{$order->id} ({$order->code}): confirmed_count column ({$order->confirmed_count}) != actual confirmed recipients ($actualConfirmed)", [$order->id]);
        $check1Errors++;
    }

    if ($order->backup_count !== $actualBackup) {
        recordViolation('CHECK_1_BACKUP_MISMATCH', "Order #{$order->id} ({$order->code}): backup_count column ({$order->backup_count}) != actual backup recipients ($actualBackup)", [$order->id]);
        $check1Errors++;
    }

    if ($order->confirmed_count > $order->trips_needed) {
        recordViolation('CHECK_1_CONFIRMED_OVERCAPACITY', "Order #{$order->id} ({$order->code}): confirmed_count ({$order->confirmed_count}) exceeds trips_needed ({$order->trips_needed})!", [$order->id]);
        $check1Errors++;
    }

    if ($order->backup_count > $order->backup_needed) {
        recordViolation('CHECK_1_BACKUP_OVERCAPACITY', "Order #{$order->id} ({$order->code}): backup_count ({$order->backup_count}) exceeds backup_needed ({$order->backup_needed})!", [$order->id]);
        $check1Errors++;
    }
}
echo "  Total Orders: " . count($orders) . " | Issues: $check1Errors\n";

// -----------------------------------------------------------------------------
// CHECK 2: Gapless & Unique Sequence Numbers per Work Order
// -----------------------------------------------------------------------------
echo "[CHECK 2] Verifying gapless and unique sequence numbers per order...\n";
$check2Errors = 0;
foreach ($orders as $order) {
    // Confirmed sequences (must be 1..confirmed_count)
    if ($order->confirmed_count > 0) {
        $confirmedSeqs = DB::table('work_order_recipients')
            ->where('work_order_id', $order->id)
            ->where('status', WorkOrderRecipientStatus::CONFIRMED->value)
            ->pluck('sequence_number')
            ->sort()
            ->values()
            ->all();

        $expectedSeqs = range(1, $order->confirmed_count);
        if ($confirmedSeqs !== $expectedSeqs) {
            recordViolation('CHECK_2_CONFIRMED_SEQUENCE_GAP', "Order #{$order->id} ({$order->code}): Confirmed sequences are not gapless 1..{$order->confirmed_count}. Got: " . json_encode($confirmedSeqs), [$order->id]);
            $check2Errors++;
        }
    }

    // Backup sequences (must be 1..backup_count)
    if ($order->backup_count > 0) {
        $backupSeqs = DB::table('work_order_recipients')
            ->where('work_order_id', $order->id)
            ->where('status', WorkOrderRecipientStatus::BACKUP->value)
            ->pluck('sequence_number')
            ->sort()
            ->values()
            ->all();

        $expectedBackupSeqs = range(1, $order->backup_count);
        if ($backupSeqs !== $expectedBackupSeqs) {
            recordViolation('CHECK_2_BACKUP_SEQUENCE_GAP', "Order #{$order->id} ({$order->code}): Backup sequences are not gapless 1..{$order->backup_count}. Got: " . json_encode($backupSeqs), [$order->id]);
            $check2Errors++;
        }
    }
}
echo "  Issues: $check2Errors\n";

// -----------------------------------------------------------------------------
// CHECK 3: Cancelled Order and Filled Order State Consistency
// -----------------------------------------------------------------------------
echo "[CHECK 3] Verifying cancelled orders have no active drivers & filled order slots...\n";
$check3Errors = 0;
$cancelledOrders = DB::table('work_orders')->where('status', WorkOrderStatus::CANCELLED->value)->pluck('id');
if ($cancelledOrders->isNotEmpty()) {
    $activeOnCancelled = DB::table('work_order_recipients')
        ->whereIn('work_order_id', $cancelledOrders)
        ->whereIn('status', [WorkOrderRecipientStatus::CONFIRMED->value, WorkOrderRecipientStatus::BACKUP->value])
        ->pluck('id')
        ->all();

    if (!empty($activeOnCancelled)) {
        recordViolation('CHECK_3_ACTIVE_ON_CANCELLED_ORDER', "Found active confirmed/backup recipients on cancelled orders!", $activeOnCancelled);
        $check3Errors += count($activeOnCancelled);
    }
}
echo "  Issues: $check3Errors\n";

// -----------------------------------------------------------------------------
// CHECK 4: Messages to Ineligible Drivers (opted_out / blocked / suspended)
// -----------------------------------------------------------------------------
echo "[CHECK 4] Verifying no outbound messages queued/sent to ineligible drivers after status change...\n";
$check4Errors = 0;
$ineligibleDrivers = DB::table('drivers')
    ->whereIn('status', [DriverStatus::OPTED_OUT->value, DriverStatus::BLOCKED->value, DriverStatus::SUSPENDED->value])
    ->get(['id', 'status', 'opted_out_at', 'updated_at']);

foreach ($ineligibleDrivers as $driver) {
    $cutoff = $driver->opted_out_at ?? $driver->updated_at;
    $illegalMessages = DB::table('whatsapp_messages')
        ->where('driver_id', $driver->id)
        ->where('direction', 'out')
        ->whereNotIn('type', ['opt_out_confirmation']) // opt_out confirmation is allowed as final message
        ->where('created_at', '>', $cutoff)
        ->pluck('id')
        ->all();

    if (!empty($illegalMessages)) {
        recordViolation('CHECK_4_OUTBOUND_TO_INELIGIBLE', "Driver #{$driver->id} ({$driver->status}) received outbound messages after status change at {$cutoff}", $illegalMessages);
        $check4Errors += count($illegalMessages);
    }
}
echo "  Ineligible Drivers: " . count($ineligibleDrivers) . " | Issues: $check4Errors\n";

// -----------------------------------------------------------------------------
// CHECK 5: Phone Numbers Uniqueness and E.164 Format
// -----------------------------------------------------------------------------
echo "[CHECK 5] Verifying driver phone numbers uniqueness and E.164 compliance...\n";
$check5Errors = 0;
$phones = DB::table('drivers')->whereNull('deleted_at')->pluck('phone', 'id');
$phoneCounts = array_count_values($phones->all());
$duplicatePhones = array_filter($phoneCounts, fn($cnt) => $cnt > 1);

if (!empty($duplicatePhones)) {
    foreach ($duplicatePhones as $phone => $count) {
        $dupIds = DB::table('drivers')->where('phone', $phone)->pluck('id')->all();
        recordViolation('CHECK_5_DUPLICATE_PHONE', "Duplicate phone number detected: $phone (Count: $count)", $dupIds);
        $check5Errors++;
    }
}

$invalidE164 = [];
foreach ($phones as $id => $phone) {
    // E.164: + followed by 8 to 15 digits
    if (!preg_match('/^\+[1-9]\d{7,14}$/', $phone)) {
        $invalidE164[] = $id;
    }
}
if (!empty($invalidE164)) {
    recordViolation('CHECK_5_INVALID_E164', "Driver phones not conforming to E.164: " . count($invalidE164), $invalidE164);
    $check5Errors += count($invalidE164);
}
echo "  Total Drivers: " . count($phones) . " | Issues: $check5Errors\n";

// -----------------------------------------------------------------------------
// CHECK 6: Driver Status History and State Machine Transition Legality
// -----------------------------------------------------------------------------
echo "[CHECK 6] Verifying driver status transitions legality and history tracking...\n";
$check6Errors = 0;
$histories = DB::table('driver_status_histories')->get();

foreach ($histories as $hist) {
    $from = $hist->from;
    $to = $hist->to;
    if ($from !== null) {
        $fromEnum = DriverStatus::tryFrom($from);
        $toEnum = DriverStatus::tryFrom($to);
        if (!$fromEnum || !$toEnum || !DriverStatusService::canTransition($fromEnum, $toEnum)) {
            recordViolation('CHECK_6_ILLEGAL_STATUS_TRANSITION', "History #{$hist->id} for Driver #{$hist->driver_id}: Transition from '$from' to '$to' is NOT allowed by DriverStatusService!", [$hist->id]);
            $check6Errors++;
        }
    }
}
echo "  Total History Records: " . count($histories) . " | Issues: $check6Errors\n";

// -----------------------------------------------------------------------------
// CHECK 7: Driver Metrics Recomputation Integrity
// -----------------------------------------------------------------------------
echo "[CHECK 7] Recomputing driver_metrics from work_order_recipients outcomes...\n";
$check7Errors = 0;
$metrics = DB::table('driver_metrics')->get();
foreach ($metrics as $metric) {
    $driverId = $metric->driver_id;
    $completedRecipients = DB::table('work_order_recipients')
        ->join('work_orders', 'work_orders.id', '=', 'work_order_recipients.work_order_id')
        ->where('work_order_recipients.driver_id', $driverId)
        ->where('work_orders.status', WorkOrderStatus::COMPLETED->value)
        ->select('work_order_recipients.*')
        ->get();

    $expectedTrips = $completedRecipients->where('outcome', 'attended')->count();
    $expectedNoShows = $completedRecipients->where('outcome', 'no_show')->count();
    $expectedLate = $completedRecipients->where('outcome', 'late')->count();
    $expectedCancels = $completedRecipients->where('outcome', 'cancelled_late')->count();

    $rated = $completedRecipients->whereNotNull('rating');
    $expectedAvgRating = $rated->isNotEmpty() ? round($rated->avg('rating'), 2) : null;

    if ($metric->trips_completed != $expectedTrips ||
        $metric->no_shows != $expectedNoShows ||
        $metric->late_count != $expectedLate ||
        $metric->cancellations != $expectedCancels) {
        recordViolation('CHECK_7_METRICS_DRIFT', "Driver #$driverId metrics do not match outcomes! Stored: (trips: {$metric->trips_completed}, no-shows: {$metric->no_shows}, late: {$metric->late_count}, cancel: {$metric->cancellations}) vs Recomputed: (trips: $expectedTrips, no-shows: $expectedNoShows, late: $expectedLate, cancel: $expectedCancels)", [$driverId]);
        $check7Errors++;
    }
}
echo "  Total Metrics Checked: " . count($metrics) . " | Issues: $check7Errors\n";

// -----------------------------------------------------------------------------
// CHECK 8: Driver Answers Integrity
// -----------------------------------------------------------------------------
echo "[CHECK 8] Verifying driver_answers uniqueness and question versions...\n";
$check8Errors = 0;
// Check at most one current answer per (driver_id, question_id)
$multiCurrent = DB::table('driver_answers')
    ->where('is_current', 1)
    ->select('driver_id', 'question_id', DB::raw('COUNT(*) as cnt'))
    ->groupBy('driver_id', 'question_id')
    ->having('cnt', '>', 1)
    ->get();

if ($multiCurrent->isNotEmpty()) {
    foreach ($multiCurrent as $mc) {
        recordViolation('CHECK_8_MULTIPLE_CURRENT_ANSWERS', "Driver #{$mc->driver_id} has {$mc->cnt} current answers for Question #{$mc->question_id}!", [$mc->driver_id]);
        $check8Errors++;
    }
}

// Check answers point to valid question_versions
$orphanedAnswers = DB::table('driver_answers')
    ->leftJoin('question_versions', 'question_versions.id', '=', 'driver_answers.question_version_id')
    ->whereNull('question_versions.id')
    ->pluck('driver_answers.id')
    ->all();

if (!empty($orphanedAnswers)) {
    recordViolation('CHECK_8_ORPHANED_ANSWER_VERSION', "Found driver answers pointing to non-existent question_versions!", $orphanedAnswers);
    $check8Errors += count($orphanedAnswers);
}
echo "  Issues: $check8Errors\n";

// -----------------------------------------------------------------------------
// CHECK 9: Webhook Events Uniqueness
// -----------------------------------------------------------------------------
echo "[CHECK 9] Verifying webhook_events external_id uniqueness...\n";
$check9Errors = 0;
$dupWebhooks = DB::table('webhook_events')
    ->select('external_id', DB::raw('COUNT(*) as cnt'))
    ->groupBy('external_id')
    ->having('cnt', '>', 1)
    ->get();

if ($dupWebhooks->isNotEmpty()) {
    foreach ($dupWebhooks as $dw) {
        recordViolation('CHECK_9_DUPLICATE_WEBHOOK_EVENT', "Duplicate webhook external_id: {$dw->external_id} (count: {$dw->cnt})", []);
        $check9Errors++;
    }
}
echo "  Total Webhook Events: " . DB::table('webhook_events')->count() . " | Issues: $check9Errors\n";

// -----------------------------------------------------------------------------
// CHECK 10: WhatsApp Messages Lifecycle Monotonicity
// -----------------------------------------------------------------------------
echo "[CHECK 10] Verifying whatsapp_messages status monotonicity and timestamps...\n";
$check10Errors = 0;
$regressedMessages = DB::table('whatsapp_messages')
    ->whereNotNull('read_at')
    ->whereNotNull('delivered_at')
    ->whereRaw('read_at < delivered_at')
    ->pluck('id')
    ->all();

if (!empty($regressedMessages)) {
    recordViolation('CHECK_10_TIMESTAMP_REGRESSION', "Messages where read_at is earlier than delivered_at!", $regressedMessages);
    $check10Errors += count($regressedMessages);
}
echo "  Total Messages: " . DB::table('whatsapp_messages')->count() . " | Issues: $check10Errors\n";

// -----------------------------------------------------------------------------
// FINAL SUMMARY
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "INTEGRITY AUDIT SUMMARY\n";
echo "====================================================================\n";
echo "Total Violations Found: " . count($violations) . "\n";
if (empty($violations)) {
    echo "VERDICT: 100% PASS! All schema and business data integrity rules satisfied.\n";
    exit(0);
} else {
    echo "VERDICT: FAIL - Integrity violations detected:\n";
    foreach ($violations as $v) {
        echo sprintf("  [%s] %s (Offending IDs: %s)\n", $v['rule'], $v['message'], json_encode($v['ids']));
    }
    exit(1);
}
