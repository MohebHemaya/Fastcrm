<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a work order or its recipients change (acceptance, cancellation, filling, etc.).
 * Broadcasts on private-work-orders and private-work-order.{id} for live real-time UI updates.
 */
class WorkOrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $recipient
     */
    public function __construct(
        public readonly int $workOrderId,
        public readonly string $orderCode,
        public readonly string $status,
        public readonly int $confirmedCount,
        public readonly int $backupCount,
        public readonly int $tripsNeeded,
        public readonly int $backupNeeded,
        public readonly bool $isFilled,
        public readonly ?array $recipient = null,
        public readonly ?string $notificationMessage = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('work-orders'),
            new PrivateChannel("work-order.{$this->workOrderId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WorkOrderUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'work_order_id' => $this->workOrderId,
            'order_code' => $this->orderCode,
            'status' => $this->status,
            'confirmed_count' => $this->confirmedCount,
            'backup_count' => $this->backupCount,
            'trips_needed' => $this->tripsNeeded,
            'backup_needed' => $this->backupNeeded,
            'is_filled' => $this->isFilled,
            'recipient' => $this->recipient,
            'message' => $this->notificationMessage,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
