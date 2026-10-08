<?php

namespace App\Console\Commands;

use App\Models\WorkOrder;
use App\Services\WorkOrderFollowUpService;
use App\Services\WorkOrderService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class TestFollowUpCommand extends Command
{
    protected $signature = 'work-order:test-followup 
                            {action : promote or cancel} 
                            {orderId : ID of the work order} 
                            {recipientId? : ID of the backup recipient for promote} 
                            {--barrier= : File path used as a synchronization start barrier}';

    protected $description = 'Worker command to simulate concurrent promote and cancel operations for race condition testing';

    public function handle(
        WorkOrderFollowUpService $followUpService,
        WorkOrderService $workOrderService
    ): int {
        $action = (string) $this->argument('action');
        $orderId = (int) $this->argument('orderId');
        $recipientId = $this->argument('recipientId') ? (int) $this->argument('recipientId') : null;
        $barrier = $this->option('barrier');

        // Wait for synchronization barrier
        if ($barrier) {
            $timeout = microtime(true) + 15.0;
            while (! file_exists($barrier)) {
                if (microtime(true) > $timeout) {
                    $this->error('Barrier wait timed out');

                    return 1;
                }
                usleep(500); // 0.5ms wait
            }
        }

        /** @var WorkOrder|null $order */
        $order = WorkOrder::find($orderId);
        if (! $order) {
            $this->error("Work order {$orderId} not found");

            return 1;
        }

        try {
            if ($action === 'promote') {
                $recipient = $followUpService->promoteBackup(
                    order: $order,
                    recipientId: $recipientId
                );

                $this->line(json_encode([
                    'success' => true,
                    'action' => 'promote',
                    'recipient_id' => $recipient->id,
                    'sequence_number' => $recipient->sequence_number,
                ]));

                return 0;
            } elseif ($action === 'cancel') {
                $cancelledOrder = $workOrderService->cancel($order, 'إلغاء متزامن للرحلة');

                $this->line(json_encode([
                    'success' => true,
                    'action' => 'cancel',
                    'order_status' => $cancelledOrder->status->value,
                ]));

                return 0;
            } else {
                $this->error("Unknown action {$action}");

                return 1;
            }
        } catch (ValidationException $e) {
            $this->line(json_encode([
                'success' => false,
                'action' => $action,
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
            ]));

            return 0;
        } catch (\Throwable $e) {
            $this->line(json_encode([
                'success' => false,
                'action' => $action,
                'exception' => $e->getMessage(),
            ]));

            return 0;
        }
    }
}
