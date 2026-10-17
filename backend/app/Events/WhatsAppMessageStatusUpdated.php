<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a WhatsApp message status updates (e.g. sent -> delivered -> read).
 * Broadcasts on private-whatsapp.driver.{driverId}
 */
class WhatsAppMessageStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $driverId,
        public readonly int $messageId,
        public readonly string $status,
        public readonly ?string $deliveredAt = null,
        public readonly ?string $readAt = null,
        public readonly ?string $errorMessage = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("whatsapp.driver.{$this->driverId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageStatusUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driverId,
            'message_id' => $this->messageId,
            'status' => $this->status,
            'delivered_at' => $this->deliveredAt,
            'read_at' => $this->readAt,
            'error_message' => $this->errorMessage,
        ];
    }
}
