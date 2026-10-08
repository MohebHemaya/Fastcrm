<?php
// AUDIT Scenario C: Questionnaire Engine Exhaustive Walkthrough
// Target DB: driver_system_audit

require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Driver;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use App\Models\DriverAnswer;
use App\Models\WhatsAppMessage;
use App\Models\Setting;
use App\Enums\DriverStatus;
use App\Enums\QuestionType;
use App\Enums\WhatsAppMessageType;
use App\Services\QuestionnaireService;
use App\Services\InboundMessageRouter;
use App\DTOs\WhatsApp\InboundMessage;

config(['database.connections.mysql.database' => 'driver_system_audit']);
DB::purge('mysql');
DB::reconnect('mysql');

echo "====================================================================\n";
echo "STAGE 5 SCENARIO C: QUESTIONNAIRE ENGINE EXHAUSTIVE AUDIT\n";
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
DriverAnswer::truncate();
QuestionOption::truncate();
QuestionVersion::truncate();
Question::truncate();
DB::table('whatsapp_messages')->truncate();
DB::table('driver_status_histories')->truncate();
Driver::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1');

$qService = app(QuestionnaireService::class);
$router = app(InboundMessageRouter::class);

// Seed 4 questions:
// Q1: YES_NO
$q1 = Question::create([
    'key' => 'has_license',
    'type' => QuestionType::YES_NO,
    'order' => 1,
    'is_active' => true,
]);
$v1 = $q1->recordVersion('has_license', QuestionType::YES_NO, [], 'هل لديك رخصة قيادة مهنية سارية؟');

// Q2: SINGLE_CHOICE (<=3 options -> Buttons)
$q2 = Question::create([
    'key' => 'vehicle_type',
    'type' => QuestionType::SINGLE_CHOICE,
    'order' => 2,
    'is_active' => true,
]);
$opt2_1 = QuestionOption::create(['question_id' => $q2->id, 'value' => 'sedan', 'label_ar' => 'سيدان', 'order' => 1]);
$opt2_2 = QuestionOption::create(['question_id' => $q2->id, 'value' => 'suv', 'label_ar' => 'دفع رباعي', 'order' => 2]);
$opt2_3 = QuestionOption::create(['question_id' => $q2->id, 'value' => 'van', 'label_ar' => 'ميكروباص', 'order' => 3]);
$v2 = $q2->recordVersion('vehicle_type', QuestionType::SINGLE_CHOICE, [
    ['id' => $opt2_1->id, 'value' => 'sedan', 'label_ar' => 'سيدان'],
    ['id' => $opt2_2->id, 'value' => 'suv', 'label_ar' => 'دفع رباعي'],
    ['id' => $opt2_3->id, 'value' => 'van', 'label_ar' => 'ميكروباص'],
], 'ما هو نوع سيارتك؟');

// Q3: SINGLE_CHOICE (>3 options -> List, e.g. 5 options)
$q3 = Question::create([
    'key' => 'governorate',
    'type' => QuestionType::SINGLE_CHOICE,
    'order' => 3,
    'is_active' => true,
]);
$opt3_1 = QuestionOption::create(['question_id' => $q3->id, 'value' => 'cairo', 'label_ar' => 'القاهرة', 'order' => 1]);
$opt3_2 = QuestionOption::create(['question_id' => $q3->id, 'value' => 'giza', 'label_ar' => 'الجيزة', 'order' => 2]);
$opt3_3 = QuestionOption::create(['question_id' => $q3->id, 'value' => 'alex', 'label_ar' => 'الإسكندرية', 'order' => 3]);
$opt3_4 = QuestionOption::create(['question_id' => $q3->id, 'value' => 'sharqia', 'label_ar' => 'الشرقية', 'order' => 4]);
$opt3_5 = QuestionOption::create(['question_id' => $q3->id, 'value' => 'daqahlia', 'label_ar' => 'الدقهلية', 'order' => 5]);
$v3 = $q3->recordVersion('governorate', QuestionType::SINGLE_CHOICE, [
    ['id' => $opt3_1->id, 'value' => 'cairo', 'label_ar' => 'القاهرة'],
    ['id' => $opt3_2->id, 'value' => 'giza', 'label_ar' => 'الجيزة'],
    ['id' => $opt3_3->id, 'value' => 'alex', 'label_ar' => 'الإسكندرية'],
    ['id' => $opt3_4->id, 'value' => 'sharqia', 'label_ar' => 'الشرقية'],
    ['id' => $opt3_5->id, 'value' => 'daqahlia', 'label_ar' => 'الدقهلية'],
], 'في أي محافظة تعمل بانتظام؟');

// Q4: NUMBER
$q4 = Question::create([
    'key' => 'experience_years',
    'type' => QuestionType::NUMBER,
    'order' => 4,
    'is_active' => true,
]);
$v4 = $q4->recordVersion('experience_years', QuestionType::NUMBER, [], 'كم عدد سنوات خبرتك في القيادة؟');

