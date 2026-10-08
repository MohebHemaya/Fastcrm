<?php

namespace App\Exports;

use App\Exports\Sheets\WorkOrderBackupSheet;
use App\Exports\Sheets\WorkOrderConfirmedSheet;
use App\Exports\Sheets\WorkOrderSummarySheet;
use App\Models\WorkOrder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WorkOrderExport implements Export, WithMultipleSheets
{
    public function __construct(
        protected WorkOrder $workOrder
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function sheets(): array
    {
        return [
            new WorkOrderSummarySheet($this->workOrder),
            new WorkOrderConfirmedSheet($this->workOrder),
            new WorkOrderBackupSheet($this->workOrder),
        ];
    }
}
