<?php

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredExportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exports:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired export files and database records older than 7 days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredExports = Export::where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDays(7))
            ->get();

        $count = 0;
        foreach ($expiredExports as $export) {
            if (! empty($export->path) && Storage::disk('local')->exists($export->path)) {
                Storage::disk('local')->delete($export->path);
            }

            $export->delete();
            $count++;
        }

        $this->info("Successfully cleaned up {$count} expired exports.");

        return Command::SUCCESS;
    }
}
