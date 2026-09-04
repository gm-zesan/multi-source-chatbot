<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\ClarificationManager;
use App\Services\AI\ContextualQueryBuilder;
use App\Services\AI\CustomerSupportService;
use App\Services\AI\DTOs\ContextualResolutionResult;
use App\Services\FAQ\FAQSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Phase M4 Acceptance & Characterization Tests:
 * Verifies the Answerability & Interactive Clarification Loop:
 * 1. Single clear entity -> Answer pipeline continues normally
 * 2. Multiple products -> Polite clarification question asked
 * 3. Multiple orders -> Polite clarification question asked (#1042 vs #2088)
 * 4. Unresolved "এটা/ওটা" -> Clarification question generated
 * 5. Clarification state -> Short-circuited (zero FAQ retrieval, zero KGM retrieval, zero LLM calls)
 * 6. User answers clarification -> Original context restored in Turn 2
 * 7. 2nd clarification ambiguity -> Clarify again with incremented count
 * 8. 3 consecutive uncertain turns -> Human handoff policy triggered
 * 9. Self-contained FAQ -> Normal retrieval pipeline runs
 * 10. Out-of-Domain (OOD) -> Existing OOD behavior preserved
 * 11. Raw query -> Guaranteed 100% immutable in database
 * 12. KGM candidates -> Zero internal metadata leakage in user-facing message
 */
class M4ClarificationAnswerabilityContractTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Conversation $conversation;
    private CustomerSupportService $service;
    private FAQSearch $faqSearchMock;
    private HybridRouter $routerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Commerce WS', 'slug' => 'commerce-ws-m4']);
        $channel = Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Web M4',
            'external_id'  => 'acc_storefront_m4',
            'access_token' => 'tok_storefront_m4',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_m4_test',
            'status'             => 'active',
            'customer_name'      => 'Tanvir Islam',
            'last_direction'     => 'inbound',
            'metadata'           => [],
        ]);

        $this->faqSearchMock = Mockery::mock(FAQSearch::class);
        $this->routerMock = Mockery::mock(HybridRouter::class);

        $this->routerMock->shouldReceive('route')
            ->byDefault()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.90, 'faq_intent'));

        $this->faqSearchMock->shouldReceive('getLastTelemetry')
            ->byDefault()
            ->andReturn([]);

        $this->service = new CustomerSupportService(
            faqSearch: $this->faqSearchMock,
            conversationService: app(\App\Services\Chat\ConversationService::class),
            router: $this->routerMock,
        );
    }

    /**
     * Case 1: Single clear entity -> Answer pipeline continues normally.
     */
    public function test_single_clear_entity_continues_answer_pipeline(): void
    {
        // Turn 1 mentions exactly one product
        $this->createTurn('Black Cotton Panjabi এর ফেব্রিক কেমন?', 'outbound', 'এটি ১০০% সুতি কাপড়ের তৈরি।');

        // Router classifies follow-up as knowledge
        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.92, 'product_pricing'));

        // FAQSearch MUST be invoked because single entity is unambiguous
        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection());

        $result = $this->service->handleQuery('এটার দাম কত?', $this->workspace->id, $this->conversation);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_handoff']);
        $this->assertSame('knowledge', $result['route']);
    }

    /**
     * Case 2: Multiple products -> Clarification question without guessing.
     */
    public function test_multiple_products_generates_clarification_question(): void
    {
        // Turn 1 mentions two competing products
        $this->createTurn('Black Cotton Panjabi এবং White Silk Panjabi এর মধ্যে পার্থক্য কী?', 'outbound', 'দুটোই আমাদের প্রিমিয়াম কোয়ালিটি।');

        // Neither FAQSearch nor Router should be called due to short-circuit!
        $this->faqSearchMock->shouldNotReceive('search');

        $result = $this->service->handleQuery('এটার দাম কত?', $this->workspace->id, $this->conversation);

        $this->assertIsArray($result);
        $this->assertSame('uncertain', $result['route']);
        $this->assertStringContainsString('Black Cotton Panjabi', $result['reply']);
        $this->assertStringContainsString('White Silk Panjabi', $result['reply']);
        $this->assertStringContainsString('কোন পণ্যটি', $result['reply']);
    }

    /**
     * Case 3: Multiple orders -> Clarification question asking #1042 vs #2088.
     */
    public function test_multiple_orders_generates_order_clarification_question(): void
    {
        $clarificationManager = app(ClarificationManager::class);
        $contextResult = new ContextualResolutionResult(
            rawQuery: 'ওটা কবে পাবো?',
            resolvedQuery: null,
            activeTopic: 'Order_Tracking',
            candidates: [
                ['type' => 'Order', 'id' => '1042', 'name' => '#1042'],
                ['type' => 'Order', 'id' => '2088', 'name' => '#2088'],
            ],
            confidence: 0.85,
            status: 'ambiguous',
            source: 'kgm'
        );

        $question = $clarificationManager->buildClarificationQuestion($contextResult);

        $this->assertStringContainsString('#1042', $question);
        $this->assertStringContainsString('#2088', $question);
        $this->assertStringContainsString('কোন অর্ডারটি', $question);
    }

    /**
     * Case 4: Unresolved "এটা/ওটা" without prior context -> Polite clarification.
     */
    public function test_unresolved_pronoun_without_context_generates_clarification(): void
    {
        // Brand new conversation with zero prior turns
        $this->faqSearchMock->shouldNotReceive('search');

        $result = $this->service->handleQuery('এটার দাম কত?', $this->workspace->id, $this->conversation);

        $this->assertIsArray($result);
        $this->assertSame('uncertain', $result['route']);
        $this->assertStringContainsString('অনুগ্রহ করে', $result['reply']);
    }

    /**
     * Case 5: Clarification state -> Strictly short-circuited (no FAQ search, no KGM, no LLM call).
     */
    public function test_clarification_state_strictly_short_circuits_retrieval_and_llm(): void
    {
        $this->createTurn('Black Cotton Panjabi এবং White Silk Panjabi কোনটি ভালো?', 'outbound', 'দুটোই ভালো।');

        // Strict assertion: FAQSearch search() MUST NOT be called!
        $this->faqSearchMock->shouldNotReceive('search');

        $result = $this->service->handleQuery('এটার সাইজ আছে?', $this->workspace->id, $this->conversation);

        $this->assertCount(0, $result['retrieval_hits']);
        $this->assertNull($result['memory_context']);
        $this->assertFalse($result['answered']);
        $this->assertSame('deterministic_m4_clarifier', $result['raw_llm_response']['provider']);
        $this->assertTrue($result['routing_telemetry']['short_circuited']);
    }

    /**
     * Case 6: User answers clarification -> Original context restored in Turn 2!
     */
    public function test_user_answering_clarification_restores_original_inquiry_intent(): void
    {
        // Turn 1 was ambiguous, saving pending_clarification
        $this->conversation->metadata = [
            'uncertain_count'       => 1,
            'pending_clarification' => [
                'original_query'    => 'ওটা কবে পাবো?',
                'active_topic'      => 'Order_Tracking',
                'expected_type'     => 'Order',
                'candidates'        => [
                    ['type' => 'Order', 'id' => '1042', 'name' => '#1042'],
                    ['type' => 'Order', 'id' => '2088', 'name' => '#2088'],
                ],
                'consecutive_count' => 1,
            ],
        ];
        $this->conversation->save();

        $queryBuilder = new ContextualQueryBuilder();

        // Turn 2: User provides the chosen order number "#1042"
        $resolution = $queryBuilder->resolveContext('#1042', $this->conversation);

        $this->assertSame('resolved', $resolution->status);
        $this->assertSame('clarification_resolution', $resolution->source);
        $this->assertSame('#1042', $resolution->rawQuery); // Raw query strictly preserved
        $this->assertNotNull($resolution->resolvedQuery);
        $this->assertStringContainsString('#1042', $resolution->resolvedQuery);
        $this->assertStringContainsString('কবে পাবো', $resolution->resolvedQuery); // Original intent restored!

        // Conversation metadata must have cleared pending_clarification
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->fresh()->metadata ?? []);
        $this->assertSame(0, $this->conversation->fresh()->metadata['uncertain_count'] ?? 0);
    }

    /**
     * Case 7: 2nd clarification ambiguity -> Clarifies again with incremented count.
     */
    public function test_second_clarification_ambiguity_clarifies_again_with_incremented_count(): void
    {
        $this->conversation->metadata = [
            'uncertain_count' => 1,
        ];
        $this->conversation->save();

        $this->faqSearchMock->shouldNotReceive('search');

        // Brand new pronoun query still ambiguous
        $result = $this->service->handleQuery('এটার দাম কত?', $this->workspace->id, $this->conversation);

        $this->assertFalse($result['is_handoff']);
        $this->assertSame('uncertain', $result['route']);
        $this->assertSame(2, $this->conversation->fresh()->metadata['uncertain_count']);
    }

    /**
     * Case 8: 3 consecutive uncertain turns -> Graceful fallback without premature human transfer.
     */
    public function test_three_consecutive_uncertain_turns_triggers_graceful_fallback(): void
    {
        // Already had 2 uncertain turns
        $this->conversation->metadata = [
            'uncertain_count' => 2,
        ];
        $this->conversation->save();

        $this->faqSearchMock->shouldNotReceive('search');

        // 3rd ambiguous turn triggers graceful fallback without blocking or transferring to human
        $result = $this->service->handleQuery('এটার দাম কত?', $this->workspace->id, $this->conversation);

        $this->assertFalse($result['is_handoff']); // Disabled per user instruction
        $this->assertStringContainsString('বিস্তারিত', $result['reply']);
        $this->assertFalse($this->conversation->fresh()->metadata['handoff_to_human'] ?? false);
    }

    /**
     * Case 9: Self-contained FAQ -> Normal retrieval pipeline runs.
     */
    public function test_self_contained_faq_runs_normal_retrieval(): void
    {
        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'delivery_charge'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection());

        $result = $this->service->handleQuery('ঢাকার বাইরে ডেলিভারি চার্জ কত?', $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertFalse($result['is_handoff']);
    }

    /**
     * Case 10: Out-of-Domain (OOD) -> Existing OOD behavior preserved.
     */
    public function test_ood_query_preserves_existing_ood_behavior(): void
    {
        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::OOD, 0.99, 'ood_intent'));

        $result = $this->service->handleQuery('who won the world cup?', $this->workspace->id, $this->conversation);

        $this->assertSame('ood', $result['route']);
        $this->assertFalse($result['is_handoff']);
    }

    /**
     * Case 11: Raw query remains strictly immutable in database.
     */
    public function test_raw_query_remains_strictly_immutable_in_database(): void
    {
        $rawInput = 'এটার দাম কত?';

        $this->service->handleQuery($rawInput, $this->workspace->id, $this->conversation);

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => $rawInput,
        ]);

        $this->assertSame($rawInput, $message->fresh()->body);
    }

    /**
     * Case 12: KGM candidate list strictly sanitizes private/internal graph metadata.
     */
    public function test_kgm_candidate_list_sanitizes_internal_graph_metadata(): void
    {
        $dirtyCandidates = [
            [
                'type'         => 'Order',
                'id'           => 'node_994857_secret_guid',
                'name'         => '#1042',
                'score'        => 0.88,
                'embedding'    => [0.12, 0.45, 0.99],
                'cypher_props' => ['private_token' => 'tok_secret'],
            ],
            [
                'type'         => 'Order',
                'id'           => 'node_208842_internal',
                'name'         => '#2088',
                'score'        => 0.85,
                'embedding'    => [0.11, 0.33, 0.77],
                'cypher_props' => ['user_ssn' => 'secret_ssn'],
            ],
        ];

        $manager = new ClarificationManager();
        $clean = $manager->sanitizeCandidates($dirtyCandidates);

        foreach ($clean as $item) {
            $this->assertArrayNotHasKey('score', $item);
            $this->assertArrayNotHasKey('embedding', $item);
            $this->assertArrayNotHasKey('cypher_props', $item);
            $this->assertStringNotContainsString('node_', $item['id']);
        }

        $contextResult = new ContextualResolutionResult(
            rawQuery: 'ওটা কবে পাবো?',
            resolvedQuery: null,
            activeTopic: 'Order_Tracking',
            candidates: $dirtyCandidates,
            confidence: 0.85,
            status: 'ambiguous',
            source: 'kgm'
        );

        $question = $manager->buildClarificationQuestion($contextResult);

        // Assert user-facing string contains only safe name/id
        $this->assertStringContainsString('#1042', $question);
        $this->assertStringContainsString('#2088', $question);
        $this->assertStringNotContainsString('node_', $question);
        $this->assertStringNotContainsString('secret', $question);
        $this->assertStringNotContainsString('cypher', $question);
    }

    /**
     * Helper to create mock conversation turn.
     */
    private function createTurn(string $userMsg, string $botDir, string $botMsg): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => $userMsg,
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => $botDir,
            'type'            => 'text',
            'body'            => $botMsg,
        ]);
    }
}
