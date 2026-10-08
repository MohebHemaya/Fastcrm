<?php

namespace Tests\Feature;

use App\Enums\DriverStatus;
use App\Enums\ImportStatus;
use App\Jobs\CommitImportJob;
use App\Jobs\ParseImportJob;
use App\Models\Driver;
use App\Models\Import;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EndToEnd2000RowImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_2000_row_sample_file_imports_commits_and_reimports_without_duplicates(): void
    {
        $user = User::factory()->create();

        // 1. Pre-seed two drivers in DB: one active, one invited
        $activeDriver = Driver::factory()->create([
            'phone' => '+201011111111',
            'name' => 'سائق نشط مسبقاً',
            'status' => DriverStatus::ACTIVE,
        ]);

        $invitedDriver = Driver::factory()->create([
            'phone' => '+201022222222',
            'name' => 'سائق تمت دعوته مسبقاً',
            'status' => DriverStatus::INVITED,
        ]);

        // 2. Generate a realistic 2,000-row CSV file
        // Row 1: Header
        // Rows 2..1801: 1,800 unique valid Egyptian numbers
        // Rows 1802..1851: 50 in-file duplicates (re-appearing from the first 50 numbers with a different group)
        // Row 1852: Pre-existing active driver
        // Row 1853: Pre-existing contacted driver
        // Rows 1854..2001: 147 invalid numbers (landlines, bad prefixes, junk)
        // Total rows: 1800 + 50 + 2 + 148 = 2,000 data rows

        $tmpDir = sys_get_temp_dir();
        $filePath = $tmpDir.DIRECTORY_SEPARATOR.'e2e_2000_drivers_'.uniqid().'.csv';
        $handle = fopen($filePath, 'w');
        $this->assertNotFalse($handle);

        // UTF-8 BOM
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['رقم الهاتف', 'اسم المجموعة']);

        // 1,800 new Egyptian numbers
        for ($i = 1; $i <= 1800; $i++) {
            $num = str_pad((string) $i, 7, '0', STR_PAD_LEFT);
            // Alternate prefixes 010, 011, 012, 015 with Arabic & English digits, dashes, spaces
            $prefix = match ($i % 4) {
                0 => '010',
                1 => '011',
                2 => '012',
                default => '015',
            };
            $phone = "{$prefix}3{$num}";
            $group = ($i % 2 === 0) ? 'سائقين القاهرة' : 'سائقين الجيزة';
            fputcsv($handle, [$phone, $group]);
        }

        // 50 duplicate rows in-file with a different group
        for ($i = 1; $i <= 50; $i++) {
            $num = str_pad((string) $i, 7, '0', STR_PAD_LEFT);
            $prefix = match ($i % 4) {
                0 => '010',
                1 => '011',
                2 => '012',
                default => '015',
            };
            $phone = "{$prefix}3{$num}";
            fputcsv($handle, [$phone, 'مجموعة الإسكندرية الإضافية']);
        }

        // 2 pre-existing drivers in DB
        fputcsv($handle, ['01011111111', 'سائقين القاهرة']);
        fputcsv($handle, ['01022222222', 'سائقين الجيزة']);

        // 148 invalid rows (landlines, short numbers, letters)
        for ($i = 1; $i <= 148; $i++) {
            $invalid = match ($i % 3) {
                0 => '022345678', // Cairo landline
                1 => '010123',     // too short
                default => 'سائق ممتاز بدون رقم',
            };
            fputcsv($handle, [$invalid, 'مجموعة غير محددة']);
        }

        fclose($handle);

        $initialDriverCount = Driver::count();
        $this->assertEquals(2, $initialDriverCount);

        // --- FIRST IMPORT PASS ---
        $import = Import::create([
            'user_id' => $user->id,
            'original_name' => 'sample_2000.csv',
            'stored_path' => $filePath,
            'status' => ImportStatus::UPLOADED,
            'counts' => [
                'total' => 0,
                'new' => 0,
                'duplicate_in_file' => 0,
                'existing' => 0,
                'active' => 0,
                'invalid' => 0,
            ],
            'processed_chunks' => 0,
            'total_chunks' => 0,
        ]);

        // Run parser job
        (new ParseImportJob($import))->handle();
        $import->refresh();

        $this->assertEquals(ImportStatus::READY_FOR_REVIEW, $import->status);
        $this->assertEquals(2000, $import->counts['total']);
        $this->assertEquals(1800, $import->counts['new']);
        $this->assertEquals(50, $import->counts['duplicate_in_file']);
        $this->assertEquals(1, $import->counts['active']); // active driver detected
        $this->assertEquals(1, $import->counts['existing']); // contacted driver detected
        $this->assertEquals(148, $import->counts['invalid']);

        // Run commit job
        (new CommitImportJob($import))->handle();
        $import->refresh();

        $this->assertEquals(ImportStatus::COMPLETED, $import->status);

        // Verify database counts
        $postFirstImportDriverCount = Driver::count();
        // 2 initial + 1800 new = 1802 drivers
        $this->assertEquals(1802, $postFirstImportDriverCount);

        // Verify status preservation: existing active is still active, invited is still invited
        $this->assertEquals(DriverStatus::ACTIVE, $activeDriver->fresh()->status);
        $this->assertEquals(DriverStatus::INVITED, $invitedDriver->fresh()->status);

        // Verify group attachments: duplicate rows merged into multiple groups
        $firstDriver = Driver::where('phone', '+201030000004')->first();
        $this->assertNotNull($firstDriver);
        $this->assertGreaterThanOrEqual(2, $firstDriver->whatsappGroups()->count());

        // --- SECOND IMPORT PASS (RE-IMPORT OF EXACT SAME 2,000-ROW FILE) ---
        $import2 = Import::create([
            'user_id' => $user->id,
            'original_name' => 'sample_2000_reimport.csv',
            'stored_path' => $filePath,
            'status' => ImportStatus::UPLOADED,
            'counts' => [
                'total' => 0,
                'new' => 0,
                'duplicate_in_file' => 0,
                'existing' => 0,
                'active' => 0,
                'invalid' => 0,
            ],
            'processed_chunks' => 0,
            'total_chunks' => 0,
        ]);

        // Run parser job on the second import
        (new ParseImportJob($import2))->handle();
        $import2->refresh();

        $this->assertEquals(ImportStatus::READY_FOR_REVIEW, $import2->status);
        $this->assertEquals(2000, $import2->counts['total']);
        // Crucial test: 0 new drivers! All 1,800 previously new drivers are now classified as existing/active
        $this->assertEquals(0, $import2->counts['new']);
        $this->assertEquals(1801, $import2->counts['existing']); // 1800 previous new + 1 contacted
        $this->assertEquals(1, $import2->counts['active']); // 1 active
        $this->assertEquals(50, $import2->counts['duplicate_in_file']);
        $this->assertEquals(148, $import2->counts['invalid']);

        // Run commit job on second import
        (new CommitImportJob($import2))->handle();
        $import2->refresh();

        $this->assertEquals(ImportStatus::COMPLETED, $import2->status);

        // Verify total driver count in DB DID NOT CHANGE (0 duplicates created)
        $this->assertEquals($postFirstImportDriverCount, Driver::count());

        // Verify no existing driver status was downgraded or altered
        $this->assertEquals(DriverStatus::ACTIVE, $activeDriver->fresh()->status);
        $this->assertEquals(DriverStatus::INVITED, $invitedDriver->fresh()->status);

        // Cleanup temp file
        @unlink($filePath);
    }
}
