<?php

namespace App\Console\Commands;

use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WorkOrderRecipientStatus;
use App\Models\WhatsAppMessage;
use App\Models\WorkOrderRecipient;
use App\Services\MessageSender;
use Illuminate\Console\Command;

class ReconcileWorkOrderNotificationsCommand extends Command
{
    protected $signature = 'work-orders:reconcile-notifications';

    protected $description = 'Find confirmed, backup, and too_late work order recipients missing outgoing notifications and send them idempotently';

    public function handle(MessageSender $messageSender): int
    {
        $recipients = WorkOrderRecipient::whereIn('status', [
            WorkOrderRecipientStatus::CONFIRMED,
            WorkOrderRecipientStatus::BACKUP,
            WorkOrderRecipientStatus::TOO_LATE,
        ])
            ->with(['driver', 'workOrder'])
            ->get();

        $reconciledCount = 0;

        foreach ($recipients as $recipient) {
            $driver = $recipient->driver;
            $order = $recipient->workOrder;

            if (! $driver || ! $order) {
                continue;
            }

            if (in_array($driver->status, [DriverStatus::OPTED_OUT, DriverStatus::BLOCKED], true)) {
                continue;
            }

            $expectedTemplate = match ($recipient->status) {
                WorkOrderRecipientStatus::CONFIRMED => 'order_confirmation',
                WorkOrderRecipientStatus::BACKUP => 'order_backup_notice',
                WorkOrderRecipientStatus::TOO_LATE => 'order_filled',
                default => null,
            };

            if (! $expectedTemplate) {
                continue;
            }

            $alreadySent = WhatsAppMessage::where('driver_id', $driver->id)
                ->where('order_id', $order->id)
                ->where('direction', WhatsAppMessageDirection::OUT)
                ->where('template_name', $expectedTemplate)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $driverName = $driver->name ?: 'كابتن';

            try {
                if ($recipient->status === WorkOrderRecipientStatus::CONFIRMED) {
                    $scheduledFormatted = $order->scheduled_at ? $order->scheduled_at->format('Y-m-d H:i') : 'حسب الاتفاق';
                    $payFormatted = $order->pay_amount ? "{$order->pay_amount} ج.م" : 'حسب الاتفاق';

                    $messageSender->sendTemplate(
                        driver: $driver,
                        templateName: 'order_confirmation',
                        lang: 'ar',
                        params: [
                            $driverName,
                            $order->code,
                            $order->from_location,
                            $scheduledFormatted,
                            'مشرف العمليات',
                        ],
                        orderId: $order->id,
                        body: "مبروك يا {$driverName}! تم تأكيد تكليفك بالرحلة رقم {$order->code}.\n- خط السير: من {$order->from_location} إلى {$order->to_location}\n- الموعد: {$scheduledFormatted}\n- المقابل: {$payFormatted}\nنتمنى لك رحلة موفقة!"
                    );
                    $reconciledCount++;
                } elseif ($recipient->status === WorkOrderRecipientStatus::BACKUP) {
                    $seq = $recipient->sequence_number ?: 1;
                    $messageSender->sendTemplate(
                        driver: $driver,
                        templateName: 'order_backup_notice',
                        lang: 'ar',
                        params: [
                            $driverName,
                            $order->code,
                        ],
                        orderId: $order->id,
                        body: "أهلاً يا {$driverName}، تم اكتمال المقاعد الأساسية للرحلة رقم {$order->code}، وتم إدراجك كسائق احتياطي رقم ({$seq}). في حال اعتذار أي سائق أساسي سيتم ترقيتك تلقائيًا وتنبيهك فورًا."
                    );
                    $reconciledCount++;
                } elseif ($recipient->status === WorkOrderRecipientStatus::TOO_LATE) {
                    $messageSender->sendTemplate(
                        driver: $driver,
                        templateName: 'order_filled',
                        lang: 'ar',
                        params: [
                            $driverName,
                            $order->code,
                        ],
                        orderId: $order->id,
                        body: "شكرًا لتجاوبك يا {$driverName}. نود إعلامك باكتمال العدد المطلوب بالكامل للرحلة رقم {$order->code}. فرص أخرى مميزة ستصلك قريبًا!"
                    );
                    $reconciledCount++;
                }
            } catch (\Throwable $e) {
                // Ignore send errors during scheduled reconciliation
            }
        }

        $this->info("Reconciled {$reconciledCount} missing notifications.");

        return self::SUCCESS;
    }
}
