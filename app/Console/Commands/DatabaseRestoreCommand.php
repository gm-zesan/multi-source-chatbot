<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DatabaseRestoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:restore
                            {file : Filename (in storage/app/backups) or absolute path to the backup archive}
                            {--force : Force execution without interactive confirmation}
                            {--skip-checksum : Skip SHA256 integrity verification}
                            {--skip-safety-backup : Skip automated pre-restore safety snapshot}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely restore a database backup archive with cryptographic integrity verification';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fileInput = (string) $this->argument('file');
        $backupDir = storage_path('app/backups');

        // Resolve file path
        $filePath = File::exists($fileInput) ? $fileInput : "{$backupDir}/{$fileInput}";

        if (! File::exists($filePath)) {
            $this->error("Backup file not found at: {$filePath}");

            return self::FAILURE;
        }

        // Verify SHA-256 Checksum if .meta.json exists
        $metaPath = $filePath.'.meta.json';
        if (! $this->option('skip-checksum') && File::exists($metaPath)) {
            $metaData = json_decode(File::get($metaPath), true);
            $expectedChecksum = $metaData['sha256_checksum'] ?? null;
            if ($expectedChecksum) {
                $actualChecksum = hash_file('sha256', $filePath);
                if ($actualChecksum !== $expectedChecksum) {
                    $this->error("CRITICAL INTEGRITY FAILURE: Checksum mismatch! Expected {$expectedChecksum}, got {$actualChecksum}. Backup archive may be corrupted.");

                    return self::FAILURE;
                }
                $this->info("SHA-256 checksum verified: {$actualChecksum}");
            }
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if (! $this->option('force')) {
            if (! $this->confirm("Are you SURE you want to restore [{$filePath}] into connection [{$connection}]? THIS WILL OVERWRITE EXISTING DATA!")) {
                $this->warn('Database restore cancelled by operator.');

                return self::SUCCESS;
            }
        }

        $safetySnapshotName = null;
        if (! $this->option('skip-safety-backup')) {
            $safetySnapshotName = 'pre_restore_safety_'.date('Y_m_d_His');
            $this->info("Creating pre-restore safety snapshot: [{$safetySnapshotName}]...");
            try {
                $backupExit = $this->callSilent('db:backup', [
                    '--filename' => $safetySnapshotName,
                    '--retention' => 30,
                ]);
                if ($backupExit === self::SUCCESS) {
                    $this->info("Pre-restore safety snapshot created successfully: {$safetySnapshotName}");
                } else {
                    $this->warn("Automated pre-restore safety snapshot returned exit code [{$backupExit}]. Proceeding with caution.");
                }
            } catch (\Throwable $e) {
                $this->warn("Automated pre-restore safety snapshot failed: {$e->getMessage()}. Proceeding.");
            }
        }

        $this->info("Restoring database to connection: [{$connection}]...");
        $startTime = microtime(true);

        try {
            if ($driver === 'sqlite') {
                $this->restoreSqlite($connection, $filePath);
            } elseif ($driver === 'mysql') {
                $this->restoreMysql($connection, $filePath);
            } else {
                $this->error("Unsupported database driver: [{$driver}].");

                return self::FAILURE;
            }

            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("Database restored successfully in {$elapsedMs} ms!");

            Log::warning('[DatabaseRestore] Database restored from archive', [
                'file' => $filePath,
                'connection' => $connection,
                'driver' => $driver,
                'duration_ms' => $elapsedMs,
                'safety_snapshot' => $safetySnapshotName,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Restore failed with exception: '.$e->getMessage());
            if ($safetySnapshotName) {
                $this->error("CRITICAL: Target database may be in an inconsistent state. Automated safety snapshot is available to rollback: php artisan db:restore {$safetySnapshotName} --force");
            }
            Log::critical('[DatabaseRestore] Restore failed', [
                'file' => $filePath,
                'safety_snapshot' => $safetySnapshotName,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    private function restoreSqlite(string $connection, string $filePath): void
    {
        $dbPath = config("database.connections.{$connection}.database");

        if ($dbPath === ':memory:') {
            $pdo = DB::connection($connection)->getPdo();
            $sql = File::get($filePath);
            $pdo->exec($sql);

            return;
        }

        if (str_ends_with($filePath, '.sql')) {
            $sql = File::get($filePath);
            DB::connection($connection)->unprepared($sql);
        } else {
            File::copy($filePath, $dbPath);
        }
    }

    private function restoreMysql(string $connection, string $filePath): void
    {
        $config = config("database.connections.{$connection}");
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 3306);
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'] ?? '';

        $isCompressed = str_ends_with($filePath, '.gz');

        $mysqlCmd = [
            'mysql',
            "--host={$host}",
            "--port={$port}",
            "--user={$username}",
            $database,
        ];

        if (! empty($password)) {
            $mysqlCmd[] = "--password={$password}";
        }

        if ($isCompressed) {
            $cmd = 'gzip -dc '.escapeshellarg($filePath).' | '.implode(' ', array_map('escapeshellarg', $mysqlCmd));
            $process = Process::fromShellCommandLine($cmd);
        } else {
            $process = Process::fromShellCommandLine(
                implode(' ', array_map('escapeshellarg', $mysqlCmd)).' < '.escapeshellarg($filePath)
            );
        }

        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysql restore failed: '.$process->getErrorOutput());
        }
    }
}
