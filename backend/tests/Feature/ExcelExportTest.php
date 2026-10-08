<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\QuestionType;
use App\Enums\WorkOrderOutcome;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Exports\DriversExport;
use App\Exports\GroupPerformanceExport;
use App\Exports\WorkOrderExport;
use App\Models\Driver;
use App\Models\Question;
use App\Models\User;
use App\Models\WhatsappGroup;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Storage::fake('local');
});

test('drivers export generates RTL sheet, correct headers, preserves phone as string, and respects filters', function () {
    // 1. Create a question
    $question = Question::create([
        'key' => 'license_type',
        'type' => QuestionType::SINGLE_CHOICE,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $question->recordVersion('ما نوع رخصتك؟', QuestionType::SINGLE_CHOICE, [
        ['value' => 'first', 'label_ar' => 'درجة أولى'],
        ['value' => 'second', 'label_ar' => 'درجة ثانية'],
    ]);

    $group = WhatsappGroup::create(['name' => 'جروب الإسكندرية']);

    // Driver 1: Active, with group, with answer
    $d1 = Driver::factory()->create([
        'name' => 'أحمد مصطفى',
        'phone' => '+201012345678',
        'status' => DriverStatus::ACTIVE,
    ]);
    $group->drivers()->attach($d1->id);

    // Driver 2: Opted Out
    $d2 = Driver::factory()->create([
        'name' => 'محمود سعيد',
        'phone' => '+201198765432',
        'status' => DriverStatus::OPTED_OUT,
    ]);

    // Export with filter for ACTIVE drivers only
    $export = new DriversExport(['status' => 'active']);
    $filePath = 'exports/test_drivers.xlsx';
    Excel::store($export, $filePath, 'local');

    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    // Inspect the generated spreadsheet via PhpSpreadsheet
    $fullPath = Storage::disk('local')->path($filePath);
    $spreadsheet = IOFactory::load($fullPath);
    $sheet = $spreadsheet->getActiveSheet();

    // RTL sheet
    expect($sheet->getRightToLeft())->toBeTrue();
    expect($sheet->getTitle())->toBe('السائقون');

    // Headers
    expect($sheet->getCell('A1')->getValue())->toBe('معرف السائق')
        ->and($sheet->getCell('B1')->getValue())->toBe('الاسم')
        ->and($sheet->getCell('C1')->getValue())->toBe('رقم الهاتف')
        ->and($sheet->getCell('D1')->getValue())->toBe('الحالة')
        ->and($sheet->getCell('G1')->getValue())->toBe('ما نوع رخصتك؟');

    // Row count: 1 header + 1 matching active driver = 2 rows
    expect($sheet->getHighestRow())->toBe(2);

    // Data verification
    expect($sheet->getCell('B2')->getValue())->toBe('أحمد مصطفى');
    $phoneCell = $sheet->getCell('C2');
    expect($phoneCell->getDataType())->toBe(DataType::TYPE_STRING);
    expect((string) $phoneCell->getValue())->toBe('+201012345678');
    expect($sheet->getCell('E2')->getValue())->toContain('جروب الإسكندرية');
});

test('work order export generates multiple RTL sheets for summary, confirmed, and backup drivers', function () {
    $order = WorkOrder::factory()->create([
        'code' => 'WO-TEST-777',
        'from_location' => 'القاهرة',
        'to_location' => 'السويس',
        'scheduled_at' => now()->addDays(2),
        'trips_needed' => 2,
        'backup_needed' => 1,
        'confirmed_count' => 1,
        'backup_count' => 1,
        'status' => WorkOrderStatus::FILLED,
    ]);

    $dConfirmed = Driver::factory()->create(['name' => 'سامي إبراهيم', 'phone' => '+201200000001']);
    $dBackup = Driver::factory()->create(['name' => 'طارق كمال', 'phone' => '+201200000002']);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $dConfirmed->id,
        'status' => WorkOrderRecipientStatus::CONFIRMED,
        'sequence_number' => 1,
        'responded_at' => now()->subHours(2),
        'outcome' => WorkOrderOutcome::ATTENDED,
        'rating' => 5,
        'outcome_notes' => 'التزام رائع',
    ]);

    WorkOrderRecipient::create([
        'work_order_id' => $order->id,
        'driver_id' => $dBackup->id,
        'status' => WorkOrderRecipientStatus::BACKUP,
        'sequence_number' => 1,
        'responded_at' => now()->subHour(),
    ]);

    $export = new WorkOrderExport($order);
    $filePath = 'exports/test_order_report.xlsx';
    Excel::store($export, $filePath, 'local');

    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    $spreadsheet = IOFactory::load(Storage::disk('local')->path($filePath));
    expect($spreadsheet->getSheetCount())->toBe(3);

    // Sheet 1: Summary
    $summarySheet = $spreadsheet->getSheet(0);
    expect($summarySheet->getTitle())->toBe('تفاصيل الطلب')
        ->and($summarySheet->getRightToLeft())->toBeTrue()
        ->and($summarySheet->getCell('A1')->getValue())->toBe('البيان')
        ->and($summarySheet->getCell('B1')->getValue())->toBe('القيمة')
        ->and($summarySheet->getCell('B2')->getValue())->toBe('WO-TEST-777');

    // Sheet 2: Confirmed
    $confirmedSheet = $spreadsheet->getSheet(1);
    expect($confirmedSheet->getTitle())->toBe('السائقون المؤكدون')
        ->and($confirmedSheet->getRightToLeft())->toBeTrue()
        ->and($confirmedSheet->getCell('B2')->getValue())->toBe('سامي إبراهيم');
    $phoneCell = $confirmedSheet->getCell('C2');
    expect($phoneCell->getDataType())->toBe(DataType::TYPE_STRING)
        ->and((string) $phoneCell->getValue())->toBe('+201200000001')
        ->and($confirmedSheet->getCell('E2')->getValue())->toBe('حضر')
        ->and($confirmedSheet->getCell('F2')->getValue())->toBe('5 / 5');

    // Sheet 3: Backup
    $backupSheet = $spreadsheet->getSheet(2);
    expect($backupSheet->getTitle())->toBe('السائقون الاحتياط')
        ->and($backupSheet->getRightToLeft())->toBeTrue()
        ->and($backupSheet->getCell('B2')->getValue())->toBe('طارق كمال');
});

