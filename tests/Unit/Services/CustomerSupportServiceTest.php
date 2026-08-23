<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSupportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_query_returns_structured_result(): void
    {
        $workspace = Workspace::create([
            'name' => 'Default Workspace',
            'slug' => 'default-workspace',
        ]);

        $faqSearch = $this->createMock(FAQSearch::class);
        $faqSearch->method('search')
            ->willReturn(new Collection());

        $conversationService = $this->createMock(ConversationService::class);

        $service = new CustomerSupportService($faqSearch, $conversationService);

        $result = $service->handleQuery(
            query: 'How to register?',
            workspaceId: $workspace->id,
        );

        $this->assertArrayHasKey('reply', $result);
        $this->assertArrayHasKey('retrieval_hits', $result);
        $this->assertArrayHasKey('top_hit', $result);
        $this->assertArrayHasKey('answered', $result);
        $this->assertFalse($result['answered']);
    }

    public function test_generate_reply_uses_fallback_when_empty(): void
    {
        $workspace = Workspace::create([
            'name' => 'Default Workspace 2',
            'slug' => 'default-workspace-2',
        ]);

        $channel = \App\Models\Channel::create([
            'name' => 'Facebook',
            'slug' => 'facebook',
            'is_active' => true,
        ]);

        $channelAccount = \App\Models\ChannelAccount::create([
            'channel_id' => $channel->id,
            'workspace_id' => $workspace->id,
            'external_id' => 'fb_acc_999',
            'name' => 'Test Page',
            'access_token' => 'test_token_123',
            'is_active' => true,
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'fb_user_999',
            'status'             => 'open',
            'last_direction'     => 'inbound',
            'last_message'       => 'Hello',
            'last_message_at'    => now(),
        ]);

        $faqSearch = $this->createMock(FAQSearch::class);
        $faqSearch->method('search')
            ->willReturn(new Collection());

        $conversationService = $this->createMock(ConversationService::class);

        $service = new CustomerSupportService($faqSearch, $conversationService);

        $reply = $service->generateReply(
            conversation: $conversation,
            query: 'Unknown query',
            workspaceId: $workspace->id,
        );

        $this->assertNotEmpty($reply);
    }
}
