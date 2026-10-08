<?php
// AUDIT Scenario G: Scheduler & Daemons Verification
// Target DB: driver_system_audit

require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

config(['database.connections.mysql.database' => 'driver_system_audit']);
DB::purge('mysql');
DB::reconnect('mysql');

echo "====================================================================\n";
echo "STAGE 5 SCENARIO G: SCHEDULER & CRON DAEMONS AUDIT\n";
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

// -----------------------------------------------------------------------------
// STEP 1: schedule:list verification
// -----------------------------------------------------------------------------
$listCode = Artisan::call('schedule:list');
$listOutput = Artisan::output();

$expectedCommands = [
    'queue:work',
    'app:heartbeat',
    'drivers:expire-invites',
    'questionnaire:check-timeouts',
    'work-orders:reconcile-notifications',
    'work-orders:send-reminders',
    'exports:cleanup',
    'app:prune-retention-data',
    'app:backup-db',
    'app:check-health-alerts',
];

$missingScheduled = [];
foreach ($expectedCommands as $cmd) {
    if (!str_contains($listOutput, $cmd)) {
        $missingScheduled[] = $cmd;
    }
}

$step1Passed = ($listCode === 0 && empty($missingScheduled));
recordStep('STEP_1', 'schedule:list schedules all 10 background tasks', $step1Passed,
    empty($missingScheduled) ? "All 10 scheduled tasks present in schedule:list" : "Missing: " . implode(', ', $missingScheduled));

// -----------------------------------------------------------------------------
// STEP 2: Heartbeat Command & Cache Verification
// -----------------------------------------------------------------------------
Cache::forget('last_scheduler_run_at');
$hbCode = Artisan::call('app:heartbeat');
$cachedHeartbeat = Cache::get('last_scheduler_run_at');

$step2Passed = ($hbCode === 0 && !empty($cachedHeartbeat));
recordStep('STEP_2', 'app:heartbeat records last_scheduler_run_at in cache', $step2Passed,
    "Heartbeat exit: $hbCode, Cached timestamp: $cachedHeartbeat");

// -----------------------------------------------------------------------------
// STEP 3: Individual Execution of Every Scheduled Command
// -----------------------------------------------------------------------------
$commandsToTest = [
    'drivers:expire-invites' => [],
    'questionnaire:check-timeouts' => [],
    'work-orders:reconcile-notifications' => [],
    'work-orders:send-reminders' => [],
    'exports:cleanup' => [],
    'app:prune-retention-data' => [],
    'app:check-health-alerts' => [],
    'queue:work' => ['--stop-when-empty' => true],
];

$failedCommands = [];
foreach ($commandsToTest as $cmd => $params) {
    try {
        $code = Artisan::call($cmd, $params);
        if ($code !== 0) {
            $failedCommands[] = "$cmd (exit: $code)";
        }
    } catch (\Throwable $e) {
        $failedCommands[] = "$cmd (exception: {$e->getMessage()})";
    }
}

$step3Passed = empty($failedCommands);
recordStep('STEP_3', 'Individual execution of all scheduled cron commands', $step3Passed,
    empty($failedCommands) ? "All 8 commands executed cleanly with exit code 0" : "Failed: " . implode(', ', $failedCommands));

// -----------------------------------------------------------------------------
// STEP 4: schedule:run Execution Simulation
// -----------------------------------------------------------------------------
$runCode = Artisan::call('schedule:run');
$runOutput = trim(Artisan::output());

$step4Passed = ($runCode === 0);
recordStep('STEP_4', 'Artisan schedule:run execution (cron runner emulation)', $step4Passed,
    "Exit code: $runCode, Output: " . (substr($runOutput, 0, 120) ?: 'Executed scheduled tasks.'));

// -----------------------------------------------------------------------------
// FINAL SUMMARY
// -----------------------------------------------------------------------------
$allPassed = !in_array(false, array_column($results, 'passed'), true);
echo "====================================================================\n";
echo "SCENARIO G AUDIT RESULT: " . ($allPassed ? "100% PASS" : "FAIL") . "\n";
echo "====================================================================\n";
exit($allPassed ? 0 : 1);
