<?php

namespace App\Exports\Sheets;

use App\Exports\StringValueBinder;
use App\Models\WorkOrder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkOrderSummarySheet extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        protected WorkOrder $workOrder
    ) {}

    public function bindValue(Cell $cell, $value): bool
    {
        return (new StringValueBinder)->bindValue($cell, $value);
    }

    public function title(): string
    {
        return 'تفاصيل الطلب';
    }

    public function headings(): array
    {
        return ['البيان', 'القيمة'];
    }

    public function array(): array
    {
        $wo = $this->workOrder;

        return [
            ['كود الرحلة', (string) $wo->code],
            ['الحالة', $wo->status->label()],
            ['خط السير (من)', $wo->from_location],
            ['خط السير (إلى)', $wo->to_location],
            ['موعد التحرك', $wo->scheduled_at->format('Y-m-d H:i')],
            ['نوع الحمولة', $wo->cargo_type ?: 'غير محدد'],
            ['المقابل المادي', $wo->pay_amount ? number_format((float) $wo->pay_amount, 2).' ج.م' : 'حسب الاتفاق'],
            ['الرحلات المطلوبة', $wo->trips_needed],
            ['المؤكدون حاليًا', $wo->confirmed_count],
            ['الاحتياط المطلوب', $wo->backup_needed],
            ['الاحتياط حاليًا', $wo->backup_count],
            ['تاريخ الإنشاء', $wo->created_at->format('Y-m-d H:i')],
            ['تاريخ الاكتمال', $wo->filled_at ? $wo->filled_at->format('Y-m-d H:i') : '—'],
            ['الملاحظات', $wo->notes ?: 'لا توجد ملاحظات'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1F2937'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            'A' => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFF3F4F6'],
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
