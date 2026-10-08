<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'app:backup-db
                            {--keep=7 : Number of backup files to retain}
                            {--filename= : Custom filename for the backup}
                            {--secondary-disk= : Optional secondary storage disk destination}
                            {--encrypt : Encrypt backup file with APP_KEY}';

    protected $description = 'Create a full MySQL database backup using mysqldump or native Laravel SQL export with optional encryption and secondary disk copy';

    public function handle(): int
    {
        $keep = max(1, (int) $this->option('keep'));
        $backupDir = storage_path('app/backups');

        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
            // Place .gitignore and htaccess to protect backup files
            File::put($backupDir.'/.gitignore', "*\n!.gitignore\n");
            File::put($backupDir.'/.htaccess', "Deny from all\n");
        }

        $filename = $this->option('filename') ?: 'backup_'.date('Y_m_d_His').'.sql';
        $targetFile = $backupDir.'/'.$filename;

        $this->info("Creating database backup: {$filename}");

        $success = false;
        $method = 'mysqldump';

        // Attempt 1: mysqldump if available
        if ($this->isMysqldumpAvailable()) {
            $this->line('Using mysqldump utility...');
            $success = $this->dumpWithMysqldump($targetFile);
        }

        // Attempt 2: Fallback to native PHP/Laravel SQL export
        if (! $success) {
            $method = 'php_native';
            $this->line('Falling back to native Laravel/PDO SQL exporter...');
            $success = $this->dumpWithPhpNative($targetFile);
        }

        if (! $success || ! file_exists($targetFile) || filesize($targetFile) === 0) {
            $this->error('Database backup failed to generate a valid file.');
            Log::error('[Backup] Database backup failed', compact('targetFile', 'method'));

            return self::FAILURE;
        }

        // Optional Encryption with APP_KEY
        $encrypt = $this->option('encrypt') || (bool) config('backup.encryption_enabled');
        if ($encrypt) {
            $this->line('Encrypting backup with APP_KEY...');
            $encryptedFile = $targetFile.'.enc';
            $content = (string) File::get($targetFile);
            File::put($encryptedFile, Crypt::encrypt($content));
            File::delete($targetFile);
            $targetFile = $encryptedFile;
            $filename = basename($targetFile);
            $this->info("Backup encrypted successfully: {$filename}");
        }

        $sizeKb = round(filesize($targetFile) / 1024, 2);
        $this->info("Backup successfully generated ({$sizeKb} KB) using {$method}.");

        // Optional Replication to Secondary Disk
        $secondaryDisk = $this->option('secondary-disk') ?: config('backup.secondary_disk');
        if ($secondaryDisk) {
            try {
                $this->line("Copying backup to secondary destination disk: [{$secondaryDisk}]...");
                $stream = fopen($targetFile, 'r');
                if ($stream !== false) {
                    Storage::disk($secondaryDisk)->put("backups/{$filename}", $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    $this->info("Backup successfully replicated to secondary disk: [{$secondaryDisk}].");
                }
            } catch (\Throwable $e) {
                Log::warning("[Backup] Failed to copy backup to secondary disk [{$secondaryDisk}]: {$e->getMessage()}");
                $this->warn("Failed to copy to secondary disk: {$e->getMessage()}");
            }
        }

        // Apply retention policy: prune old backups
        $this->pruneOldBackups($backupDir, $keep);

        Log::info('[Backup] Database backup completed successfully', [
            'file' => $filename,
            'size_kb' => $sizeKb,
            'method' => $method,
            'encrypted' => $encrypt,
            'secondary_disk' => $secondaryDisk,
            'retained' => $keep,
        ]);

        return self::SUCCESS;
    }

    /**
     * Check if mysqldump CLI is available in PATH.
     */
    private function isMysqldumpAvailable(): bool
    {
        $testCommand = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump' : 'which mysqldump';
        $proc = Process::fromShellCommandline($testCommand);
        $proc->run();

        return $proc->isSuccessful() && ! empty(trim($proc->getOutput()));
    }

    /**
     * Dump database using mysqldump.
     */
    private function dumpWithMysqldump(string $targetFile): bool
    {
        $connection = config('database.default');
        $dbConfig = config("database.connections.{$connection}", []);

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? 3306;
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';
        $database = $dbConfig['database'] ?? 'driver_system';

        $passArg = $password !== '' ? "-p\"{$password}\"" : '';

        $cmd = sprintf(
            'mysqldump --single-transaction --quick --skip-lock-tables --default-character-set=utf8mb4 -h %s -P %s -u %s %s %s > %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            $passArg,
            escapeshellarg($database),
            escapeshellarg($targetFile)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(300);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Native PHP/Laravel SQL export for shared-hosting environments without mysqldump.
     */
    private function dumpWithPhpNative(string $targetFile): bool
    {
        $handle = fopen($targetFile, 'wb');
        if (! $handle) {
            return false;
        }

        $pdo = DB::connection()->getPdo();

        $header = "-- Fast CRM Database Backup (Laravel Native SQL Exporter)\n"
            .'-- Generated at: '.date('Y-m-d H:i:s')."\n"
            .'-- Host: '.config('database.connections.mysql.host', '127.0.0.1')."\n"
            .'-- Database: '.config('database.connections.mysql.database', 'driver_system')."\n\n"
            ."SET FOREIGN_KEY_CHECKS=0;\n"
            ."SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n"
            ."SET NAMES utf8mb4;\n"
            ."START TRANSACTION;\n\n";

        fwrite($handle, $header);

        // Fetch all base tables
        $tablesStatement = $pdo->query('SHOW FULL TABLES WHERE Table_Type = "BASE TABLE"');
        if ($tablesStatement === false) {
            fclose($handle);

            return false;
        }
        $tables = $tablesStatement->fetchAll(\PDO::FETCH_COLUMN, 0);

        foreach ($tables as $table) {
            // 1. Structure
            $createTableStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createSql = '';
            if ($createTableStmt !== false) {
                $createRow = $createTableStmt->fetch(\PDO::FETCH_ASSOC);
                $createSql = is_array($createRow) ? (string) ($createRow['Create Table'] ?? '') : '';
            }

            fwrite($handle, "-- --------------------------------------------------------\n");
            fwrite($handle, "-- Table structure for `{$table}`\n");
            fwrite($handle, "-- --------------------------------------------------------\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $createSql.";\n\n");

            // 2. Data
            fwrite($handle, "-- Dumping data for table `{$table}`\n");
            $rowsStatement = $pdo->query("SELECT * FROM `{$table}`");
            if ($rowsStatement === false) {
                continue;
            }

            $batchValues = [];
            $batchSize = 100;

            while ($row = $rowsStatement->fetch(\PDO::FETCH_ASSOC)) {
                if (! is_array($row)) {
                    continue;
                }

                $escapedValues = array_map(function ($val) use ($pdo) {
                    if ($val === null) {
                        return 'NULL';
                    }

                    return $pdo->quote((string) $val);
                }, $row);

                $batchValues[] = '('.implode(', ', $escapedValues).')';

                if (count($batchValues) >= $batchSize) {
                    $insertSql = "INSERT INTO `{$table}` VALUES \n".implode(",\n", $batchValues).";\n";
                    fwrite($handle, $insertSql);
                    $batchValues = [];
                }
            }

            if (! empty($batchValues)) {
                $insertSql = "INSERT INTO `{$table}` VALUES \n".implode(",\n", $batchValues).";\n";
                fwrite($handle, $insertSql);
            }

            fwrite($handle, "\n");
        }

        $footer = "COMMIT;\n"
            ."SET FOREIGN_KEY_CHECKS=1;\n"
            ."-- Backup completed successfully\n";

        fwrite($handle, $footer);
        fclose($handle);

        return true;
    }

    /**
     * Retain only the most recent N backups.
     */
    private function pruneOldBackups(string $backupDir, int $keep): void
    {
        $files = array_filter(File::files($backupDir), function ($file) {
            $ext = strtolower($file->getExtension());

            return in_array($ext, ['sql', 'enc', 'gz', 'zip'], true);
        });

        if (count($files) <= $keep) {
            return;
        }

        // Sort files by mtime descending (newest first)
        usort($files, fn ($a, $b) => $b->getMTime() <=> $a->getMTime());

        $toDelete = array_slice($files, $keep);
        foreach ($toDelete as $oldFile) {
            File::delete($oldFile->getPathname());
            $this->line('Pruned old backup: '.$oldFile->getFilename());
        }
    }
}
