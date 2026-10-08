<?php

namespace App\Jobs;

use App\DTOs\PhoneNormalizationResult;
use App\Enums\DriverStatus;
use App\Enums\ImportRowClassification;
use App\Enums\ImportStatus;
use App\Models\Driver;
use App\Models\Import;
use App\Models\ImportRow;
use App\Services\Audit;
use App\Services\ImportFileParser;
use App\Services\PhoneNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParseImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public Import $import
    ) {}

    public function handle(): void
    {
        $this->import->update([
            'status' => ImportStatus::PARSING,
            'error' => null,
        ]);

        $storedPath = $this->import->stored_path;
        $filePath = file_exists($storedPath)
            ? $storedPath
            : Storage::disk('local')->path($storedPath);

        if (! file_exists($filePath)) {
            $this->import->update([
                'status' => ImportStatus::FAILED,
                'error' => 'ملف الاستيراد غير موجود على الخادم.',
            ]);

            return;
        }

        $counts = [
            'total' => 0,
            'new' => 0,
            'duplicate_in_file' => 0,
            'existing' => 0,
            'active' => 0,
            'invalid' => 0,
        ];

        // Track normalized phones seen within this import
        /** @var array<string, true> $seenInFile */
        $seenInFile = [];

        $batch = [];
        $batchSize = 500;

        try {
            foreach (ImportFileParser::streamRows($filePath) as $row) {
                $batch[] = $row;

                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch, $seenInFile, $counts);
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                $this->processBatch($batch, $seenInFile, $counts);
            }

            $this->import->update([
                'status' => ImportStatus::READY_FOR_REVIEW,
                'counts' => $counts,
            ]);

            Audit::log('import_parsed', $this->import, $counts);
        } catch (Throwable $e) {
            $this->import->update([
                'status' => ImportStatus::FAILED,
                'error' => 'حدث خطأ أثناء معالجة الملف: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Process a chunk of rows with batch DB checks and inserts.
     *
     * @param  list<array{row_number: int, raw_phone: string, group_name: string}>  $batch
     * @param  array<string, true>  $seenInFile
     * @param  array{total: int, new: int, duplicate_in_file: int, existing: int, active: int, invalid: int}  $counts
     */
    protected function processBatch(array $batch, array &$seenInFile, array &$counts): void
    {
        // 1. Normalize all phones in batch
        /** @var list<array{row_number: int, raw_phone: string, group_name: string, norm: PhoneNormalizationResult}> $normalizedBatch */
        $normalizedBatch = [];
        $validPhones = [];

        foreach ($batch as $item) {
            $norm = PhoneNormalizer::normalize($item['raw_phone']);
            $normalizedBatch[] = [
                'row_number' => $item['row_number'],
                'raw_phone' => $item['raw_phone'],
                'group_name' => $item['group_name'],
                'norm' => $norm,
            ];

            if ($norm->isValid && $norm->e164 !== null) {
                $validPhones[] = $norm->e164;
            }
        }

        // 2. Query existing drivers in DB for these phones
        /** @var Collection<string, Driver> $dbDrivers */
        $dbDrivers = collect();
        if (! empty($validPhones)) {
            $dbDrivers = Driver::whereIn('phone', array_unique($validPhones))
                ->get(['id', 'phone', 'status'])
                ->keyBy('phone');
        }

        // 3. Classify rows
        $rowsToInsert = [];
        $now = now();

        foreach ($normalizedBatch as $item) {
            $counts['total']++;
            $norm = $item['norm'];

            if (! $norm->isValid || $norm->e164 === null) {
                $counts['invalid']++;
                $rowsToInsert[] = [
                    'import_id' => $this->import->id,
                    'row_number' => $item['row_number'],
                    'raw_phone' => $item['raw_phone'],
                    'group_name' => $item['group_name'],
                    'normalized_phone' => null,
                    'classification' => ImportRowClassification::INVALID->value,
                    'error' => $norm->error,
                    'driver_id' => null,
                    'created_at' => $now,
                ];

                continue;
            }

            $phone = $norm->e164;
            $dbDriver = $dbDrivers->get($phone);

            if (isset($seenInFile[$phone])) {
                $counts['duplicate_in_file']++;
                $rowsToInsert[] = [
                    'import_id' => $this->import->id,
                    'row_number' => $item['row_number'],
                    'raw_phone' => $item['raw_phone'],
                    'group_name' => $item['group_name'],
                    'normalized_phone' => $phone,
                    'classification' => ImportRowClassification::DUPLICATE_IN_FILE->value,
                    'error' => null,
                    'driver_id' => $dbDriver?->id,
                    'created_at' => $now,
                ];
            } else {
                $seenInFile[$phone] = true;

                if ($dbDriver !== null) {
                    if ($dbDriver->status === DriverStatus::ACTIVE) {
                        $counts['active']++;
                        $classification = ImportRowClassification::ACTIVE;
                    } else {
                        $counts['existing']++;
                        $classification = ImportRowClassification::EXISTING;
                    }

                    $rowsToInsert[] = [
                        'import_id' => $this->import->id,
                        'row_number' => $item['row_number'],
                        'raw_phone' => $item['raw_phone'],
                        'group_name' => $item['group_name'],
                        'normalized_phone' => $phone,
                        'classification' => $classification->value,
                        'error' => null,
                        'driver_id' => $dbDriver->id,
                        'created_at' => $now,
                    ];
                } else {
                    $counts['new']++;
                    $rowsToInsert[] = [
                        'import_id' => $this->import->id,
                        'row_number' => $item['row_number'],
                        'raw_phone' => $item['raw_phone'],
                        'group_name' => $item['group_name'],
                        'normalized_phone' => $phone,
                        'classification' => ImportRowClassification::NEW->value,
                        'error' => null,
                        'driver_id' => null,
                        'created_at' => $now,
                    ];
                }
            }
        }

        ImportRow::insert($rowsToInsert);
    }
}
