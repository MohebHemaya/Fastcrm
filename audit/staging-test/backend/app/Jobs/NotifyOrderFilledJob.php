<?php

namespace App\Jobs;

use App\Enums\WorkOrderRecipientStatus;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\MessageSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyOrderFilledJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $workOrderId
    ) {}

    public function handle(MessageSender $messageSender): void
    {
        $order = WorkOrder::find($this->workOrderId);
        if (! $order) {
            return;
        }

        // Cancel any remaining PENDING recipients that were never dispatched
        WorkOrderRecipient::where('work_order_id', $order->id)
            ->where('status', WorkOrderRecipientStatus::PENDING)
            ->update(['status' => WorkOrderRecipientStatus::CANCELLED]);

        // Find all recipients that were sent an offer but haven't answered yet
        $unanswered = WorkOrderRecipient::where('work_order_id', $order->id)
            ->where('status', WorkOrderRecipientStatus::SENT)
            ->with('driver')
            ->get();

        foreach ($unanswered as $recipient) {
            $recipient->update([
                'status' => WorkOrderRecipientStatus::TOO_LATE,
                'responded_at' => now(),
            ]);

            $driver = $recipient->driver;
            if (! $driver) {
                continue;
            }

            try {
                $driverName = $driver->name ?: 'كابتن';
                $messageSender->sendTemplate(
                    driver: $driver,
                    templateName: 'order_filled',
                    lang: 'ar',
                    params: [$driverName, $order->code],
                    orderId: $order->id,
                    body: "شكرًا لتجاوبك يا {$driverName}. نود إعلامك باكتمال العدد المطلوب بالكامل لأمر العمل {$order->code} (من {$order->from_location} إلى {$order->to_location}). فرص أخرى مميزة ستصلك قريبًا!"
                );
            } catch (\Throwable $e) {
                // Ignore send errors in background notification
            }
        }
    }
}
