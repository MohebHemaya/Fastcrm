<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired during Excel import jobs to show live progress bar.
 * Broadcasts on private-import.{jobId}
 */
class ImportProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly int    $processed,
        public readonly int    $total,
        public readonly bool   $completed,
        public readonly string $status,    // 'processing' | 'completed' | 'failed'
        public readonly ?string $error = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("import.{$this->jobId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ImportProgressUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id'    => $this->jobId,
            'processed' => $this->processed,
            'total'     => $this->total,
            'percent'   => $this->total > 0 ? round(($this->processed / $this->total) * 100) : 0,
            'completed' => $this->completed,
            'status'    => $this->status,
            'error'     => $this->error,
        ];
    }
}
