<?php

namespace App\DTOs\WhatsApp;

/**
 * DTO for a status update received via webhook.
 */
final readonly class StatusUpdate
{
    public function __construct(
        public string $providerMessageId,
        public string $status,            // sent|delivered|read|failed
        public ?string $errorCode = null, // non-null on failed
    ) {}
}
