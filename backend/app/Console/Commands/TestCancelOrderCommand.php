<?php

namespace App\Console\Commands;

use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Console\Command;

class TestCancelOrderCommand extends Command
{
    protected $signature = 'work-order:test-cancel 
                            {orderId : ID of the work order} 
                            {--barrier= : File path used as a synchronization start barrier}';

    protected $description = 'Worker command to simulate cancel racing with acceptances for race condition tests';

    public function handle(WorkOrderService $workOrderService): int
    {
        $orderId = (int) $this->argument('orderId');
        $barrier = $this->option('barrier');

        if ($barrier) {
            $timeout = microtime(true) + 15.0;
            while (! file_exists($barrier)) {
                if (microtime(true) > $timeout) {
                    return 1;
                }
                usleep(500);
            }
        }

        $order = WorkOrder::find($orderId);
        if (! $order) {
            return 1;
        }

        try {
            $workOrderService->cancel($order, 'إلغاء تجريبي متزامن');
            $this->line(json_encode(['status' => 'cancelled']));

            return 0;
        } catch (\Throwable $e) {
            $this->line(json_encode(['error' => $e->getMessage()]));

            return 1;
        }
    }
}
