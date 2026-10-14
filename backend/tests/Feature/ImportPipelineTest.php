<?php

use App\Enums\DriverStatus;
use App\Enums\ImportRowClassification;
use App\Enums\ImportStatus;
use App\Jobs\CommitImportJob;
use App\Jobs\ParseImportJob;
use App\Models\Driver;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    Driver::query()->delete();
});

test('template download returns valid CSV with Arabic UTF-8 BOM and headers', function () {
    $response = $this->actingAs($this->user)
        ->get('/api/v1/imports/template');

    $response->assertStatus(200)
        ->assertHeader('Content-Disposition', 'attachment; filename="drivers_import_template.csv"');

    $content = $response->streamedContent();
    expect($content)->toStartWith("\xEF\xBB\xBF");
    expect($content)->toContain('رقم الهاتف');
    expect($content)->toContain('01012345678');
});

test('upload validates mime type and file presence', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/imports', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/imports', [
            'file' => $invalidFile,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('upload stores file, dispatches ParseImportJob, and returns 201', function () {
    Queue::fake();

    $csvContent = "01012345678,جروب المعادي\n";
    $file = UploadedFile::fake()->createWithContent('drivers.csv', $csvContent);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/imports', [
            'file' => $file,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'original_name', 'status', 'counts'],
            'message',
        ]);

    Queue::assertPushed(ParseImportJob::class);
});

test('ParseImportJob correctly classifies new, duplicate_in_file, existing, active, and invalid rows', function () {
    // 1. Existing inactive driver (status = new)
    $existingDriver = Driver::factory()->create([
        'phone' => '+201011111111',
        'status' => DriverStatus::NEW,
    ]);

    // 2. Existing active driver (status = active)
    $activeDriver = Driver::factory()->create([
        'phone' => '+201022222222',
        'status' => DriverStatus::ACTIVE,
    ]);

    // CSV with:
    // Header
    // 1: new valid phone
    // 2: duplicate in file of #1
    // 3: existing driver
    // 4: active driver
    // 5: invalid phone (bad prefix)
    $csvContent = "رقم الهاتف,اسم المجموعة\n"
        ."01033333333,جروب جديد 1\n"
        ."01033333333,جروب جديد 2\n"
        ."01011111111,جروب قديم\n"
        ."01022222222,جروب نشط\n"
        ."01999999999,جروب خطأ\n";

    Storage::disk('local')->put('imports/sample.csv', $csvContent);

    $import = Import::create([
        'user_id' => $this->user->id,
        'original_name' => 'sample.csv',
        'stored_path' => 'imports/sample.csv',
        'status' => ImportStatus::UPLOADED,
    ]);

    // Run job synchronously
    (new ParseImportJob($import))->handle();

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::READY_FOR_REVIEW);
    expect($import->counts)->toBe([
        'total' => 5,
        'new' => 1,
        'duplicate_in_file' => 1,
        'existing' => 1,
        'active' => 1,
        'invalid' => 1,
    ]);

    // Verify row classifications
    $rows = ImportRow::where('import_id', $import->id)->orderBy('row_number')->get();
    expect($rows)->toHaveCount(5);

    expect($rows[0]->classification)->toBe(ImportRowClassification::NEW);
    expect($rows[0]->normalized_phone)->toBe('+201033333333');

    expect($rows[1]->classification)->toBe(ImportRowClassification::DUPLICATE_IN_FILE);
    expect($rows[1]->normalized_phone)->toBe('+201033333333');

    expect($rows[2]->classification)->toBe(ImportRowClassification::EXISTING);
    expect($rows[2]->normalized_phone)->toBe('+201011111111');
    expect($rows[2]->driver_id)->toBe($existingDriver->id);

    expect($rows[3]->classification)->toBe(ImportRowClassification::ACTIVE);
    expect($rows[3]->normalized_phone)->toBe('+201022222222');
    expect($rows[3]->driver_id)->toBe($activeDriver->id);

    expect($rows[4]->classification)->toBe(ImportRowClassification::INVALID);
    expect($rows[4]->error)->toBe('bad_prefix');
});

