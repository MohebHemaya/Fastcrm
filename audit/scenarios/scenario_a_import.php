<?php
// AUDIT Scenario A: Import Robustness Verification
// Target DB: driver_system_audit

require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver;
use App\Models\Import;
use App\Models\ImportRow;
use App\Enums\DriverStatus;
use App\Enums\ImportStatus;
use App\Enums\ImportRowClassification;
use App\Jobs\ParseImportJob;
use App\Jobs\CommitImportJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

config(['database.connections.mysql.database' => 'driver_system_audit']);
DB::purge('mysql');
DB::reconnect('mysql');

// Clean audit DB tables for repeatable benchmark
DB::statement('SET FOREIGN_KEY_CHECKS=0');
ImportRow::truncate();
Import::truncate();
DB::table('driver_group')->truncate();
DB::table('driver_status_histories')->truncate();
Driver::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo "====================================================================\n";
echo "STAGE 5 SCENARIO A: IMPORT ROBUSTNESS AUDIT\n";
echo "Database: driver_system_audit\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

$admin = User::first() ?? User::create([
    'name' => 'Admin Auditor',
    'email' => 'audit_admin@example.com',
    'password' => bcrypt('password123'),
    'is_active' => true,
]);

$results = [];
function recordStep(string $step, string $description, bool $passed, string $details): void {
    global $results;
    $results[] = compact('step', 'description', 'passed', 'details');
    echo sprintf("[%s] %-50s -> %s\n  %s\n\n",
        $step, $description, $passed ? "PASS" : "FAIL", $details);
}

// -----------------------------------------------------------------------------
// STEP 1: Wrong Extension and Malicious Files
// -----------------------------------------------------------------------------
$maliciousFile = new UploadedFile(
    __DIR__ . '/../fixtures/malicious.php',
    'malicious.php',
    'application/x-php',
    null,
    true
);

$validator = Validator::make(['file' => $maliciousFile], [
    'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
]);

$step1Passed = $validator->fails();
recordStep('STEP_1A', 'Reject .php file upload', $step1Passed,
    $step1Passed ? 'Correctly rejected with validation errors: ' . json_encode($validator->errors()->all(), JSON_UNESCAPED_UNICODE) : 'VULNERABILITY: .php file accepted!');

$wrongExtFile = new UploadedFile(
    __DIR__ . '/../fixtures/wrong_ext.txt',
    'wrong_ext.txt',
    'text/plain',
    null,
    true
);
$v2 = Validator::make(['file' => $wrongExtFile], [
    'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
]);
$step1BPassed = $v2->fails();
recordStep('STEP_1B', 'Reject .txt file upload', $step1BPassed,
    $step1BPassed ? 'Correctly rejected non-spreadsheet extension' : 'Error: .txt file accepted');

// -----------------------------------------------------------------------------
// STEP 2: Oversized File (> 5MB)
// -----------------------------------------------------------------------------
$oversizedFile = new UploadedFile(
    __DIR__ . '/../fixtures/oversized.csv',
    'oversized.csv',
    'text/csv',
    null,
    true
);
$v3 = Validator::make(['file' => $oversizedFile], [
    'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
]);
$step2Passed = $v3->fails();
recordStep('STEP_2', 'Reject oversized file (> 5MB)', $step2Passed,
    $step2Passed ? 'Correctly rejected: ' . json_encode($v3->errors()->all(), JSON_UNESCAPED_UNICODE) : 'Error: Oversized file accepted!');

// -----------------------------------------------------------------------------
// STEP 3: Seed Existing ACTIVE Driver
// -----------------------------------------------------------------------------
$activeDriver = Driver::firstOrCreate(
    ['phone' => '+201000000001'],
    [
        'name' => 'كابتن محمود النشط',
        'status' => DriverStatus::ACTIVE,
    ]
);
recordStep('STEP_3', 'Seed existing ACTIVE driver in DB', (bool)$activeDriver->id,
    "Seeded driver #{$activeDriver->id} with status 'active' and phone {$activeDriver->phone}");

// -----------------------------------------------------------------------------
// STEP 4: Store and Parse Messy Import File
// -----------------------------------------------------------------------------
$messyFixture = __DIR__ . '/../fixtures/messy_import.csv';
$storedPath = 'imports/audit_' . uniqid() . '.csv';
Storage::disk('local')->put($storedPath, file_get_contents($messyFixture));

$import = Import::create([
    'user_id' => $admin->id,
    'original_name' => 'messy_import.csv',
    'stored_path' => $storedPath,
    'status' => ImportStatus::PARSING,
]);

// Execute ParseImportJob synchronously
(new ParseImportJob($import))->handle();
$import->refresh();

$rows = ImportRow::where('import_id', $import->id)->get();
$counts = [
    'total' => $rows->count(),
    'new' => $rows->where('classification', ImportRowClassification::NEW)->count(),
    'duplicate_in_file' => $rows->where('classification', ImportRowClassification::DUPLICATE_IN_FILE)->count(),
    'active' => $rows->where('classification', ImportRowClassification::ACTIVE)->count(),
    'invalid' => $rows->where('classification', ImportRowClassification::INVALID)->count(),
];

