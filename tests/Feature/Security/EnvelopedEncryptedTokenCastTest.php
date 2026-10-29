<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Casts\EnvelopedEncryptedString;
use App\Exceptions\ChannelTokenDecryptionException;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Workspace;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnvelopedEncryptedTokenCastTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Cast Test Workspace', 'slug' => 'cast-test']);
        $this->channel = Channel::create(['slug' => 'facebook', 'name' => 'Facebook', 'is_active' => true]);
    }

    public function test_newly_written_tokens_are_stored_as_enveloped_ciphertext_in_database(): void
    {
        $account = ChannelAccount::create([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Page 1',
            'external_id'   => 'page_1',
            'access_token'  => 'super_secret_access_token_abc',
            'refresh_token' => 'super_secret_refresh_token_xyz',
            'is_active'     => true,
        ]);

        // 1. Inspect raw values in the database via raw SQL / query builder
        $raw = DB::table('channel_accounts')->where('id', $account->id)->first();
        $this->assertNotNull($raw);

        $this->assertStringStartsWith(EnvelopedEncryptedString::PREFIX, $raw->access_token);
        $this->assertStringStartsWith(EnvelopedEncryptedString::PREFIX, $raw->refresh_token);

        // Raw database columns must NEVER contain the plaintext token
        $this->assertStringNotContainsString('super_secret_access_token_abc', $raw->access_token);
        $this->assertStringNotContainsString('super_secret_refresh_token_xyz', $raw->refresh_token);

        // 2. Underlying payload can be manually decrypted using Crypt::decryptString
        $strippedAccess = substr($raw->access_token, strlen(EnvelopedEncryptedString::PREFIX));
        $this->assertSame('super_secret_access_token_abc', Crypt::decryptString($strippedAccess));

        $strippedRefresh = substr($raw->refresh_token, strlen(EnvelopedEncryptedString::PREFIX));
        $this->assertSame('super_secret_refresh_token_xyz', Crypt::decryptString($strippedRefresh));

        // 3. Eloquent accessor transparently decrypts
        $fresh = $account->fresh();
        $this->assertSame('super_secret_access_token_abc', $fresh->access_token);
        $this->assertSame('super_secret_refresh_token_xyz', $fresh->refresh_token);
    }

    public function test_legacy_plaintext_record_without_prefix_reads_correctly(): void
    {
        // Simulate legacy record by inserting directly with DB facade (bypassing Eloquent mutator)
        $id = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Legacy Page',
            'external_id'   => 'page_legacy',
            'access_token'  => 'EAAB_legacy_plaintext_token_12345',
            'refresh_token' => 'legacy_refresh_plaintext_67890',
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $account = ChannelAccount::findOrFail($id);

        // Accessor should recognize lack of 'enc:v1:' and return plaintext seamlessly
        $this->assertSame('EAAB_legacy_plaintext_token_12345', $account->access_token);
        $this->assertSame('legacy_refresh_plaintext_67890', $account->refresh_token);
    }

    public function test_corrupted_enveloped_ciphertext_throws_exception_and_fails_closed(): void
    {
        $id = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Corrupted Page',
            'external_id'   => 'page_corrupted',
            'access_token'  => EnvelopedEncryptedString::PREFIX . 'corrupted_invalid_base64_payload',
            'refresh_token' => null,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $account = ChannelAccount::findOrFail($id);

        // Accessing corrupted access_token MUST throw ChannelTokenDecryptionException
        $this->expectException(ChannelTokenDecryptionException::class);
        $this->expectExceptionMessageMatches('/corrupted ciphertext or APP_KEY mismatch/i');

        // Trigger accessor
        $unused = $account->access_token;
    }

    public function test_app_key_mismatch_throws_exception_and_never_falls_back_to_plaintext(): void
    {
        // Encrypt with a different key
        $foreignKey = random_bytes(32);
        $foreignEncrypter = new Encrypter($foreignKey, 'AES-256-CBC');
        $foreignCiphertext = EnvelopedEncryptedString::PREFIX . $foreignEncrypter->encryptString('secret_token_from_another_app');

        $id = DB::table('channel_accounts')->insertGetId([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Key Mismatch Page',
            'external_id'   => 'page_mismatch',
            'access_token'  => $foreignCiphertext,
            'refresh_token' => null,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $account = ChannelAccount::findOrFail($id);

        // Decryption MUST fail-closed because local APP_KEY does not match
        $this->expectException(ChannelTokenDecryptionException::class);

        $unused = $account->access_token;
    }

    public function test_null_and_empty_values_are_handled_safely(): void
    {
        $account = ChannelAccount::create([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Null Page',
            'external_id'   => 'page_null',
            'access_token'  => 'valid_token',
            'refresh_token' => null,
            'is_active'     => true,
        ]);

        $this->assertNull($account->refresh_token);

        $raw = DB::table('channel_accounts')->where('id', $account->id)->first();
        $this->assertNull($raw->refresh_token);
    }

    public function test_already_enveloped_string_is_not_double_encrypted_on_save(): void
    {
        $account = ChannelAccount::create([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Double Enc Page',
            'external_id'   => 'page_double',
            'access_token'  => 'single_encryption_test',
            'is_active'     => true,
        ]);

        $rawBefore = DB::table('channel_accounts')->where('id', $account->id)->value('access_token');
        $this->assertStringStartsWith(EnvelopedEncryptedString::PREFIX, $rawBefore);

        // Re-saving with the enveloped string directly
        $account->access_token = $rawBefore;
        $account->save();

        $rawAfter = DB::table('channel_accounts')->where('id', $account->id)->value('access_token');
        $this->assertSame($rawBefore, $rawAfter);

        // Prefix should only appear once
        $this->assertSame(1, substr_count($rawAfter, EnvelopedEncryptedString::PREFIX));

        // Still decrypts cleanly
        $this->assertSame('single_encryption_test', $account->fresh()->access_token);
    }
}
