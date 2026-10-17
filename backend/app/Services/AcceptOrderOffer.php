<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Events\WorkOrderUpdated;
use App\Jobs\NotifyOrderFilledJob;
use App\Models\Driver;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcceptOrderOffer
{
    public function __construct(
        protected MessageSender $messageSender
    ) {}

    /**
     * Atomically process a driver's response (accept or decline) to a work order offer.
     * Guaranteed gapless sequence numbers and zero over-capacity under concurrency via InnoDB lockForUpdate.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     sequence_number: int|null,
     *     order_status: string,
     *     is_duplicate: bool
     * }
     */
    public function handle(int $workOrderId, int $driverId, string $action = 'accept'): array
    {
        $shouldNotifyFilled = false;
        $assignedStatus = null;
        $sequenceNumber = null;
        $orderRecord = null;
        $recipientRecord = null;
        $isDuplicate = false;

        $result = DB::transaction(function () use (
            $workOrderId,
            $driverId,
            $action,
            &$shouldNotifyFilled,
            &$assignedStatus,
            &$sequenceNumber,
            &$orderRecord,
            &$recipientRecord,
            &$isDuplicate
        ) {
            // 1. Lock the work order row
            /** @var WorkOrder|null $order */
            $order = WorkOrder::where('id', $workOrderId)->lockForUpdate()->first();
            if (! $order) {
                return [
                    'success' => false,
                    'status' => 'order_not_found',
                    'sequence_number' => null,
                    'order_status' => 'unknown',
                    'is_duplicate' => false,
                ];
            }
            $orderRecord = $order;

            // 2. Lock the recipient row
            /** @var WorkOrderRecipient|null $recipient */
            $recipient = WorkOrderRecipient::where('work_order_id', $workOrderId)
                ->where('driver_id', $driverId)
                ->lockForUpdate()
                ->first();

            if (! $recipient) {
                return [
                    'success' => false,
                    'status' => 'recipient_not_found',
                    'sequence_number' => null,
                    'order_status' => $order->status->value,
                    'is_duplicate' => false,
                ];
            }
            $recipientRecord = $recipient;

            // 3. Re-check driver eligibility at acceptance time
            /** @var Driver|null $driver */
            $driver = Driver::find($driverId);
            if (! $driver || in_array($driver->status, [DriverStatus::OPTED_OUT, DriverStatus::BLOCKED, DriverStatus::SUSPENDED], true)) {
                $recipient->update([
                    'status' => WorkOrderRecipientStatus::CANCELLED,
                    'responded_at' => now(),
                ]);
                $assignedStatus = 'cancelled';

                return [
                    'success' => false,
                    'status' => 'cancelled',
                    'sequence_number' => null,
                    'order_status' => $order->status->value,
                    'is_duplicate' => false,
                ];
            }

            // 3. Handle Decline
            if ($action === 'decline') {
                if (in_array($recipient->status, [
                    WorkOrderRecipientStatus::CONFIRMED,
                    WorkOrderRecipientStatus::BACKUP,
                    WorkOrderRecipientStatus::DECLINED,
                    WorkOrderRecipientStatus::TOO_LATE,
                ], true)) {
                    $isDuplicate = true;

                    return [
                        'success' => true,
                        'status' => $recipient->status->value,
                        'sequence_number' => $recipient->sequence_number,
                        'order_status' => $order->status->value,
                        'is_duplicate' => true,
                    ];
                }

                $recipient->update([
                    'status' => WorkOrderRecipientStatus::DECLINED,
                    'responded_at' => now(),
                ]);

                $assignedStatus = 'declined';

                return [
                    'success' => true,
                    'status' => 'declined',
                    'sequence_number' => null,
                    'order_status' => $order->status->value,
                    'is_duplicate' => false,
                ];
            }

            // 4. Handle Accept: Check Idempotency
            if ($recipient->status === WorkOrderRecipientStatus::CONFIRMED) {
                $isDuplicate = true;

                return [
                    'success' => true,
                    'status' => 'confirmed',
                    'sequence_number' => $recipient->sequence_number,
                    'order_status' => $order->status->value,
                    'is_duplicate' => true,
                ];
            }

            if ($recipient->status === WorkOrderRecipientStatus::BACKUP) {
                $isDuplicate = true;

                return [
                    'success' => true,
                    'status' => 'backup',
                    'sequence_number' => $recipient->sequence_number,
                    'order_status' => $order->status->value,
                    'is_duplicate' => true,
                ];
            }

            if (in_array($recipient->status, [
                WorkOrderRecipientStatus::DECLINED,
                WorkOrderRecipientStatus::TOO_LATE,
                WorkOrderRecipientStatus::CANCELLED,
            ], true)) {
                $isDuplicate = true;

                return [
                    'success' => false,
                    'status' => $recipient->status->value,
                    'sequence_number' => null,
                    'order_status' => $order->status->value,
                    'is_duplicate' => true,
                ];
            }

            // 5. Check if order is in FILLING status
            if ($order->status !== WorkOrderStatus::FILLING) {
                $recipient->update([
                    'status' => WorkOrderRecipientStatus::TOO_LATE,
                    'responded_at' => now(),
                ]);
                $assignedStatus = 'too_late';

                return [
                    'success' => false,
                    'status' => 'too_late',
                    'sequence_number' => null,
                    'order_status' => $order->status->value,
                    'is_duplicate' => false,
                ];
            }

            // 6. Capacity Assignment
            if ($order->confirmed_count < $order->trips_needed) {
                $order->confirmed_count++;
                $recipient->status = WorkOrderRecipientStatus::CONFIRMED;
                $recipient->sequence_number = $order->confirmed_count;
                $assignedStatus = 'confirmed';
            } elseif ($order->backup_count < $order->backup_needed) {
                $order->backup_count++;
                $recipient->status = WorkOrderRecipientStatus::BACKUP;
                $recipient->sequence_number = $order->backup_count;
                $assignedStatus = 'backup';
            } else {
                $recipient->status = WorkOrderRecipientStatus::TOO_LATE;
                $assignedStatus = 'too_late';
            }

            $recipient->responded_at = now();
            $sequenceNumber = $recipient->sequence_number;

            // 7. Check if order is completely filled
            if ($order->confirmed_count >= $order->trips_needed && $order->backup_count >= $order->backup_needed) {
                $order->status = WorkOrderStatus::FILLED;
                $order->filled_at = now();
                $shouldNotifyFilled = true;
            }

            $order->save();
            $recipient->save();

            return [
                'success' => in_array($assignedStatus, ['confirmed', 'backup'], true),
                'status' => $assignedStatus,
                'sequence_number' => $sequenceNumber,
                'order_status' => $order->status->value,
                'is_duplicate' => false,
            ];
        }, attempts: 3);

        // 8. AFTER COMMIT: Dispatch Messages, Pusher Broadcasts, and Notifications
        if (! $isDuplicate && $orderRecord && $recipientRecord && $assignedStatus !== 'cancelled') {
            $driver = Driver::find($driverId);
            if ($driver) {
                $this->sendPostAcceptanceMessage($driver, $orderRecord, $assignedStatus, $sequenceNumber);
            }

            if ($shouldNotifyFilled) {
                NotifyOrderFilledJob::dispatch($orderRecord->id);
            }

            $isFilled = ($orderRecord->status === WorkOrderStatus::FILLED);
            $driverName = $driver?->name ?: 'كابتن';
            $msg = match ($assignedStatus) {
                'confirmed' => "قام الكابتن {$driverName} بتأكيد قبوله للرحلة {$orderRecord->code} (مؤكد رقم {$sequenceNumber})",
                'backup' => "انضم الكابتن {$driverName} كاحتياطي رقم {$sequenceNumber} للرحلة {$orderRecord->code}",
                'declined' => "اعتذر الكابتن {$driverName} عن الرحلة {$orderRecord->code}",
                'too_late' => "رد الكابتن {$driverName} بعد اكتمال العدد للرحلة {$orderRecord->code}",
                default => null,
            };

            if ($isFilled) {
                $msg = "تم اكتمال العدد المطلوب بالكامل للرحلة {$orderRecord->code}! 🎉";
            }

            try {
                broadcast(new WorkOrderUpdated(
                    workOrderId: $orderRecord->id,
                    orderCode: $orderRecord->code,
                    status: $orderRecord->status->value,
                    confirmedCount: $orderRecord->confirmed_count,
                    backupCount: $orderRecord->backup_count,
                    tripsNeeded: $orderRecord->trips_needed,
                    backupNeeded: $orderRecord->backup_needed,
                    isFilled: $isFilled,
                    recipient: [
                        'id' => $recipientRecord->id,
                        'driver_id' => $driverId,
                        'driver_name' => $driverName,
                        'phone' => $driver?->phone,
                        'status' => $recipientRecord->status->value,
                        'sequence_number' => $sequenceNumber,
                        'responded_at' => $recipientRecord->responded_at?->toIso8601String(),
                    ],
                    notificationMessage: $msg
                ));
            } catch (\Throwable $e) {
                Log::warning("[AcceptOrderOffer] Failed to broadcast WorkOrderUpdated: {$e->getMessage()}");
            }
        }

        return $result;
    }

    /**
     * Send corresponding WhatsApp reply message to driver.
     */
    protected function sendPostAcceptanceMessage(
        Driver $driver,
        WorkOrder $order,
        ?string $assignedStatus,
        ?int $sequenceNumber
    ): void {
        $driverName = $driver->name ?: 'كابتن';

        try {
            $tplService = app(WhatsAppTemplateService::class);

            if ($assignedStatus === 'confirmed') {
                $scheduledFormatted = $order->scheduled_at ? $order->scheduled_at->format('Y-m-d H:i') : 'حسب الاتفاق';

                $body = $tplService->render('order_confirmation', [
                    'اسم_السائق' => $driverName,
                    'driver_name' => $driverName,
                    'رقم_الطلب' => $order->code,
                    'order_code' => $order->code,
                    'مكان_الانطلاق' => $order->from_location,
                    'from_location' => $order->from_location,
                    'جهة_الوصول' => $order->to_location,
                    'وجهة_الوصول' => $order->to_location,
                    'to_location' => $order->to_location,
                    'موعد_الرحلة' => $scheduledFormatted,
                    'scheduled_at' => $scheduledFormatted,
                    'المقابل_المادي' => ($order->pay_amount ? "{$order->pay_amount} ج.م" : 'حسب الاتفاق'),
                    'pay_amount' => ($order->pay_amount ? "{$order->pay_amount} ج.م" : 'حسب الاتفاق'),
                ]);

                $this->messageSender->sendTemplate(
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
                    body: $body
                );
            } elseif ($assignedStatus === 'backup') {
                $body = $tplService->render('order_backup_notice', [
                    'اسم_السائق' => $driverName,
                    'driver_name' => $driverName,
                    'رقم_الطلب' => $order->code,
                    'order_code' => $order->code,
                    'رقم_الترتيب' => (string) ($sequenceNumber ?? 1),
                ]);

                $this->messageSender->sendTemplate(
                    driver: $driver,
                    templateName: 'order_backup_notice',
                    lang: 'ar',
                    params: [
                        $driverName,
                        $order->code,
                    ],
                    orderId: $order->id,
                    body: $body
                );
            } elseif ($assignedStatus === 'too_late') {
                $body = $tplService->render('order_filled', [
                    'اسم_السائق' => $driverName,
                    'driver_name' => $driverName,
                    'رقم_الطلب' => $order->code,
                    'order_code' => $order->code,
                ]);

                $this->messageSender->sendTemplate(
                    driver: $driver,
                    templateName: 'order_filled',
                    lang: 'ar',
                    params: [
                        $driverName,
                        $order->code,
                    ],
                    orderId: $order->id,
                    body: $body
                );
            } elseif ($assignedStatus === 'declined') {
                $this->messageSender->sendText(
                    driver: $driver,
                    body: "شكرًا لتجاوبك يا {$driverName}. تم تسجيل اعتذارك عن الرحلة رقم {$order->code}. نتمنى لك التوفيق دائمًا.",
                    orderId: $order->id
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send post-acceptance WhatsApp message', [
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                'status' => $assignedStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
