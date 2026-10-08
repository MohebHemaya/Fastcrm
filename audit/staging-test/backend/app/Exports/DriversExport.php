<?php

namespace App\Exports;

use App\Models\Driver;
use App\Models\Question;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DriversExport extends DefaultValueBinder implements FromQuery, WithColumnWidths, WithCustomChunkSize, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @var Collection<int, Question> */
    protected $questions;

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

    public function bindValue(Cell $cell, $value): bool
    {
        return (new StringValueBinder)->bindValue($cell, $value);
    }

    public function title(): string
    {
        return 'السائقون';
    }

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

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 24,
            'C' => 18,
            'D' => 16,
            'E' => 24,
            'F' => 14,
            'G' => 14,
            'H' => 12,
            'I' => 12,
            'J' => 14,
            'K' => 12,
            'L' => 14,
        ];
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
     * @param  Driver  $driver
     * @return array<int, mixed>
     */
    public function map($driver): array
    {
        $groups = $driver->whatsappGroups->pluck('name')->implode('، ');

        // Map answers by question_id
        $answersMap = [];
        foreach ($driver->answers as $ans) {
            $answersMap[$ans->question_id] = $ans->display_value;
        }

        $row = [
            $driver->id,
            $driver->name ?: '—',
            (string) $driver->phone, // Bound as DataType::TYPE_STRING by StringValueBinder
            $driver->status->label(),
            $groups ?: '—',
            $driver->created_at?->format('Y-m-d H:i') ?? '—',
        ];

        foreach ($this->questions as $question) {
            $row[] = $answersMap[$question->id] ?? '—';
        }

        $m = $driver->metrics;
        $row[] = $m ? $m->trips_completed : 0;
        $row[] = $m ? $m->cancellations : 0;
        $row[] = $m ? $m->no_shows : 0;
        $row[] = $m ? $m->late_count : 0;
        $row[] = ($m && $m->avg_rating !== null) ? number_format((float) $m->avg_rating, 2) : '—';
        $row[] = ($m && $m->last_trip_at) ? Carbon::parse($m->last_trip_at)->format('Y-m-d H:i') : '—';

        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1F2937'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Set worksheet RTL for Arabic readability
                $event->sheet->getDelegate()->setRightToLeft(true);
            },
        ];
    }
}
