<?php

namespace App\Exports;

use App\Models\Driver;
use App\Models\Question;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class StreamingDriversExport
{
    /** @var Collection<int, Question> */
    protected Collection $questions;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected array $filters = []
    ) {
        $this->questions = Question::where('is_active', true)
            ->with('currentVersion')
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    /**
     * @return Builder<Driver>
     */
    public function query(): Builder
    {
        $query = Driver::query()->with([
            'whatsappGroups',
            'metrics',
            'answers.option',
        ]);

        if (! empty($this->filters['search'])) {
            $search = (string) $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (! empty($this->filters['group_id'])) {
            $groupId = (int) $this->filters['group_id'];
            $query->whereHas('whatsappGroups', function ($q) use ($groupId) {
                $q->where('whatsapp_groups.id', $groupId);
            });
        }

        if (! empty($this->filters['start_date']) && ! empty($this->filters['end_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($this->filters['start_date'])->startOfDay(),
                Carbon::parse($this->filters['end_date'])->endOfDay(),
            ]);
        }

        return $query->orderBy('id', 'asc');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $headers = [
            'معرف السائق',
            'الاسم',
            'رقم الهاتف',
            'الحالة',
            'المجموعات',
            'تاريخ التسجيل',
        ];

        foreach ($this->questions as $question) {
            $headers[] = $question->text;
        }

        return array_merge($headers, [
            'رحلات مكتملة',
            'اعتذار متأخر',
            'لم يحضر',
            'تأخير',
            'متوسط التقييم',
            'آخر رحلة',
        ]);
    }

    /**
     * Build row cells for OpenSpout streaming writer.
     */
    public function mapRow(Driver $driver): Row
    {
        $groups = $driver->whatsappGroups->pluck('name')->implode('، ');

        $answersMap = [];
        foreach ($driver->answers as $ans) {
            $answersMap[$ans->question_id] = $ans->display_value;
        }

        $cells = [
            Cell::fromValue($driver->id),
            Cell::fromValue($driver->name ?: '—'),
            new StringCell((string) $driver->phone, null),
            Cell::fromValue($driver->status->label()),
            Cell::fromValue($groups ?: '—'),
            Cell::fromValue($driver->created_at?->format('Y-m-d H:i') ?? '—'),
        ];

        foreach ($this->questions as $question) {
            $cells[] = Cell::fromValue($answersMap[$question->id] ?? '—');
        }

        $m = $driver->metrics;
        $cells[] = Cell::fromValue($m ? $m->trips_completed : 0);
        $cells[] = Cell::fromValue($m ? $m->cancellations : 0);
        $cells[] = Cell::fromValue($m ? $m->no_shows : 0);
        $cells[] = Cell::fromValue($m ? $m->late_count : 0);
        $cells[] = Cell::fromValue(($m && $m->avg_rating !== null) ? number_format((float) $m->avg_rating, 2) : '—');
        $cells[] = Cell::fromValue(($m && $m->last_trip_at) ? Carbon::parse($m->last_trip_at)->format('Y-m-d H:i') : '—');

        return new Row($cells);
    }

    /**
     * Export drivers to a file on local filesystem.
     */
    public function exportToFile(string $filePath): void
    {
        $options = new Options;
        $writer = new Writer($options);
        $writer->openToFile($filePath);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('السائقون');

        // RTL Sheet View
        $sheetView = new SheetView;
        $sheetView->setRightToLeft(true);
        $sheet->setSheetView($sheetView);

        // Column widths
        $sheet->setColumnWidth(12, 1);
        $sheet->setColumnWidth(24, 2);
        $sheet->setColumnWidth(18, 3);
        $sheet->setColumnWidth(16, 4);
        $sheet->setColumnWidth(24, 5);
        $sheet->setColumnWidth(16, 6);

        // Header style matching Phase 7 specs
        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setFontSize(11)
            ->setBackgroundColor(Color::toARGB('1F2937'))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);

        $writer->addRow(Row::fromValues(array_values($this->headings()), $headerStyle));

        // Stream drivers in chunks of 500 to keep memory footprint minimal
        $this->query()->chunk(500, function ($drivers) use ($writer) {
            /** @var Collection<int, Driver> $drivers */
            foreach ($drivers as $driver) {
                /** @var Driver $driver */
                $writer->addRow($this->mapRow($driver));
            }
        });

        $writer->close();
    }

    /**
     * Export drivers directly to a Laravel storage disk.
     */
    public function exportToDisk(string $disk, string $path): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'drivers_export_');
        if ($tempPath === false) {
            throw new \RuntimeException('Failed to create temporary file for streaming export.');
        }

        try {
            $this->exportToFile($tempPath);

            $stream = fopen($tempPath, 'r');
            if ($stream === false) {
                throw new \RuntimeException("Unable to open temp file {$tempPath} for reading.");
            }

            Storage::disk($disk)->put($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
