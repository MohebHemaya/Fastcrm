<?php

namespace App\Enums;

enum WorkOrderOutcome: string
{
    case ATTENDED = 'attended';
    case LATE = 'late';
    case NO_SHOW = 'no_show';
    case CANCELLED_LATE = 'cancelled_late';

    public function label(): string
    {
        return match ($this) {
            self::ATTENDED => 'حضر',
            self::LATE => 'تأخر',
            self::NO_SHOW => 'لم يحضر',
            self::CANCELLED_LATE => 'اعتذر متأخرًا',
        };
    }

    public function isCompletedTrip(): bool
    {
        return in_array($this, [self::ATTENDED, self::LATE], true);
    }
}
