<?php

namespace App\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\DTOs\WhatsApp\InboundMessage;
use App\DTOs\WhatsApp\StatusUpdate;
use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Fake WhatsApp provider for development and tests.
 * Stores outgoing messages in the DB; never makes network calls.
 */
class FakeWhatsAppProvider implements WhatsAppProvider
{
    public function sendTemplate(
        string $to,
        string $name,
        string $lang,
        array $params = [],
        ?array $buttons = null
    ): string {
        $messageId = 'fake_'.uniqid('tpl_', true);
        Log::debug("[FakeWhatsApp] sendTemplate to={$to} name={$name} lang={$lang}", compact('params', 'buttons'));

        return $messageId;
    }

    public function sendText(string $to, string $body): string
    {
        $messageId = 'fake_'.uniqid('txt_', true);
        Log::debug("[FakeWhatsApp] sendText to={$to}", compact('body'));

        return $messageId;
    }

    public function sendButtons(string $to, string $body, array $buttons): string
    {
        $messageId = 'fake_'.uniqid('btn_', true);
        Log::debug("[FakeWhatsApp] sendButtons to={$to}", compact('body', 'buttons'));

        return $messageId;
    }

    public function sendList(string $to, string $body, string $buttonLabel, array $rows): string
    {
        $messageId = 'fake_'.uniqid('lst_', true);
        Log::debug("[FakeWhatsApp] sendList to={$to}", compact('body', 'buttonLabel', 'rows'));

        return $messageId;
    }

    public function verifyWebhook(Request $request): string
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = config('whatsapp.verify_token', 'fake_verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken && $challenge) {
            return (string) $challenge;
        }

        throw ValidationException::withMessages(['webhook' => 'Invalid verification token.']);
    }

    /**
     * Parse a simulated inbound webhook payload.
     * In the fake provider we accept a simplified JSON body
     * (the same shape we generate in tests/artisan command).
     *
     * @return array<InboundMessage|StatusUpdate>
     */
    public function parseWebhook(Request $request): array
    {
        $payload = $request->json()->all();
        $events = [];

        // Inbound messages
        foreach ($payload['messages'] ?? [] as $msg) {
            $events[] = new InboundMessage(
                from: $msg['from'],
                type: $msg['type'] ?? 'text',
                text: $msg['text'] ?? null,
                payloadId: $msg['payload_id'] ?? null,
                providerMessageId: $msg['id'],
                timestamp: (int) ($msg['timestamp'] ?? now()->timestamp),
            );
        }

        // Status updates
        foreach ($payload['statuses'] ?? [] as $st) {
            $events[] = new StatusUpdate(
                providerMessageId: $st['id'],
                status: $st['status'],
                errorCode: $st['error_code'] ?? null,
            );
        }

        return $events;
    }

    /**
     * Simulate an inbound message by updating a WhatsAppMessage row's status.
     * Used in artisan simulate commands and tests.
     */
    public function simulateStatusUpdate(string $providerMessageId, WhatsAppMessageStatus $status): void
    {
        $msg = WhatsAppMessage::where('provider_message_id', $providerMessageId)->first();
        if (! $msg) {
            return;
        }

        // Only upgrade — never regress (e.g., read → delivered is ignored)
        if ($msg->status->order() >= $status->order()) {
            return;
        }

        $msg->status = $status;
        match ($status) {
            WhatsAppMessageStatus::SENT => $msg->sent_at = now(),
            WhatsAppMessageStatus::DELIVERED => $msg->delivered_at = now(),
            WhatsAppMessageStatus::READ => $msg->read_at = now(),
            default => null,
        };
        $msg->save();
    }
}
