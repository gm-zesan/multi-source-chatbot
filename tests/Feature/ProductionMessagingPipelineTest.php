<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\CustomerSupportAgent;
use App\Events\IncomingMessageReceived;
use App\Jobs\ReceiveMessengerWebhookJob;
use App\Listeners\ExtractCRMEntitiesListener;
use App\Listeners\RunFAQEngineListener;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\FAQCategory;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductionMessagingPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private Channel $facebookChannel;
    private ChannelAccount $accountA;
    private ChannelAccount $accountB;
    private FAQ $faqA;
    private FAQ $faqB;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Workspaces
        $this->workspaceA = Workspace::create(['name' => 'Workspace A', 'slug' => 'workspace-a']);
        $this->workspaceB = Workspace::create(['name' => 'Workspace B', 'slug' => 'workspace-b']);

        // Setup Channels & Accounts
        $this->facebookChannel = Channel::create(['slug' => 'facebook', 'name' => 'Facebook Messenger', 'is_active' => true]);

        $this->accountA = ChannelAccount::create([
            'channel_id'   => $this->facebookChannel->id,
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Page A',
            'external_id'  => 'fb_page_A_100',
            'access_token' => 'token_A',
        ]);

        $this->accountB = ChannelAccount::create([
            'channel_id'   => $this->facebookChannel->id,
            'workspace_id' => $this->workspaceB->id,
            'name'         => 'Page B',
            'external_id'  => 'fb_page_B_200',
            'access_token' => 'token_B',
        ]);

        // Setup FAQs
        $catA = FAQCategory::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Orders A',
            'slug' => 'orders-a',
            'is_active' => true,
        ]);
        $this->faqA = FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'faq_category_id' => $catA->id,
            'question' => 'What is the return policy for Store A?',
            'answer' => 'Store A allows 30-day returns with receipt.',
            'is_active' => true,
            'is_searchable' => true,
        ]);

        $catB = FAQCategory::create([
            'workspace_id' => $this->workspaceB->id,
            'name' => 'Orders B',
            'slug' => 'orders-b',
            'is_active' => true,
        ]);
        $this->faqB = FAQ::create([
            'workspace_id' => $this->workspaceB->id,
            'faq_category_id' => $catB->id,
            'question' => 'What is the return policy for Store B?',
            'answer' => 'Store B has a strict all-sales-final policy.',
            'is_active' => true,
            'is_searchable' => true,
        ]);
    }

    /**
     * Complete Production Pipeline Test:
     * Webhook Job -> Persistence -> CRM Extraction -> AI Agent -> Channel Delivery -> DB Persistence
     */
    public function test_complete_inbound_webhook_to_ai_delivery_pipeline(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'recipient_id' => 'fb_cust_001',
                'message_id'   => 'm_mid_graph_success_777',
            ], 200),
        ]);

        CustomerSupportAgent::fake([
            'Store A allows returns within 30 days when accompanied by your purchase receipt.',
        ]);

        // 1. Process incoming webhook job
        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: $this->accountA->id,
            channel: 'facebook',
            payload: [
                'external_user_id'    => 'fb_cust_001',
                'external_message_id' => 'mid_inbound_001',
                'text'                => 'Contact me at cust@example.com or +8801712345678. What is the return policy for Store A?',
                'customer_name'       => 'Alice Customer',
            ],
        );
        $job->handle();

        $conversation = Conversation::where('channel_account_id', $this->accountA->id)
            ->where('external_user_id', 'fb_cust_001')
            ->first();

        $this->assertNotNull($conversation);
        $inboundMsg = $conversation->messages()->where('direction', 'inbound')->first();
        $this->assertNotNull($inboundMsg);

        // 2. Execute CRM Listener
        $crmListener = app(ExtractCRMEntitiesListener::class);
        $crmListener->handle(new IncomingMessageReceived(
            conversation: $conversation,
            message: $inboundMsg,
            account: $this->accountA,
            rawPayload: [],
        ));

        $this->assertDatabaseHas('crm_contact_emails', ['email' => 'cust@example.com']);
        $this->assertDatabaseHas('crm_contact_phones', ['phone' => '+8801712345678']);

        // 3. Execute FAQ AI Agent Listener
        $faqListener = app(RunFAQEngineListener::class);
        $faqListener->handle(new IncomingMessageReceived(
            conversation: $conversation,
            message: $inboundMsg,
            account: $this->accountA,
            rawPayload: [],
        ));

        // 4. Verify Outbound Message sent to Facebook Graph API
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com')
                && isset($request['message']['text'])
                && str_contains($request['message']['text'], 'Store A allows returns within 30 days');
        });

        // 5. Verify Outbound Message saved to DB with delivery response metadata
        $outboundMsg = $conversation->messages()->where('direction', 'outbound')->first();
        $this->assertNotNull($outboundMsg);
        $this->assertStringContainsString('Store A allows returns within 30 days', $outboundMsg->body);
        $this->assertSame('m_mid_graph_success_777', $outboundMsg->metadata['message_id'] ?? null);
        $this->assertSame('customer_support_agent', $outboundMsg->metadata['source'] ?? null);
    }

    /**
     * Test Workspace Scoping:
     * Inbound message in Workspace A should ONLY search Workspace A FAQs
     */
    public function test_faq_retrieval_respects_workspace_isolation(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->once())
            ->method('search')
            ->with('What is the return policy for Store A?', 5, $this->workspaceA->id)
            ->willReturn(new EloquentCollection([
                new FAQSearchResult(
                    faq: $this->faqA,
                    keywordScore: 0.9,
                    semanticScore: 0.95,
                    finalScore: 0.95,
                    matchType: 'hybrid',
                ),
            ]));

        $tool = new \App\AI\Tools\KnowledgeRetrievalTool($faqSearchMock, $this->workspaceA->id);
        $result = (string) $tool->handle(new \Laravel\Ai\Tools\Request(['query' => 'What is the return policy for Store A?']));

        $this->assertStringContainsString('Store A allows 30-day returns with receipt.', $result);
        $this->assertStringNotContainsString('Store B', $result);
    }

    /**
     * Test Idempotency: Duplicate inbound webhook does not dispatch duplicate events or create duplicate messages
     */
    public function test_duplicate_webhook_does_not_create_duplicate_inbound_or_outbound(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->accountA->id,
            'external_user_id'   => 'user_dup_123',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => 'msg_dup_mid_001',
            'direction'           => 'inbound',
            'type'                => 'text',
            'body'                => 'First attempt',
        ]);

        // Attempt duplicate webhook arrival
        $job = new ReceiveMessengerWebhookJob(
            channelAccountId: $this->accountA->id,
            channel: 'facebook',
            payload: [
                'external_user_id'    => 'user_dup_123',
                'external_message_id' => 'msg_dup_mid_001',
                'text'                => 'First attempt duplicate',
            ],
        );
        $job->handle();

        // Exactly 1 message should exist in DB
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->count());
    }
}
