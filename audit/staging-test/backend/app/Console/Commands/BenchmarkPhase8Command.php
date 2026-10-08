<?php

namespace App\Console\Commands;

use App\Enums\DriverStatus;
use App\Enums\ImportStatus;
use App\Exports\DriversExport;
use App\Jobs\CommitImportJob;
use App\Jobs\ParseImportJob;
use App\Models\Driver;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ImportFileParser;
use App\Services\WorkOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;

class BenchmarkPhase8Command extends Command
{
    protected $signature = 'app:benchmark-phase8 {--only=all}';

    protected $description = 'Benchmark 5,000-row import and sending order to 500 recipients';

    public function handle(ImportFileParser $parser, WorkOrderService $workOrderService): int
    {
        $only = $this->option('only');

        if ($only === 'all' || $only === 'import') {
            $this->benchmarkImport($parser);
        }

        if ($only === 'all' || $only === 'order') {
            $this->benchmarkSendOrder($workOrderService);
        }

        if ($only === 'all' || $only === 'export') {
            $this->benchmarkExport();
        }

        return 0;
    }

    protected function benchmarkImport(ImportFileParser $parser): void
    {
        $this->info('Starting 5,000-row import benchmark...');
        $user = User::first() ?? User::factory()->create();

        // 1. Generate 5,000-row CSV file
        $tmpDir = sys_get_temp_dir();
        $filePath = $tmpDir.DIRECTORY_SEPARATOR.'bench_5000_'.uniqid().'.csv';
        $handle = fopen($filePath, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['رقم الهاتف', 'اسم المجموعة']);

        $groups = ['جروب الجيزة', 'جروب القاهرة', 'جروب الدلتا', 'جروب الإسكندرية', 'جروب الصعيد'];
        for ($i = 1; $i <= 5000; $i++) {
            $phone = '010'.str_pad((string) $i, 8, '0', STR_PAD_LEFT);
            $group = $groups[$i % 5];
            fputcsv($handle, [$phone, $group]);
        }
        fclose($handle);

        $import = Import::create([
            'user_id' => $user->id,
            'original_name' => 'bench_5000.csv',
            'stored_path' => $filePath,
            'status' => ImportStatus::UPLOADED,
        ]);

        // Parse file
        $parseStart = microtime(true);
        (new ParseImportJob($import))->handle();
        $parseDuration = microtime(true) - $parseStart;
        $this->line('  -> Parsing 5,000 rows took: '.number_format($parseDuration, 3).'s');

        // Commit import
        $commitStart = microtime(true);
        (new CommitImportJob($import, $user))->handle();
        $commitDuration = microtime(true) - $commitStart;
        $totalImportTime = $parseDuration + $commitDuration;

        $this->info('✓ 5,000-row Commit took: '.number_format($commitDuration, 3).'s (Total Import: '.number_format($totalImportTime, 3).'s)');

        // Clean up created benchmark data
        @unlink($filePath);
        ImportRow::where('import_id', $import->id)->delete();
        $import->delete();
    }

