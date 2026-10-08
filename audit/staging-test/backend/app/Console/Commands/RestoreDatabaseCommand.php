<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RestoreDatabaseCommand extends Command
{
    protected $signature = 'app:restore-db
                            {file? : Path to the backup file to restore}
                            {--dry-run : Validate and test decryption without modifying database}
                            {--force : Force the operation without interactive confirmation}';

    protected $description = 'Restore a MySQL database backup from a .sql or encrypted .sql.enc / .sql.gz.enc file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! $filePath) {
            // Find most recent backup in storage/app/backups
            $backupDir = storage_path('app/backups');
            if (File::isDirectory($backupDir)) {
                $files = File::files($backupDir);
                $backups = array_filter($files, function ($f) {
                    $ext = $f->getExtension();
                    return in_array($ext, ['sql', 'enc']);
                });
                usort($backups, fn ($a, $b) => $b->getMTime() - $a->getMTime());
                if (! empty($backups)) {
                    $filePath = $backups[0]->getPathname();
                    $this->line("Auto-selected latest backup: {$filePath}");
                }
            }
        }

        if (! $filePath || ! File::exists($filePath)) {
            $this->error("Backup file not found: {$filePath}");
            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $this->info("Validating backup file: {$filePath}");

        $rawContent = (string) File::get($filePath);
        $sqlContent = $rawContent;

        // Check if encrypted
        if (str_ends_with($filePath, '.enc')) {
            $this->line('File is encrypted. Testing decryption with APP_KEY...');
            try {
                $sqlContent = Crypt::decrypt($rawContent);
                $this->info('Decryption successful.');
            } catch (\Throwable $e) {
                $this->error("Failed to decrypt backup with APP_KEY: {$e->getMessage()}");
                return self::FAILURE;
            }
        }

        // Check if gzipped (.gz or .gz.enc)
        if (str_contains($filePath, '.gz')) {
            $decompressed = @gzdecode($sqlContent);
            if ($decompressed !== false) {
                $sqlContent = $decompressed;
                $this->info('Decompressed gzip payload successfully.');
            }
        }

        if (empty(trim($sqlContent))) {
            $this->error('Backup content is empty after decompression/decryption.');
            return self::FAILURE;
        }

        $sizeKb = round(strlen($sqlContent) / 1024, 2);
        $this->info("Valid SQL payload verified ({$sizeKb} KB).");

        if ($isDryRun) {
            $this->info('Dry-run complete: Backup is readable, decrypted, and valid. Database was NOT modified.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && app()->isProduction()) {
            if (! $this->confirm('WARNING: Restoring the database will overwrite current data. Continue?')) {
                $this->warn('Operation cancelled.');
                return self::FAILURE;
            }
        }

        $this->line('Applying SQL restore to current database...');
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::unprepared($sqlContent);
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->info('Database restored successfully.');
            Log::info('[Backup] Database restored from file', ['file' => $filePath]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->error("Database restoration failed: {$e->getMessage()}");
            Log::error('[Backup] Database restoration failed', ['file' => $filePath, 'error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
