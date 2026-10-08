<?php

namespace App\Enums;

enum WhatsAppMessageStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'في الانتظار',
            self::SENT => 'أُرسلت',
            self::DELIVERED => 'وصلت',
            self::READ => 'قُرئت',
            self::FAILED => 'فشلت',
        };
    }

    /**
     * Return status order (higher = later in lifecycle).
     */
    public function order(): int
    {
        return match ($this) {
            self::QUEUED => 0,
            self::SENT => 1,
            self::DELIVERED => 2,
            self::READ => 3,
            self::FAILED => 99,
        };
    }
}
