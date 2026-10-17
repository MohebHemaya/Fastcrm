<?php

namespace App\Services;

use App\DTOs\WhatsApp\StatusUpdate;
use App\Enums\WhatsAppMessageStatus;
use App\Events\WhatsAppMessageStatusUpdated;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class WebhookStatusUpdater
{
    /**
     * Handle an incoming status update from the WhatsApp provider.
     */
    public function handle(StatusUpdate $update): ?WhatsAppMessage
    {
        $msg = WhatsAppMessage::where('provider_message_id', $update->providerMessageId)->first();

        if (! $msg && ! empty($update->altProviderMessageId)) {
            $msg = WhatsAppMessage::where('provider_message_id', $update->altProviderMessageId)->first();
        }

        if (! $msg) {
            Log::debug("[WebhookStatusUpdater] Message not found for provider_message_id: {$update->providerMessageId}");

            return null;
        }

        $newStatus = WhatsAppMessageStatus::tryFrom($update->status);

        if (! $newStatus) {
            Log::warning("[WebhookStatusUpdater] Unknown status value received: {$update->status}");

            return $msg;
        }

        // Never regress status order (e.g. read -> delivered is ignored).
        // Only allow status transition if newStatus is FAILED, or if current is not FAILED and order is advancing.
        if ($newStatus === WhatsAppMessageStatus::FAILED) {
            $msg->status = WhatsAppMessageStatus::FAILED;
            $msg->error_code = $update->errorCode;
            $msg->error_message = $update->errorCode ? "Meta error code: {$update->errorCode}" : 'Delivery failed';
            $msg->save();

            $this->broadcastStatus($msg);

            return $msg;
        }

        // If current status is already FAILED, do not overwrite unless explicit
        if ($msg->status === WhatsAppMessageStatus::FAILED) {
            return $msg;
        }

        // If the new status is equal to or earlier in lifecycle than current status, ignore
        if ($newStatus->order() <= $msg->status->order()) {
            return $msg;
        }

        $msg->status = $newStatus;

        $now = now();
        if ($newStatus === WhatsAppMessageStatus::SENT) {
            $msg->sent_at = $msg->sent_at ?? $now;
        } elseif ($newStatus === WhatsAppMessageStatus::DELIVERED) {
            $msg->sent_at = $msg->sent_at ?? $now;
            $msg->delivered_at = $msg->delivered_at ?? $now;
        } elseif ($newStatus === WhatsAppMessageStatus::READ) {
            $msg->sent_at = $msg->sent_at ?? $now;
            $msg->delivered_at = $msg->delivered_at ?? $now;
            $msg->read_at = $msg->read_at ?? $now;
        }

        $msg->save();

        $this->broadcastStatus($msg);

        return $msg;
    }

    /**
     * Safely broadcast status update via Pusher.
     */
    protected function broadcastStatus(WhatsAppMessage $msg): void
    {
        try {
            broadcast(new WhatsAppMessageStatusUpdated(
                driverId: $msg->driver_id,
                messageId: $msg->id,
                status: $msg->status->value,
                deliveredAt: $msg->delivered_at?->toIso8601String(),
                readAt: $msg->read_at?->toIso8601String(),
                errorMessage: $msg->error_message,
            ));
        } catch (\Throwable $e) {
            Log::debug("[WebhookStatusUpdater] Broadcast failed (offline/testing): {$e->getMessage()}");
        }
    }
}
