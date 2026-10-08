<?php

namespace App\Listeners;

use App\Events\OutcomeRecordedEvent;
use App\Services\DriverMetricsService;

class UpdateDriverMetricsListener
{
    public function __construct(
        protected DriverMetricsService $metricsService
    ) {}

    public function handle(OutcomeRecordedEvent $event): void
    {
        $this->metricsService->recalculateForDriver($event->driverId);
    }
}