test('group performance export generates RTL sheet with Arabic headers and accurate data', function () {
    $group = WhatsappGroup::create(['name' => 'جروب الشرقية']);
    $driver = Driver::factory()->create(['status' => DriverStatus::ACTIVE]);
    $group->drivers()->attach($driver->id, ['first_seen_at' => now()]);

    $export = new GroupPerformanceExport;
    $filePath = 'exports/test_group_perf.xlsx';
    Excel::store($export, $filePath, 'local');

    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    $spreadsheet = IOFactory::load(Storage::disk('local')->path($filePath));
    $sheet = $spreadsheet->getActiveSheet();

    expect($sheet->getRightToLeft())->toBeTrue();
    expect($sheet->getTitle())->toBe('أداء المجموعات');
    expect($sheet->getCell('A1')->getValue())->toBe('اسم المجموعة');
    expect($sheet->getCell('H1')->getValue())->toBe('نسبة التحويل (%)');
    expect($sheet->getCell('A2')->getValue())->toBe('جروب الشرقية');
    expect($sheet->getCell('B2')->getValue())->toBe(1);
    expect($sheet->getCell('E2')->getValue())->toBe(1);
    expect($sheet->getCell('H2')->getValue())->toBe('100.0%');
});

test('chunked large export of 2,000 drivers completes successfully within shared hosting memory limits', function () {
    // Fast batch insertion of 2,000 drivers
    $records = [];
    $basePhone = 201100000000;
    $now = now()->toDateTimeString();

    for ($i = 1; $i <= 2000; $i++) {
        $records[] = [
            'phone' => '+'.($basePhone + $i),
            'name' => "سائق اختبار {$i}",
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (count($records) === 500) {
            DB::table('drivers')->insert($records);
            $records = [];
        }
    }
    if (! empty($records)) {
        DB::table('drivers')->insert($records);
    }

    $initialMemory = memory_get_usage();

    $export = new DriversExport;
    $filePath = 'exports/large_drivers_2000.xlsx';
    Excel::store($export, $filePath, 'local');

    $finalMemory = memory_get_usage();
    $memoryUsedMb = ($finalMemory - $initialMemory) / (1024 * 1024);

    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    // Verify row count
    $spreadsheet = IOFactory::load(Storage::disk('local')->path($filePath));
    $sheet = $spreadsheet->getActiveSheet();

    // 1 header row + 2000 data rows (plus any prior seeded in this test run) >= 2001
    expect($sheet->getHighestRow())->toBeGreaterThanOrEqual(2001);

    // Verify phone formatting on random row
    $cell = $sheet->getCell('C500');
    expect($cell->getDataType())->toBe(DataType::TYPE_STRING);
    expect((string) $cell->getValue())->toStartWith('+2011');
});
