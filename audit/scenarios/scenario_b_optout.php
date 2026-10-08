<?php
// AUDIT Scenario B: Opt-out Matrix & Ineligible Messaging Safety
// Target DB: driver_system_audit

require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Driver;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Models\WhatsAppMessage;
use App\Enums\DriverStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Services\InboundMessageRouter;
use App\Services\MessageSender;
use App\DTOs\WhatsApp\InboundMessage;

config(['database.connections.mysql.database' => 'driver_system_audit']);
DB::purge('mysql');
DB::reconnect('mysql');

// Clean audit DB tables for repeatable test
DB::statement('SET FOREIGN_KEY_CHECKS=0');
DB::table('work_order_recipients')->truncate();
DB::table('work_orders')->truncate();
DB::table('whatsapp_messages')->truncate();
DB::table('driver_status_histories')->truncate();
Driver::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1');

$router = app(InboundMessageRouter::class);
$messageSender = app(MessageSender::class);

echo "====================================================================\n";
echo "STAGE 5 SCENARIO B: OPT-OUT & MESSAGING SAFETY AUDIT\n";
echo "Database: driver_system_audit\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

$results = [];
function recordStep(string $step, string $description, bool $passed, string $details): void {
    global $results;
    $results[] = compact('step', 'description', 'passed', 'details');
    echo sprintf("[%s] %-50s -> %s\n  %s\n\n",
        $step, $description, $passed ? "PASS" : "FAIL", $details);
}

// -----------------------------------------------------------------------------
// STEP 1: False Positive Matrix (Sentences with opt-out keywords that should NOT trigger opt-out)
// -----------------------------------------------------------------------------
$falsePositives = [
    'عربيتي واقفة في الورشة ومحتاج صيانة',
    'الشغل وقف النهارده بسبب المطر',
    'مش هقدر انهي الشغل بدري',
    'لو سمحت انا مش عايز الغاء الرحلة',
    'الساعة واقفة معايا ومش عارف الوقت',
    'المحطة عند إشارة قف القادمة',
    'خروج السيارة من الجراج بعد قليل',
    'ممكن توقف الرحلة خمس دقائق ونكمل؟',
];

$step1Failures = 0;
foreach ($falsePositives as $idx => $sentence) {
    $driver = Driver::create([
        'phone' => sprintf('+20110000%04d', $idx + 1),
        'name' => "Driver FP $idx",
        'status' => DriverStatus::ACTIVE,
    ]);

    $inbound = new InboundMessage(
        from: $driver->phone,
        type: 'text',
        text: $sentence,
        payloadId: null,
        providerMessageId: 'fp_msg_' . uniqid(),
        timestamp: time()
    );

    $router->handle($inbound);
    $driver->refresh();

    if ($driver->status === DriverStatus::OPTED_OUT) {
        $step1Failures++;
        echo "  [FAIL] Driver incorrectly OPTED OUT by sentence: \"$sentence\"\n";
    }
}

$step1Passed = ($step1Failures === 0);
recordStep('STEP_1', 'False-positive sentence suppression', $step1Passed,
    $step1Passed ? 'All 8 conversational sentences containing ambiguous words correctly kept driver active!' : "FAIL: $step1Failures false positives occurred!");

// -----------------------------------------------------------------------------
// STEP 2: Real Opt-Out Matrix (Standard Arabic and Latin keywords)
// -----------------------------------------------------------------------------
$realKeywords = [
    'إيقاف',
    'ايقاف',
    'stop',
    'STOP',
    'إلغاء',
    'الغاء',
    'unsubscribe',
    'اوقف',
    'قف',
    'خروج',
    'إنهاء',
    'انهاء',
];

$step2Failures = 0;
foreach ($realKeywords as $idx => $kw) {
    $driver = Driver::create([
        'phone' => sprintf('+20111111%04d', $idx + 1),
        'name' => "Driver Real $idx",
        'status' => DriverStatus::ACTIVE,
    ]);

    $inbound = new InboundMessage(
        from: $driver->phone,
        type: 'text',
        text: $kw,
        payloadId: null,
        providerMessageId: 'real_msg_' . uniqid(),
        timestamp: time()
    );

    $router->handle($inbound);
    $driver->refresh();

    if ($driver->status !== DriverStatus::OPTED_OUT) {
        $step2Failures++;
        echo "  [FAIL] Keyword failed to opt out driver: \"$kw\"\n";
    }
}

