<?php

namespace App\Console\Commands;

use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Events\WorkOrderUpdated;
use App\Jobs\DispatchStagedWorkOrderBatchJob;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderRecipient;
use App\Services\WorkOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessWorkOrderSchedulesCommand extends Command
{
    protected $signature = 'work-orders:process-schedules';
    protected $description = 'Process scheduled work order dispatches and expired offer timeouts';

    public function handle(WorkOrderService $workOrderService): int
    {
        $now = now();

        // 1. Process Scheduled Dispatches
        $scheduledOrders = WorkOrder::where('status', WorkOrderStatus::DRAFT)
            ->whereNotNull('scheduled_dispatch_at')
            ->where('scheduled_dispatch_at', '<=', $now)
            ->get();

        foreach ($scheduledOrders as $order) {
            $this->info("Dispatching scheduled work order: {$order->code}");
            try {
                $workOrderService->send($order);
                $this->info("Successfully dispatched {$order->code}");
            } catch (\Throwable $e) {
                $this->error("Failed to dispatch {$order->code}: {$e->getMessage()}");
                Log::error("[ProcessWorkOrderSchedules] Failed to send scheduled order {$order->id}: {$e->getMessage()}");
            }
        }

        // 2. Process Expired Recipient Offers
        $expiredRecipients = WorkOrderRecipient::where('status', WorkOrderRecipientStatus::SENT)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->with(['workOrder', 'driver'])
            ->get();

        foreach ($expiredRecipients as $recipient) {
            $order = $recipient->workOrder;
            $driver = $recipient->driver;

            if (! $order) {
                continue;
            }

            $recipient->update([
                'status' => WorkOrderRecipientStatus::NO_RESPONSE,
                'responded_at' => $now,
            ]);

            $driverName = $driver?->name ?: 'كابتن';
            $this->line("Offer expired for driver {$driverName} on order {$order->code}");

            WorkOrderEvent::create([
                'work_order_id' => $order->id,
                'driver_id' => $recipient->driver_id,
                'action' => 'offer_expired',
                'description' => "انتهت مهلة الرد على عرض الرحلة للسائق {$driverName}",
                'metadata' => [
                    'expires_at' => $recipient->expires_at?->toIso8601String(),
                ],
            ]);

            try {
                broadcast(new WorkOrderUpdated(
                    workOrderId: $order->id,
                    orderCode: $order->code,
                    status: $order->status->value,
                    confirmedCount: $order->confirmed_count,
                    backupCount: $order->backup_count,
                    tripsNeeded: $order->trips_needed,
                    backupNeeded: $order->backup_needed,
                    isFilled: $order->status === WorkOrderStatus::FILLED,
                    recipient: [
                        'id' => $recipient->id,
                        'driver_id' => $recipient->driver_id,
                        'driver_name' => $driverName,
                        'phone' => $driver?->phone,
                        'status' => WorkOrderRecipientStatus::NO_RESPONSE->value,
                    ],
                    notificationMessage: "انتهت مهلة العرض للسائق {$driverName} في الرحلة {$order->code}"
                ));
            } catch (\Throwable $e) {
                Log::warning("[ProcessWorkOrderSchedules] Failed to broadcast expiration: {$e->getMessage()}");
            }

            // Check if staged order still has open slots and next batch exists
            if ($order->send_strategy === 'staged' && $order->hasOpenSlots()) {
                $currentBatchPendingOrSent = WorkOrderRecipient::where('work_order_id', $order->id)
                    ->where('stage_batch', $recipient->stage_batch)
                    ->whereIn('status', [WorkOrderRecipientStatus::PENDING, WorkOrderRecipientStatus::SENT])
                    ->exists();

                if (! $currentBatchPendingOrSent) {
                    $nextBatch = $recipient->stage_batch + 1;
                    $hasNextBatch = WorkOrderRecipient::where('work_order_id', $order->id)
                        ->where('stage_batch', $nextBatch)
                        ->where('status', WorkOrderRecipientStatus::PENDING)
                        ->exists();

                    if ($hasNextBatch) {
                        $this->info("Current batch {$recipient->stage_batch} finished with open slots. Advancing order {$order->code} to batch {$nextBatch}.");
                        DispatchStagedWorkOrderBatchJob::dispatch($order->id, $nextBatch);
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}
