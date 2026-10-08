<?php

namespace App\Console\Commands;

use App\DTOs\WhatsApp\StatusUpdate;
use App\Models\WhatsAppMessage;
use App\Services\WebhookStatusUpdater;
use Illuminate\Console\Command;

class SimulateWhatsAppStatusCommand extends Command
{
    protected $signature = 'whatsapp:simulate-status
                            {message_id : The ID or provider_message_id of the WhatsAppMessage}
                            {status : The target status: sent, delivered, read, failed}
                            {--error-code= : Optional error code for failed status}';

    protected $description = 'Simulate an incoming status update for a sent WhatsApp message';

    public function handle(WebhookStatusUpdater $updater): int
    {
        $messageIdentifier = $this->argument('message_id');
        $targetStatus = strtolower($this->argument('status'));
        $errorCode = $this->option('error-code');

        $msg = WhatsAppMessage::where('id', $messageIdentifier)
            ->orWhere('provider_message_id', $messageIdentifier)
            ->first();

        if (! $msg) {
            $this->error("WhatsApp message not found for identifier: {$messageIdentifier}");

            return Command::FAILURE;
        }

        if (! $msg->provider_message_id) {
            $msg->update(['provider_message_id' => 'fake_'.uniqid('sim_', true)]);
        }

        $update = new StatusUpdate(
            providerMessageId: $msg->provider_message_id,
            status: $targetStatus,
            errorCode: $errorCode,
        );

        $updater->handle($update);

        $msg->refresh();

        $this->info("Updated message #{$msg->id} status to: {$msg->status->value}");

        return Command::SUCCESS;
    }
}
