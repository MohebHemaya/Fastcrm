<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a driver's status changes.
 * Broadcasts on private-drivers for table/badge live updates.
 */
class DriverStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $driverId,
        public readonly string $driverName,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly string $updatedAt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('drivers'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DriverStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'driver_id'   => $this->driverId,
            'driver_name' => $this->driverName,
            'old_status'  => $this->oldStatus,
            'new_status'  => $this->newStatus,
            'updated_at'  => $this->updatedAt,
        ];
    }
}
