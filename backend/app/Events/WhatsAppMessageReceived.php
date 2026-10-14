<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a driver sends a WhatsApp message inbound.
 * Broadcasts on private-whatsapp.driver.{driverId}
 */
class WhatsAppMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $driverId,
        public readonly int    $messageId,
        public readonly string $body,
        public readonly string $direction,   // 'inbound'
        public readonly string $createdAt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("whatsapp.driver.{$this->driverId}"),
            new PrivateChannel('whatsapp.inbox'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageReceived';
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
