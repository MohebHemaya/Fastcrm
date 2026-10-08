<?php

namespace App\Enums;

enum ImportStatus: string
{
    case UPLOADED = 'uploaded';
    case PARSING = 'parsing';
    case READY_FOR_REVIEW = 'ready_for_review';
    case COMMITTING = 'committing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Localized Arabic label for import status.
     */
    public function label(): string
    {
        return match ($this) {
            self::UPLOADED => 'تم الرفع',
            self::PARSING => 'جاري المعالجة والفحص',
            self::READY_FOR_REVIEW => 'جاهز للمراجعة',
            self::COMMITTING => 'جاري الاعتماد والإضافة',
            self::COMPLETED => 'مكتمل',
            self::FAILED => 'فشل',
            self::CANCELLED => 'ملغي',
        };
    }
}