test('CommitImportJob creates new drivers, attaches groups, and never overwrites existing status', function () {
    $activeDriver = Driver::factory()->create([
        'phone' => '+201022222222',
        'status' => DriverStatus::ACTIVE,
    ]);

    $import = Import::create([
        'user_id' => $this->user->id,
        'original_name' => 'test.csv',
        'stored_path' => 'imports/test.csv',
        'status' => ImportStatus::READY_FOR_REVIEW,
        'counts' => [
            'total' => 3,
            'new' => 1,
            'duplicate_in_file' => 1,
            'existing' => 0,
            'active' => 1,
            'invalid' => 0,
        ],
    ]);

    ImportRow::create([
        'import_id' => $import->id,
        'row_number' => 1,
        'raw_phone' => '01033333333',
        'group_name' => 'جروب الأهرام',
        'normalized_phone' => '+201033333333',
        'classification' => ImportRowClassification::NEW,
    ]);

    ImportRow::create([
        'import_id' => $import->id,
        'row_number' => 2,
        'raw_phone' => '01033333333',
        'group_name' => 'جروب النيل',
        'normalized_phone' => '+201033333333',
        'classification' => ImportRowClassification::DUPLICATE_IN_FILE,
    ]);

    ImportRow::create([
        'import_id' => $import->id,
        'row_number' => 3,
        'raw_phone' => '01022222222',
        'group_name' => 'جروب الشروق',
        'normalized_phone' => '+201022222222',
        'classification' => ImportRowClassification::ACTIVE,
        'driver_id' => $activeDriver->id,
    ]);

    // Run commit job
    (new CommitImportJob($import, $this->user))->handle();

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::COMPLETED);

    // Verify new driver created with status NEW
    $newDriver = Driver::where('phone', '+201033333333')->first();
    expect($newDriver)->not->toBeNull();
    expect($newDriver->status)->toBe(DriverStatus::NEW);

    // Verify new driver belongs to both groups
    expect($newDriver->whatsappGroups()->count())->toBe(2);
    $groupNames = $newDriver->whatsappGroups->pluck('name')->all();
    expect($groupNames)->toContain('جروب الأهرام');
    expect($groupNames)->toContain('جروب النيل');

    // Verify existing active driver was NOT downgraded or changed
    $activeDriver->refresh();
    expect($activeDriver->status)->toBe(DriverStatus::ACTIVE);

    // Verify active driver received the new group
    expect($activeDriver->whatsappGroups()->where('name', 'جروب الشروق')->exists())->toBeTrue();

    // Idempotency: re-running commit job causes no duplicate errors or data corruption
    (new CommitImportJob($import, $this->user))->handle();
    expect(Driver::where('phone', '+201033333333')->count())->toBe(1);
});

test('cancel import changes status before commit', function () {
    $import = Import::factory()->create([
        'status' => ImportStatus::READY_FOR_REVIEW,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/imports/{$import->id}/cancel");

    $response->assertStatus(200);
    $import->refresh();
    expect($import->status)->toBe(ImportStatus::CANCELLED);
});

test('export invalid rows returns CSV with errors', function () {
    $import = Import::factory()->create([
        'status' => ImportStatus::READY_FOR_REVIEW,
    ]);

    ImportRow::create([
        'import_id' => $import->id,
        'row_number' => 4,
        'raw_phone' => '01912345678',
        'group_name' => 'جروب خطأ',
        'classification' => ImportRowClassification::INVALID,
        'error' => 'bad_prefix',
    ]);

    $response = $this->actingAs($this->user)
        ->get("/api/v1/imports/{$import->id}/export-invalid");

    $response->assertStatus(200);
    $content = $response->streamedContent();
    expect($content)->toContain('01912345678');
    expect($content)->toContain('bad_prefix');
});

test('chunked import of a 5,000-row file finishes and memory stays bounded', function () {
    // Generate 5,000 CSV rows
    $filePath = 'imports/large_5000.csv';

    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ['رقم الهاتف', 'المجموعة']);

    // Pre-create 1 active driver in DB
    $activePhone = '+201000000001';
    Driver::create([
        'phone' => $activePhone,
        'status' => DriverStatus::ACTIVE,
    ]);

    // Build 5,000 rows
    // Row 1: active phone
    fputcsv($handle, ['01000000001', 'جروب النشطين']);

    // 4,500 unique valid new phones
    for ($i = 2; $i <= 4501; $i++) {
        $num = str_pad((string) $i, 8, '0', STR_PAD_LEFT);
        fputcsv($handle, ["010{$num}", "جروب {$i}"]);
    }

    // 300 duplicates of row 2
    for ($i = 1; $i <= 300; $i++) {
        fputcsv($handle, ['01000000002', 'جروب مكرر']);
    }

    // 199 invalid rows
    for ($i = 1; $i <= 199; $i++) {
        fputcsv($handle, ["019{$i}", 'جروب غلط']);
    }

    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    Storage::disk('local')->put($filePath, $content);

    $import = Import::create([
        'user_id' => $this->user->id,
        'original_name' => 'large_5000.csv',
        'stored_path' => $filePath,
        'status' => ImportStatus::UPLOADED,
    ]);

    $memBefore = memory_get_usage(true);

    // Execute Parse Job
    (new ParseImportJob($import))->handle();

    $memAfterParse = memory_get_usage(true);
    $memDiffParseMb = ($memAfterParse - $memBefore) / (1024 * 1024);

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::READY_FOR_REVIEW);
    expect($import->counts['total'])->toBe(5000);
    expect($import->counts['active'])->toBe(1);
    expect($import->counts['duplicate_in_file'])->toBe(300);
    expect($import->counts['invalid'])->toBe(199);
    expect($import->counts['new'])->toBe(4500);

    // Execute Commit Job
    (new CommitImportJob($import, $this->user))->handle();

    $memAfterCommit = memory_get_usage(true);
    $memDiffCommitMb = ($memAfterCommit - $memBefore) / (1024 * 1024);

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::COMPLETED);

    // Verify drivers were created
    expect(Driver::where('status', DriverStatus::NEW)->count())->toBe(4500);

    // Verify memory growth remained bounded (< 30 MB on 5,000 rows)
    expect($memDiffParseMb)->toBeLessThan(35);
    expect($memDiffCommitMb)->toBeLessThan(35);
});
