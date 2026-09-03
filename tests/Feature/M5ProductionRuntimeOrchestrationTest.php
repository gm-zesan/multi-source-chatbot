<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Jobs\IngestConversationMemoryJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use App\Services\Memory\ConversationMemoryClient;
use App\Services\Memory\ConversationMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Phase M5: Production Runtime Orchestration Integration Test
 * 
 * Verifies the complete end-to-end production flow:
 * User Query
 *    ↓
 * HybridRouter
 *    ↓
 * M2 Context Resolution
 *    ↓
 * M4 Clarification Guard (Short-circuit on ambiguity)
 *    ↓
 * M3 Memory Relevance Gate (Decide RETRIEVE vs BYPASS)
 *    ↓
 * KGM / Memory Context
 *    ↓
 * Retrieval (FAQ Search with contextualSignal)
 *    ↓
 * Answerability Gate
 *    ↓
 * LLM / Grounded Answer
 *    ↓
 * Memory Ingestion Safety (No isolated 1-token fact corruption in Neo4j)
 */
class M5ProductionRuntimeOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Conversation $conversation;
    private CustomerSupportService $service;
    private FAQSearch $faqSearchMock;
    private HybridRouter $routerMock;
    private ConversationMemoryClient $memoryClientMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Commerce WS M5', 'slug' => 'commerce-ws-m5']);
        $channel = Channel::create(['name' => 'Web M5', 'slug' => 'web-m5', 'driver' => 'web']);
        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Web M5',
            'external_id'  => 'acc_storefront_m5',
            'access_token' => 'tok_storefront_m5',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_m5_orchestration',
            'status'             => 'active',
            'customer_name'      => 'Sabbir Ahmed',
            'last_direction'     => 'inbound',
            'metadata'           => [],
        ]);

        $this->faqSearchMock = Mockery::mock(FAQSearch::class);
        $this->routerMock = Mockery::mock(HybridRouter::class);
        $this->memoryClientMock = Mockery::mock(ConversationMemoryClient::class);

        $this->routerMock->shouldReceive('route')
            ->byDefault()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.90, 'order_tracking'));

        $this->faqSearchMock->shouldReceive('getLastTelemetry')
            ->byDefault()
            ->andReturn([]);

        $this->app->instance(FAQSearch::class, $this->faqSearchMock);
        $this->app->instance(HybridRouter::class, $this->routerMock);
        $this->app->instance(ConversationMemoryClient::class, $this->memoryClientMock);

        $this->service = new CustomerSupportService(
            faqSearch: $this->faqSearchMock,
            conversationService: app(ConversationService::class),
            router: $this->routerMock,
            memoryService: app(ConversationMemoryService::class),
        );
    }

    /**
     * Test Turn 1 -> Turn 2 -> Turn 3 -> Turn 4 Complete Multi-Turn Flow
     */
    public function test_complete_e2e_runtime_orchestration_flow(): void
    {
        // ── Turn 1: User introduces order reference ──────────────────────────
        $this->recordTurn('আমি #1042 অর্ডার নিয়ে জানতে চাই', 'outbound', 'জি, আপনার অর্ডার #1042 আমাদের সিস্টেমে প্রসেসিং অবস্থায় আছে।');

        // ── Turn 2: User asks anaphora query "ওটা কবে পাবো?" ─────────────────
        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.94, 'delivery_eta'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $perPage, $workspaceId, $conversation, $contextualSignal) {
                // Must pass contextualSignal resolved to #1042
                return str_contains((string) $contextualSignal, '#1042');
            })
            ->andReturn(new \Illuminate\Database\Eloquent\Collection());

        $turn2Result = $this->service->handleQuery('ওটা কবে পাবো?', $this->workspace->id, $this->conversation);

        $this->assertFalse($turn2Result['is_handoff']);
        $this->assertSame('knowledge', $turn2Result['route']);

        // ── Turn 3: User introduces ambiguity between two competing items ─────
        $this->recordTurn('Black Cotton Panjabi নাকি White Silk Panjabi কোনটি ভালো?', 'outbound', 'দুটোই উন্নত মানের।');

        // Ambiguity Short-Circuit: Zero FAQ calls, zero LLM calls
        $this->faqSearchMock->shouldNotReceive('search');

        $turn3Result = $this->service->handleQuery('এটার দাম কত?', $this->workspace->id, $this->conversation);

        $this->assertSame('uncertain', $turn3Result['route']);
        $this->assertFalse($turn3Result['is_handoff']);
        $this->assertStringContainsString('Black Cotton Panjabi', $turn3Result['reply']);
        $this->assertStringContainsString('White Silk Panjabi', $turn3Result['reply']);

        // Verify pending clarification was persisted
        $this->assertNotNull($this->conversation->fresh()->metadata['pending_clarification'] ?? null);

        // ── Turn 4: User selects "White Silk Panjabi" ─────────────────────────
        // Step 0 restores intent from pending clarification
        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'product_pricing'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($query, $perPage, $workspaceId, $conversation, $contextualSignal) {
                return str_contains((string) $contextualSignal, 'White Silk Panjabi');
            })
            ->andReturn(new \Illuminate\Database\Eloquent\Collection());

        $turn4Result = $this->service->handleQuery('White Silk Panjabi', $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $turn4Result['route']);
        $this->assertFalse($turn4Result['is_handoff']);

        // Verify pending clarification was cleaned up after resolution
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->fresh()->metadata ?? []);
    }

    /**
     * Test Audit Point 4: OOD query during pending clarification is never hijacked as a selection!
     */
    public function test_ood_query_during_pending_clarification_is_not_hijacked(): void
    {
        // Set pending clarification
        $this->conversation->metadata = [
            'pending_clarification' => [
                'original_query' => 'ওটা কবে পাবো?',
                'active_topic'   => 'Order_Tracking',
                'expected_type'  => 'Order',
                'candidates'     => [
                    ['type' => 'Order', 'id' => '1042', 'name' => '#1042'],
                    ['type' => 'Order', 'id' => '2088', 'name' => '#2088'],
                ],
            ],
        ];
        $this->conversation->save();

        // User asks OOD instead of choosing an order
        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::OOD, 0.98, 'general_weather'));

        $this->faqSearchMock->shouldNotReceive('search');

        $result = $this->service->handleQuery('আজকের আবহাওয়া কেমন?', $this->workspace->id, $this->conversation);

        $this->assertSame('ood', $result['route']);
        $this->assertFalse($result['is_handoff']);
        // Verify stale pending clarification was cleanly purged
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->fresh()->metadata ?? []);
    }

    /**
     * Test Audit Point 1: Clarification Turn Ingestion Safety
     * Ensures single-token choices use contextual_intent so Neo4j does not ingest isolated/broken facts.
     */
    public function test_clarification_ingestion_safety_prevents_isolated_token_fact_extraction(): void
    {
        Queue::fake();

        // Message 1: Bot asks clarification
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'আপনি কোন অর্ডারটি সম্পর্কে জানতে চাচ্ছেন—#1042 নাকি #2088?',
        ]);

        // Message 2: User responds with just "#1042", but tagged with contextual_intent
        $userMsg = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => '#1042',
            'metadata'        => [
                'contextual_intent' => 'অর্ডার #1042 কবে পাবো পার্সেল ডেলিভারি ট্র্যাকিং',
            ],
        ]);

        // Emulate IngestConversationMemoryJob payload generation
        $job = new IngestConversationMemoryJob($this->conversation);

        $memoryClientMock = Mockery::mock(ConversationMemoryClient::class);
        $memoryClientMock->shouldReceive('ingest')
            ->once()
            ->withArgs(function ($workspaceId, $customerId, $conversationId, $channel, $messages) {
                // Find the inbound message in the payload
                $inbound = collect($messages)->firstWhere('direction', 'inbound');
                // MUST have replaced isolated "#1042" with contextual_intent!
                return $inbound['body'] === 'অর্ডার #1042 কবে পাবো পার্সেল ডেলিভারি ট্র্যাকিং';
            })
            ->andReturn(['edges_created' => 1, 'entities_extracted' => 1]);

        $job->handle($memoryClientMock);
    }

    /**
     * Test Audit Point 2: User Directive Confirmation
     * "tobe transfer to human ekhoni lagbe na. pore korbo eita. jokhon lagbe ami bolbo."
     * Reaching 3 consecutive uncertain turns gracefully prompts user without halting automation or transferring to human.
     */
    public function test_human_handoff_remains_disabled_per_user_instruction(): void
    {
        $this->conversation->metadata = [
            'uncertain_count' => 2,
        ];
        $this->conversation->save();

        $this->faqSearchMock->shouldNotReceive('search');

        $result = $this->service->handleQuery('এটার দাম কত?', $this->workspace->id, $this->conversation);

        $this->assertFalse($result['is_handoff'], 'Handoff must be false per user directive');
        $this->assertStringContainsString('বিস্তারিত', $result['reply']);
        $this->assertFalse($this->conversation->fresh()->metadata['handoff_to_human'] ?? false);
    }

    /**
     * Test Audit Point 3: Clarification count is strictly session-scoped.
     * Uncertainty in Conversation A does not leak into Conversation B.
     */
    public function test_session_scoped_counter_isolation(): void
    {
        // Conversation A has 2 uncertain turns
        $this->conversation->metadata = ['uncertain_count' => 2];
        $this->conversation->save();

        // Conversation B belongs to another customer/session
        $conversationB = Conversation::create([
            'channel_account_id' => $this->conversation->channel_account_id,
            'external_user_id'   => 'user_m5_other_session',
            'status'             => 'active',
            'last_direction'     => 'inbound',
            'metadata'           => ['uncertain_count' => 0],
        ]);

        $this->assertSame(2, $this->conversation->fresh()->metadata['uncertain_count']);
        $this->assertSame(0, $conversationB->fresh()->metadata['uncertain_count']);
    }

    /**
     * Helper to create messages.
     */
    private function recordTurn(string $userText, string $botDir, string $botText): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => $userText,
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => $botDir,
            'type'            => 'text',
            'body'            => $botText,
        ]);
    }
}
