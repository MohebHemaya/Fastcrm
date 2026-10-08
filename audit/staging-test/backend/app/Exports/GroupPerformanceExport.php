<?php

namespace App\Exports;

use App\Services\DashboardService;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GroupPerformanceExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected array $filters = []
    ) {}

    public function title(): string
    {
        return 'أداء المجموعات';
    }

    public function headings(): array
    {
        return [
            'اسم المجموعة',
            'إجمالي السائقين',
            'تمت دعوتهم',
            'مهتمون',
            'سائقون نشطون',
            'معتذرون',
            'إلغاء اشتراك',
            'نسبة التحويل (%)',
        ];
    }

    public function collection(): Enumerable
    {
        $dashboardService = app(DashboardService::class);
        $groups = $dashboardService->getGroupPerformance(
            startDate: $this->filters['start_date'] ?? null,
            endDate: $this->filters['end_date'] ?? null
        );

        return collect($groups);
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<int, mixed>
     */
    public function map($group): array
    {
        return [
            $group['name'] ?? '—',
            $group['imported'] ?? 0,
            $group['invited'] ?? 0,
            $group['interested'] ?? 0,
            $group['active'] ?? 0,
            $group['declined'] ?? 0,
            $group['opted_out'] ?? 0,
            number_format((float) ($group['conversion_rate'] ?? 0), 1).'%',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1E293B'],
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
                $event->sheet->getDelegate()->setRightToLeft(true);
            },
        ];
    }
}