$step2Passed = ($step2Failures === 0);
recordStep('STEP_2', 'Real opt-out keyword execution', $step2Passed,
    $step2Passed ? 'All 12 keyword variations transitioned driver immediately to opted_out!' : "FAIL: $step2Failures keywords failed!");

// -----------------------------------------------------------------------------
// STEP 3: Opt-Out After Invitation
// -----------------------------------------------------------------------------
$invitedDriver = Driver::create([
    'phone' => '+201122220001',
    'name' => 'Invited Driver',
    'status' => DriverStatus::INVITED,
]);
$router->handle(new InboundMessage(
    from: $invitedDriver->phone,
    type: 'text',
    text: 'إيقاف',
    payloadId: null,
    providerMessageId: 'inv_optout_' . uniqid(),
    timestamp: time()
));
$invitedDriver->refresh();
$step3Passed = ($invitedDriver->status === DriverStatus::OPTED_OUT);
recordStep('STEP_3', 'Opt-out while in INVITED status', $step3Passed,
    "Status: {$invitedDriver->status->value} (Expected: opted_out)");

// -----------------------------------------------------------------------------
// STEP 4: Opt-Out Mid-Questionnaire
// -----------------------------------------------------------------------------
$qDriver = Driver::create([
    'phone' => '+201122220002',
    'name' => 'Questionnaire Driver',
    'status' => DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
]);
$router->handle(new InboundMessage(
    from: $qDriver->phone,
    type: 'text',
    text: 'الغاء',
    payloadId: null,
    providerMessageId: 'q_optout_' . uniqid(),
    timestamp: time()
));
$qDriver->refresh();
$step4Passed = ($qDriver->status === DriverStatus::OPTED_OUT);
recordStep('STEP_4', 'Opt-out while in QUESTIONNAIRE_IN_PROGRESS status', $step4Passed,
    "Status: {$qDriver->status->value} (Expected: opted_out)");

// -----------------------------------------------------------------------------
// STEP 5: Opt-Out After Receiving Work Order Offer
// -----------------------------------------------------------------------------
$woDriver = Driver::create([
    'phone' => '+201122220003',
    'name' => 'Work Order Driver',
    'status' => DriverStatus::ACTIVE,
]);
$order = WorkOrder::create([
    'code' => 'WO-OPT-01',
    'from_location' => 'القاهرة',
    'to_location' => 'السويس',
    'scheduled_at' => now()->addDay(),
    'trips_needed' => 5,
    'backup_needed' => 2,
    'status' => WorkOrderStatus::FILLING,
]);
$recipient = WorkOrderRecipient::create([
    'work_order_id' => $order->id,
    'driver_id' => $woDriver->id,
    'status' => WorkOrderRecipientStatus::SENT,
    'sent_at' => now(),
]);

$router->handle(new InboundMessage(
    from: $woDriver->phone,
    type: 'text',
    text: 'stop',
    payloadId: null,
    providerMessageId: 'wo_optout_' . uniqid(),
    timestamp: time()
));
$woDriver->refresh();
$step5Passed = ($woDriver->status === DriverStatus::OPTED_OUT);
recordStep('STEP_5', 'Opt-out after receiving Work Order offer', $step5Passed,
    "Driver status: {$woDriver->status->value} (Expected: opted_out)");

// -----------------------------------------------------------------------------
// STEP 6: Verify MessageSender Refuses Outgoing Messages to Opted-Out Driver
// -----------------------------------------------------------------------------
$blockedExceptions = 0;
try {
    $messageSender->sendText($woDriver, 'رسالة تجريبية ممنوعة');
} catch (\Throwable $e) {
    $blockedExceptions++;
}

try {
    $messageSender->sendTemplate($woDriver, 'trip_reminder', 'ar', ['كابتن', 'WO-999', '2026-10-04']);
} catch (\Throwable $e) {
    $blockedExceptions++;
}

$step6Passed = ($blockedExceptions === 2);
recordStep('STEP_6', 'MessageSender blocks outbound sends to opted_out driver', $step6Passed,
    "Attempted text and template sends: $blockedExceptions blocked with exception (Expected: 2)");

// -----------------------------------------------------------------------------
// FINAL SUMMARY
// -----------------------------------------------------------------------------
$allPassed = !in_array(false, array_column($results, 'passed'), true);
echo "====================================================================\n";
echo "SCENARIO B AUDIT RESULT: " . ($allPassed ? "100% PASS" : "FAIL") . "\n";
echo "====================================================================\n";
exit($allPassed ? 0 : 1);
