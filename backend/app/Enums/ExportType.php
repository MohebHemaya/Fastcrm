<?php

namespace App\Enums;

enum ExportType: string
{
    case DRIVERS = 'drivers';
    case WORK_ORDER = 'work_order';
    case GROUP_PERFORMANCE = 'group_performance';
    case ORDER_PDF = 'order_pdf';
    case DRIVER_PROFILE_PDF = 'driver_profile_pdf';

    public function label(): string
    {
        return match ($this) {
            self::DRIVERS => 'تصدير السائقين (Excel)',
            self::WORK_ORDER => 'تقرير أمر العمل (Excel)',
            self::GROUP_PERFORMANCE => 'تقرير أداء المجموعات (Excel)',
            self::ORDER_PDF => 'بيان الرحلة (PDF)',
            self::DRIVER_PROFILE_PDF => 'ملف السائق (PDF)',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::DRIVERS, self::WORK_ORDER, self::GROUP_PERFORMANCE => 'xlsx',
            self::ORDER_PDF, self::DRIVER_PROFILE_PDF => 'pdf',
        };
    }
}
