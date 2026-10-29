<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Casts\EnvelopedEncryptedString;
use App\Models\Channel;
use App\Models\Workspace;
use Illuminate\Database\Events\QueryExecuting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EncryptChannelTokensCommandTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Migration Test Workspace', 'slug' => 'mig-test']);
        $this->channel = Channel::create(['slug' => 'facebook', 'name' => 'Facebook', 'is_active' => true]);
    }

    public function test_dry_run_is_default_and_makes_no_database_changes(): void
    {
        $id = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Page 1',
            'external_id'   => 'page_1',
            'access_token'  => 'plain_text_token_123',
            'refresh_token' => 'plain_text_refresh_456',
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Default run without --apply should be dry-run
        $this->artisan('channel:encrypt-tokens')
            ->expectsOutputToContain('MODE: DRY-RUN')
            ->expectsOutputToContain('Would Migrate (Dry-Run)')
            ->assertExitCode(0);

        // Verify database is completely unchanged
        $raw = DB::table('channel_accounts')->where('id', $id)->first();
        $this->assertSame('plain_text_token_123', $raw->access_token);
        $this->assertSame('plain_text_refresh_456', $raw->refresh_token);

        // Explicit --dry-run flag should also make no changes
        $this->artisan('channel:encrypt-tokens --dry-run')
            ->expectsOutputToContain('MODE: DRY-RUN')
            ->assertExitCode(0);

        $rawAfter = DB::table('channel_accounts')->where('id', $id)->first();
        $this->assertSame('plain_text_token_123', $rawAfter->access_token);
    }

    public function test_apply_mode_encrypts_legacy_access_and_refresh_tokens(): void
    {
        $id = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Page To Encrypt',
            'external_id'   => 'page_2',
            'access_token'  => 'plain_access_secret_789',
            'refresh_token' => 'plain_refresh_secret_321',
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->artisan('channel:encrypt-tokens --apply')
            ->expectsOutputToContain('MODE: LIVE APPLY')
            ->expectsOutputToContain('Successfully Migrated')
            ->assertExitCode(0);

        $raw = DB::table('channel_accounts')->where('id', $id)->first();

        // 1. Must start with envelope prefix
        $this->assertStringStartsWith(EnvelopedEncryptedString::PREFIX, $raw->access_token);
        $this->assertStringStartsWith(EnvelopedEncryptedString::PREFIX, $raw->refresh_token);

        // 2. Must not contain raw token
        $this->assertStringNotContainsString('plain_access_secret_789', $raw->access_token);
        $this->assertStringNotContainsString('plain_refresh_secret_321', $raw->refresh_token);

        // 3. Must decrypt to original values
        $this->assertSame(
            'plain_access_secret_789',
            Crypt::decryptString(substr($raw->access_token, strlen(EnvelopedEncryptedString::PREFIX)))
        );
        $this->assertSame(
            'plain_refresh_secret_321',
            Crypt::decryptString(substr($raw->refresh_token, strlen(EnvelopedEncryptedString::PREFIX)))
        );
    }

    public function test_existing_valid_ciphertext_remains_unchanged(): void
    {
        $existingEncrypted = EnvelopedEncryptedString::PREFIX . Crypt::encryptString('already_encrypted_token_value');

        $id = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Already Encrypted Page',
            'external_id'   => 'page_3',
            'access_token'  => $existingEncrypted,
            'refresh_token' => null,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->artisan('channel:encrypt-tokens --apply')
            ->expectsOutputToContain('Already Encrypted (Valid)')
            ->assertExitCode(0);

        $raw = DB::table('channel_accounts')->where('id', $id)->first();

        // Ciphertext must remain byte-for-byte identical (not re-encrypted)
        $this->assertSame($existingEncrypted, $raw->access_token);
    }

    public function test_null_and_empty_values_are_handled_safely(): void
    {
        // access_token is NOT NULL in database schema; test with empty string for access_token and null for refresh_token
        $id = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Null Account',
            'external_id'   => 'page_null',
            'access_token'  => '',
            'refresh_token' => null,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->artisan('channel:encrypt-tokens --apply')
            ->expectsOutputToContain('Null / Empty Records')
            ->assertExitCode(0);

        $raw = DB::table('channel_accounts')->where('id', $id)->first();
        $this->assertSame('', $raw->access_token);
        $this->assertNull($raw->refresh_token);
    }

    public function test_corrupt_ciphertext_is_preserved_and_reported_as_failure(): void
    {
        $corruptValue = EnvelopedEncryptedString::PREFIX . 'corrupted_un_decryptable_payload';

        $id = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Corrupted Account',
            'external_id'   => 'page_corrupt',
            'access_token'  => $corruptValue,
            'refresh_token' => null,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->artisan('channel:encrypt-tokens --apply')
            ->expectsOutputToContain('CRITICAL: 1 record(s) contain corrupted or un-decryptable ciphertext')
            ->assertExitCode(1);

        // Corrupted value must be preserved as-is, never modified or converted to plaintext
        $raw = DB::table('channel_accounts')->where('id', $id)->first();
        $this->assertSame($corruptValue, $raw->access_token);
    }

    public function test_concurrent_change_simulation_prevents_overwriting_newer_token(): void
    {
        // Seed two records so cursor loads both in a single batch
        $id1 = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Page 1 First in Batch',
            'external_id'   => 'page_concurrent_1',
            'access_token'  => 'row_1_token',
            'refresh_token' => null,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $id2 = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Page 2 Concurrent Target',
            'external_id'   => 'page_concurrent_2',
            'access_token'  => 'row_2_token_read_into_cursor_buffer',
            'refresh_token' => null,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // When row 1 is updated, mutate row 2 concurrently in the database
        // so row 2's pre-read token no longer matches the database during its CAS update
        $mutated = false;
        DB::listen(function ($query) use ($id2, &$mutated) {
            $sql = strtolower($query->sql);
            if (! $mutated && str_contains($sql, 'update') && str_contains($sql, 'channel_accounts')) {
                $mutated = true;
                DB::connection()->getPdo()->exec("UPDATE channel_accounts SET access_token = 'concurrently_changed_newer_token' WHERE id = {$id2}");
            }
        });

        // Run command with batch size large enough to hold both rows in cursor memory
        $exitCode = \Illuminate\Support\Facades\Artisan::call('channel:encrypt-tokens', [
            '--apply'      => true,
            '--batch-size' => 10,
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('were skipped due to concurrent modifications', $output);
        $this->assertStringContainsString((string) $id2, $output);

        // Row 1 should be migrated successfully
        $raw1 = DB::table('channel_accounts')->where('id', $id1)->first();
        $this->assertStringStartsWith(EnvelopedEncryptedString::PREFIX, $raw1->access_token);

        // Row 2 should NOT have been overwritten by the migration command
        $raw2 = DB::table('channel_accounts')->where('id', $id2)->first();
        $this->assertSame('concurrently_changed_newer_token', $raw2->access_token);
    }

    public function test_re_running_command_is_idempotent_and_does_not_double_encrypt(): void
    {
        $id = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Idempotent Page',
            'external_id'   => 'page_idempotent',
            'access_token'  => 'plain_text_to_encrypt_once',
            'refresh_token' => null,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // First pass: encrypts
        $this->artisan('channel:encrypt-tokens --apply')->assertExitCode(0);
        $firstCiphertext = DB::table('channel_accounts')->where('id', $id)->value('access_token');

        // Second pass: re-run should identify as already encrypted
        $this->artisan('channel:encrypt-tokens --apply')
            ->expectsOutputToContain('Already Encrypted (Valid)')
            ->assertExitCode(0);

        $secondCiphertext = DB::table('channel_accounts')->where('id', $id)->value('access_token');

        // Exact raw value remains identical (no double encryption)
        $this->assertSame($firstCiphertext, $secondCiphertext);
        $this->assertSame(1, substr_count($secondCiphertext, EnvelopedEncryptedString::PREFIX));
    }

    public function test_batch_processing_works_with_custom_batch_size(): void
    {
        // Seed 15 records
        for ($i = 1; $i <= 15; $i++) {
            DB::table('channel_accounts')->insert([
                'channel_id'    => $this->channel->id,
                'workspace_id'  => $this->workspace->id,
                'name'          => "Batch Page {$i}",
                'external_id'   => "page_batch_{$i}",
                'access_token'  => "plain_token_{$i}",
                'refresh_token' => "refresh_token_{$i}",
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Run with batch size of 4
        $this->artisan('channel:encrypt-tokens --apply --batch-size=4')
            ->assertExitCode(0);

        $records = DB::table('channel_accounts')->get();
        $this->assertCount(15, $records);

        foreach ($records as $record) {
            $this->assertStringStartsWith(EnvelopedEncryptedString::PREFIX, $record->access_token);
            $this->assertStringStartsWith(EnvelopedEncryptedString::PREFIX, $record->refresh_token);
        }
    }
}
