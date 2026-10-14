<?php

namespace App\Console\Commands;

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Models\Driver;
use Illuminate\Console\Command;
use Throwable;

class ListenWhatsAppInboundCommand extends Command
{
    protected $signature = 'whatsapp:listen 
                            {--interval=3 : Polling frequency in seconds (default 3s)}
                            {--once : Run once and exit}';

    protected $description = 'Background daemon to continuously capture and process WhatsApp replies from drivers';

    public function handle(WhatsAppProvider $provider): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $once = (bool) $this->option('once');

        $this->info("-------------------------------------------------------");
        $this->info(" WhatsApp Background Daemon Listener Started");
        $this->info("-------------------------------------------------------");
        $this->line("Provider : <comment>" . config('whatsapp.provider') . "</comment>");
        $this->line("Interval : <comment>{$interval}s</comment>");
        $this->line("Status   : <info>Listening for driver replies in background...</info>");
        $this->info("-------------------------------------------------------");

        do {
            try {
                // Find drivers awaiting onboarding answers, with active work order offers, or recent outbound messages
                $activeDrivers = Driver::query()
                    ->whereIn('status', [
                        DriverStatus::INVITED,
                        DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
                    ])
                    ->orWhereHas('workOrderRecipients', function ($q) {
                        $q->where('status', 'sent');
                    })
                    ->orWhereHas('whatsappMessages', function ($q) {
                        $q->where('direction', 'out')
                          ->where('created_at', '>=', now()->subHours(2));
                    })
                    ->orWhere('updated_at', '>=', now()->subMinutes(30))
                    ->distinct()
                    ->limit(25)
                    ->get();

                foreach ($activeDrivers as $driver) {
                    $count = $provider->syncChatInbound($driver->phone);
                    if ($count > 0) {
                        $this->info("[" . now()->toTimeString() . "] Captured {$count} reply(s) from driver {$driver->phone} ({$driver->name})");
                    }
                }
            } catch (Throwable $e) {
                $this->error("[" . now()->toTimeString() . "] Error polling inbound: " . $e->getMessage());
            }

            if ($once) {
                break;
            }

            sleep($interval);
        } while (true);

        return self::SUCCESS;
    }
}
