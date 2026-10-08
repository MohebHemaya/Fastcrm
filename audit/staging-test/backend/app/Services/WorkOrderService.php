<?php

namespace App\Services;

use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Jobs\DispatchStagedWorkOrderBatchJob;
use App\Models\WorkOrder;
use App\Models\WorkOrderFilter;
use App\Models\WorkOrderRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function __construct(
        protected DriverMatcher $driverMatcher,
        protected MessageSender $messageSender
    ) {}

    /**
     * Create a new work order draft with filters.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, ?int $userId = null): WorkOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $code = WorkOrder::generateCode();

            $order = WorkOrder::create([
                'code' => $code,
                'from_location' => $data['from_location'],
                'to_location' => $data['to_location'],
                'scheduled_at' => $data['scheduled_at'],
                'cargo_type' => $data['cargo_type'] ?? null,
                'pay_amount' => $data['pay_amount'] ?? null,
                'notes' => $data['notes'] ?? null,
                'trips_needed' => $data['trips_needed'] ?? 1,
                'backup_needed' => $data['backup_needed'] ?? 0,
                'status' => WorkOrderStatus::DRAFT,
                'send_strategy' => $data['send_strategy'] ?? 'all',
                'batch_size' => $data['batch_size'] ?? null,
                'batch_interval_minutes' => $data['batch_interval_minutes'] ?? null,
                'exclude_same_day_confirmed' => $data['exclude_same_day_confirmed'] ?? false,
                'min_avg_rating' => $data['min_avg_rating'] ?? null,
                'max_no_shows' => $data['max_no_shows'] ?? null,
                'last_trip_within_days' => $data['last_trip_within_days'] ?? null,
                'include_unrated' => $data['include_unrated'] ?? false,
                'created_by' => $userId,
            ]);

            if (! empty($data['filters']) && is_array($data['filters'])) {
                foreach ($data['filters'] as $f) {
                    if (! empty($f['question_id'])) {
                        WorkOrderFilter::create([
                            'work_order_id' => $order->id,
                            'question_id' => $f['question_id'],
                            'operator' => $f['operator'] ?? 'in',
                            'value' => $f['value'] ?? [],
                        ]);
                    }
                }
            }

            Audit::log('work_order_created', $order, [
                'code' => $order->code,
                'trips_needed' => $order->trips_needed,
                'backup_needed' => $order->backup_needed,
            ]);

            return $order->load(['filters.question', 'creator']);
        });
    }

    /**
     * Update an existing draft work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(WorkOrder $order, array $data): WorkOrder
    {
        if ($order->status !== WorkOrderStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تعديل أمر العمل بعد بدء إرساله.'],
            ]);
        }

        return DB::transaction(function () use ($order, $data) {
            $order->update([
                'from_location' => $data['from_location'] ?? $order->from_location,
                'to_location' => $data['to_location'] ?? $order->to_location,
                'scheduled_at' => $data['scheduled_at'] ?? $order->scheduled_at,
                'cargo_type' => array_key_exists('cargo_type', $data) ? $data['cargo_type'] : $order->cargo_type,
                'pay_amount' => array_key_exists('pay_amount', $data) ? $data['pay_amount'] : $order->pay_amount,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $order->notes,
                'trips_needed' => $data['trips_needed'] ?? $order->trips_needed,
                'backup_needed' => array_key_exists('backup_needed', $data) ? $data['backup_needed'] : $order->backup_needed,
                'send_strategy' => $data['send_strategy'] ?? $order->send_strategy,
                'batch_size' => $data['batch_size'] ?? $order->batch_size,
                'batch_interval_minutes' => $data['batch_interval_minutes'] ?? $order->batch_interval_minutes,
                'exclude_same_day_confirmed' => $data['exclude_same_day_confirmed'] ?? $order->exclude_same_day_confirmed,
                'min_avg_rating' => array_key_exists('min_avg_rating', $data) ? $data['min_avg_rating'] : $order->min_avg_rating,
                'max_no_shows' => array_key_exists('max_no_shows', $data) ? $data['max_no_shows'] : $order->max_no_shows,
                'last_trip_within_days' => array_key_exists('last_trip_within_days', $data) ? $data['last_trip_within_days'] : $order->last_trip_within_days,
                'include_unrated' => array_key_exists('include_unrated', $data) ? $data['include_unrated'] : $order->include_unrated,
            ]);

            if (isset($data['filters']) && is_array($data['filters'])) {
                $order->filters()->delete();
                foreach ($data['filters'] as $f) {
                    if (! empty($f['question_id'])) {
                        WorkOrderFilter::create([
                            'work_order_id' => $order->id,
                            'question_id' => $f['question_id'],
                            'operator' => $f['operator'] ?? 'in',
                            'value' => $f['value'] ?? [],
                        ]);
                    }
                }
            }

            Audit::log('work_order_updated', $order);

            return $order->load(['filters.question', 'creator']);
        });
    }

    /**
     * Send order offers to matching drivers.
     */
    public function send(WorkOrder $order): WorkOrder
    {
        if ($order->status !== WorkOrderStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['أمر العمل تم إرساله مسبقًا.'],
            ]);
        }

        // Match eligible drivers
        $matchedDriverIds = $this->driverMatcher->matchForWorkOrder($order)->pluck('id')->all();

        if (empty($matchedDriverIds)) {
            throw ValidationException::withMessages([
                'filters' => ['لا يوجد أي سائق نشط يطابق معايير وفلاتر أمر العمل.'],
            ]);
        }

        return DB::transaction(function () use ($order, $matchedDriverIds) {
            $order->update(['status' => WorkOrderStatus::SENDING]);

            $isStaged = ($order->send_strategy === 'staged');
            $batchSize = $order->batch_size ?: (int) config('whatsapp.invite_batch_size', 20);
            $batchInterval = $order->batch_interval_minutes ?: 10;

            // Create recipient records
            $chunks = $isStaged ? array_chunk($matchedDriverIds, $batchSize) : [$matchedDriverIds];
            $totalBatches = count($chunks);

            $recipientData = [];
            $now = now();
            foreach ($chunks as $chunkIndex => $driverIdsInBatch) {
                $batchNumber = $chunkIndex + 1;
                foreach ($driverIdsInBatch as $dId) {
                    $recipientData[] = [
                        'work_order_id' => $order->id,
                        'driver_id' => $dId,
                        'status' => WorkOrderRecipientStatus::PENDING->value,
                        'stage_batch' => $batchNumber,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($recipientData, 500) as $chunk) {
                WorkOrderRecipient::insert($chunk);
            }

            // Advance status to FILLING once recipients are prepared
            $order->update(['status' => WorkOrderStatus::FILLING]);

            // Dispatch First Batch (Batch 1) immediately
            $firstBatchRecipients = WorkOrderRecipient::where('work_order_id', $order->id)
                ->where('stage_batch', 1)
                ->with('driver')
                ->get();

            $sentCount = 0;
            foreach ($firstBatchRecipients as $recipient) {
                $driver = $recipient->driver;
                if (! $driver) {
                    continue;
                }

                try {
                    $buttons = [
                        ['id' => "order_accept:{$order->id}:{$driver->id}", 'title' => 'أوافق'],
                        ['id' => "order_decline:{$order->id}:{$driver->id}", 'title' => 'أعتذر'],
                    ];

                    $driverName = $driver->name ?: 'كابتن';
                    $payFormatted = $order->pay_amount ? number_format((float) $order->pay_amount, 2).' ج.م' : 'حسب الاتفاق';

                    $this->messageSender->sendTemplate(
                        driver: $driver,
                        templateName: 'order_offer',
                        lang: 'ar',
                        params: [
                            $driverName,
                            $order->code,
                            $order->from_location,
                            $order->to_location,
                            $order->scheduled_at->format('Y-m-d H:i'),
                            $payFormatted,
                        ],
                        buttons: $buttons,
                        orderId: $order->id,
                        body: "فرصة رحلة جديدة 🚗\nمرحبًا يا {$driverName}، تتوفر رحلة جديدة مطابقة لبياناتك:\n- رقم الطلب: {$order->code}\n- خط السير: من {$order->from_location} إلى {$order->to_location}\n- الموعد: {$order->scheduled_at->format('Y-m-d H:i')}\n- المقابل المادي: {$payFormatted}\nهل ترغب في قبول هذه الرحلة؟ (الأولوية لأسبقية الرد)"
                    );

                    $recipient->update([
                        'status' => WorkOrderRecipientStatus::SENT,
                        'sent_at' => now(),
                    ]);
                    $sentCount++;
                } catch (\Throwable $e) {
                    Log::warning("[WorkOrderService] Failed to send offer to driver {$driver->id}: {$e->getMessage()}");
                    $recipient->update(['status' => WorkOrderRecipientStatus::FAILED]);
                }
            }

            $order->update(['sent_count' => $sentCount]);

            // If staged and there is a Batch 2, dispatch delayed job
            if ($isStaged && $totalBatches > 1) {
                DispatchStagedWorkOrderBatchJob::dispatch($order->id, 2)->delay(now()->addMinutes($batchInterval));
            }

            Audit::log('work_order_sent', $order, [
                'matched_count' => count($matchedDriverIds),
                'batch_count' => $totalBatches,
                'send_strategy' => $order->send_strategy,
            ]);

            return $order->refresh();
        });
    }

    /**
     * Cancel an active or filling work order.
     */
    public function cancel(WorkOrder $order, ?string $reason = null): WorkOrder
    {
        if ($order->status->isFinal()) {
            throw ValidationException::withMessages([
                'status' => ['أمر العمل مكتمل أو ملغي بالفعل.'],
            ]);
        }

        return DB::transaction(function () use ($order, $reason) {
            /** @var WorkOrder|null $lockedOrder */
            $lockedOrder = WorkOrder::where('id', $order->id)->lockForUpdate()->first();
            if (! $lockedOrder || $lockedOrder->status->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => ['أمر العمل مكتمل أو ملغي بالفعل.'],
                ]);
            }

            $lockedOrder->update([
                'status' => WorkOrderStatus::CANCELLED,
            ]);

            // Notify confirmed and backup drivers about the cancellation
            $committedRecipients = WorkOrderRecipient::where('work_order_id', $order->id)
                ->whereIn('status', [WorkOrderRecipientStatus::CONFIRMED, WorkOrderRecipientStatus::BACKUP])
                ->with('driver')
                ->get();

            $cancelReason = $reason ?: 'إلغاء من قبل إدارة التشغيل';

            foreach ($committedRecipients as $recipient) {
                $driver = $recipient->driver;
                if (! $driver) {
                    continue;
                }

                $recipient->update(['status' => WorkOrderRecipientStatus::CANCELLED]);

                try {
                    $driverName = $driver->name ?: 'كابتن';
                    $this->messageSender->sendTemplate(
                        driver: $driver,
                        templateName: 'trip_cancellation',
                        lang: 'ar',
                        params: [
                            $driverName,
                            $order->code,
                            $order->scheduled_at->format('Y-m-d H:i'),
                            $cancelReason,
                        ],
                        orderId: $order->id,
                        body: "تنبيه إلغاء رحلة ⚠️\nعناية الكابتن {$driverName}، نحيطك علمًا بأنه تم إلغاء الرحلة رقم {$order->code} المقررة بتاريخ {$order->scheduled_at->format('Y-m-d H:i')} بسبب: {$cancelReason}. نعتذر عن أي إزعاج وستكون لك الأولوية في الرحلات القادمة."
                    );
                } catch (\Throwable $e) {
                    Log::warning("[WorkOrderService] Cancellation notice failed for driver {$driver->id}: {$e->getMessage()}");
                }
            }

            // Cancel any remaining pending/sent recipients
            WorkOrderRecipient::where('work_order_id', $order->id)
                ->whereIn('status', [WorkOrderRecipientStatus::PENDING, WorkOrderRecipientStatus::SENT])
                ->update(['status' => WorkOrderRecipientStatus::CANCELLED]);

            Audit::log('work_order_cancelled', $order, [
                'reason' => $reason,
                'notified_drivers_count' => $committedRecipients->count(),
            ]);

            return $order->refresh();
        }, attempts: 3);
    }

    /**
     * Duplicate a previous work order into a new draft.
     */
    public function duplicate(WorkOrder $order, ?int $userId = null): WorkOrder
    {
        return DB::transaction(function () use ($order, $userId) {
            $newCode = WorkOrder::generateCode();

            $newOrder = WorkOrder::create([
                'code' => $newCode,
                'from_location' => $order->from_location,
                'to_location' => $order->to_location,
                'scheduled_at' => now()->addDays(2)->setHour(9)->setMinute(0),
                'cargo_type' => $order->cargo_type,
                'pay_amount' => $order->pay_amount,
                'notes' => $order->notes,
                'trips_needed' => $order->trips_needed,
                'backup_needed' => $order->backup_needed,
                'status' => WorkOrderStatus::DRAFT,
                'send_strategy' => $order->send_strategy,
                'batch_size' => $order->batch_size,
                'batch_interval_minutes' => $order->batch_interval_minutes,
                'exclude_same_day_confirmed' => $order->exclude_same_day_confirmed,
                'min_avg_rating' => $order->min_avg_rating,
                'max_no_shows' => $order->max_no_shows,
                'last_trip_within_days' => $order->last_trip_within_days,
                'include_unrated' => $order->include_unrated,
                'created_by' => $userId,
            ]);

            /** @var WorkOrderFilter $filter */
            foreach ($order->filters as $filter) {
                WorkOrderFilter::create([
                    'work_order_id' => $newOrder->id,
                    'question_id' => $filter->question_id,
                    'operator' => $filter->operator,
                    'value' => $filter->value,
                ]);
            }

            Audit::log('work_order_duplicated', $newOrder, [
                'original_code' => $order->code,
                'new_code' => $newOrder->code,
            ]);

            return $newOrder->load(['filters.question', 'creator']);
        });
    }
}
