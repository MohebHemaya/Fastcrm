<?php

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Exports\StreamingDriversExport;
use App\Jobs\ProcessExportJob;
use App\Models\Driver;
use App\Models\Export;
use App\Models\User;
use App\Models\WhatsappGroup;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    app()->instance(WhatsAppProvider::class, new FakeWhatsAppProvider);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Storage::fake('local');
});

test('streaming drivers export generates RTL sheet, correct headers, preserves phone as text, and formats properly', function () {
    $group = WhatsappGroup::create(['name' => 'جروب الدلتا']);
    $driver = Driver::factory()->create([
        'name' => 'سامح علي',
        'phone' => '+201234567890',
        'status' => DriverStatus::ACTIVE,
    ]);
    $group->drivers()->attach($driver->id);

    $exporter = new StreamingDriversExport(['status' => 'active']);
    $filePath = 'exports/test_streaming_drivers.xlsx';
    $exporter->exportToDisk('local', $filePath);

    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    $spreadsheet = IOFactory::load(Storage::disk('local')->path($filePath));
    $sheet = $spreadsheet->getActiveSheet();

    expect($sheet->getTitle())->toBe('السائقون')
        ->and($sheet->getRightToLeft())->toBeTrue()
        ->and((string) $sheet->getCell('A1')->getValue())->toBe('معرف السائق')
        ->and((string) $sheet->getCell('B1')->getValue())->toBe('الاسم')
        ->and((string) $sheet->getCell('C1')->getValue())->toBe('رقم الهاتف')
        ->and((string) $sheet->getCell('D1')->getValue())->toBe('الحالة')
        ->and((string) $sheet->getCell('B2')->getValue())->toBe('سامح علي')
        ->and((string) $sheet->getCell('C2')->getValue())->toBe('+201234567890')
        ->and($sheet->getCell('C2')->getDataType())->toBe(DataType::TYPE_INLINE);
});

test('process export job switches to streaming exporter when driver count exceeds 1000', function () {
    // Create 1005 drivers
    $records = [];
    $now = now()->toDateTimeString();
    for ($i = 1; $i <= 1005; $i++) {
        $records[] = [
            'phone' => '+2015'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            'name' => "سائق تدفق {$i}",
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    DB::table('drivers')->insert($records);

    $export = Export::create([
        'user_id' => $this->user->id,
        'type' => ExportType::DRIVERS,
        'params' => [],
        'status' => ExportStatus::QUEUED,
    ]);

    $job = new ProcessExportJob($export->id);
    $job->handle();

    $export->refresh();
    expect($export->status)->toBe(ExportStatus::DONE)
        ->and(Storage::disk('local')->exists($export->path))->toBeTrue();

    // Verify row count with PhpSpreadsheet
    $spreadsheet = IOFactory::load(Storage::disk('local')->path($export->path));
    $sheet = $spreadsheet->getActiveSheet();

    expect($sheet->getHighestRow())->toBeGreaterThanOrEqual(1006)
        ->and($sheet->getRightToLeft())->toBeTrue()
        ->and($sheet->getTitle())->toBe('السائقون');
});
