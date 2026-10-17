<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\WorkOrderOutcome;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Events\OutcomeRecordedEvent;
use App\Models\Driver;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderRecipient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorkOrderFollowUpService
{
    public function __construct(
        protected WorkOrderService $workOrderService,
        protected MessageSender $messageSender
    ) {}

    /**
     * Remove or cancel a confirmed driver with a required reason.
     * Decrements confirmed_count, marks recipient cancelled, keeps order status as-is (e.g. FILLED),
     * and NEVER auto-promotes any backup driver.
     */
    public function removeConfirmedDriver(
        WorkOrder $order,
        WorkOrderRecipient $recipient,
        string $reason,
        ?int $userId = null
    ): WorkOrder {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['سبب الإلغاء إلزامي.'],
            ]);
        }

        if ($recipient->work_order_id !== $order->id || $recipient->status !== WorkOrderRecipientStatus::CONFIRMED) {
            throw ValidationException::withMessages([
                'recipient' => ['المستلم المحدد ليس سائقًا مؤكدًا في هذا الطلب.'],
            ]);
        }

        return DB::transaction(function () use ($order, $recipient, $reason, $userId) {
            /** @var WorkOrder $lockedOrder */
            $lockedOrder = WorkOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            /** @var WorkOrderRecipient $lockedRecipient */
            $lockedRecipient = WorkOrderRecipient::where('id', $recipient->id)->lockForUpdate()->firstOrFail();

            if ($lockedRecipient->status !== WorkOrderRecipientStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'recipient' => ['تم تغيير حالة السائق مسبقًا.'],
                ]);
            }

            $driver = $lockedRecipient->driver;
            $driverName = $driver?->name ?: 'كابتن';
            $oldSeq = $lockedRecipient->sequence_number;

            // Decrement confirmed count
            if ($lockedOrder->confirmed_count > 0) {
                $lockedOrder->decrement('confirmed_count');
            }

            // Update recipient status to cancelled
            $lockedRecipient->update([
                'status' => WorkOrderRecipientStatus::CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'sequence_number' => null,
            ]);

            // Re-sequence remaining confirmed drivers gaplessly 1..confirmed_count
            $remainingConfirmed = WorkOrderRecipient::where('work_order_id', $order->id)
                ->where('status', WorkOrderRecipientStatus::CONFIRMED)
                ->orderBy('sequence_number', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($remainingConfirmed as $idx => $r) {
                $r->update(['sequence_number' => $idx + 1]);
            }

            // Record timeline event
            WorkOrderEvent::create([
                'work_order_id' => $lockedOrder->id,
                'user_id' => $userId,
                'driver_id' => $lockedRecipient->driver_id,
                'action' => 'driver_cancelled',
                'description' => "إلغاء تكليف السائق المؤكد {$driverName}: {$reason}",
                'metadata' => [
                    'reason' => $reason,
                    'previous_sequence' => $oldSeq,
                    'confirmed_count' => $lockedOrder->confirmed_count,
                    'open_slots' => max(0, $lockedOrder->trips_needed - $lockedOrder->confirmed_count),
                ],
            ]);

            Audit::log('work_order_driver_removed', $lockedOrder, [
                'recipient_id' => $lockedRecipient->id,
                'driver_id' => $lockedRecipient->driver_id,
                'reason' => $reason,
                'confirmed_count' => $lockedOrder->confirmed_count,
            ]);

            return $lockedOrder->refresh();
        }, attempts: 3);
    }

    /**
     * Promote a backup driver to confirmed.
     * Checks capacity, locks work order, checks driver eligibility,
     * re-sequences both confirmed and backup lists gaplessly,
     * and sends backup_promoted WhatsApp template.
     */
    public function promoteBackup(
        WorkOrder $order,
        ?int $recipientId = null,
        ?int $userId = null
    ): WorkOrderRecipient {
        // Pre-check driver eligibility: if ineligible, mark cancelled and decrement backup_count permanently
        $targetQuery = WorkOrderRecipient::where('work_order_id', $order->id)
            ->where('status', WorkOrderRecipientStatus::BACKUP);
        if ($recipientId) {
            $targetQuery->where('id', $recipientId);
        } else {
            $targetQuery->orderBy('sequence_number', 'asc')->orderBy('id', 'asc');
        }

        /** @var WorkOrderRecipient|null $preCheckRecipient */
        $preCheckRecipient = $targetQuery->first();
        if ($preCheckRecipient) {
            $preCheckDriver = Driver::find($preCheckRecipient->driver_id);
            if (! $preCheckDriver || in_array($preCheckDriver->status, [DriverStatus::OPTED_OUT, DriverStatus::BLOCKED, DriverStatus::SUSPENDED], true)) {
                $preCheckRecipient->update([
                    'status' => WorkOrderRecipientStatus::CANCELLED,
                    'cancellation_reason' => 'غير مؤهل عند محاولة الترقية',
                    'cancelled_at' => now(),
                    'sequence_number' => null,
                ]);

                if ($order->backup_count > 0) {
                    $order->decrement('backup_count');
                }

                $remainingBackups = WorkOrderRecipient::where('work_order_id', $order->id)
                    ->where('status', WorkOrderRecipientStatus::BACKUP)
                    ->orderBy('sequence_number', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($remainingBackups as $bIdx => $bRec) {
                    $bRec->update(['sequence_number' => $bIdx + 1]);
                }

                throw ValidationException::withMessages([
                    'driver' => ['السائق غير مؤهل للترقية بسبب حالته الحالية ('.($preCheckDriver ? $preCheckDriver->status->label() : 'غير معروف').').'],
                ]);
            }
        }

        return DB::transaction(function () use ($order, $recipientId, $userId) {
            /** @var WorkOrder $lockedOrder */
            $lockedOrder = WorkOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // 1. Capacity check: must have open slot
            if ($lockedOrder->confirmed_count >= $lockedOrder->trips_needed) {
                throw ValidationException::withMessages([
                    'capacity' => ['لا يوجد مكان شاغر في الرحلة للترقية.'],
                ]);
            }

            // 2. Select backup recipient: specified or lowest sequence
            $query = WorkOrderRecipient::where('work_order_id', $order->id)
                ->where('status', WorkOrderRecipientStatus::BACKUP);

            if ($recipientId) {
                $query->where('id', $recipientId);
            } else {
                $query->orderBy('sequence_number', 'asc')->orderBy('id', 'asc');
            }

            /** @var WorkOrderRecipient|null $recipient */
            $recipient = $query->lockForUpdate()->first();

            if (! $recipient) {
                throw ValidationException::withMessages([
                    'recipient' => ['لا يوجد سائق احتياطي متاح للترقية.'],
                ]);
            }

            // 3. Re-check driver eligibility at promotion time
            $driver = Driver::where('id', $recipient->driver_id)->lockForUpdate()->first();
            if (! $driver || in_array($driver->status, [DriverStatus::OPTED_OUT, DriverStatus::BLOCKED, DriverStatus::SUSPENDED], true)) {
                $recipient->update([
                    'status' => WorkOrderRecipientStatus::CANCELLED,
                    'cancellation_reason' => 'غير مؤهل عند محاولة الترقية',
                    'cancelled_at' => now(),
                    'sequence_number' => null,
                ]);

                if ($lockedOrder->backup_count > 0) {
                    $lockedOrder->decrement('backup_count');
                }

                throw ValidationException::withMessages([
                    'driver' => ['السائق غير مؤهل للترقية بسبب حالته الحالية ('.($driver ? $driver->status->label() : 'غير معروف').').'],
                ]);
            }

            $driverName = $driver->name ?: 'كابتن';
            $oldBackupSeq = $recipient->sequence_number;

            // 4. Update counts
            $lockedOrder->increment('confirmed_count');
            if ($lockedOrder->backup_count > 0) {
                $lockedOrder->decrement('backup_count');
            }

            // 5. Update recipient to confirmed
            $recipient->update([
                'status' => WorkOrderRecipientStatus::CONFIRMED,
                'sequence_number' => $lockedOrder->confirmed_count,
                'responded_at' => $recipient->responded_at ?? now(),
            ]);

            // 6. Re-sequence remaining backups gaplessly 1..backup_count
            $remainingBackups = WorkOrderRecipient::where('work_order_id', $order->id)
                ->where('status', WorkOrderRecipientStatus::BACKUP)
                ->orderBy('sequence_number', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($remainingBackups as $bIdx => $bRec) {
                $bRec->update(['sequence_number' => $bIdx + 1]);
            }

            // 7. Re-sequence confirmed drivers gaplessly 1..confirmed_count
            $allConfirmed = WorkOrderRecipient::where('work_order_id', $order->id)
                ->where('status', WorkOrderRecipientStatus::CONFIRMED)
                ->orderBy('sequence_number', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($allConfirmed as $cIdx => $cRec) {
                $cRec->update(['sequence_number' => $cIdx + 1]);
            }

            // 8. Log timeline event
            WorkOrderEvent::create([
                'work_order_id' => $lockedOrder->id,
                'user_id' => $userId,
                'driver_id' => $recipient->driver_id,
                'action' => 'backup_promoted',
                'description' => "ترقية السائق الاحتياطي {$driverName} ليصبح مؤكدًا في الرحلة",
                'metadata' => [
                    'from_backup_seq' => $oldBackupSeq,
                    'new_confirmed_seq' => $recipient->sequence_number,
                    'confirmed_count' => $lockedOrder->confirmed_count,
                    'backup_count' => $lockedOrder->backup_count,
                ],
            ]);

            Audit::log('work_order_backup_promoted', $lockedOrder, [
                'recipient_id' => $recipient->id,
                'driver_id' => $driver->id,
                'confirmed_count' => $lockedOrder->confirmed_count,
                'backup_count' => $lockedOrder->backup_count,
            ]);

            // 9. Send WhatsApp backup_promoted template after commit
            DB::afterCommit(function () use ($driver, $driverName, $lockedOrder) {
                try {
                    $buttons = [
                        ['id' => "confirm_backup:{$lockedOrder->id}:{$driver->id}", 'title' => 'جاهز للتحرك'],
                        ['id' => "decline_backup:{$lockedOrder->id}:{$driver->id}", 'title' => 'اعتذار'],
                    ];

                    $scheduledFormatted = $lockedOrder->scheduled_at ? $lockedOrder->scheduled_at->format('Y-m-d H:i') : 'حسب الاتفاق';

                    $body = app(WhatsAppTemplateService::class)->render('backup_promoted', [
                        'اسم_السائق' => $driverName,
                        'driver_name' => $driverName,
                        'رقم_الطلب' => $lockedOrder->code,
                        'order_code' => $lockedOrder->code,
                        'وجهة_الوصول' => $lockedOrder->to_location,
                        'to_location' => $lockedOrder->to_location,
                        'موعد_الرحلة' => $scheduledFormatted,
                        'scheduled_at' => $scheduledFormatted,
                    ]);

                    $this->messageSender->sendTemplate(
                        driver: $driver,
                        templateName: 'backup_promoted',
                        lang: 'ar',
                        params: [
                            $driverName,
                            $lockedOrder->code,
                            $lockedOrder->to_location,
                            $scheduledFormatted,
                        ],
                        buttons: $buttons,
                        orderId: $lockedOrder->id,
                        body: $body
                    );
                } catch (\Throwable $e) {
                    Log::warning("[WorkOrderFollowUpService] Failed to send backup_promoted to driver {$driver->id}: {$e->getMessage()}");
                }
            });

            return $recipient->refresh();
        }, attempts: 3);
    }

    /**
     * Remove a backup driver with reason.
     */
    public function removeBackupDriver(
        WorkOrder $order,
        WorkOrderRecipient $recipient,
        string $reason,
        ?int $userId = null
    ): WorkOrder {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['سبب الحذف إلزامي.'],
            ]);
        }

        if ($recipient->work_order_id !== $order->id || $recipient->status !== WorkOrderRecipientStatus::BACKUP) {
            throw ValidationException::withMessages([
                'recipient' => ['المستلم المحدد ليس سائقًا احتياطيًا في هذا الطلب.'],
            ]);
        }

        return DB::transaction(function () use ($order, $recipient, $reason, $userId) {
            /** @var WorkOrder $lockedOrder */
            $lockedOrder = WorkOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            /** @var WorkOrderRecipient $lockedRecipient */
            $lockedRecipient = WorkOrderRecipient::where('id', $recipient->id)->lockForUpdate()->firstOrFail();

            $driver = $lockedRecipient->driver;
            $driverName = $driver?->name ?: 'كابتن';

            if ($lockedOrder->backup_count > 0) {
                $lockedOrder->decrement('backup_count');
            }

            $lockedRecipient->update([
                'status' => WorkOrderRecipientStatus::CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'sequence_number' => null,
            ]);

            // Re-sequence remaining backups gaplessly
            $remainingBackups = WorkOrderRecipient::where('work_order_id', $order->id)
                ->where('status', WorkOrderRecipientStatus::BACKUP)
                ->orderBy('sequence_number', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($remainingBackups as $bIdx => $bRec) {
                $bRec->update(['sequence_number' => $bIdx + 1]);
            }

            WorkOrderEvent::create([
                'work_order_id' => $lockedOrder->id,
                'user_id' => $userId,
                'driver_id' => $lockedRecipient->driver_id,
                'action' => 'backup_removed',
                'description' => "إلغاء السائق الاحتياطي {$driverName}: {$reason}",
                'metadata' => [
                    'reason' => $reason,
                    'backup_count' => $lockedOrder->backup_count,
                ],
            ]);

            return $lockedOrder->refresh();
        }, attempts: 3);
    }

    /**
     * Add a driver manually (even if outside filters) as confirmed or backup.
     */
    public function addDriverManually(
        WorkOrder $order,
        int $driverId,
        string $as = 'confirmed',
        ?int $userId = null
    ): WorkOrderRecipient {
        if (! in_array($as, ['confirmed', 'backup'], true)) {
            throw ValidationException::withMessages([
                'as' => ['نوع الإضافة يجب أن يكون confirmed أو backup.'],
            ]);
        }

        return DB::transaction(function () use ($order, $driverId, $as, $userId) {
            /** @var WorkOrder $lockedOrder */
            $lockedOrder = WorkOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            /** @var Driver|null $driver */
            $driver = Driver::where('id', $driverId)->lockForUpdate()->first();
            if (! $driver || $driver->status !== DriverStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'driver_id' => ['السائق غير موجود أو غير نشط في النظام.'],
                ]);
            }

            $isConfirmed = ($as === 'confirmed');

            if ($isConfirmed) {
                if ($lockedOrder->confirmed_count >= $lockedOrder->trips_needed) {
                    throw ValidationException::withMessages([
                        'capacity' => ['اكتمل عدد السائقين المؤكدين بالفعل لهذا الطلب.'],
                    ]);
                }
            } else {
                if ($lockedOrder->backup_count >= $lockedOrder->backup_needed) {
                    throw ValidationException::withMessages([
                        'capacity' => ['اكتمل عدد السائقين الاحتياطيين بالفعل لهذا الطلب.'],
                    ]);
                }
            }

            /** @var WorkOrderRecipient|null $existingRecipient */
            $existingRecipient = WorkOrderRecipient::where('work_order_id', $order->id)
                ->where('driver_id', $driverId)
                ->lockForUpdate()
                ->first();

            if ($existingRecipient && in_array($existingRecipient->status, [WorkOrderRecipientStatus::CONFIRMED, WorkOrderRecipientStatus::BACKUP], true)) {
                throw ValidationException::withMessages([
                    'driver_id' => ['السائق مسجل بالفعل في هذا الطلب كـ '.$existingRecipient->status->label().'.'],
                ]);
            }

            if ($isConfirmed) {
                $lockedOrder->increment('confirmed_count');
                $seq = $lockedOrder->confirmed_count;
                $targetStatus = WorkOrderRecipientStatus::CONFIRMED;
            } else {
                $lockedOrder->increment('backup_count');
                $seq = $lockedOrder->backup_count;
                $targetStatus = WorkOrderRecipientStatus::BACKUP;
            }

            if ($existingRecipient) {
                $existingRecipient->update([
                    'status' => $targetStatus,
                    'sequence_number' => $seq,
                    'responded_at' => now(),
                    'cancellation_reason' => null,
                    'cancelled_at' => null,
                ]);
                $recipient = $existingRecipient;
            } else {
                $recipient = WorkOrderRecipient::create([
                    'work_order_id' => $lockedOrder->id,
                    'driver_id' => $driver->id,
                    'status' => $targetStatus,
                    'sequence_number' => $seq,
                    'responded_at' => now(),
                    'stage_batch' => 1,
                ]);
            }

            // Re-sequence target list to ensure gapless sequence numbers
            $list = WorkOrderRecipient::where('work_order_id', $order->id)
                ->where('status', $targetStatus)
                ->orderBy('sequence_number', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($list as $lIdx => $lRec) {
                $lRec->update(['sequence_number' => $lIdx + 1]);
            }

            $driverName = $driver->name ?: 'كابتن';
            $roleLabel = $isConfirmed ? 'مؤكد' : 'احتياطي';

            WorkOrderEvent::create([
                'work_order_id' => $lockedOrder->id,
                'user_id' => $userId,
                'driver_id' => $driver->id,
                'action' => 'driver_added_manually',
                'description' => "إضافة السائق {$driverName} يدويًا كـ ({$roleLabel})",
                'metadata' => [
                    'as' => $as,
                    'sequence_number' => $recipient->sequence_number,
                    'confirmed_count' => $lockedOrder->confirmed_count,
                    'backup_count' => $lockedOrder->backup_count,
                ],
            ]);

            Audit::log('work_order_driver_added_manually', $lockedOrder, [
                'driver_id' => $driver->id,
                'as' => $as,
                'sequence_number' => $recipient->sequence_number,
            ]);

            // Notify driver after commit
            DB::afterCommit(function () use ($driver, $driverName, $lockedOrder, $isConfirmed, $recipient) {
                try {
                    $scheduledFormatted = $lockedOrder->scheduled_at ? $lockedOrder->scheduled_at->format('Y-m-d H:i') : 'حسب الاتفاق';
                    $payFormatted = $lockedOrder->pay_amount ? "{$lockedOrder->pay_amount} ج.م" : 'حسب الاتفاق';

                    if ($isConfirmed) {
                        $this->messageSender->sendTemplate(
                            driver: $driver,
                            templateName: 'order_confirmation',
                            lang: 'ar',
                            params: [
                                $driverName,
                                $lockedOrder->code,
                                $lockedOrder->from_location,
                                $scheduledFormatted,
                                'مشرف العمليات',
                            ],
                            orderId: $lockedOrder->id,
                            body: "مبروك يا {$driverName}! تم تأكيد تكليفك بالرحلة رقم {$lockedOrder->code}.\n- خط السير: من {$lockedOrder->from_location} إلى {$lockedOrder->to_location}\n- الموعد: {$scheduledFormatted}\n- المقابل: {$payFormatted}\nنتمنى لك رحلة موفقة!"
                        );
                    } else {
                        $this->messageSender->sendTemplate(
                            driver: $driver,
                            templateName: 'order_backup_notice',
                            lang: 'ar',
                            params: [
                                $driverName,
                                $lockedOrder->code,
                            ],
                            orderId: $lockedOrder->id,
                            body: "أهلاً يا {$driverName}، تم اكتمال المقاعد الأساسية للرحلة رقم {$lockedOrder->code}، وتم إدراجك كسائق احتياطي رقم ({$recipient->sequence_number}). في حال اعتذار أي سائق أساسي سيتم ترقيتك تلقائيًا وتنبيهك فورًا."
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning("[WorkOrderFollowUpService] Failed to notify manually added driver {$driver->id}: {$e->getMessage()}");
                }
            });

            return $recipient->refresh();
        }, attempts: 3);
    }

    /**
     * Resend offers to no_response / failed recipients.
     * Re-opens work order status to 'filling' if free slots exist.
     */
    public function resendOffers(
        WorkOrder $order,
        array $recipientIds = [],
        ?int $userId = null
    ): int {
        return DB::transaction(function () use ($order, $recipientIds, $userId) {
            /** @var WorkOrder $lockedOrder */
            $lockedOrder = WorkOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // Re-sending offer is only allowed when there are open slots
            if ($lockedOrder->confirmed_count >= $lockedOrder->trips_needed) {
                throw ValidationException::withMessages([
                    'slots' => ['لا يمكن إعادة إرسال العروض لعدم وجود أماكن شاغرة في الرحلة.'],
                ]);
            }

            $query = WorkOrderRecipient::where('work_order_id', $order->id)
                ->whereIn('status', [
                    WorkOrderRecipientStatus::NO_RESPONSE,
                    WorkOrderRecipientStatus::FAILED,
                    WorkOrderRecipientStatus::SENT,
                ])
                ->with('driver');

            if (! empty($recipientIds)) {
                $query->whereIn('id', $recipientIds);
            }

            /** @var Collection<int, WorkOrderRecipient> $recipients */
            $recipients = $query->lockForUpdate()->get();

            if ($recipients->isEmpty()) {
                throw ValidationException::withMessages([
                    'recipients' => ['لا يوجد أي مستلم مؤهل لإعادة الإرسال.'],
                ]);
            }

            // Re-open status to filling
            $lockedOrder->update(['status' => WorkOrderStatus::FILLING]);

            $resentCount = 0;
            foreach ($recipients as $recipient) {
                $driver = $recipient->driver;
                if (! $driver || $driver->status !== DriverStatus::ACTIVE) {
                    continue;
                }

                $recipient->update([
                    'status' => WorkOrderRecipientStatus::SENT,
                    'sent_at' => now(),
                ]);
                $resentCount++;

                DB::afterCommit(function () use ($driver, $lockedOrder) {
                    try {
                        $buttons = [
                            ['id' => "order_accept:{$lockedOrder->id}:{$driver->id}", 'title' => 'أوافق'],
                            ['id' => "order_decline:{$lockedOrder->id}:{$driver->id}", 'title' => 'أعتذر'],
                        ];

                        $driverName = $driver->name ?: 'كابتن';
                        $payFormatted = $lockedOrder->pay_amount ? number_format((float) $lockedOrder->pay_amount, 2).' ج.م' : 'حسب الاتفاق';

                        $scheduledFormatted = $lockedOrder->scheduled_at ? $lockedOrder->scheduled_at->format('Y-m-d H:i') : 'حسب الاتفاق';

                        $this->messageSender->sendTemplate(
                            driver: $driver,
                            templateName: 'order_offer',
                            lang: 'ar',
                            params: [
                                $driverName,
                                $lockedOrder->code,
                                $lockedOrder->from_location,
                                $lockedOrder->to_location,
                                $scheduledFormatted,
                                $payFormatted,
                            ],
                            buttons: $buttons,
                            orderId: $lockedOrder->id,
                            body: "فرصة رحلة جديدة 🚗\nمرحبًا يا {$driverName}، تتوفر رحلة جديدة مطابقة لبياناتك:\n- رقم الطلب: {$lockedOrder->code}\n- خط السير: من {$lockedOrder->from_location} إلى {$lockedOrder->to_location}\n- الموعد: {$scheduledFormatted}\n- المقابل المادي: {$payFormatted}\nهل ترغب في قبول هذه الرحلة؟ (الأولوية لأسبقية الرد)"
                        );
                    } catch (\Throwable $e) {
                        Log::warning("[WorkOrderFollowUpService] Failed to resend offer to driver {$driver->id}: {$e->getMessage()}");
                    }
                });
            }

            WorkOrderEvent::create([
                'work_order_id' => $lockedOrder->id,
                'user_id' => $userId,
                'action' => 'offers_resent',
                'description' => "إعادة إرسال عروض الرحلة إلى ({$resentCount}) سائق وإعادة فتح استقبال السائقين",
                'metadata' => [
                    'resent_count' => $resentCount,
                    'order_status' => $lockedOrder->status->value,
                ],
            ]);

            Audit::log('work_order_offers_resent', $lockedOrder, [
                'resent_count' => $resentCount,
            ]);

            return $resentCount;
        }, attempts: 3);
    }

    /**
     * Alias for changeOrderStatus
     */
    public function changeStatus(
        WorkOrder $order,
        WorkOrderStatus $newStatus,
        ?string $reason = null,
        ?int $userId = null
    ): WorkOrder {
        return $this->changeOrderStatus($order, $newStatus, $reason, $userId);
    }

    /**
     * Advance order status through its lifecycle:
     * filled -> in_progress -> completed, and cancelled.
     */
    public function changeOrderStatus(
        WorkOrder $order,
        WorkOrderStatus $newStatus,
        ?string $reason = null,
        ?int $userId = null
    ): WorkOrder {
        if ($newStatus === WorkOrderStatus::CANCELLED) {
            if (empty(trim((string) $reason))) {
                throw ValidationException::withMessages([
                    'reason' => ['سبب الإلغاء إلزامي عند إلغاء أمر العمل.'],
                ]);
            }

            $order = $this->workOrderService->cancel($order, $reason);

            WorkOrderEvent::create([
                'work_order_id' => $order->id,
                'user_id' => $userId,
                'action' => 'order_cancelled',
                'description' => "إلغاء أمر العمل بالكامل: {$reason}",
                'metadata' => ['reason' => $reason],
            ]);

            return $order;
        }

        return DB::transaction(function () use ($order, $newStatus, $userId) {
            /** @var WorkOrder $lockedOrder */
            $lockedOrder = WorkOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => ['أمر العمل مكتمل أو ملغي بالفعل ولا يمكن تعديل حالته.'],
                ]);
            }

            if ($newStatus === WorkOrderStatus::IN_PROGRESS) {
                if (! in_array($lockedOrder->status, [WorkOrderStatus::FILLED, WorkOrderStatus::FILLING], true)) {
                    throw ValidationException::withMessages([
                        'status' => ['لا يمكن بدء تنفيذ الرحلة إلا بعد اكتمال أو بدء استقبال السائقين.'],
                    ]);
                }
                $description = 'بدء تنفيذ الرحلة (قيد التنفيذ)';
                $action = 'status_in_progress';
            } elseif ($newStatus === WorkOrderStatus::COMPLETED) {
                if ($lockedOrder->status !== WorkOrderStatus::IN_PROGRESS && $lockedOrder->status !== WorkOrderStatus::FILLED) {
                    throw ValidationException::withMessages([
                        'status' => ['لا يمكن إنهاء الرحلة إلا عندما تكون قيد التنفيذ.'],
                    ]);
                }
                $description = 'اكتمال تنفيذ أمر العمل بنجاح';
                $action = 'status_completed';
            } else {
                throw ValidationException::withMessages([
                    'status' => ['تغيير الحالة المطلوب غير مسموح.'],
                ]);
            }

            $oldStatusLabel = $lockedOrder->status->label();
            $lockedOrder->update(['status' => $newStatus]);

            WorkOrderEvent::create([
                'work_order_id' => $lockedOrder->id,
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'metadata' => [
                    'from_status' => $oldStatusLabel,
                    'to_status' => $newStatus->label(),
                ],
            ]);

            Audit::log('work_order_status_updated', $lockedOrder, [
                'from_status' => $oldStatusLabel,
                'to_status' => $newStatus->value,
            ]);

            return $lockedOrder->refresh();
        }, attempts: 3);
    }

    /**
     * Record or edit outcomes and ratings for confirmed drivers after order is completed.
     *
     * @param  array<int, array{recipient_id: int, outcome: string, rating?: int|null, notes?: string|null}>  $outcomes
     */
    public function recordOutcomes(
        WorkOrder $order,
        array $outcomes,
        ?int $userId = null
    ): void {
        if ($order->status !== WorkOrderStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'order' => ['لا يمكن تسجيل النتائج والتقييمات إلا بعد اكتمال أمر العمل (حالة: مكتمل).'],
            ]);
        }

        DB::transaction(function () use ($order, $outcomes, $userId) {
            foreach ($outcomes as $item) {
                $recipientId = (int) ($item['recipient_id'] ?? 0);
                $outcomeVal = (string) ($item['outcome'] ?? '');
                $rating = isset($item['rating']) && $item['rating'] !== '' ? (int) $item['rating'] : null;
                $notes = isset($item['notes']) ? (string) $item['notes'] : null;

                $outcomeEnum = WorkOrderOutcome::tryFrom($outcomeVal);
                if (! $outcomeEnum) {
                    throw ValidationException::withMessages([
                        'outcome' => ["النتيجة المحددة '{$outcomeVal}' غير صالحة."],
                    ]);
                }

                if ($rating !== null && ($rating < 1 || $rating > 5)) {
                    throw ValidationException::withMessages([
                        'rating' => ['التقييم يجب أن يكون بين 1 و 5 نجوم.'],
                    ]);
                }

                /** @var WorkOrderRecipient|null $recipient */
                $recipient = WorkOrderRecipient::where('id', $recipientId)
                    ->where('work_order_id', $order->id)
                    ->lockForUpdate()
                    ->first();

                if (! $recipient || $recipient->status !== WorkOrderRecipientStatus::CONFIRMED) {
                    continue;
                }

                $driver = $recipient->driver;
                $driverName = $driver?->name ?: 'كابتن';
                $prevOutcome = $recipient->outcome?->value;

                $recipient->update([
                    'outcome' => $outcomeEnum,
                    'rating' => $rating,
                    'outcome_notes' => $notes,
                    'outcome_recorded_at' => now(),
                    'outcome_recorded_by' => $userId,
                ]);

                WorkOrderEvent::create([
                    'work_order_id' => $order->id,
                    'user_id' => $userId,
                    'driver_id' => $recipient->driver_id,
                    'action' => 'outcome_recorded',
                    'description' => "تسجيل نتيجة السائق {$driverName}: {$outcomeEnum->label()}".($rating ? " (تقييم: {$rating}/5)" : ''),
                    'metadata' => [
                        'recipient_id' => $recipient->id,
                        'outcome' => $outcomeEnum->value,
                        'rating' => $rating,
                        'notes' => $notes,
                        'previous_outcome' => $prevOutcome,
                    ],
                ]);

                // Fire event to trigger driver_metrics recalculation
                event(new OutcomeRecordedEvent(
                    driverId: $recipient->driver_id,
                    recipientId: $recipient->id,
                    workOrderId: $order->id
                ));
            }

            Audit::log('work_order_outcomes_recorded', $order, [
                'count' => count($outcomes),
            ]);
        }, attempts: 3);
    }
}
