<?php
// AUDIT Scenario D: Work Order Lifecycle, Staged Matcher, and Concurrency Under Edge Cases
// Target DB: driver_system_audit

require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\Driver;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\DriverAnswer;
use App\Models\WorkOrder;
use App\Models\WorkOrderFilter;
use App\Models\WorkOrderRecipient;
use App\Models\WhatsAppMessage;
use App\Enums\DriverStatus;
use App\Enums\QuestionType;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Services\DriverMatcher;
use App\Services\WorkOrderService;
use App\Services\AcceptOrderOffer;
use App\Services\InboundMessageRouter;
use App\DTOs\WhatsApp\InboundMessage;
use App\Jobs\DispatchStagedWorkOrderBatchJob;

config(['database.connections.mysql.database' => 'driver_system_audit']);
DB::purge('mysql');
DB::reconnect('mysql');

echo "====================================================================\n";
echo "STAGE 5 SCENARIO D: WORK ORDER LIFECYCLE & EDGE CASES AUDIT\n";
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
WorkOrderRecipient::truncate();
WorkOrderFilter::truncate();
WorkOrder::truncate();
DriverAnswer::truncate();
QuestionOption::truncate();
QuestionVersion::truncate();
Question::truncate();
DB::table('whatsapp_messages')->truncate();
DB::table('driver_status_histories')->truncate();
Driver::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1');

$matcher = app(DriverMatcher::class);
$woService = app(WorkOrderService::class);
$acceptService = app(AcceptOrderOffer::class);
$router = app(InboundMessageRouter::class);

// Setup questions with is_filter = true
$qVehicle = Question::create(['key' => 'vehicle_type', 'type' => QuestionType::SINGLE_CHOICE, 'order' => 1, 'is_active' => true, 'is_filter' => true]);
$optSedan = QuestionOption::create(['question_id' => $qVehicle->id, 'value' => 'sedan', 'label_ar' => 'سيدان', 'order' => 1]);
$optSuv = QuestionOption::create(['question_id' => $qVehicle->id, 'value' => 'suv', 'label_ar' => 'دفع رباعي', 'order' => 2]);
$vVehicle = $qVehicle->recordVersion('vehicle_type', QuestionType::SINGLE_CHOICE, [
    ['id' => $optSedan->id, 'value' => 'sedan', 'label_ar' => 'سيدان'],
    ['id' => $optSuv->id, 'value' => 'suv', 'label_ar' => 'دفع رباعي'],
]);

$qGov = Question::create(['key' => 'governorate', 'type' => QuestionType::SINGLE_CHOICE, 'order' => 2, 'is_active' => true, 'is_filter' => true]);
$optCairo = QuestionOption::create(['question_id' => $qGov->id, 'value' => 'cairo', 'label_ar' => 'القاهرة', 'order' => 1]);
$optGiza = QuestionOption::create(['question_id' => $qGov->id, 'value' => 'giza', 'label_ar' => 'الجيزة', 'order' => 2]);
$optAlex = QuestionOption::create(['question_id' => $qGov->id, 'value' => 'alex', 'label_ar' => 'الإسكندرية', 'order' => 3]);
$vGov = $qGov->recordVersion('governorate', QuestionType::SINGLE_CHOICE, [
    ['id' => $optCairo->id, 'value' => 'cairo', 'label_ar' => 'القاهرة'],
    ['id' => $optGiza->id, 'value' => 'giza', 'label_ar' => 'الجيزة'],
    ['id' => $optAlex->id, 'value' => 'alex', 'label_ar' => 'الإسكندرية'],
]);

// -----------------------------------------------------------------------------
// STEP 1: Matcher AND/OR Evaluation & Skipped Answer Exclusion
// -----------------------------------------------------------------------------
// Driver 1: Sedan + Cairo
$d1 = Driver::create(['phone' => '+201144440001', 'name' => 'D1 Sedan Cairo', 'status' => DriverStatus::ACTIVE]);
DriverAnswer::create(['driver_id' => $d1->id, 'question_id' => $qVehicle->id, 'question_version_id' => $vVehicle->id, 'option_id' => $optSedan->id, 'value_text' => 'sedan', 'is_current' => true]);
DriverAnswer::create(['driver_id' => $d1->id, 'question_id' => $qGov->id, 'question_version_id' => $vGov->id, 'option_id' => $optCairo->id, 'value_text' => 'cairo', 'is_current' => true]);

