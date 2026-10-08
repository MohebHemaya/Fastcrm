<?php

namespace App\Console\Commands;

use App\Contracts\WhatsAppProvider;
use App\Http\Controllers\WebhookController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Throwable;

class InternalWebhookWorkerCommand extends Command
{
    protected $signature = 'app:internal-webhook-worker
                            {runId : Unique run ID}
                            {workerId : Worker ID}
                            {count=10 : Number of webhook requests to generate}
                            {--barrier= : Optional barrier file path to wait for}';

    protected $description = 'Worker process for concurrent webhook stress testing';

    public function handle(WhatsAppProvider $provider): int
    {
        $runId = (string) $this->argument('runId');
        $workerId = (int) $this->argument('workerId');
        $count = (int) $this->argument('count');
        $barrierFile = (string) $this->option('barrier');

        // Wait for barrier release if barrier file is specified
        if ($barrierFile !== '') {
            $timeout = microtime(true) + 10;
            while (! file_exists($barrierFile)) {
                if (microtime(true) > $timeout) {
                    break;
                }
                usleep(2000); // 2ms check
            }
        }

        $controller = app(WebhookController::class);

        $successCount = 0;
        $duplicateCount = 0;
        $deadlockCount = 0;
        $errorCount = 0;

        $startTime = microtime(true);

        for ($i = 1; $i <= $count; $i++) {
            // Worker 2 sends the canonical shared message at i=5.
            // Workers 3 through 10 (8 workers total) intentionally send the exact same message ID concurrently
            // to test concurrent MySQL row-level lock deduplication under contention.
            $isDuplicate = ($i === 5 && $workerId > 1);
            $msgId = $isDuplicate
                ? "wamid_{$runId}_shared_dup_msg"
                : "wamid_{$runId}_w{$workerId}_m{$i}";

            $phone = '+2010'.str_pad((string) (($workerId * 100) + $i), 8, '0', STR_PAD_LEFT);

            $payload = [
                'messages' => [
                    [
                        'id' => $msgId,
                        'from' => $phone,
                        'type' => 'text',
                        'text' => 'نعم موافق',
                        'timestamp' => time(),
                    ],
                ],
            ];

            $content = (string) json_encode($payload);
            $request = Request::create(
                '/webhooks/whatsapp',
                'POST',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                $content
            );

            try {
                $response = $controller->receive($request, $provider);
                $status = $response->getStatusCode();
                $data = json_decode((string) $response->getContent(), true);

                if ($status === 200) {
                    if (($data['status'] ?? '') === 'duplicate_ignored') {
                        $duplicateCount++;
                    } else {
                        $successCount++;
                    }
                } else {
                    $errorCount++;
                }
            } catch (Throwable $e) {
                $errorMsg = $e->getMessage();
                if (str_contains($errorMsg, 'Deadlock') || str_contains($errorMsg, '1213')) {
                    $deadlockCount++;
                } else {
                    $errorCount++;
                }
            }
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $this->line((string) json_encode([
            'worker' => $workerId,
            'success' => $successCount,
            'duplicate' => $duplicateCount,
            'deadlock' => $deadlockCount,
            'error' => $errorCount,
            'duration_ms' => $durationMs,
        ]));

        return self::SUCCESS;
    }
}
