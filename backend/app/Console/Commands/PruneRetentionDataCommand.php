<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use App\Models\WhatsAppMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneRetentionDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-retention-data
                            {--months= : Number of months of data to keep (defaults to config app.retention_months)}
                            {--dry-run : Simulate pruning without modifying database records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old webhook events and strip payloads from old WhatsApp messages based on data retention policy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $months = (int) ($this->option('months') ?: config('app.retention_months', 3));

        if ($months < 1) {
            $this->error('Retention months must be at least 1.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMonths($months);

        $this->info("Retention cutoff date: {$cutoff->toDateTimeString()} ({$months} months retention)");

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No database rows will be deleted or modified.');
        }

        // 1. Webhook events
        $webhookQuery = WebhookEvent::where('created_at', '<', $cutoff);
        $webhookCount = $webhookQuery->count();

        if (! $dryRun && $webhookCount > 0) {
            $deletedWebhooks = $webhookQuery->delete();
        } else {
            $deletedWebhooks = $webhookCount;
        }

        // 2. WhatsApp messages payload stripping
        $messageQuery = WhatsAppMessage::where('created_at', '<', $cutoff)->whereNotNull('payload');
        $messagePayloadCount = $messageQuery->count();

        if (! $dryRun && $messagePayloadCount > 0) {
            $updatedMessages = $messageQuery->update(['payload' => null]);
        } else {
            $updatedMessages = $messagePayloadCount;
        }

        $this->table(
            ['Entity', 'Action', 'Candidate Rows', 'Processed Rows', 'Dry Run'],
            [
                ['webhook_events', 'Delete old events', $webhookCount, $deletedWebhooks, $dryRun ? 'Yes' : 'No'],
                ['whatsapp_messages', 'Nullify old JSON payload', $messagePayloadCount, $updatedMessages, $dryRun ? 'Yes' : 'No'],
            ]
        );

        Log::info('[Retention] Prune retention data completed', [
            'cutoff' => $cutoff->toDateTimeString(),
            'months' => $months,
            'dry_run' => $dryRun,
            'webhooks_pruned' => $deletedWebhooks,
            'message_payloads_cleared' => $updatedMessages,
        ]);

        return self::SUCCESS;
    }
}
