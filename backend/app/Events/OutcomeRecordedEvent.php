<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OutcomeRecordedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $driverId,
        public ?int $recipientId = null,
        public ?int $workOrderId = null
    ) {}
}
