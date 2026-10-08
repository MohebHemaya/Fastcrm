<?php

namespace App\Console\Commands;

use App\Services\AcceptOrderOffer;
use Illuminate\Console\Command;

class TestAcceptOrderOfferCommand extends Command
{
    protected $signature = 'work-order:test-accept 
                            {orderId : ID of the work order} 
                            {driverId : ID of the driver} 
                            {action=accept : Action: accept or decline} 
                            {--barrier= : File path used as a synchronization start barrier}';

    protected $description = 'Worker command to simulate concurrent driver acceptances for race condition tests';

    public function handle(AcceptOrderOffer $acceptOrderOffer): int
    {
        $orderId = (int) $this->argument('orderId');
        $driverId = (int) $this->argument('driverId');
        $action = (string) $this->argument('action');
        $barrier = $this->option('barrier');

        // Wait for the start signal barrier file to be created by the test coordinator
        if ($barrier) {
            $timeout = microtime(true) + 15.0; // 15 seconds max wait
            while (! file_exists($barrier)) {
                if (microtime(true) > $timeout) {
                    $this->error('Barrier wait timed out');

                    return 1;
                }
                usleep(500); // 0.5ms wait
            }
        }

        $result = $acceptOrderOffer->handle($orderId, $driverId, $action);

        $this->line(json_encode($result));

        return 0;
    }
}