// Q5: TEXT
$q5 = Question::create([
    'key' => 'driver_notes',
    'type' => QuestionType::TEXT,
    'order' => 5,
    'is_active' => true,
]);
$v5 = $q5->recordVersion('driver_notes', QuestionType::TEXT, [], 'هل لديك أي ملاحظات إضافية ترغب في مشاركتها؟');

// -----------------------------------------------------------------------------
// STEP 1: Verify Button formatting for <= 3 options and List for > 3 options
// -----------------------------------------------------------------------------
$testDriver1 = Driver::create([
    'phone' => '+201133330001',
    'name' => 'Driver Format Test',
    'status' => DriverStatus::ACTIVE,
]);

$msgBtn = $qService->sendQuestion($testDriver1, $q2); // 3 options -> BUTTONS
$msgList = $qService->sendQuestion($testDriver1, $q3); // 5 options -> LIST

$step1Passed = ($msgBtn->type === WhatsAppMessageType::BUTTON && $msgList->type === WhatsAppMessageType::LIST);
recordStep('STEP_1', 'UI Formatting: <=3 options -> BUTTONS, >3 options -> LIST', $step1Passed,
    "Q2 (3 options) type: {$msgBtn->type->value}, Q3 (5 options) type: {$msgList->type->value}");

// -----------------------------------------------------------------------------
// STEP 2: Full happy-path walkthrough across all question types
// -----------------------------------------------------------------------------
$happyDriver = Driver::create([
    'phone' => '+201133330002',
    'name' => 'Driver Happy Path',
    'status' => DriverStatus::INTERESTED,
]);

// Start questionnaire
$qService->startQuestionnaire($happyDriver);
$happyDriver->refresh();

// Driver responds to Q1 (YES_NO) via button payload
$router->handle(new InboundMessage(
    from: $happyDriver->phone,
    type: 'button',
    text: 'نعم',
    payloadId: "q_{$q1->id}_v_{$v1->id}_opt_yes",
    providerMessageId: 'hp_msg_1',
    timestamp: time()
));

// Driver responds to Q2 (SINGLE_CHOICE <=3) via text lookup ("سيدان")
$router->handle(new InboundMessage(
    from: $happyDriver->phone,
    type: 'text',
    text: 'سيدان',
    payloadId: null,
    providerMessageId: 'hp_msg_2',
    timestamp: time()
));

// Driver responds to Q3 (SINGLE_CHOICE >3) via list payload
$router->handle(new InboundMessage(
    from: $happyDriver->phone,
    type: 'list',
    text: 'الإسكندرية',
    payloadId: "q_{$q3->id}_v_{$v3->id}_opt_{$opt3_3->id}",
    providerMessageId: 'hp_msg_3',
    timestamp: time()
));

// Driver responds to Q4 (NUMBER) using Arabic-Indic numerals ("٥ سنوات")
$router->handle(new InboundMessage(
    from: $happyDriver->phone,
    type: 'text',
    text: '٥ سنوات خبرة',
    payloadId: null,
    providerMessageId: 'hp_msg_4',
    timestamp: time()
));

// Driver responds to Q5 (TEXT)
$router->handle(new InboundMessage(
    from: $happyDriver->phone,
    type: 'text',
    text: 'جاهز للعمل في ورديات ليلية ونهارية',
    payloadId: null,
    providerMessageId: 'hp_msg_5',
    timestamp: time()
));

$happyDriver->refresh();
$answersCount = DriverAnswer::where('driver_id', $happyDriver->id)->where('is_current', true)->count();
$q4Answer = DriverAnswer::where('driver_id', $happyDriver->id)->where('question_id', $q4->id)->first();

$step2Passed = ($happyDriver->status === DriverStatus::ACTIVE && $answersCount === 5 && $q4Answer?->value_number == 5);
recordStep('STEP_2', 'Full happy-path across all question types (YES_NO, BUTTONS, LIST, NUMBER, TEXT)', $step2Passed,
    "Final Driver Status: {$happyDriver->status->value}, Total answers: $answersCount/5, Q4 number parsed: {$q4Answer?->value_number}");

// -----------------------------------------------------------------------------
// STEP 3: Retry limit exceeding -> skip question & flag needs_review
// -----------------------------------------------------------------------------
$retryDriver = Driver::create([
    'phone' => '+201133330003',
    'name' => 'Driver Retries',
    'status' => DriverStatus::INTERESTED,
]);
$qService->startQuestionnaire($retryDriver);

// Driver sends garbage to Q1 (YES_NO)
// Attempt 1:
$router->handle(new InboundMessage(
    from: $retryDriver->phone,
    type: 'text',
    text: 'مش عارف والله',
    payloadId: null,
    providerMessageId: 'err_msg_1',
    timestamp: time()
));
$retryDriver->refresh();

// Attempt 2 (should trigger skip & review):
$router->handle(new InboundMessage(
    from: $retryDriver->phone,
    type: 'text',
    text: 'كلام تاني مش مفهوم خالص',
    payloadId: null,
    providerMessageId: 'err_msg_2',
    timestamp: time()
));
$retryDriver->refresh();

