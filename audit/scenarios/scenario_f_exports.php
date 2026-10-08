<?php
// AUDIT Scenario F: Export Engine & High-Volume Streaming Verification
// Target DB: driver_system_audit

require __DIR__ . '/../../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Driver;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Models\DriverMetric;
use App\Enums\DriverStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Exports\StreamingDriversExport;
use App\Services\WorkOrderPdfService;

config(['database.connections.mysql.database' => 'driver_system_audit']);
DB::purge('mysql');
DB::reconnect('mysql');

echo "====================================================================\n";
echo "STAGE 5 SCENARIO F: EXPORT ENGINE & HIGH-VOLUME STREAMING AUDIT\n";
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
WorkOrder::truncate();
DriverMetric::truncate();
DB::table('driver_status_histories')->truncate();
Driver::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1');

$samplesDir = __DIR__ . '/../../docs/audit/samples';
if (!is_dir($samplesDir)) {
    mkdir($samplesDir, 0755, true);
}

// -----------------------------------------------------------------------------
// STEP 1: PDF Export Generation & Verification
// -----------------------------------------------------------------------------
$order = WorkOrder::create([
    'code' => 'WO-EXP-PDF-01',
    'from_location' => 'القاهرة (التحرير)',
    'to_location' => 'العين السخنة (بورتو)',
    'scheduled_at' => now()->addDays(2),
    'cargo_type' => 'ركاب سياحي',
    'pay_amount' => 1200.00,
    'trips_needed' => 2,
    'backup_needed' => 1,
    'confirmed_count' => 2,
    'backup_count' => 1,
    'status' => WorkOrderStatus::FILLED,
]);

$dPdf1 = Driver::create(['phone' => '+201101234567', 'name' => 'محمد أحمد إبراهيم', 'status' => DriverStatus::ACTIVE]);
$dPdf2 = Driver::create(['phone' => '+201102345678', 'name' => 'طارق محمود حسن', 'status' => DriverStatus::ACTIVE]);
$dPdf3 = Driver::create(['phone' => '+201103456789', 'name' => 'كريم عصام الدين', 'status' => DriverStatus::ACTIVE]);

WorkOrderRecipient::create([
    'work_order_id' => $order->id,
    'driver_id' => $dPdf1->id,
    'status' => WorkOrderRecipientStatus::CONFIRMED,
    'sequence_number' => 1,
    'responded_at' => now(),
]);
WorkOrderRecipient::create([
    'work_order_id' => $order->id,
    'driver_id' => $dPdf2->id,
    'status' => WorkOrderRecipientStatus::CONFIRMED,
    'sequence_number' => 2,
    'responded_at' => now(),
]);
WorkOrderRecipient::create([
    'work_order_id' => $order->id,
    'driver_id' => $dPdf3->id,
    'status' => WorkOrderRecipientStatus::BACKUP,
    'sequence_number' => 1,
    'responded_at' => now(),
]);

$pdfService = app(WorkOrderPdfService::class);
$pdfBinary = $pdfService->generatePdf($order);
$pdfPath = $samplesDir . '/sample_work_order.pdf';
file_put_contents($pdfPath, $pdfBinary);

$pdfSize = strlen($pdfBinary);
$isPdfHeaderValid = str_starts_with($pdfBinary, '%PDF');
$step1Passed = ($pdfSize > 1000 && $isPdfHeaderValid);
recordStep('STEP_1', 'Work order PDF export generation & header check', $step1Passed,
    "Generated: sample_work_order.pdf ({$pdfSize} bytes, header: %PDF)");

// -----------------------------------------------------------------------------
// STEP 2: Excel Export (StreamingDriversExport) & RTL / String verification
// -----------------------------------------------------------------------------
$xlsxPath = $samplesDir . '/sample_drivers_export.xlsx';
$export = new StreamingDriversExport();
$export->exportToFile($xlsxPath);

$xlsxSize = file_exists($xlsxPath) ? filesize($xlsxPath) : 0;
// Check if file is a valid zip containing xl/worksheets/sheet1.xml with rightToLeft="1"
$zip = new ZipArchive();
$isRtlConfigured = false;
if ($zip->open($xlsxPath) === true) {
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml && (str_contains($sheetXml, 'rightToLeft="1"') || str_contains($sheetXml, 'rightToLeft="true"'))) {
        $isRtlConfigured = true;
    }
    $zip->close();
}

$step2Passed = ($xlsxSize > 1000 && $isRtlConfigured);
recordStep('STEP_2', 'Excel export generation & RTL rightToLeft verification', $step2Passed,
    "Generated: sample_drivers_export.xlsx ({$xlsxSize} bytes, RTL in sheet1.xml: " . ($isRtlConfigured ? 'YES' : 'NO') . ")");

// -----------------------------------------------------------------------------
// STEP 3: High-Volume 5,000 Drivers Memory & Streaming Benchmark (< 128 MB limit)
// -----------------------------------------------------------------------------
echo "  Seeding 5,000 drivers in audit DB for streaming benchmark...\n";
$startSeed = microtime(true);
$now = now();
$batch = [];
for ($i = 1; $i <= 5000; $i++) {
    $batch[] = [
        'phone' => sprintf('+2015000%05d', $i),
        'name' => "سائق اختبار حجم {$i}",
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ];

    if (count($batch) >= 1000) {
        Driver::insert($batch);
        $batch = [];
    }
}
if (!empty($batch)) {
    Driver::insert($batch);
}
$seedDuration = round(microtime(true) - $startSeed, 2);
echo "  Seeded 5,000 drivers in {$seedDuration}s. Starting streaming export...\n";

gc_collect_cycles();
$memBefore = memory_get_usage(true);
$startExport = microtime(true);

$largeExportPath = $samplesDir . '/benchmark_5000_drivers.xlsx';
$largeExport = new StreamingDriversExport();
$largeExport->exportToFile($largeExportPath);

$exportDuration = round(microtime(true) - $startExport, 2);
$memPeak = memory_get_peak_usage(true);
$memPeakMb = round($memPeak / 1024 / 1024, 2);
$largeSize = file_exists($largeExportPath) ? filesize($largeExportPath) : 0;
$largeSizeMb = round($largeSize / 1024 / 1024, 2);

// Must stay well under 128MB shared hosting limit (e.g. under 64MB)
$step3Passed = ($largeSize > 50000 && $memPeakMb < 128.0);
recordStep('STEP_3', 'Streaming 5,000 drivers memory benchmark (< 128MB Hostinger limit)', $step3Passed,
    "Rows: 5,000 | Peak Memory: {$memPeakMb} MB (Limit: 128 MB) | Duration: {$exportDuration}s | File Size: {$largeSizeMb} MB");

// -----------------------------------------------------------------------------
// FINAL SUMMARY
// -----------------------------------------------------------------------------
$allPassed = !in_array(false, array_column($results, 'passed'), true);
echo "====================================================================\n";
echo "SCENARIO F AUDIT RESULT: " . ($allPassed ? "100% PASS" : "FAIL") . "\n";
echo "====================================================================\n";
exit($allPassed ? 0 : 1);
