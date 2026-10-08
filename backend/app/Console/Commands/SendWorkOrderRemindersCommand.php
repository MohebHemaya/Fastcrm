<?php

namespace App\Console\Commands;

use App\Enums\DriverStatus;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Setting;
use App\Models\WorkOrderRecipient;
use App\Services\MessageSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWorkOrderRemindersCommand extends Command
{
    protected $signature = 'work-orders:send-reminders';

    protected $description = 'Send trip reminders to confirmed drivers based on order_reminder_hours_before setting';

    public function handle(MessageSender $messageSender): int
    {
        $setting = Setting::where('key', 'order_reminder_hours_before')->first();
        $hoursBefore = $setting && is_numeric($setting->value) ? (int) $setting->value : null;

        if ($hoursBefore === null || $hoursBefore <= 0) {
            $this->info('Order reminders are disabled or not configured (order_reminder_hours_before <= 0).');

            return self::SUCCESS;
        }

        $now = Carbon::now();
        $targetWindow = $now->copy()->addHours($hoursBefore);

        // Find confirmed recipients for orders scheduled within the window who haven't received a reminder
        $recipients = WorkOrderRecipient::where('status', WorkOrderRecipientStatus::CONFIRMED)
            ->whereNull('reminder_sent_at')
            ->whereHas('workOrder', function ($q) use ($now, $targetWindow) {
                $q->whereIn('status', [WorkOrderStatus::FILLED, WorkOrderStatus::IN_PROGRESS])
                    ->whereBetween('scheduled_at', [$now, $targetWindow]);
            })
            ->with(['driver', 'workOrder'])
            ->get();

        $sentCount = 0;

        foreach ($recipients as $recipient) {
            $driver = $recipient->driver;
            $order = $recipient->workOrder;

            if (! $driver || ! $order) {
                continue;
            }

            if (in_array($driver->status, [DriverStatus::OPTED_OUT, DriverStatus::BLOCKED, DriverStatus::SUSPENDED], true)) {
                continue;
            }

            $driverName = $driver->name ?: 'كابتن';

            try {
                $buttons = [
                    ['id' => "reminder_ok:{$order->id}:{$driver->id}", 'title' => 'أنا جاهز ومستعد'],
                ];

                $messageSender->sendTemplate(
                    driver: $driver,
                    templateName: 'trip_reminder',
                    lang: 'ar',
                    params: [
                        $driverName,
                        $order->code,
                        $order->scheduled_at->format('Y-m-d H:i'),
                        $order->from_location,
                    ],
                    buttons: $buttons,
                    orderId: $order->id,
                    body: "تذكير بموعد رحلتك غدًا:\n- كابتن: {$driverName}\n- الرحلة رقم: {$order->code}\n- الموعد: {$order->scheduled_at->format('Y-m-d H:i')}\n- نقطة التجمع: {$order->from_location}\nنرجو التأكد من جاهزية المركبة ونظافتها والوصول قبل الموعد بـ 15 دقيقة على الأقل. نتمنى لك رحلة آمنة."
                );

                $recipient->update(['reminder_sent_at' => now()]);
                $sentCount++;
            } catch (\Throwable $e) {
                Log::warning("[SendWorkOrderRemindersCommand] Failed to send reminder to driver {$driver->id}: {$e->getMessage()}");
            }
        }

        $this->info("Dispatched {$sentCount} trip reminder notifications.");

        return self::SUCCESS;
    }
}
