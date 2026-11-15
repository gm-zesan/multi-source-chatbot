<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup
                            {--retention=30 : Number of days to keep old backups}
                            {--filename= : Custom filename for the backup archive}
                            {--compress : Whether to gzip compress the backup archive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a secure point-in-time database backup with integrity checksum and automated retention purge';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database backup process...');
        $startTime = microtime(true);

        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0750, true);
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $timestamp = now()->format('Y-m-d_His');
        $customName = $this->option('filename');

        $baseName = $customName ? (string) $customName : "db_backup_{$connection}_{$timestamp}";
        $isCompress = (bool) $this->option('compress');

        $this->line("Target connection: <comment>{$connection}</comment> (driver: <comment>{$driver}</comment>)");

        $backupFilePath = null;

        try {
            if ($driver === 'sqlite') {
                $backupFilePath = $this->backupSqlite($connection, $backupDir, $baseName);
            } elseif ($driver === 'mysql') {
                $backupFilePath = $this->backupMysql($connection, $backupDir, $baseName, $isCompress);
            } else {
                $this->error("Unsupported database driver for automated dump: [{$driver}].");
                return self::FAILURE;
            }

            if (! File::exists($backupFilePath) || File::size($backupFilePath) === 0) {
                $this->error("Backup failed: output file is empty or missing: {$backupFilePath}");
                return self::FAILURE;
            }

            // Calculate SHA-256 Checksum for integrity validation
            $checksum = hash_file('sha256', $backupFilePath);
            $sizeBytes = File::size($backupFilePath);
            $sizeMb = round($sizeBytes / 1024 / 1024, 2);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            // Write metadata sidecar file (.meta.json)
            $metaPath = $backupFilePath . '.meta.json';
            $metaData = [
                'timestamp'        => now()->toIso8601String(),
                'connection'       => $connection,
                'driver'           => $driver,
                'file_name'        => basename($backupFilePath),
                'file_size_bytes'  => $sizeBytes,
                'sha256_checksum'  => $checksum,
                'duration_ms'      => $elapsedMs,
                'is_compressed'    => $isCompress,
            ];
            File::put($metaPath, json_encode($metaData, JSON_PRETTY_PRINT));

            $this->info("Backup created successfully!");
            $this->table(
                ['Attribute', 'Value'],
                [
                    ['File Path', $backupFilePath],
                    ['File Size', "{$sizeMb} MB ({$sizeBytes} bytes)"],
                    ['SHA256 Hash', $checksum],
                    ['Duration', "{$elapsedMs} ms"],
                ]
            );

            Log::info('[DatabaseBackup] Backup completed successfully', $metaData);

            // Automated Retention Purge
            $retentionDays = (int) $this->option('retention');
            $this->purgeOldBackups($backupDir, $retentionDays);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Backup encountered fatal exception: " . $e->getMessage());
            Log::error('[DatabaseBackup] Backup failed with exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Backup SQLite Database safely.
     */
    private function backupSqlite(string $connection, string $backupDir, string $baseName): string
    {
        $dbPath = config("database.connections.{$connection}.database");

        if ($dbPath === ':memory:') {
            // For in-memory sqlite, dump schema & records to SQL script
            $targetFile = "{$backupDir}/{$baseName}.sql";
            $tables = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            
            $sqlDump = "-- In-Memory SQLite Dump: " . now()->toIso8601String() . "\n";
            foreach ($tables as $t) {
                $tableName = $t->name;
                $createRow = DB::connection($connection)->selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$tableName]);
                if ($createRow && $createRow->sql) {
                    $createSql = preg_replace('/^CREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', (string) $createRow->sql);
                    $sqlDump .= "\n" . $createSql . ";\n";
                    $sqlDump .= "DELETE FROM \"{$tableName}\";\n";
                    $rows = DB::connection($connection)->table($tableName)->get();
                    foreach ($rows as $row) {
                        $values = array_map(function ($val) {
                            return $val === null ? 'NULL' : "'" . addslashes((string) $val) . "'";
                        }, (array) $row);
                        $sqlDump .= "INSERT INTO \"{$tableName}\" VALUES (" . implode(', ', $values) . ");\n";
                    }
                }
            }
            File::put($targetFile, $sqlDump);
            return $targetFile;
        }

        $targetFile = "{$backupDir}/{$baseName}.sqlite";
        File::copy($dbPath, $targetFile);

        return $targetFile;
    }

    /**
     * Backup MySQL Database using mysqldump with transactional safety.
     */
    private function backupMysql(string $connection, string $backupDir, string $baseName, bool $isCompress): string
    {
        $config = config("database.connections.{$connection}");
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 3306);
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'] ?? '';

        $extension = $isCompress ? 'sql.gz' : 'sql';
        $targetFile = "{$backupDir}/{$baseName}.{$extension}";

        $dumpCmd = [
            'mysqldump',
            "--host={$host}",
            "--port={$port}",
            "--user={$username}",
            "--single-transaction",
            "--quick",
            "--skip-lock-tables",
            "--routines",
            "--triggers",
            $database,
        ];

        if (! empty($password)) {
            $dumpCmd[] = "--password={$password}";
        }

        if ($isCompress) {
            $process = Process::fromShellCommandLine(
                implode(' ', array_map('escapeshellarg', $dumpCmd)) . ' | gzip > ' . escapeshellarg($targetFile)
            );
        } else {
            $process = new Process($dumpCmd);
        }

        $process->setTimeout(3600); // 1 hour timeout for large databases

        if (! $isCompress) {
            $process->run(function ($type, $buffer) use ($targetFile) {
                File::append($targetFile, $buffer);
            });
        } else {
            $process->run();
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("mysqldump failed: " . $process->getErrorOutput());
        }

        return $targetFile;
    }

    /**
     * Purge backups older than given retention days.
     */
    private function purgeOldBackups(string $backupDir, int $retentionDays): void
    {
        if ($retentionDays <= 0) {
            return;
        }

        $threshold = Carbon::now()->subDays($retentionDays)->getTimestamp();
        $files = File::files($backupDir);
        $purgedCount = 0;

        foreach ($files as $file) {
            if ($file->getMTime() < $threshold) {
                File::delete($file->getRealPath());
                $purgedCount++;
            }
        }

        if ($purgedCount > 0) {
            $this->info("Purged {$purgedCount} backup file(s) older than {$retentionDays} days.");
            Log::info("[DatabaseBackup] Purged old backups", ['count' => $purgedCount, 'retention_days' => $retentionDays]);
        }
    }
}
