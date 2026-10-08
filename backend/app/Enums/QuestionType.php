<?php

namespace App\Enums;

enum QuestionType: string
{
    case SINGLE_CHOICE = 'single_choice';
    case YES_NO = 'yes_no';
    case NUMBER = 'number';
    case TEXT = 'text';

    /**
     * Localized Arabic label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SINGLE_CHOICE => 'اختيار من متعدد',
            self::YES_NO => 'نعم / لا',
            self::NUMBER => 'رقم',
            self::TEXT => 'نص حر',
        };
    }
}
