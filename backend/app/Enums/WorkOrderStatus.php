<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case DRAFT = 'draft';
    case SENDING = 'sending';
    case FILLING = 'filling';
    case FILLED = 'filled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::SENDING => 'جاري الإرسال',
            self::FILLING => 'جاري استقبال السائقين',
            self::FILLED => 'مكتمل العدد',
            self::IN_PROGRESS => 'قيد التنفيذ',
            self::COMPLETED => 'مكتمل',
            self::CANCELLED => 'ملغي',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }
}
