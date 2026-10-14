<?php

namespace App\Contracts;

use App\DTOs\WhatsApp\InboundMessage;
use App\DTOs\WhatsApp\StatusUpdate;
use Illuminate\Http\Request;

/**
 * Provider-agnostic WhatsApp messaging interface.
 * All sending goes through this contract; never call a provider directly.
 */
interface WhatsAppProvider
{
    /**
     * Send a WhatsApp template message.
     *
     * @param  array<string>  $params  template parameter values
     * @param  array<array{id: string, title: string}>|null  $buttons
     */
    public function sendTemplate(
        string $to,
        string $name,
        string $lang,
        array $params = [],
        ?array $buttons = null
    ): string; // returns provider_message_id

    /**
     * Send a free-form text message (only valid within 24h window).
     */
    public function sendText(string $to, string $body): string;

    /**
     * Send a message with up to 3 quick-reply buttons.
     *
     * @param  array<array{id: string, title: string}>  $buttons  max 3
     */
    public function sendButtons(string $to, string $body, array $buttons): string;

    /**
     * Send a list message with up to 10 rows.
     *
     * @param  array<array{id: string, title: string, description?: string}>  $rows  max 10
     */
    public function sendList(string $to, string $body, string $buttonLabel, array $rows): string;

    /**
     * Verify the webhook challenge request.
     * Should return the challenge string on success or throw on failure.
     */
    public function verifyWebhook(Request $request): string;

    /**
     * Parse an inbound webhook request into normalized events.
     *
     * @return array<InboundMessage|StatusUpdate>
     */
    public function parseWebhook(Request $request): array;

    /**
     * Actively poll and sync recent message statuses (e.g. delivered, read) from provider if supported.
     */
    public function syncRecentStatuses(int $limit = 30): int;

    /**
     * Actively poll recent inbound messages for a specific phone/chat if supported.
     */
    public function syncChatInbound(string $phone): int;
}
