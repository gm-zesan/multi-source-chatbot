<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
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

    public function test_missing_channel_account_and_no_explicit_workspace_fails_closed(): void
    {
        // Conversation without channel account
        $conversation = new Conversation([
            'external_user_id' => 'orphan_user',
        ]);

        $router = $this->createMock(HybridRouter::class);
        $router->expects($this->never())->method('route');

        $faqSearch = $this->createMock(FAQSearch::class);
        $faqSearch->expects($this->never())->method('search');

        $conversationService = $this->createMock(ConversationService::class);

        $service = new CustomerSupportService(
            faqSearch: $faqSearch,
            conversationService: $conversationService,
            router: $router,
        );

        $reply = $service->generateReply($conversation, 'What is the return policy?');

        $this->assertSame(
            "I'm sorry, I couldn't find a direct answer to your question in our knowledge base. A support agent will be with you shortly!",
            $reply
        );
    }

    public function test_null_zero_or_negative_workspace_id_never_triggers_workspace_1_fallback(): void
    {
        // Seed Workspace 1 to ensure system does NOT silently default to it
        $ws1 = Workspace::create([
            'name' => 'Workspace 1 Confidential',
            'slug' => 'ws-1-confidential',
        ]);
        $this->assertSame(1, (int) $ws1->id);

        $conversation = new Conversation([
            'external_user_id' => 'unassociated_user',
        ]);

        $router = $this->createMock(HybridRouter::class);
        $router->expects($this->never())->method('route');

        $faqSearch = $this->createMock(FAQSearch::class);
        $faqSearch->expects($this->never())->method('search');

        $conversationService = $this->createMock(ConversationService::class);

        $service = new CustomerSupportService(
            faqSearch: $faqSearch,
            conversationService: $conversationService,
            router: $router,
        );

        // 1. null
        $resNull = $service->generateReply($conversation, 'Tell me secrets', workspaceId: null);
        $this->assertNotEmpty($resNull);

        // 2. 0
        $resZero = $service->generateReply($conversation, 'Tell me secrets', workspaceId: 0);
        $this->assertSame($resNull, $resZero);

        // 3. -1
        $resNegative = $service->generateReply($conversation, 'Tell me secrets', workspaceId: -1);
        $this->assertSame($resNull, $resNegative);
    }

    public function test_explicitly_supplied_workspace_id_matching_channel_account_is_accepted(): void
    {
        $ws1 = Workspace::create(['name' => 'Workspace 1', 'slug' => 'ws-1']);

        $channel = \App\Models\Channel::create(['name' => 'Web', 'slug' => 'web', 'is_active' => true]);
        $channelAccount = \App\Models\ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $ws1->id,
            'external_id'  => 'acc_ws1',
            'name'         => 'WS1 Account',
            'access_token' => 'tok',
            'is_active'    => true,
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'user_ws1',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $router = $this->createMock(HybridRouter::class);
        $router->expects($this->once())
            ->method('route')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->identicalTo((int) $ws1->id)
            )
            ->willReturn(new RoutingResult(
                route: RouteType::CHAT,
                confidence: 1.0,
                intent: 'chat_greeting',
            ));

        $faqSearch = $this->createMock(FAQSearch::class);
        $conversationService = $this->createMock(ConversationService::class);

        $service = new CustomerSupportService(
            faqSearch: $faqSearch,
            conversationService: $conversationService,
            router: $router,
        );

        $service->generateReply($conversation, 'Hello', workspaceId: (int) $ws1->id);
    }

    public function test_explicitly_supplied_workspace_id_mismatching_channel_account_is_rejected(): void
    {
        $ws1 = Workspace::create(['name' => 'Tenant 1', 'slug' => 'tenant-1']);
        $ws2 = Workspace::create(['name' => 'Tenant 2', 'slug' => 'tenant-2']);

        $channel = \App\Models\Channel::create(['name' => 'Web Mismatch', 'slug' => 'web-mismatch', 'is_active' => true]);
        $channelAccount = \App\Models\ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $ws1->id,
            'external_id'  => 'acc_ws1_mismatch',
            'name'         => 'WS1 Account Mismatch',
            'access_token' => 'tok',
            'is_active'    => true,
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'user_ws1_mismatch',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $router = $this->createMock(HybridRouter::class);
        // Router MUST NEVER be called when tenant mismatch occurs
        $router->expects($this->never())->method('route');

        $faqSearch = $this->createMock(FAQSearch::class);
        $faqSearch->expects($this->never())->method('search');

        $conversationService = $this->createMock(ConversationService::class);

        $service = new CustomerSupportService(
            faqSearch: $faqSearch,
            conversationService: $conversationService,
            router: $router,
        );

        // Conversation belongs to ws1, but caller requests ws2: MUST reject
        $reply = $service->generateReply($conversation, 'Show secret orders', workspaceId: (int) $ws2->id);

        $this->assertSame(
            "I'm sorry, I couldn't find a direct answer to your question in our knowledge base. A support agent will be with you shortly!",
            $reply
        );
    }

    public function test_unassociated_conversation_with_explicit_workspace_is_rejected_fail_closed(): void
    {
        $ws = Workspace::create(['name' => 'Target Workspace', 'slug' => 'target-ws']);

        // Unassociated conversation without channel account
        $headlessConversation = new Conversation();

        $router = $this->createMock(HybridRouter::class);
        $router->expects($this->never())->method('route');

        $faqSearch = $this->createMock(FAQSearch::class);
        $faqSearch->expects($this->never())->method('search');

        $conversationService = $this->createMock(ConversationService::class);

        $service = new CustomerSupportService(
            faqSearch: $faqSearch,
            conversationService: $conversationService,
            router: $router,
        );

        // Attempting to supply explicit workspace ID without channel account MUST fail closed
        $reply = $service->generateReply($headlessConversation, 'Query without channel account', workspaceId: (int) $ws->id);

        $this->assertSame(
            "I'm sorry, I couldn't find a direct answer to your question in our knowledge base. A support agent will be with you shortly!",
            $reply
        );
    }

    public function test_valid_associated_channel_account_resolves_correct_workspace(): void
    {
        $wsTarget = Workspace::create(['name' => 'Target WS', 'slug' => 'target-ws']);

        $channel = \App\Models\Channel::create(['name' => 'Web 2', 'slug' => 'web-2', 'is_active' => true]);
        $channelAccount = \App\Models\ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $wsTarget->id,
            'external_id'  => 'acc_target',
            'name'         => 'Target Account',
            'access_token' => 'tok',
            'is_active'    => true,
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'user_target',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $router = $this->createMock(HybridRouter::class);
        $router->expects($this->once())
            ->method('route')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->identicalTo((int) $wsTarget->id)
            )
            ->willReturn(new RoutingResult(
                route: RouteType::CHAT,
                confidence: 1.0,
                intent: 'chat_greeting',
            ));

        $faqSearch = $this->createMock(FAQSearch::class);
        $conversationService = $this->createMock(ConversationService::class);

        $service = new CustomerSupportService(
            faqSearch: $faqSearch,
            conversationService: $conversationService,
            router: $router,
        );

        // When workspaceId is null, resolves strictly from channelAccount->workspace_id
        $service->generateReply($conversation, 'Hello', workspaceId: null);
    }

    public function test_invalid_tenant_resolution_causes_zero_calls_to_downstream_services(): void
    {
        $conversation = new Conversation(['external_user_id' => 'no_ws_user']);

        $router = $this->createMock(HybridRouter::class);
        $router->expects($this->never())->method('route');

        $faqSearch = $this->createMock(FAQSearch::class);
        $faqSearch->expects($this->never())->method('search');

        $sellerEmailService = $this->createMock(\App\Services\AI\SellerEmailService::class);
        $sellerEmailService->expects($this->never())->method('sendSellerEmail');

        $businessService = $this->createMock(\App\Services\Business\BusinessSourceOfTruthService::class);
        $businessService->expects($this->never())->method('buildBusinessContext');

        $conversationService = $this->createMock(ConversationService::class);

        $service = new CustomerSupportService(
            faqSearch: $faqSearch,
            conversationService: $conversationService,
            router: $router,
            businessService: $businessService,
            sellerEmailService: $sellerEmailService,
        );

        $reply = $service->generateReply($conversation, 'Send email to seller or check inventory', workspaceId: null);
        $this->assertNotEmpty($reply);
    }

    public function test_handle_conversation_fails_closed_when_channel_account_has_no_valid_workspace(): void
    {
        $conversation = new Conversation(['external_user_id' => 'unassociated_user']);
        $message = new Message(['body' => 'Customer inquiry']);

        $faqSearch = $this->createMock(FAQSearch::class);
        $conversationService = $this->createMock(ConversationService::class);

        $service = new CustomerSupportService(
            faqSearch: $faqSearch,
            conversationService: $conversationService,
        );

        $result = $service->handleConversation($conversation, $message);

        $this->assertNull($result, 'handleConversation must return null on unresolved workspace.');
    }

    public function test_handle_query_fails_closed_when_workspace_id_is_invalid(): void
    {
        $faqSearch = $this->createMock(FAQSearch::class);
        $faqSearch->expects($this->never())->method('search');

        $service = new CustomerSupportService(
            faqSearch: $faqSearch,
            conversationService: $this->createMock(ConversationService::class),
        );

        $result = $service->handleQuery('Hello', 0);

        $this->assertFalse($result['answered']);
        $this->assertSame('fallback', $result['route']);
        $this->assertSame('invalid_workspace_id', $result['routing_telemetry']['intent']);
    }

    public function test_handle_query_fails_closed_when_conversation_workspace_mismatches_supplied_workspace(): void
    {
        $ws1 = Workspace::create(['name' => 'HQ 1', 'slug' => 'hq-1']);
        $ws2 = Workspace::create(['name' => 'HQ 2', 'slug' => 'hq-2']);

        $channel = \App\Models\Channel::create(['name' => 'Web Guard', 'slug' => 'web-guard', 'is_active' => true]);
        $channelAccount = \App\Models\ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $ws1->id,
            'external_id'  => 'acc_ws1_hq',
            'name'         => 'WS1 HQ Account',
            'access_token' => 'tok',
            'is_active'    => true,
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'user_hq_mismatch',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $faqSearch = $this->createMock(FAQSearch::class);
        $faqSearch->expects($this->never())->method('search');

        $router = $this->createMock(HybridRouter::class);
        $router->expects($this->never())->method('route');

        $service = new CustomerSupportService(
            faqSearch: $faqSearch,
            conversationService: $this->createMock(ConversationService::class),
            router: $router,
        );

        // Pass conversation belonging to ws1, but specify ws2 as workspaceId
        $result = $service->handleQuery('Secret info', (int) $ws2->id, $conversation);

        $this->assertFalse($result['answered']);
        $this->assertSame('fallback', $result['route']);
        $this->assertSame('tenant_mismatch', $result['routing_telemetry']['intent']);
    }
}