// Driver 2: SUV + Cairo (Does not match Sedan)
$d2 = Driver::create(['phone' => '+201144440002', 'name' => 'D2 SUV Cairo', 'status' => DriverStatus::ACTIVE]);
DriverAnswer::create(['driver_id' => $d2->id, 'question_id' => $qVehicle->id, 'question_version_id' => $vVehicle->id, 'option_id' => $optSuv->id, 'value_text' => 'suv', 'is_current' => true]);
DriverAnswer::create(['driver_id' => $d2->id, 'question_id' => $qGov->id, 'question_version_id' => $vGov->id, 'option_id' => $optCairo->id, 'value_text' => 'cairo', 'is_current' => true]);

// Driver 3: Sedan + Giza (Matches Sedan AND [Cairo OR Giza])
$d3 = Driver::create(['phone' => '+201144440003', 'name' => 'D3 Sedan Giza', 'status' => DriverStatus::ACTIVE]);
DriverAnswer::create(['driver_id' => $d3->id, 'question_id' => $qVehicle->id, 'question_version_id' => $vVehicle->id, 'option_id' => $optSedan->id, 'value_text' => 'sedan', 'is_current' => true]);
DriverAnswer::create(['driver_id' => $d3->id, 'question_id' => $qGov->id, 'question_version_id' => $vGov->id, 'option_id' => $optGiza->id, 'value_text' => 'giza', 'is_current' => true]);

// Driver 4: Skipped vehicle question + Cairo (Must NOT match)
$d4 = Driver::create(['phone' => '+201144440004', 'name' => 'D4 Skipped Answer', 'status' => DriverStatus::ACTIVE]);
DriverAnswer::create(['driver_id' => $d4->id, 'question_id' => $qVehicle->id, 'question_version_id' => $vVehicle->id, 'option_id' => null, 'value_text' => 'skipped', 'is_current' => true, 'is_skipped' => true]);
DriverAnswer::create(['driver_id' => $d4->id, 'question_id' => $qGov->id, 'question_version_id' => $vGov->id, 'option_id' => $optCairo->id, 'value_text' => 'cairo', 'is_current' => true]);

// Driver 5: Missing vehicle answer + Cairo (Must NOT match)
$d5 = Driver::create(['phone' => '+201144440005', 'name' => 'D5 Missing Answer', 'status' => DriverStatus::ACTIVE]);
DriverAnswer::create(['driver_id' => $d5->id, 'question_id' => $qGov->id, 'question_version_id' => $vGov->id, 'option_id' => $optCairo->id, 'value_text' => 'cairo', 'is_current' => true]);

// Filter: vehicle_type IN ['sedan'] AND governorate IN ['cairo', 'giza']
$filterSpecs = [
    ['question_id' => $qVehicle->id, 'operator' => 'in', 'value' => ['sedan']],
    ['question_id' => $qGov->id, 'operator' => 'in', 'value' => ['cairo', 'giza']],
];

$matchedIds = $matcher->buildQuery($filterSpecs)->pluck('id')->all();
sort($matchedIds);
$expectedIds = [$d1->id, $d3->id];
sort($expectedIds);

$step1Passed = ($matchedIds === $expectedIds);
recordStep('STEP_1', 'Matcher AND/OR logic & skipped/missing answer exclusion', $step1Passed,
    "Matched Driver IDs: " . implode(',', $matchedIds) . " (Expected: " . implode(',', $expectedIds) . ")");

// -----------------------------------------------------------------------------
// STEP 2: Staged Sending Stops Automatically When Filled
// -----------------------------------------------------------------------------
$stagedOrder = WorkOrder::create([
    'code' => 'WO-STAGED-01',
    'from_location' => 'مدينة نصر',
    'to_location' => 'التجمع',
    'scheduled_at' => now()->addDays(2),
    'trips_needed' => 1,
    'backup_needed' => 0,
    'status' => WorkOrderStatus::FILLING,
    'send_strategy' => 'staged',
    'batch_size' => 1,
]);

// Batch 1: Driver 1 (SENT)
$rBatch1 = WorkOrderRecipient::create([
    'work_order_id' => $stagedOrder->id,
    'driver_id' => $d1->id,
    'status' => WorkOrderRecipientStatus::SENT,
    'stage_batch' => 1,
    'sent_at' => now(),
]);

