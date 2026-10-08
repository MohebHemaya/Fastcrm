<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;

class StringValueBinder extends DefaultValueBinder implements IValueBinder
{
    /**
     * Bind value to a cell, forcing phone numbers and codes to string type.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            // Check for phone numbers (+201..., 01...), order codes (WO-...), or leading zeroes
            if (
                str_starts_with($value, '+')
                || str_starts_with($value, '0')
                || str_starts_with($value, 'WO-')
                || preg_match('/^\+?[0-9]{8,16}$/', $value)
            ) {
                $cell->setValueExplicit($value, DataType::TYPE_STRING);

                return true;
            }
        }

        return parent::bindValue($cell, $value);
    }
}
