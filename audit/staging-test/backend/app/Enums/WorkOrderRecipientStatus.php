<?php

namespace App\Enums;

enum WorkOrderRecipientStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case CONFIRMED = 'confirmed';
    case BACKUP = 'backup';
    case DECLINED = 'declined';
    case TOO_LATE = 'too_late';
    case NO_RESPONSE = 'no_response';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الإرسال',
            self::SENT => 'تم الإرسال',
            self::CONFIRMED => 'مؤكد',
            self::BACKUP => 'احتياطي',
            self::DECLINED => 'معتذر',
            self::TOO_LATE => 'متأخر (اكتمل العدد)',
            self::NO_RESPONSE => 'لم يرد',
            self::FAILED => 'فشل الإرسال',
            self::CANCELLED => 'ملغي',
        };
    }
}
