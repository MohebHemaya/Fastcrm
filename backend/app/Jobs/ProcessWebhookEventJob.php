<?php

namespace App\Jobs;

use App\DTOs\WhatsApp\InboundMessage;
use App\DTOs\WhatsApp\StatusUpdate;
use App\Models\WebhookEvent;
use App\Services\InboundMessageRouter;
use App\Services\WebhookStatusUpdater;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes a single webhook event that was stored for idempotency.
 */
class ProcessWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly int $webhookEventId
    ) {}

    public function handle(
        InboundMessageRouter $router,
        WebhookStatusUpdater $statusUpdater
    ): void {
        $event = WebhookEvent::find($this->webhookEventId);

        if (! $event || $event->processed_at !== null) {
            // Already processed (idempotent) or deleted
            return;
        }

        $payload = $event->payload;

        // Process inbound messages
        foreach ($payload['messages'] ?? [] as $msgData) {
            try {
                $inbound = new InboundMessage(
                    from: $msgData['from'],
                    type: $msgData['type'] ?? 'text',
                    text: $msgData['text'] ?? null,
                    payloadId: $msgData['payload_id'] ?? null,
                    providerMessageId: $msgData['id'],
                    timestamp: (int) ($msgData['timestamp'] ?? now()->timestamp),
                );
                $router->handle($inbound);
            } catch (\Throwable $e) {
                Log::error('[ProcessWebhookEventJob] Error routing inbound message', [
                    'error' => $e->getMessage(),
                    'msg' => $msgData,
                ]);
            }
        }

        // Process status updates
        foreach ($payload['statuses'] ?? [] as $stData) {
            try {
                $update = new StatusUpdate(
                    providerMessageId: $stData['id'],
                    status: $stData['status'],
                    errorCode: $stData['error_code'] ?? null,
                );
                $statusUpdater->handle($update);
            } catch (\Throwable $e) {
                Log::error('[ProcessWebhookEventJob] Error processing status update', [
                    'error' => $e->getMessage(),
                    'update' => $stData,
                ]);
            }
        }

        $event->update(['processed_at' => now()]);
    }
}
