<?php

namespace App\Services;

use Generator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;

class ImportChunkReadFilter implements IReadFilter
{
    private int $startRow = 0;

    private int $endRow = 0;

    public function setRows(int $startRow, int $chunkSize): void
    {
        $this->startRow = $startRow;
        $this->endRow = $startRow + $chunkSize - 1;
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $row >= $this->startRow && $row <= $this->endRow;
    }
}

class ImportFileParser
{
    /**
     * Parse file in a memory-safe streaming generator.
     * Yields: ['row_number' => int, 'raw_phone' => string, 'group_name' => string]
     *
     * @return Generator<int, array{row_number: int, raw_phone: string, group_name: string}>
     */
    public static function streamRows(string $filePath): Generator
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            yield from self::streamCsv($filePath);
        } else {
            yield from self::streamExcel($filePath);
        }
    }

    /**
     * Stream CSV line by line.
     *
     * @return Generator<int, array{row_number: int, raw_phone: string, group_name: string}>
     */
    protected static function streamCsv(string $filePath): Generator
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new RuntimeException('تعذر فتح ملف الاستيراد.');
        }

        $rowNumber = 0;
        $phoneCol = 0;
        $groupCol = 1;
        $isFirstRow = true;

        try {
            while (($data = fgetcsv($handle, 0, ',')) !== false) {
                // If single column with semicolon or tab delimiter, detect and split
                if (count($data) === 1 && isset($data[0])) {
                    if (str_contains($data[0], ';')) {
                        $data = str_getcsv($data[0], ';');
                    } elseif (str_contains($data[0], "\t")) {
                        $data = str_getcsv($data[0], "\t");
                    }
                }

                $rowNumber++;

                // Strip UTF-8 BOM on first column of first row if present
                if ($isFirstRow && isset($data[0])) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]);
                }

                if ($isFirstRow) {
                    $isFirstRow = false;
                    if (self::isHeaderRow($data)) {
                        [$phoneCol, $groupCol] = self::detectColumns($data);

                        continue;
                    }
                }

                $rawPhone = isset($data[$phoneCol]) ? trim((string) $data[$phoneCol]) : '';
                $groupName = isset($data[$groupCol]) ? trim((string) $data[$groupCol]) : '';

                if ($rawPhone === '' && $groupName === '') {
                    continue; // Skip blank lines
                }

                yield [
                    'row_number' => $rowNumber,
                    'raw_phone' => $rawPhone,
                    'group_name' => $groupName !== '' ? $groupName : 'عام',
                ];
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Stream Excel file in memory-safe chunks using PhpSpreadsheet.
     *
     * @return Generator<int, array{row_number: int, raw_phone: string, group_name: string}>
     */
    protected static function streamExcel(string $filePath): Generator
    {
        $inputFileType = IOFactory::identify($filePath);
        $reader = IOFactory::createReader($inputFileType);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $chunkFilter = new ImportChunkReadFilter;
        $reader->setReadFilter($chunkFilter);

        // Get total rows by inspecting first sheet info
        $info = $reader->listWorksheetInfo($filePath);
        $totalRows = $info[0]['totalRows'] ?? 0;

        $chunkSize = 1000;
        $rowNumber = 0;
        $phoneColIndex = 0;
        $groupColIndex = 1;
        $headerDetected = false;

        for ($startRow = 1; $startRow <= $totalRows; $startRow += $chunkSize) {
            $chunkFilter->setRows($startRow, $chunkSize);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $highestRow = min($startRow + $chunkSize - 1, $totalRows);

            for ($r = $startRow; $r <= $highestRow; $r++) {
                $rowNumber++;

                $colA = (string) $sheet->getCell([1, $r])->getFormattedValue();
                $colB = (string) $sheet->getCell([2, $r])->getFormattedValue();

                if ($rowNumber === 1 && ! $headerDetected) {
                    if (self::isHeaderRow([$colA, $colB])) {
                        [$phoneColIndex, $groupColIndex] = self::detectColumns([$colA, $colB]);
                        $headerDetected = true;

                        continue;
                    }
                }

                $rawPhone = trim($phoneColIndex === 0 ? $colA : $colB);
                $groupName = trim($groupColIndex === 1 ? $colB : $colA);

                if ($rawPhone === '' && $groupName === '') {
                    continue;
                }

                yield [
                    'row_number' => $rowNumber,
                    'raw_phone' => $rawPhone,
                    'group_name' => $groupName !== '' ? $groupName : 'عام',
                ];
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            gc_collect_cycles();
        }
    }

    /**
     * Check if a row represents header titles.
     *
     * @param  array<int, string|null>  $row
     */
    public static function isHeaderRow(array $row): bool
    {
        $keywords = [
            'phone', 'mobile', 'tel', 'cell', 'number',
            'هاتف', 'رقم', 'موبايل', 'تليفون', 'جوال',
            'group', 'name', 'source', 'مجموعة', 'جروب', 'مصدر',
        ];

        foreach ($row as $cell) {
            if ($cell === null) {
                continue;
            }
            $clean = mb_strtolower(trim((string) $cell));
            foreach ($keywords as $kw) {
                if (str_contains($clean, $kw)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect which column is phone and which is group.
     * Returns: [phoneColIndex, groupColIndex]
     *
     * @param  array<int, string|null>  $headerRow
     * @return array{0: int, 1: int}
     */
    public static function detectColumns(array $headerRow): array
    {
        $col0 = mb_strtolower(trim((string) ($headerRow[0] ?? '')));
        $col1 = mb_strtolower(trim((string) ($headerRow[1] ?? '')));

        $phoneKeywords = ['phone', 'mobile', 'tel', 'cell', 'number', 'هاتف', 'رقم', 'موبايل', 'تليفون', 'جوال'];
        $groupKeywords = ['group', 'source', 'مجموعة', 'جروب', 'مصدر'];

        $col0IsGroup = false;
        $col1IsPhone = false;

        foreach ($groupKeywords as $kw) {
            if (str_contains($col0, $kw)) {
                $col0IsGroup = true;
                break;
            }
        }

        foreach ($phoneKeywords as $kw) {
            if (str_contains($col1, $kw)) {
                $col1IsPhone = true;
                break;
            }
        }

        if ($col0IsGroup && $col1IsPhone) {
            return [1, 0];
        }

        return [0, 1];
    }
}
