<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupDir = storage_path('app/backups');
        if (! File::isDirectory($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0750, true);
        }
    }

    protected function tearDown(): void
    {
        // Cleanup test backup files
        if (File::isDirectory($this->backupDir)) {
            $files = File::files($this->backupDir);
            foreach ($files as $file) {
                if (str_starts_with($file->getFilename(), 'test_e2e_backup') || str_starts_with($file->getFilename(), 'pre_restore_safety_')) {
                    File::delete($file->getRealPath());
                }
            }
        }
        parent::tearDown();
    }

    public function test_database_backup_creates_valid_archive_and_metadata(): void
    {
        $customName = 'test_e2e_backup_'.time();

        $this->artisan('db:backup', [
            '--filename' => $customName,
            '--retention' => 30,
        ])
            ->expectsOutputToContain('Backup created successfully!')
            ->assertExitCode(0);

        $backupFiles = File::files($this->backupDir);
        $foundSql = false;
        $foundMeta = false;
        $sqlPath = null;

        foreach ($backupFiles as $file) {
            if ($file->getFilename() === "{$customName}.sql" || $file->getFilename() === "{$customName}.sqlite") {
                $foundSql = true;
                $sqlPath = $file->getRealPath();
            }
            if ($file->getFilename() === "{$customName}.sql.meta.json" || $file->getFilename() === "{$customName}.sqlite.meta.json") {
                $foundMeta = true;
                $metaContent = json_decode(File::get($file->getRealPath()), true);
                $this->assertArrayHasKey('sha256_checksum', $metaContent);
                $this->assertArrayHasKey('connection', $metaContent);
                $this->assertArrayHasKey('file_size_bytes', $metaContent);
            }
        }

        $this->assertTrue($foundSql, "Expected backup file {$customName} was not found.");
        $this->assertTrue($foundMeta, "Expected metadata sidecar for {$customName} was not found.");

        // Verify SHA-256 Checksum matches file
        if ($sqlPath && $foundMeta) {
            $calculatedHash = hash_file('sha256', $sqlPath);
            $this->assertSame($calculatedHash, $metaContent['sha256_checksum']);
        }
    }

    public function test_database_backup_purges_old_archives(): void
    {
        $oldFile = "{$this->backupDir}/test_e2e_backup_ancient.sql";
        File::put($oldFile, '-- Ancient SQL Backup');
        // Set modification time to 40 days ago
        touch($oldFile, time() - (40 * 86400));

        $this->artisan('db:backup', [
            '--filename' => 'test_e2e_backup_purge_test',
            '--retention' => 30,
        ])
            ->expectsOutputToContain('Purged 1 backup file(s) older than 30 days.')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($oldFile);
    }

    public function test_database_restore_recovers_data_with_integrity(): void
    {
        $backupName = 'test_e2e_backup_restore_'.time();

        $this->artisan('db:backup', [
            '--filename' => $backupName,
            '--retention' => 30,
        ])
            ->assertExitCode(0);

        $backupFileName = "{$backupName}.sql";
        if (! File::exists("{$this->backupDir}/{$backupFileName}")) {
            $backupFileName = "{$backupName}.sqlite";
        }

        $this->artisan('db:restore', [
            'file' => $backupFileName,
            '--force' => true,
        ])
            ->expectsOutputToContain('Pre-restore safety snapshot created successfully')
            ->expectsOutputToContain('Database restored successfully')
            ->assertExitCode(0);
    }

    public function test_database_restore_respects_skip_safety_backup_flag(): void
    {
        $backupName = 'test_e2e_backup_restore_nosafety_'.time();

        $this->artisan('db:backup', [
            '--filename' => $backupName,
            '--retention' => 30,
        ])
            ->assertExitCode(0);

        $backupFileName = "{$backupName}.sql";
        if (! File::exists("{$this->backupDir}/{$backupFileName}")) {
            $backupFileName = "{$backupName}.sqlite";
        }

        $this->artisan('db:restore', [
            'file' => $backupFileName,
            '--force' => true,
            '--skip-safety-backup' => true,
        ])
            ->doesntExpectOutputToContain('Creating pre-restore safety snapshot')
            ->expectsOutputToContain('Database restored successfully')
            ->assertExitCode(0);
    }
}
