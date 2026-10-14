<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a driver's WhatsApp conversation is updated
 * (new message, read status change, etc.).
 * Broadcasts on private-whatsapp.inbox for sidebar refresh.
 */
class WhatsAppConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $driverId,
        public readonly string $driverName,
        public readonly string $lastMessage,
        public readonly int    $unreadCount,
        public readonly string $updatedAt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('whatsapp.inbox'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ConversationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id'    => $this->driverId,
            'driver_name'  => $this->driverName,
            'last_message' => $this->lastMessage,
            'unread_count' => $this->unreadCount,
            'updated_at'   => $this->updatedAt,
        ];
    }
}