$skippedAnswer = DriverAnswer::where('driver_id', $retryDriver->id)
    ->where('question_id', $q1->id)
    ->where('is_skipped', true)
    ->first();

$step3Passed = ($retryDriver->needs_review === true && $skippedAnswer !== null);
recordStep('STEP_3', 'Max retry limit reached -> Question skipped & needs_review = true', $step3Passed,
    "needs_review: " . ($retryDriver->needs_review ? 'true' : 'false') . ", Review reason: {$retryDriver->review_reason}, Skipped answer stored: " . ($skippedAnswer ? 'yes' : 'no'));

// -----------------------------------------------------------------------------
// STEP 4: Reminder and Timeout Simulation
// -----------------------------------------------------------------------------
// Driver 4A: Stalled for 25 hours -> should receive reminder
$driver4A = Driver::create([
    'phone' => '+201133330004',
    'name' => 'Driver Stalled 25h',
    'status' => DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
    'questionnaire_last_sent_at' => now()->subHours(25),
    'questionnaire_reminder_sent_at' => null,
]);

// Driver 4B: Stalled for 4 days -> should timeout to NO_RESPONSE
$driver4B = Driver::create([
    'phone' => '+201133330005',
    'name' => 'Driver Stalled 4d',
    'status' => DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
    'questionnaire_last_sent_at' => now()->subDays(4),
    'questionnaire_reminder_sent_at' => now()->subDays(3),
]);

$cronResult = $qService->processRemindersAndTimeouts();
$driver4A->refresh();
$driver4B->refresh();

$step4Passed = (
    $driver4A->questionnaire_reminder_sent_at !== null &&
    $driver4B->status === DriverStatus::NO_RESPONSE &&
    $cronResult['reminded'] >= 1 &&
    $cronResult['timed_out'] >= 1
);
recordStep('STEP_4', 'Reminder (>24h) and Timeout (>3d) batch processing', $step4Passed,
    "4A reminder_sent_at: " . ($driver4A->questionnaire_reminder_sent_at ? 'SET' : 'NULL') . ", 4B status: {$driver4B->status->value} (Expected: no_response)");

// -----------------------------------------------------------------------------
// STEP 5: Resume Questionnaire After Timeout (NO_RESPONSE)
// -----------------------------------------------------------------------------
// Driver 4B responds after timeout -> should resume
$router->handle(new InboundMessage(
    from: $driver4B->phone,
    type: 'text',
    text: 'أنا رجعت وعايز اكمل الاستبيان',
    payloadId: null,
    providerMessageId: 'resume_msg_1',
    timestamp: time()
));
$driver4B->refresh();

$step5Passed = ($driver4B->status === DriverStatus::QUESTIONNAIRE_IN_PROGRESS);
recordStep('STEP_5', 'Resume questionnaire after NO_RESPONSE timeout upon reply', $step5Passed,
    "Driver status after reply: {$driver4B->status->value} (Expected: questionnaire_in_progress)");

// -----------------------------------------------------------------------------
// STEP 6: Question Versioning on Edit Mid-Flight
// -----------------------------------------------------------------------------
// Driver has answered Q1 under version 1.
// Admin updates Q1 text -> records new version 2.
$v1_id = $v1->id;
$v1_answer = DriverAnswer::where('driver_id', $happyDriver->id)->where('question_id', $q1->id)->first();

// Edit Q1
$v1_new = $q1->recordVersion('has_license', QuestionType::YES_NO, [], 'هل تمتلك رخصة قيادة درجة أولى أو ثانية سارية المفعول؟');

// Another driver answers under new version
$driverV2 = Driver::create([
    'phone' => '+201133330006',
    'name' => 'Driver V2',
    'status' => DriverStatus::INTERESTED,
]);
$qService->startQuestionnaire($driverV2);

$router->handle(new InboundMessage(
    from: $driverV2->phone,
    type: 'text',
    text: 'نعم',
    payloadId: "q_{$q1->id}_v_{$v1_new->id}_opt_yes",
    providerMessageId: 'v2_msg_1',
    timestamp: time()
));

$v2_answer = DriverAnswer::where('driver_id', $driverV2->id)->where('question_id', $q1->id)->first();

$step6Passed = (
    $v1_answer->question_version_id === $v1_id &&
    $v2_answer->question_version_id === $v1_new->id &&
    $v1_id !== $v1_new->id
);
recordStep('STEP_6', 'Question versioning preserves historical versions for past answers', $step6Passed,
    "HappyDriver Answer Version: {$v1_answer->question_version_id}, DriverV2 Answer Version: {$v2_answer->question_version_id}");

// -----------------------------------------------------------------------------
// FINAL SUMMARY
// -----------------------------------------------------------------------------
$allPassed = !in_array(false, array_column($results, 'passed'), true);
echo "====================================================================\n";
echo "SCENARIO C AUDIT RESULT: " . ($allPassed ? "100% PASS" : "FAIL") . "\n";
echo "====================================================================\n";
exit($allPassed ? 0 : 1);