    protected function benchmarkSendOrder(WorkOrderService $workOrderService): void
    {
        $this->info('Starting 500-recipient send order benchmark...');
        $user = User::first() ?? User::factory()->create();

        // Create or ensure 500 active drivers
        $existingCount = Driver::where('status', DriverStatus::ACTIVE)->count();
        $needed = 500 - $existingCount;
        if ($needed > 0) {
            $this->line("  -> Seeding {$needed} additional active drivers for benchmark...");
            $inserts = [];
            $now = now();
            for ($i = 1; $i <= $needed; $i++) {
                $inserts[] = [
                    'phone' => '+2018'.str_pad((string) ($existingCount + $i), 8, '0', STR_PAD_LEFT),
                    'name' => 'سائق تجريبي '.($existingCount + $i),
                    'status' => DriverStatus::ACTIVE->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($inserts, 250) as $chunk) {
                Driver::insert($chunk);
            }
        }

        $order = WorkOrder::create([
            'code' => 'WO-BENCH-'.uniqid(),
            'title' => 'رحلة تجريبية لـ 500 سائق',
            'from_location' => 'القاهرة',
            'to_location' => 'الإسكندرية',
            'scheduled_at' => now()->addDay(),
            'trips_needed' => 10,
            'backup_needed' => 5,
            'send_strategy' => 'all_at_once',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $sendStart = microtime(true);
        $workOrderService->send($order);
        $sendDuration = microtime(true) - $sendStart;

        $recipientCount = $order->recipients()->count();
        $this->info("✓ Sending order to {$recipientCount} recipients took: ".number_format($sendDuration, 3).'s');

        // Clean up benchmark order
        $order->recipients()->delete();
        $order->delete();
    }

    protected function benchmarkExport(): void
    {
        $this->info('Starting Excel Export Peak Memory Benchmark with memory_limit=128M...');
        ini_set('memory_limit', '128M');

        // Ensure 2,000 drivers exist
        $count = Driver::count();
        $needed = 2000 - $count;
        if ($needed > 0) {
            $this->line("  -> Seeding {$needed} drivers to reach 2,000 drivers...");
            $inserts = [];
            $now = now();
            for ($i = 1; $i <= $needed; $i++) {
                $inserts[] = [
                    'phone' => '+2017'.str_pad((string) ($count + $i), 8, '0', STR_PAD_LEFT),
                    'name' => 'سائق تصدير '.($count + $i),
                    'status' => DriverStatus::ACTIVE->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($inserts, 500) as $chunk) {
                Driver::insert($chunk);
            }
        }

        // Test 2,000 drivers export
        gc_collect_cycles();
        $memBefore2k = memory_get_usage(true) / 1024 / 1024;
        $start2k = microtime(true);
        Excel::store(new DriversExport([]), 'bench_drivers_2k.xlsx', 'local');
        $duration2k = microtime(true) - $start2k;
        $peak2k = memory_get_peak_usage(true) / 1024 / 1024;
        $this->info('✓ 2,000-driver Excel Export: '.number_format($duration2k, 2).'s, Peak Memory: '.number_format($peak2k, 2).' MB (Baseline: '.number_format($memBefore2k, 2).' MB)');

        // Ensure 5,000 drivers exist
        $count = Driver::count();
        $needed5k = 5000 - $count;
        if ($needed5k > 0) {
            $this->line("  -> Seeding {$needed5k} drivers to reach 5,000 drivers...");
            $inserts = [];
            $now = now();
            for ($i = 1; $i <= $needed5k; $i++) {
                $inserts[] = [
                    'phone' => '+2016'.str_pad((string) ($count + $i), 8, '0', STR_PAD_LEFT),
                    'name' => 'سائق تصدير '.($count + $i),
                    'status' => DriverStatus::ACTIVE->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($inserts, 500) as $chunk) {
                Driver::insert($chunk);
            }
        }

        // Test 5,000 drivers export
        gc_collect_cycles();
        $memBefore5k = memory_get_usage(true) / 1024 / 1024;
        $start5k = microtime(true);
        Excel::store(new DriversExport([]), 'bench_drivers_5k.xlsx', 'local');
        $duration5k = microtime(true) - $start5k;
        $peak5k = memory_get_peak_usage(true) / 1024 / 1024;
        $this->info('✓ 5,000-driver Excel Export: '.number_format($duration5k, 2).'s, Peak Memory: '.number_format($peak5k, 2).' MB (Baseline: '.number_format($memBefore5k, 2).' MB)');

        // Clean up temp excel files
        @unlink(storage_path('app/bench_drivers_2k.xlsx'));
        @unlink(storage_path('app/bench_drivers_5k.xlsx'));
        @unlink(storage_path('app/private/bench_drivers_2k.xlsx'));
        @unlink(storage_path('app/private/bench_drivers_5k.xlsx'));
    }
}