// Batch 2: Driver 3 (PENDING)
$rBatch2 = WorkOrderRecipient::create([
    'work_order_id' => $stagedOrder->id,
    'driver_id' => $d3->id,
    'status' => WorkOrderRecipientStatus::PENDING,
    'stage_batch' => 2,
]);

// Driver 1 accepts -> Order fills (trips_needed=1 filled)
$acceptService->handle($stagedOrder->id, $d1->id, 'accept');
$stagedOrder->refresh();

// Trigger Batch 2 job
$batchJob = new DispatchStagedWorkOrderBatchJob($stagedOrder->id, 2);
$batchJob->handle(app(\App\Services\MessageSender::class));

$rBatch2->refresh();
$step2Passed = ($stagedOrder->status === WorkOrderStatus::FILLED && $rBatch2->status === WorkOrderRecipientStatus::CANCELLED);
recordStep('STEP_2', 'Staged sending stops when order filled -> pending batch cancelled', $step2Passed,
    "Order status: {$stagedOrder->status->value}, Batch 2 recipient status: {$rBatch2->status->value} (Expected: cancelled)");

// -----------------------------------------------------------------------------
// STEP 3: Ambiguity Handling (Driver with 2 Open Offers Typing Free-Text)
// -----------------------------------------------------------------------------
$multiOrder1 = WorkOrder::create([
    'code' => 'WO-MULTI-01',
    'from_location' => 'المعادي',
    'to_location' => 'أكتوبر',
    'scheduled_at' => now()->addDays(3),
    'trips_needed' => 2,
    'backup_needed' => 1,
    'status' => WorkOrderStatus::FILLING,
]);
$multiOrder2 = WorkOrder::create([
    'code' => 'WO-MULTI-02',
    'from_location' => 'شبرا',
    'to_location' => 'الرحاب',
    'scheduled_at' => now()->addDays(3),
    'trips_needed' => 2,
    'backup_needed' => 1,
    'status' => WorkOrderStatus::FILLING,
]);

$ambigDriver = Driver::create([
    'phone' => '+201144440006',
    'name' => 'Driver Ambig',
    'status' => DriverStatus::ACTIVE,
]);

$mo1_r = WorkOrderRecipient::create([
    'work_order_id' => $multiOrder1->id,
    'driver_id' => $ambigDriver->id,
    'status' => WorkOrderRecipientStatus::SENT,
    'sent_at' => now(),
]);
$mo2_r = WorkOrderRecipient::create([
    'work_order_id' => $multiOrder2->id,
    'driver_id' => $ambigDriver->id,
    'status' => WorkOrderRecipientStatus::SENT,
    'sent_at' => now(),
]);

// Driver sends free-text "موافق" with 2 open offers
$router->handle(new InboundMessage(
    from: $ambigDriver->phone,
    type: 'text',
    text: 'موافق',
    payloadId: null,
    providerMessageId: 'ambig_msg_1',
    timestamp: time()
));

$mo1_r->refresh();
$mo2_r->refresh();

// Verify neither offer was accepted (status remains SENT)
// and an outgoing warning message was sent to driver
$warningMsg = WhatsAppMessage::where('driver_id', $ambigDriver->id)
    ->where('direction', \App\Enums\WhatsAppMessageDirection::OUT)
    ->latest('id')
    ->first();

$hasWarningText = $warningMsg && str_contains($warningMsg->body, 'أكثر من عرض رحلة متاح');
$step3Passed = ($mo1_r->status === WorkOrderRecipientStatus::SENT && $mo2_r->status === WorkOrderRecipientStatus::SENT && $hasWarningText);
recordStep('STEP_3', 'Ambiguity guard: 2 open offers + free text -> neither accepted, warning sent', $step3Passed,
    "MO1 status: {$mo1_r->status->value}, MO2 status: {$mo2_r->status->value}, Warning sent: " . ($hasWarningText ? 'YES' : 'NO'));

