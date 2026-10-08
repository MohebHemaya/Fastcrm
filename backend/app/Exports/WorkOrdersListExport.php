<?php

namespace App\Exports;

use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
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

class WorkOrdersListExport extends DefaultValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected array $filters = []
    ) {}

    public function bindValue(Cell $cell, $value): bool
    {
        return (new StringValueBinder)->bindValue($cell, $value);
    }

    public function title(): string
    {
        return 'أوامر الشغل';
    }

    public function query(): Builder
    {
        $query = WorkOrder::query()->orderBy('id', 'desc');

        if (! empty($this->filters['start_date'])) {
            $query->whereDate('scheduled_at', '>=', $this->filters['start_date']);
        }

        if (! empty($this->filters['end_date'])) {
            $query->whereDate('scheduled_at', '<=', $this->filters['end_date']);
        }

        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'كود أمر الشغل',
            'الحالة',
            'من موقع',
            'إلى موقع',
            'نوع الحمولة',
            'موعد التحميل',
            'الرحلات المطلوبة',
            'المؤكدين',
            'الاحتياط المطلوب',
            'الاحتياط المؤكد',
            'تم الإرسال لـ',
            'الأجر (ج.م)',
            'تاريخ الإنشاء',
            'ملاحظات',
        ];
    }

    /**
     * @param  WorkOrder  $order
     */
    public function map($order): array
    {
        return [
            $order->code,
            $order->status->label(),
            $order->from_location,
            $order->to_location,
            $order->cargo_type ?? '—',
            $order->scheduled_at ? Carbon::parse($order->scheduled_at)->format('Y-m-d H:i') : '—',
            $order->trips_needed,
            $order->confirmed_count,
            $order->backup_needed,
            $order->backup_count,
            $order->sent_count,
            $order->pay_amount !== null ? number_format((float) $order->pay_amount, 2) : '—',
            $order->created_at?->format('Y-m-d H:i') ?? '—',
            $order->notes ?? '—',
        ];
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
                $event->sheet->getDelegate()->setRightToLeft(true);
            },
        ];
    }
}
