<?php

namespace App\Jobs;

use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\MessageSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchStagedWorkOrderBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $workOrderId,
        public int $batchNumber
    ) {}

    public function handle(MessageSender $messageSender): void
    {
        $order = WorkOrder::find($this->workOrderId);
        if (! $order) {
            return;
        }

        // Automatically stop if order is already filled or cancelled
        if ($order->status !== WorkOrderStatus::FILLING) {
            Log::info("[DispatchStagedWorkOrderBatchJob] Order {$order->id} is {$order->status->value}; stopping batch {$this->batchNumber}.");

            WorkOrderRecipient::where('work_order_id', $order->id)
                ->where('stage_batch', '>=', $this->batchNumber)
                ->where('status', WorkOrderRecipientStatus::PENDING)
                ->update(['status' => WorkOrderRecipientStatus::CANCELLED]);

            return;
        }

        $recipients = WorkOrderRecipient::where('work_order_id', $order->id)
            ->where('stage_batch', $this->batchNumber)
            ->where('status', WorkOrderRecipientStatus::PENDING)
            ->with('driver')
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $sentInBatch = 0;
        $accumulatedDelay = 0;
        /** @var array{enabled?: bool, min_delay_seconds?: int, max_delay_seconds?: int} $antiBan */
        $antiBan = (array) config('whatsapp.anti_ban', []);
        $antiBanEnabled = (bool) ($antiBan['enabled'] ?? true);
        $minDelay = (int) ($antiBan['min_delay_seconds'] ?? 2);
        $maxDelay = (int) ($antiBan['max_delay_seconds'] ?? 4);

        foreach ($recipients as $recipient) {
            $driver = $recipient->driver;
            if (! $driver) {
                continue;
            }

            if ($sentInBatch > 0 && $antiBanEnabled) {
                $accumulatedDelay += random_int($minDelay, $maxDelay);
            }

            try {
                $buttons = [
                    ['id' => "order_accept:{$order->id}:{$driver->id}", 'title' => 'أوافق'],
                    ['id' => "order_decline:{$order->id}:{$driver->id}", 'title' => 'أعتذر'],
                ];

                $driverName = $driver->name ?: 'كابتن';
                $messageSender->sendTemplate(
                    driver: $driver,
                    templateName: 'order_offer',
                    lang: 'ar',
                    params: [
                        $driverName,
                        $order->code,
                        $order->from_location ?? 'غير محدد',
                        $order->to_location ?? 'غير محدد',
                        $order->scheduled_at?->format('Y-m-d H:i') ?? 'غير محدد',
                        $order->pay_amount ? number_format((float) $order->pay_amount, 2) : 'حسب الاتفاق',
                    ],
                    buttons: $buttons,
                    orderId: $order->id,
                    body: $order->renderOfferMessageForDriver($driver),
                    delaySeconds: $accumulatedDelay,
                );

                $recipient->update([
                    'status' => WorkOrderRecipientStatus::SENT,
                    'sent_at' => now(),
                    'expires_at' => $order->offer_expires_in_minutes ? now()->addMinutes($order->offer_expires_in_minutes) : null,
                ]);
                $sentInBatch++;
            } catch (\Throwable $e) {
                Log::warning("[DispatchStagedWorkOrderBatchJob] Failed to send order offer to driver {$driver->id}: {$e->getMessage()}");
                $recipient->update([
                    'status' => WorkOrderRecipientStatus::FAILED,
                ]);
            }
        }

        $order->increment('sent_count', $sentInBatch);

        // Schedule next batch if exists and order is still filling
        $hasNextBatch = WorkOrderRecipient::where('work_order_id', $order->id)
            ->where('stage_batch', $this->batchNumber + 1)
            ->where('status', WorkOrderRecipientStatus::PENDING)
            ->exists();

        if ($hasNextBatch && $order->refresh()->status === WorkOrderStatus::FILLING) {
            $interval = $order->batch_interval_minutes ?: 10;
            self::dispatch($order->id, $this->batchNumber + 1)->delay(now()->addMinutes($interval));
        }
    }
}
