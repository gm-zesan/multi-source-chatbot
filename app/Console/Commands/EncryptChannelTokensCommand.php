<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Casts\EnvelopedEncryptedString;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class EncryptChannelTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channel:encrypt-tokens
                            {--apply : Perform actual encryption and write changes to the database}
                            {--dry-run : Force dry-run mode without writing to database (default)}
                            {--batch-size=100 : Number of records to stream per cursor batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely and idempotently encrypt legacy plaintext channel account tokens using an enveloped ciphertext format';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = ! (bool) $this->option('apply') || (bool) $this->option('dry-run');
        $batchSize = max(1, (int) $this->option('batch-size'));

        $this->line('');
        $this->info('====================================================================');
        $this->info('   CHANNEL ACCOUNT TOKEN ENCRYPTION MIGRATION (IMP-SEC-001)');
        $this->info('====================================================================');

        if ($isDryRun) {
            $this->warn(' MODE: DRY-RUN (No database records will be modified)');
            $this->line(' To perform the migration, re-run with: php artisan channel:encrypt-tokens --apply');
        } else {
            $this->alert(' MODE: LIVE APPLY (Database records will be encrypted)');
        }

        // 1. Pre-flight check: Verify encryption key and cipher round-trip
        if (! $this->runPreflightEncryptionCheck()) {
            $this->error('Pre-flight check failed: Encryption key or cipher configuration is invalid.');
            return self::FAILURE;
        }

        $scanned = 0;
        $legacyPlaintext = 0;
        $alreadyEncrypted = 0;
        $nullEmpty = 0;
        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        $failedRecordIds = [];
        $skippedRecordIds = [];

        // 2. Incremental streaming cursor using primary key order
        $cursor = DB::table('channel_accounts')
            ->orderBy('id')
            ->lazyById($batchSize);

        foreach ($cursor as $row) {
            $scanned++;

            $accessEval = $this->evaluateField($row->access_token);
            $refreshEval = $this->evaluateField($row->refresh_token);

            // Check for corrupted ciphertext in either token field
            if ($accessEval['status'] === 'corrupted' || $refreshEval['status'] === 'corrupted') {
                $failed++;
                $failedRecordIds[] = $row->id;
                continue;
            }

            // Check if record contains any legacy plaintext tokens
            $hasLegacyPlaintext = ($accessEval['status'] === 'legacy') || ($refreshEval['status'] === 'legacy');

            if ($hasLegacyPlaintext) {
                $legacyPlaintext++;

                $updates = [];
                if ($accessEval['status'] === 'legacy') {
                    $updates['access_token'] = EnvelopedEncryptedString::PREFIX . Crypt::encryptString((string) $row->access_token);
                }
                if ($refreshEval['status'] === 'legacy') {
                    $updates['refresh_token'] = EnvelopedEncryptedString::PREFIX . Crypt::encryptString((string) $row->refresh_token);
                }

                if ($isDryRun) {
                    $migrated++;
                } else {
                    // 3. Compare-and-Set concurrency protection:
                    // Ensure row in database still matches the original raw values read.
                    $query = DB::table('channel_accounts')->where('id', $row->id);

                    if ($row->access_token === null) {
                        $query->whereNull('access_token');
                    } else {
                        $query->where('access_token', $row->access_token);
                    }

                    if ($row->refresh_token === null) {
                        $query->whereNull('refresh_token');
                    } else {
                        $query->where('refresh_token', $row->refresh_token);
                    }

                    $affected = $query->update($updates);

                    if ($affected === 1) {
                        $migrated++;
                    } else {
                        // Value was changed concurrently between read and write
                        $skipped++;
                        $skippedRecordIds[] = $row->id;
                    }
                }
            } elseif ($accessEval['status'] === 'encrypted' || $refreshEval['status'] === 'encrypted') {
                $alreadyEncrypted++;
            } else {
                // Both fields are null or empty
                $nullEmpty++;
            }
        }

        // 4. Summary presentation
        $this->line('');
        $this->info('Migration Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Records Scanned', $scanned],
                ['Legacy Plaintext Records', $legacyPlaintext],
                ['Already Encrypted (Valid)', $alreadyEncrypted],
                ['Null / Empty Records', $nullEmpty],
                [$isDryRun ? 'Would Migrate (Dry-Run)' : 'Successfully Migrated', $migrated],
                ['Skipped (Concurrent Changes)', $skipped],
                ['Failed (Corrupt Ciphertext)', $failed],
            ]
        );

        if ($skipped > 0) {
            $this->warn(sprintf(
                'Notice: %d record(s) were skipped due to concurrent modifications (IDs: %s). Re-run to process them.',
                $skipped,
                implode(', ', $skippedRecordIds)
            ));
        }

        if ($failed > 0) {
            $this->error(sprintf(
                'CRITICAL: %d record(s) contain corrupted or un-decryptable ciphertext (IDs: %s). These were NOT modified.',
                $failed,
                implode(', ', $failedRecordIds)
            ));
            return self::FAILURE;
        }

        if ($skipped > 0) {
            return self::FAILURE;
        }

        $this->info($isDryRun ? 'Dry-run completed successfully with zero database writes.' : 'Encryption migration applied successfully.');
        return self::SUCCESS;
    }

    /**
     * Evaluate an individual token attribute's encryption status.
     *
     * @return array{status: 'null_empty'|'encrypted'|'corrupted'|'legacy'}
     */
    private function evaluateField(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['status' => 'null_empty'];
        }

        $stringValue = (string) $value;

        if (str_starts_with($stringValue, EnvelopedEncryptedString::PREFIX)) {
            $payload = substr($stringValue, strlen(EnvelopedEncryptedString::PREFIX));
            try {
                Crypt::decryptString($payload);
                return ['status' => 'encrypted'];
            } catch (Throwable) {
                return ['status' => 'corrupted'];
            }
        }

        return ['status' => 'legacy'];
    }

    /**
     * Run an in-memory encryption round-trip to verify the application key.
     */
    private function runPreflightEncryptionCheck(): bool
    {
        try {
            $testPlaintext = 'preflight_canary_' . bin2hex(random_bytes(8));
            $ciphertext = Crypt::encryptString($testPlaintext);
            $decrypted = Crypt::decryptString($ciphertext);

            return $decrypted === $testPlaintext;
        } catch (Throwable) {
            return false;
        }
    }
}
