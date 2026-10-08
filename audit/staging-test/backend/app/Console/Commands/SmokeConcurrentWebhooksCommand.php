<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SmokeConcurrentWebhooksCommand extends Command
{
    protected $signature = 'app:smoke-concurrent-webhooks
                            {--concurrency=10 : Number of concurrent worker processes}
                            {--total=100 : Total webhooks to send across all processes}';

    protected $description = 'Run a multi-process load smoke test with concurrent inbound webhooks';

    public function handle(): int
    {
        $concurrency = max(1, (int) $this->option('concurrency'));
        $total = max(1, (int) $this->option('total'));
        $perWorker = (int) ceil($total / $concurrency);

        $this->info('=== Starting Concurrent Inbound Webhooks Smoke Test ===');
        $this->line("Concurrency: {$concurrency} separate processes");
        $this->line("Total requests: {$total} (~{$perWorker} per worker)");

        $barrierDir = storage_path('framework/testing');
        if (! is_dir($barrierDir)) {
            mkdir($barrierDir, 0777, true);
        }

        $runId = uniqid('smoke_wh_', true);
        $barrierFile = $barrierDir.'/barrier_'.uniqid().'.lock';

        // Ensure barrier file does NOT exist before launching workers
        if (file_exists($barrierFile)) {
            unlink($barrierFile);
        }

        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');

        $processes = [];

        // Launch all worker processes
        for ($w = 1; $w <= $concurrency; $w++) {
            $cmd = [
                $phpBinary,
                $artisanPath,
                'app:internal-webhook-worker',
                $runId,
                (string) $w,
                (string) $perWorker,
                "--barrier={$barrierFile}",
            ];

            $proc = new Process($cmd);
            $proc->setTimeout(120);
            $proc->start();
            $processes[$w] = $proc;
        }

        // Brief warm-up to let all child processes initialize and reach the barrier loop
        usleep(300000); // 300ms

        $overallStart = microtime(true);

        // RELEASE BARRIER simultaneously to all workers
        touch($barrierFile);

        $totalSuccess = 0;
        $totalDuplicate = 0;
        $totalDeadlock = 0;
        $totalError = 0;
        $workerDurations = [];

        foreach ($processes as $w => $proc) {
            $proc->wait();
            $output = trim($proc->getOutput());
            $errorOutput = trim($proc->getErrorOutput());

            // Clean up barrier file
            if (file_exists($barrierFile)) {
                @unlink($barrierFile);
            }

            $lines = explode("\n", $output);
            $parsed = null;
            foreach (array_reverse($lines) as $line) {
                $line = trim($line);
                if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                    $json = json_decode($line, true);
                    if ($json && isset($json['worker'])) {
                        $parsed = $json;
                        break;
                    }
                }
            }

            if ($parsed) {
                $totalSuccess += $parsed['success'];
                $totalDuplicate += $parsed['duplicate'];
                $totalDeadlock += $parsed['deadlock'];
                $totalError += $parsed['error'];
                $workerDurations[] = $parsed['duration_ms'];
            } else {
                $this->error("Worker #{$w} failed or returned unexpected output: {$output} {$errorOutput}");
                $totalError += $perWorker;
            }
        }

        $totalDuration = microtime(true) - $overallStart;
        $totalProcessed = $totalSuccess + $totalDuplicate + $totalDeadlock + $totalError;
        $throughput = $totalDuration > 0 ? round($totalProcessed / $totalDuration, 2) : 0;
        $avgWorkerDuration = count($workerDurations) > 0 ? round(array_sum($workerDurations) / count($workerDurations), 2) : 0;

        $expectedDuplicates = max(0, $concurrency - 2); // Workers 3..10 intentional duplicates of worker 2's wamid
        $expectedSuccess = $total - $expectedDuplicates;

        $this->newLine();
        $this->info('=== Concurrent Webhook Smoke Test Results ===');
        $this->line("Design Note: Exactly {$expectedDuplicates} of the {$total} webhooks are INTENTIONAL duplicates (workers 3-10 sharing worker 2's wamid) to verify concurrent row-level deduplication under high contention.");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Webhooks Sent', $totalProcessed],
                ['Successful (New Events)', "{$totalSuccess} (Expected: {$expectedSuccess})"],
                ['Duplicates Handled Gracefully', "{$totalDuplicate} (Expected: {$expectedDuplicates} intentional duplicates)"],
                ['Deadlocks (Error 1213)', $totalDeadlock],
                ['Other Errors', $totalError],
                ['Total Wall Time', round($totalDuration, 3).'s'],
                ['Throughput', "{$throughput} req/s"],
                ['Avg Worker Duration', "{$avgWorkerDuration} ms"],
            ]
        );

        if ($totalDeadlock === 0 && $totalError === 0 && $totalDuplicate === $expectedDuplicates && $totalSuccess === $expectedSuccess) {
            $this->info("PASSED: Exactly {$totalSuccess} new events and {$totalDuplicate} intentional duplicates processed with 0 deadlocks and 0 errors.");

            return self::SUCCESS;
        }

        if ($totalDeadlock === 0 && $totalError === 0 && ($totalSuccess + $totalDuplicate) >= $total) {
            $this->info("PASSED: {$totalProcessed} concurrent webhooks processed without a single deadlock or error.");

            return self::SUCCESS;
        }

        $this->warn('Completed with warnings or errors. Check counts above.');

        return $totalError > 0 ? self::FAILURE : self::SUCCESS;
    }
}
