<?php

namespace App\Console\Commands;

use App\Enums\DriverStatus;
use App\Models\Driver;
use App\Services\DriverStatusService;
use Illuminate\Console\Command;

class ExpireDriverInvitesCommand extends Command
{
    protected $signature = 'drivers:expire-invites';

    protected $description = 'Expire invitations for drivers who have not responded within the configured window';

    public function handle(): int
    {
        $days = (int) config('whatsapp.no_response_days', 3);
        $cutoff = now()->subDays($days);

        $drivers = Driver::where('status', DriverStatus::INVITED)
            ->where('updated_at', '<=', $cutoff)
            ->get();

        $count = 0;

        foreach ($drivers as $driver) {
            if (DriverStatusService::canTransition($driver->status, DriverStatus::NO_RESPONSE)) {
                DriverStatusService::transition(
                    $driver,
                    DriverStatus::NO_RESPONSE,
                    "انتهاء مهلة الرد على الدعوة ({$days} أيام)"
                );
                $count++;
            }
        }

        $this->info("Expired {$count} driver invitations.");

        return Command::SUCCESS;
    }
}
