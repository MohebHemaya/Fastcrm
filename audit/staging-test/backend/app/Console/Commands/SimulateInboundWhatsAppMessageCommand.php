<?php

namespace App\Console\Commands;

use App\DTOs\WhatsApp\InboundMessage;
use App\Services\InboundMessageRouter;
use Illuminate\Console\Command;

class SimulateInboundWhatsAppMessageCommand extends Command
{
    protected $signature = 'whatsapp:simulate-inbound 
                            {phone : The sender phone number in local or E.164 format}
                            {text? : The text body}
                            {--button= : Payload ID if simulating a button click}
                            {--message-id= : Custom provider message ID}';

    protected $description = 'Simulate an incoming WhatsApp message for local testing and dev';

    public function handle(InboundMessageRouter $router): int
    {
        $phone = $this->argument('phone');
        $text = $this->argument('text') ?? ($this->option('button') ? '' : 'مرحبًا');
        $payloadId = $this->option('button');
        $providerMsgId = $this->option('message-id') ?? ('sim_'.uniqid('', true));

        $inbound = new InboundMessage(
            from: $phone,
            type: $payloadId ? 'button' : 'text',
            text: $text,
            payloadId: $payloadId,
            providerMessageId: $providerMsgId,
            timestamp: now()->timestamp,
        );

        $this->info("Simulating inbound message from {$phone} (payloadId: {$payloadId}, text: {$text})...");

        $router->handle($inbound);

        $this->info("Handled successfully. Provider message ID: {$providerMsgId}");

        return Command::SUCCESS;
    }
}