// -----------------------------------------------------------------------------
// STEP 4: Double-Tap Accept Idempotency
// -----------------------------------------------------------------------------
$dtOrder = WorkOrder::create([
    'code' => 'WO-DOUBLETAP-01',
    'from_location' => 'الهرم',
    'to_location' => 'الزمالك',
    'scheduled_at' => now()->addDays(1),
    'trips_needed' => 3,
    'backup_needed' => 1,
    'status' => WorkOrderStatus::FILLING,
]);
$dtDriver = Driver::create([
    'phone' => '+201144440007',
    'name' => 'Driver DoubleTap',
    'status' => DriverStatus::ACTIVE,
]);
$dtRecipient = WorkOrderRecipient::create([
    'work_order_id' => $dtOrder->id,
    'driver_id' => $dtDriver->id,
    'status' => WorkOrderRecipientStatus::SENT,
    'sent_at' => now(),
]);

// First Tap
$tap1 = $acceptService->handle($dtOrder->id, $dtDriver->id, 'accept');
// Second Tap
$tap2 = $acceptService->handle($dtOrder->id, $dtDriver->id, 'accept');

$dtRecipient->refresh();
$step4Passed = (
    $tap1['status'] === 'confirmed' &&
    $tap1['sequence_number'] === 1 &&
    $tap1['is_duplicate'] === false &&
    $tap2['status'] === 'confirmed' &&
    $tap2['sequence_number'] === 1 &&
    $tap2['is_duplicate'] === true &&
    $dtRecipient->status === WorkOrderRecipientStatus::CONFIRMED
);
recordStep('STEP_4', 'Double-tap accept idempotency (returns same sequence and is_duplicate=true)', $step4Passed,
    "Tap 1 seq: {$tap1['sequence_number']} (dup: " . ($tap1['is_duplicate'] ? 'yes' : 'no') . "), Tap 2 seq: {$tap2['sequence_number']} (dup: " . ($tap2['is_duplicate'] ? 'yes' : 'no') . ")");

// -----------------------------------------------------------------------------
// STEP 5: Opted-Out Driver Accepting -> Cancelled
// -----------------------------------------------------------------------------
$optOrder = WorkOrder::create([
    'code' => 'WO-OPTOUT-ACCEPT-01',
    'from_location' => 'وسط البلد',
    'to_location' => 'العين السخنة',
    'scheduled_at' => now()->addDays(1),
    'trips_needed' => 2,
    'backup_needed' => 1,
    'status' => WorkOrderStatus::FILLING,
]);
$optDriver = Driver::create([
    'phone' => '+201144440008',
    'name' => 'Driver Opted Out Late',
    'status' => DriverStatus::ACTIVE,
]);
$optRecipient = WorkOrderRecipient::create([
    'work_order_id' => $optOrder->id,
    'driver_id' => $optDriver->id,
    'status' => WorkOrderRecipientStatus::SENT,
    'sent_at' => now(),
]);

// Driver opts out before accepting
$optDriver->update(['status' => DriverStatus::OPTED_OUT]);

// Driver tries to accept offer
$optAcceptResult = $acceptService->handle($optOrder->id, $optDriver->id, 'accept');
$optRecipient->refresh();

$step5Passed = ($optAcceptResult['success'] === false && $optRecipient->status === WorkOrderRecipientStatus::CANCELLED);
recordStep('STEP_5', 'Opted-out driver attempting acceptance -> cancelled safely', $step5Passed,
    "Accept result success: " . ($optAcceptResult['success'] ? 'true' : 'false') . ", Recipient status: {$optRecipient->status->value}");

// -----------------------------------------------------------------------------
// STEP 6: Notification Reconciliation Command Execution
// -----------------------------------------------------------------------------
$reconcileExitCode = Artisan::call('work-orders:reconcile-notifications');
$reconcileOutput = trim(Artisan::output());

$step6Passed = ($reconcileExitCode === 0);
recordStep('STEP_6', 'Artisan work-orders:reconcile-notifications execution', $step6Passed,
    "Exit code: $reconcileExitCode, Output: $reconcileOutput");

// -----------------------------------------------------------------------------
// FINAL SUMMARY
// -----------------------------------------------------------------------------
$allPassed = !in_array(false, array_column($results, 'passed'), true);
echo "====================================================================\n";
echo "SCENARIO D AUDIT RESULT: " . ($allPassed ? "100% PASS" : "FAIL") . "\n";
echo "====================================================================\n";
exit($allPassed ? 0 : 1);
