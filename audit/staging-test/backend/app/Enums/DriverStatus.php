<?php

namespace App\Enums;

enum DriverStatus: string
{
    case NEW = 'new';
    case INVITED = 'invited';
    case INTERESTED = 'interested';
    case QUESTIONNAIRE_IN_PROGRESS = 'questionnaire_in_progress';
    case ACTIVE = 'active';
    case DECLINED = 'declined';
    case NO_RESPONSE = 'no_response';
    case SUSPENDED = 'suspended';
    case BLOCKED = 'blocked';
    case OPTED_OUT = 'opted_out';

    /**
     * Localized Arabic label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NEW => 'جديد',
            self::INVITED => 'تمت الدعوة',
            self::INTERESTED => 'مهتم',
            self::QUESTIONNAIRE_IN_PROGRESS => 'جاري استكمال الأسئلة',
            self::ACTIVE => 'شغال معانا',
            self::DECLINED => 'رفض',
            self::NO_RESPONSE => 'لم يرد',
            self::SUSPENDED => 'معلق مؤقتاً',
            self::BLOCKED => 'محظور',
            self::OPTED_OUT => 'إلغاء الاشتراك',
        };
    }

    /**
     * Check if this status is terminal.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::BLOCKED, self::OPTED_OUT => true,
            default => false,
        };
    }
}
