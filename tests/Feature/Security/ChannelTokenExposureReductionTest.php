<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Channels\Facebook\FacebookDriver;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelTokenExposureReductionTest extends TestCase
{
    use RefreshDatabase;

    private ChannelAccount $account;
    private Workspace $workspace;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Security Test Workspace', 'slug' => 'sec-test']);
        $this->channel = Channel::create(['slug' => 'facebook', 'name' => 'Facebook', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'channel_id'    => $this->channel->id,
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Security Test Page',
            'display_name'  => 'Sec Page',
            'external_id'   => 'page_12345678',
            'access_token'  => 'plain_text_facebook_page_access_token_xyz987',
            'refresh_token' => 'plain_text_refresh_token_abc123',
            'settings'      => ['page_id' => 'page_12345678'],
            'is_active'     => true,
        ]);
    }

    public function test_access_token_and_refresh_token_are_excluded_from_model_serialization(): void
    {
        $array = $this->account->toArray();
        $this->assertArrayNotHasKey('access_token', $array);
        $this->assertArrayNotHasKey('refresh_token', $array);

        $json = $this->account->toJson();
        $this->assertStringNotContainsString('plain_text_facebook_page_access_token_xyz987', $json);
        $this->assertStringNotContainsString('plain_text_refresh_token_abc123', $json);
        $this->assertStringNotContainsString('access_token', $json);
        $this->assertStringNotContainsString('refresh_token', $json);

        $jsonEncoded = json_encode($this->account);
        $this->assertIsString($jsonEncoded);
        $this->assertStringNotContainsString('access_token', $jsonEncoded);
        $this->assertStringNotContainsString('refresh_token', $jsonEncoded);
    }

    public function test_existing_plaintext_token_records_continue_to_work_unchanged(): void
    {
        $fresh = ChannelAccount::findOrFail($this->account->id);

        $this->assertSame('plain_text_facebook_page_access_token_xyz987', $fresh->access_token);
        $this->assertSame('plain_text_refresh_token_abc123', $fresh->refresh_token);

        // Can update other attributes without issues
        $fresh->update(['name' => 'Updated Name']);
        $this->assertSame('plain_text_facebook_page_access_token_xyz987', $fresh->fresh()->access_token);
        $this->assertSame('Updated Name', $fresh->fresh()->name);
    }

    public function test_facebook_driver_get_user_profile_sends_bearer_header_without_token_in_url_query(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'name' => 'Jane Customer',
                'profile_pic' => 'https://example.com/pic.jpg',
            ], 200),
        ]);

        $driver = new FacebookDriver();
        $profile = $driver->getUserProfile($this->account, 'psid_987654');

        $this->assertSame('Jane Customer', $profile['name']);
        $this->assertSame('https://example.com/pic.jpg', $profile['profile_pic']);

        Http::assertSent(function (Request $request) {
            $url = $request->url();

            // 1. Token must NOT be in URL or query parameters
            $parsedUrl = parse_url($url);
            $queryString = $parsedUrl['query'] ?? '';
            parse_str($queryString, $queryParams);

            $hasTokenInUrl = str_contains($url, 'plain_text_facebook_page_access_token_xyz987');
            $hasTokenInQuery = isset($queryParams['access_token']);

            // 2. Token MUST be in Authorization: Bearer header
            $hasBearerHeader = $request->hasHeader('Authorization', 'Bearer plain_text_facebook_page_access_token_xyz987');

            // 3. Expected endpoint and parameters
            $correctEndpoint = str_starts_with($url, 'https://graph.facebook.com/psid_987654');

            return !$hasTokenInUrl
                && !$hasTokenInQuery
                && $hasBearerHeader
                && $correctEndpoint
                && ($queryParams['fields'] ?? '') === 'name,profile_pic';
        });
    }

    public function test_facebook_driver_get_user_profile_handles_failed_response_gracefully(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token',
                    'type' => 'OAuthException',
                    'code' => 190,
                ],
            ], 400),
        ]);

        $driver = new FacebookDriver();
        $profile = $driver->getUserProfile($this->account, 'psid_invalid');

        $this->assertNull($profile['name']);
        $this->assertNull($profile['profile_pic']);
    }

    public function test_facebook_driver_send_still_works_with_plaintext_token(): void
    {
        Http::fake([
            'https://graph.facebook.com/v25.0/me/messages' => Http::response([
                'recipient_id' => 'psid_987654',
                'message_id' => 'mid.123456',
            ], 200),
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'psid_987654',
            'status'             => 'active',
            'last_direction'     => 'inbound',
        ]);

        $driver = new FacebookDriver();
        $result = $driver->send($this->account, $conversation, 'Hello from bot');

        $this->assertSame('mid.123456', $result['message_id'] ?? null);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer plain_text_facebook_page_access_token_xyz987')
                && $request['recipient']['id'] === 'psid_987654'
                && $request['message']['text'] === 'Hello from bot';
        });
    }
}
