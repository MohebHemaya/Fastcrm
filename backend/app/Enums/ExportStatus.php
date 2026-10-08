<?php

namespace App\Enums;

enum ExportStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case DONE = 'done';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'قيد الانتظار',
            self::PROCESSING => 'جارٍ التجهيز',
            self::DONE => 'جاهز للتحميل',
            self::FAILED => 'فشل التصدير',
        };
    }
}
