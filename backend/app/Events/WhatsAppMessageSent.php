<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the system/admin sends a WhatsApp message to a driver.
 * Broadcasts on private-whatsapp.driver.{driverId}
 */
class WhatsAppMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $driverId,
        public readonly int    $messageId,
        public readonly string $body,
        public readonly string $direction,   // 'outbound'
        public readonly string $createdAt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("whatsapp.driver.{$this->driverId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id'  => $this->driverId,
            'message_id' => $this->messageId,
            'body'       => $this->body,
            'direction'  => $this->direction,
            'created_at' => $this->createdAt,
        ];
    }
}
