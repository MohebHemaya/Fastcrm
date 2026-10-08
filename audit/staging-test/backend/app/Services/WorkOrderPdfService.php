<?php

namespace App\Services;

use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;

class WorkOrderPdfService
{
    public function __construct(
        protected PdfService $pdfService
    ) {}

    /**
     * Generate the HTML representation of the work order sheet.
     */
    public function generateHtml(WorkOrder $workOrder): string
    {
        $confirmedRecipients = WorkOrderRecipient::where('work_order_id', $workOrder->id)
            ->where('status', 'confirmed')
            ->with('driver')
            ->orderBy('sequence_number', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $backupRecipients = WorkOrderRecipient::where('work_order_id', $workOrder->id)
            ->where('status', 'backup')
            ->with('driver')
            ->orderBy('sequence_number', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return view('pdf.work-order', [
            'order' => $workOrder,
            'confirmedRecipients' => $confirmedRecipients,
            'backupRecipients' => $backupRecipients,
            'printedAt' => now()->format('Y-m-d H:i'),
        ])->render();
    }

    /**
     * Generate PDF binary content.
     */
    public function generatePdf(WorkOrder $workOrder): string
    {
        $html = $this->generateHtml($workOrder);

        $mpdf = $this->pdfService->createMpdf([
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
