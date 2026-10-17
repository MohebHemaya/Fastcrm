<?php

namespace App\Jobs;

use App\Contracts\WhatsAppProvider;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Exceptions\WhatsAppPermanentException;
use App\Exceptions\WhatsAppRetryableException;
use App\Models\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends a single queued WhatsApp message through the active provider.
 *
 * - Idempotent: skips if provider_message_id is already set (already sent).
 * - Rate limited: respects whatsapp.send_rate_per_minute config.
 * - Retryable vs permanent: only retryable exceptions are retried.
 */
class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $messageId
    ) {}

    /**
     * @return array<RateLimited>
     */
    public function middleware(): array
    {
        return [new RateLimited('whatsapp_send')];
    }

    public function handle(WhatsAppProvider $provider): void
    {
        $msg = WhatsAppMessage::find($this->messageId);

        if (! $msg) {
            // Message was deleted — silently discard.
            return;
        }

        // Idempotency: if already sent, skip
        if ($msg->provider_message_id !== null) {
            return;
        }

        // Skip opted_out / blocked drivers (unless it is the final opt-out confirmation)
        $driver = $msg->driver;
        $isOptOutConfirmation = ($msg->payload['is_opt_out_confirmation'] ?? false) === true;
        if (! $isOptOutConfirmation && in_array($driver->status->value, ['opted_out', 'blocked'], true)) {
            $msg->update([
                'status' => WhatsAppMessageStatus::FAILED,
                'error_message' => 'السائق محظور أو ألغى الاشتراك.',
            ]);

            return;
        }

        try {
            $providerMessageId = $this->sendViaProvider($provider, $msg);

            $msg->update([
                'provider_message_id' => $providerMessageId,
                'status' => WhatsAppMessageStatus::SENT,
                'sent_at' => now(),
            ]);
        } catch (WhatsAppPermanentException $e) {
            $msg->update([
                'status' => WhatsAppMessageStatus::FAILED,
                'error_code' => (string) $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);
            // Do NOT rethrow — permanent errors are not retried
            $this->fail($e);
        } catch (WhatsAppRetryableException $e) {
            $msg->update([
                'status' => WhatsAppMessageStatus::FAILED,
                'error_code' => (string) $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);
            // Rethrow to trigger queue retry
            throw $e;
        }
    }

    /**
     * Dispatch the appropriate provider method based on message type.
     */
    private function sendViaProvider(WhatsAppProvider $provider, WhatsAppMessage $msg): string
    {
        $driver = $msg->driver;
        $payload = $msg->payload ?? [];

        return match ($msg->type) {
            WhatsAppMessageType::TEMPLATE => $provider->sendTemplate(
                $driver->phone,
                $payload['templateName'] ?? $msg->template_name,
                $payload['lang'] ?? 'ar',
                array_merge($payload['params'] ?? [], [
                    '_rendered_body' => $msg->body ?? ($payload['body'] ?? ''),
                ]),
                $payload['buttons'] ?? null,
            ),
            WhatsAppMessageType::BUTTON => $provider->sendButtons(
                $driver->phone,
                $msg->body ?? '',
                $payload['buttons'] ?? [],
            ),
            WhatsAppMessageType::LIST => $provider->sendList(
                $driver->phone,
                $msg->body ?? '',
                $payload['buttonLabel'] ?? 'اختر إجابة',
                $payload['rows'] ?? [],
            ),
            WhatsAppMessageType::IMAGE => $provider->sendImage(
                $driver->phone,
                $payload['media_url'] ?? '',
                $payload['caption'] ?? ($msg->body ?? ''),
            ),
            default => $provider->sendText($driver->phone, $msg->body ?? ''),
        };
    }

    public function failed(Throwable $exception): void
    {
        WhatsAppMessage::where('id', $this->messageId)
            ->where('status', '!=', WhatsAppMessageStatus::FAILED->value)
            ->update([
                'status' => WhatsAppMessageStatus::FAILED,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