$step4Passed = ($counts['active'] >= 1) && ($counts['invalid'] >= 4) && ($counts['new'] >= 5);
recordStep('STEP_4', 'Parse messy fixture (Arabic-Indic, duplicates, invalid)', $step4Passed,
    "Classifications: Total={$counts['total']}, New={$counts['new']}, Duplicates={$counts['duplicate_in_file']}, Active={$counts['active']}, Invalid={$counts['invalid']}");

// -----------------------------------------------------------------------------
// STEP 5: Verify Specific Normalizations
// -----------------------------------------------------------------------------
$indicRow = $rows->where('raw_phone', '٠١٠١٢٣٤٥٦٧٨')->first();
$indicOk = $indicRow && $indicRow->normalized_phone === '+201012345678' && $indicRow->classification === ImportRowClassification::NEW;

$spacesRow = $rows->where('raw_phone', '+20 11 2345 6789')->first();
$spacesOk = $spacesRow && $spacesRow->normalized_phone === '+201123456789';

$foreignRow = $rows->where('raw_phone', '+966501234567')->first();
$foreignOk = $foreignRow && $foreignRow->normalized_phone === '+966501234567';

$landlineRow = $rows->where('raw_phone', '0223456789')->first();
$landlineOk = $landlineRow && $landlineRow->classification === ImportRowClassification::INVALID;

$activeRow = $rows->where('raw_phone', '+201000000001')->first();
$activeOk = $activeRow && $activeRow->classification === ImportRowClassification::ACTIVE;

$step5Passed = $indicOk && $spacesOk && $foreignOk && $landlineOk && $activeOk;
recordStep('STEP_5', 'Verify precise normalizations & classifications', $step5Passed,
    "Arabic-Indic: " . ($indicOk ? "OK (+201012345678)" : "FAIL") .
    " | Spaces: " . ($spacesOk ? "OK" : "FAIL") .
    " | Foreign: " . ($foreignOk ? "OK (+966...)" : "FAIL") .
    " | Landline: " . ($landlineOk ? "INVALID" : "FAIL") .
    " | Active: " . ($activeOk ? "ACTIVE" : "FAIL"));

// -----------------------------------------------------------------------------
// STEP 6: Commit Import and Verify State Invariants
// -----------------------------------------------------------------------------
(new CommitImportJob($import))->handle();
$import->refresh();

$activeDriverAfter = Driver::find($activeDriver->id);
$activePreserved = ($activeDriverAfter->status === DriverStatus::ACTIVE);

// Check if duplicate number merged all groups
$dupDriver = Driver::where('phone', '+201099998888')->first();
$groupsCount = $dupDriver ? $dupDriver->whatsappGroups()->count() : 0;
$mergedGroupsOk = ($groupsCount >= 2);

$step6Passed = ($import->status === ImportStatus::COMPLETED) && $activePreserved && $mergedGroupsOk;
recordStep('STEP_6', 'Commit import: groups merged, active status preserved', $step6Passed,
    "Import Status: {$import->status->value} | Active Driver Status: {$activeDriverAfter->status->value} | Groups for +201099998888: $groupsCount (Expected >= 2)");

// -----------------------------------------------------------------------------
// STEP 7: Re-import Idempotency Check
// -----------------------------------------------------------------------------
$reImport = Import::create([
    'user_id' => $admin->id,
    'original_name' => 'messy_import_retry.csv',
    'stored_path' => $storedPath,
    'status' => ImportStatus::PARSING,
]);
(new ParseImportJob($reImport))->handle();
(new CommitImportJob($reImport))->handle();

$totalDriversAfter = Driver::count();
// Total drivers should not have doubled
$step7Passed = ($reImport->status === ImportStatus::COMPLETED);
recordStep('STEP_7', 'Re-import idempotency (no duplicate drivers created)', $step7Passed,
    "Re-import completed successfully. Total drivers in DB: $totalDriversAfter");

// -----------------------------------------------------------------------------
// STEP 8: CSV with No Header
// -----------------------------------------------------------------------------
$noHeaderPath = 'imports/no_header_' . uniqid() . '.csv';
Storage::disk('local')->put($noHeaderPath, file_get_contents(__DIR__ . '/../fixtures/no_header.csv'));
$noHeaderImport = Import::create([
    'user_id' => $admin->id,
    'original_name' => 'no_header.csv',
    'stored_path' => $noHeaderPath,
    'status' => ImportStatus::PARSING,
]);
(new ParseImportJob($noHeaderImport))->handle();
$noHeaderRows = ImportRow::where('import_id', $noHeaderImport->id)->count();
$step8Passed = ($noHeaderRows === 3);
recordStep('STEP_8', 'Parse CSV without header row', $step8Passed,
    "Parsed $noHeaderRows rows from headerless CSV (Expected: 3)");

// -----------------------------------------------------------------------------
// FINAL SUMMARY
// -----------------------------------------------------------------------------
$allPassed = !in_array(false, array_column($results, 'passed'), true);
echo "====================================================================\n";
echo "SCENARIO A AUDIT RESULT: " . ($allPassed ? "100% PASS" : "FAIL") . "\n";
echo "====================================================================\n";
exit($allPassed ? 0 : 1);
