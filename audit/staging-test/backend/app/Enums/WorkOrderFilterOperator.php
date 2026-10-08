<?php

namespace App\Enums;

enum WorkOrderFilterOperator: string
{
    case IN = 'in';
    case EQ = 'eq';
    case GTE = 'gte';
    case LTE = 'lte';

    public function toSqlOperator(): string
    {
        return match ($this) {
            self::IN => 'in',
            self::EQ => '=',
            self::GTE => '>=',
            self::LTE => '<=',
        };
    }
}
