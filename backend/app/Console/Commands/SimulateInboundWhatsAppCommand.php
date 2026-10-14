<?php

namespace App\Console\Commands;

use App\DTOs\WhatsApp\InboundMessage;
use App\Services\InboundMessageRouter;
use Illuminate\Console\Command;

class SimulateInboundWhatsAppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:simulate-inbound 
                            {phone : The E.164 phone number of the driver or sender}
                            {text? : The text message body}
                            {--button= : The interactive button payload ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate an inbound WhatsApp message or button tap for end-to-end testing and scenarios';

    /**
     * Execute the console command.
     */
    public function handle(InboundMessageRouter $router): int
    {
        $phone = (string) $this->argument('phone');
        $text = $this->argument('text');
        $button = $this->option('button');

        $type = ! empty($button) ? 'button' : 'text';
        $providerMessageId = 'sim_wamid_'.uniqid('', true);

        $inbound = new InboundMessage(
            from: $phone,
            type: $type,
            text: $text !== null ? (string) $text : null,
            payloadId: ! empty($button) ? (string) $button : null,
            providerMessageId: $providerMessageId,
            timestamp: time()
        );

        $router->handle($inbound);

        $this->info("Simulated inbound {$type} message from {$phone} processed successfully.");

        return self::SUCCESS;
    }
}
