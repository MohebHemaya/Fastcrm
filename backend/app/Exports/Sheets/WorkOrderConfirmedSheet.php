<?php

namespace App\Exports\Sheets;

use App\Exports\StringValueBinder;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
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

class WorkOrderConfirmedSheet extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
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
        return 'السائقون المؤكدون';
    }

    public function headings(): array
    {
        return [
            'الترتيب',
            'اسم السائق',
            'رقم الهاتف',
            'تاريخ ووقت التأكيد',
            'النتيجة',
            'التقييم',
            'ملاحظات النتيجة',
        ];
    }

    public function collection(): Enumerable
    {
        return WorkOrderRecipient::where('work_order_id', $this->workOrder->id)
            ->where('status', 'confirmed')
            ->with('driver')
            ->orderBy('sequence_number', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @param  WorkOrderRecipient  $recipient
     * @return array<int, mixed>
     */
    public function map($recipient): array
    {
        $driver = $recipient->driver;

        return [
            $recipient->sequence_number ?? '—',
            $driver?->name ?: '—',
            (string) ($driver ? $driver->phone : '—'),
            $recipient->responded_at?->format('Y-m-d H:i') ?? '—',
            $recipient->outcome ? $recipient->outcome->label() : 'لم تسجل بعد',
            $recipient->rating ? "{$recipient->rating} / 5" : '—',
            $recipient->outcome_notes ?: '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF15803D'], // Forest green for confirmed
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
