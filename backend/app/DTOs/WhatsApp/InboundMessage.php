<?php

namespace App\DTOs\WhatsApp;

/**
 * DTO for an inbound message received via webhook.
 */
final readonly class InboundMessage
{
    public function __construct(
        public string $from,           // E.164 phone number
        public string $type,           // text|button|list|audio|image|other
        public ?string $text,          // message body
        public ?string $payloadId,     // button/list reply payload id
        public string $providerMessageId,
        public int $timestamp,         // unix timestamp
        public ?string $mediaUrl = null,
        public ?string $mediaType = null,
    ) {}
}
