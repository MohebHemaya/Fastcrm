<?php

namespace App\Enums;

enum ImportRowClassification: string
{
    case NEW = 'new';
    case DUPLICATE_IN_FILE = 'duplicate_in_file';
    case EXISTING = 'existing';
    case ACTIVE = 'active';
    case INVALID = 'invalid';

    /**
     * Localized Arabic label for preview tabs.
     */
    public function label(): string
    {
        return match ($this) {
            self::NEW => 'جديد',
            self::DUPLICATE_IN_FILE => 'مكرر في الملف',
            self::EXISTING => 'موجود مسبقاً',
            self::ACTIVE => 'شغال معانا',
            self::INVALID => 'غلط / غير صالح',
        };
    }
}
