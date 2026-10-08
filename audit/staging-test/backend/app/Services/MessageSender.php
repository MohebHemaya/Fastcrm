<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Driver;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MessageSender is the ONLY authorised entry point for sending WhatsApp messages.
 *
 * It:
 * 1. Blocks opted_out and blocked drivers.
 * 2. Chooses template vs free-form based on 24h window.
 * 3. Writes the whatsapp_messages row.
 * 4. Dispatches SendWhatsAppMessageJob.
 */
class MessageSender
{
    /**
     * The statuses that block all sending.
     *
     * @var list<DriverStatus>
     */
    private static array $blockedStatuses = [
        DriverStatus::OPTED_OUT,
        DriverStatus::BLOCKED,
    ];

    /**
     * Send a template message to a driver.
     *
     * @param  array<string>  $params
     * @param  array<array{id: string, title: string}>|null  $buttons
     */
    public function sendTemplate(
        Driver $driver,
        string $templateName,
        string $lang,
        array $params = [],
        ?array $buttons = null,
        ?int $orderId = null,
        ?string $body = null
    ): WhatsAppMessage {
        $this->assertCanMessage($driver);

        if ($body === null && $templateName === 'driver_invitation') {
            $name = $driver->name ?: 'كابتن';
            $body = "أهلاً بك يا {$name}! معك شركة النقل السريع. نتشرف بدعوتك للانضمام لشبكة السائقين لدينا وتلقي رحلات مميزة. هل أنت مهتم بالانضمام إلينا؟ (للإلغاء أرسل: إيقاف)";
        }

        $msg = $this->createMessageRow($driver, WhatsAppMessageType::TEMPLATE, [
            'template_name' => $templateName,
            'body' => $body,
            'payload' => compact('templateName', 'lang', 'params', 'buttons'),
            'order_id' => $orderId,
        ]);

        SendWhatsAppMessageJob::dispatch($msg->id);

        return $msg;
    }

    /**
     * Send a free-form text message.
     * Caller is responsible for checking the 24h window; this method does not enforce it.
     */
    public function sendText(Driver $driver, string $body, ?int $orderId = null): WhatsAppMessage
    {
        $this->assertCanMessage($driver);

        $msg = $this->createMessageRow($driver, WhatsAppMessageType::TEXT, [
            'body' => Str::limit($body, 4096),
            'payload' => null,
            'order_id' => $orderId,
        ]);

        SendWhatsAppMessageJob::dispatch($msg->id);

        return $msg;
    }

    /**
     * Send an interactive buttons message.
     *
     * @param  array<array{id: string, title: string}>  $buttons  max 3
     */
    public function sendButtons(
        Driver $driver,
        string $body,
        array $buttons,
        ?int $orderId = null
    ): WhatsAppMessage {
        $this->assertCanMessage($driver);

        $msg = $this->createMessageRow($driver, WhatsAppMessageType::BUTTON, [
            'body' => $body,
            'payload' => compact('buttons'),
            'order_id' => $orderId,
        ]);

        SendWhatsAppMessageJob::dispatch($msg->id);

        return $msg;
    }

    /**
     * Send an interactive list message.
     *
     * @param  array<array{id: string, title: string, description?: string}>  $rows  max 10
     */
    public function sendList(
        Driver $driver,
        string $body,
        string $buttonLabel,
        array $rows,
        ?int $orderId = null
    ): WhatsAppMessage {
        $this->assertCanMessage($driver);

        $msg = $this->createMessageRow($driver, WhatsAppMessageType::LIST, [
            'body' => $body,
            'payload' => compact('buttonLabel', 'rows'),
            'order_id' => $orderId,
        ]);

        SendWhatsAppMessageJob::dispatch($msg->id);

        return $msg;
    }

    /**
     * Send the single final confirmation message when a driver opts out.
     * This method bypasses the assertCanMessage check for opted_out status.
     */
    public function sendOptOutConfirmation(Driver $driver, string $body): WhatsAppMessage
    {
        $msg = $this->createMessageRow($driver, WhatsAppMessageType::TEXT, [
            'body' => Str::limit($body, 4096),
            'payload' => ['is_opt_out_confirmation' => true],
            'order_id' => null,
        ]);

        SendWhatsAppMessageJob::dispatch($msg->id);

        return $msg;
    }

    /**
     * Decide whether a free-form message can be sent (within 24h window).
     */
    public function isWithinFreeFormWindow(Driver $driver): bool
    {
        $windowHours = (int) config('whatsapp.free_form_window_hours', 24);

        $lastInbound = $driver->inboundMessages()->latest()->first();

        if (! $lastInbound) {
            return false;
        }

        return $lastInbound->created_at >= now()->subHours($windowHours);
    }

    /**
     * Throw if the driver cannot be messaged.
     *
     * @throws RuntimeException
     */
    private function assertCanMessage(Driver $driver): void
    {
        if (in_array($driver->status, self::$blockedStatuses, true)) {
            throw new RuntimeException(
                "لا يمكن إرسال رسائل للسائق {$driver->id} (الحالة: {$driver->status->value})"
            );
        }
    }

    /**
     * Create a queued whatsapp_messages row.
     *
     * @param  array<string, mixed>  $extra
     */
    private function createMessageRow(
        Driver $driver,
        WhatsAppMessageType $type,
        array $extra
    ): WhatsAppMessage {
        return WhatsAppMessage::create([
            'driver_id' => $driver->id,
            'direction' => WhatsAppMessageDirection::OUT,
            'type' => $type,
            'status' => WhatsAppMessageStatus::QUEUED,
            'queued_at' => now(),
            ...$extra,
        ]);
    }
}
