<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\ContextualQueryBuilder;
use App\Services\AI\DTOs\ContextualResolutionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase M2 Acceptance & Characterization Tests:
 * Verifies KGM-aware structured contextual resolution contract:
 * - Status semantics ('self_contained', 'resolved', 'ambiguous', 'unresolved')
 * - Structured entity schemas (['type' => '...', 'name' => '...'])
 * - Entity-Type compatibility filtering (Product vs Order vs Size)
 * - Winner score & margin check (winner >= 0.70 AND margin >= 0.20)
 * - Ambiguous queries strictly return resolvedQuery = null (no blind guessing)
 * - Raw query remains 100% immutable in database
 */
class M2ContextualResolutionContractTest extends TestCase
{
    use RefreshDatabase;

    private ContextualQueryBuilder $builder;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new ContextualQueryBuilder();

        $workspace = Workspace::create(['name' => 'Commerce WS', 'slug' => 'commerce-ws']);
        $channel = Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Web',
            'external_id'  => 'acc_storefront',
            'access_token' => 'tok_storefront',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_m2_test',
            'status'             => 'active',
            'customer_name'      => 'Karim Ahmed',
            'last_direction'     => 'inbound',
        ]);
    }

    /**
     * Test 1: Delivery -> "কতদিন লাগবে?" resolves to delivery timeframe.
     */
    public function test_delivery_topic_resolves_subjectless_duration_query(): void
    {
        $this->createTurn('ডেলিভারি চার্জ কত?', 'outbound', 'ঢাকার ভেতর ৭০ টাকা, বাইরে ১৩০ টাকা।');

        $result = $this->builder->resolveContext('কতদিন লাগবে?', $this->conversation);

        $this->assertInstanceOf(ContextualResolutionResult::class, $result);
        $this->assertSame('Delivery', $result->activeTopic);
        $this->assertSame('resolved', $result->status);
        $this->assertSame('topic_continuation', $result->source);
        $this->assertGreaterThanOrEqual(0.70, $result->confidence);
        $this->assertTrue($result->isResolved());
        $this->assertFalse($result->needsClarification());
        $this->assertNotNull($result->resolvedQuery);
        $this->assertStringContainsString('ডেলিভারি', $result->resolvedQuery);
        $this->assertStringContainsString('সময়সীমা', $result->resolvedQuery);
    }

    /**
     * Test 2: Delivery -> "চার্জ কত?" resolves to shipping/delivery fee.
     */
    public function test_delivery_topic_resolves_subjectless_fee_query(): void
    {
        $this->createTurn('আমাদের এলাকায় কুরিয়ারে ডেলিভারি দেন?', 'outbound', 'হ্যাঁ, আমরা সারাদেশে ডেলিভারি দেই।');

        $result = $this->builder->resolveContext('চার্জ কত?', $this->conversation);

        $this->assertSame('Delivery', $result->activeTopic);
        $this->assertSame('resolved', $result->status);
        $this->assertSame('topic_continuation', $result->source);
        $this->assertGreaterThanOrEqual(0.70, $result->confidence);
        $this->assertTrue($result->isResolved());
        $this->assertNotNull($result->resolvedQuery);
        $this->assertStringContainsString('ডেলিভারি চার্জ', $result->resolvedQuery);
    }

    /**
     * Test 3: Product -> "এটার দাম কত?" resolves with structured product entity.
     */
    public function test_product_topic_resolves_anaphora_with_product_entity(): void
    {
        $this->createTurn('Black Cotton Panjabi এর ফেব্রিক কী?', 'outbound', 'এটি ১০০% প্রিমিয়াম সুতি কাপড়ের তৈরি।');

        $result = $this->builder->resolveContext('এটার দাম কত?', $this->conversation);

        $this->assertSame('resolved', $result->status);
        $this->assertSame('local_turns', $result->source);
        $this->assertIsArray($result->resolvedEntity);
        $this->assertSame('Product', $result->resolvedEntity['type']);
        $this->assertSame('Black Cotton Panjabi', $result->resolvedEntity['name']);
        $this->assertGreaterThanOrEqual(0.70, $result->confidence);
        $this->assertTrue($result->isResolved());
        $this->assertNotNull($result->resolvedQuery);
        $this->assertStringContainsString('Black Cotton Panjabi', $result->resolvedQuery);
        $this->assertStringContainsString('দাম কত', $result->resolvedQuery);
    }

    /**
     * Test 4: Product -> "ওটার সাইজ আছে?" resolves to product sizing.
     */
    public function test_product_topic_resolves_anaphora_with_sizing_inquiry(): void
    {
        $this->createTurn('White Silk Panjabi দেখতে খুব সুন্দর', 'outbound', 'ধন্যবাদ! এটি আমাদের অন্যতম জনপ্রিয় পাঞ্জাবি।');

        $result = $this->builder->resolveContext('ওটার সাইজ আছে?', $this->conversation);

        $this->assertSame('resolved', $result->status);
        $this->assertSame('local_turns', $result->source);
        $this->assertIsArray($result->resolvedEntity);
        $this->assertSame('White Silk Panjabi', $result->resolvedEntity['name']);
        $this->assertGreaterThanOrEqual(0.70, $result->confidence);
        $this->assertNotNull($result->resolvedQuery);
        $this->assertStringContainsString('White Silk Panjabi', $result->resolvedQuery);
        $this->assertStringContainsString('সাইজ', $result->resolvedQuery);
    }

    /**
     * Test 5: Order -> "ওটা কবে পাবো?" resolves to order delivery tracking.
     */
    public function test_order_topic_resolves_anaphora_with_order_arrival(): void
    {
        $this->createTurn('আমার অর্ডার #1042 এর কনফার্মেশন পেয়েছি', 'outbound', 'আপনার অর্ডারটি প্রসেসিং হচ্ছে।');

        $result = $this->builder->resolveContext('ওটা কবে পাবো?', $this->conversation);

        $this->assertSame('resolved', $result->status);
        $this->assertSame('local_turns', $result->source);
        $this->assertIsArray($result->resolvedEntity);
        $this->assertSame('Order', $result->resolvedEntity['type']);
        $this->assertGreaterThanOrEqual(0.70, $result->confidence);
        $this->assertTrue($result->isResolved());
        $this->assertNotNull($result->resolvedQuery);
        $this->assertStringContainsString('অর্ডার', $result->resolvedQuery);
        $this->assertStringContainsString('কবে পাবো', $result->resolvedQuery);
    }

    /**
     * Test 6: Banglish mixed queries ("koto din lagbe?", "charge koto?").
     */
    public function test_banglish_mixed_queries_resolve_reliably(): void
    {
        $this->createTurn('home delivery available?', 'outbound', 'Yes, all over Bangladesh.');

        $res1 = $this->builder->resolveContext('koto din lagbe?', $this->conversation);
        $this->assertSame('Delivery', $res1->activeTopic);
        $this->assertSame('resolved', $res1->status);
        $this->assertTrue($res1->isResolved());

        $res2 = $this->builder->resolveContext('charge koto?', $this->conversation);
        $this->assertSame('Delivery', $res2->activeTopic);
        $this->assertSame('resolved', $res2->status);
        $this->assertTrue($res2->isResolved());
    }

    /**
     * Test 7: Topic Switch Precedence: Delivery -> Return -> "এটার চার্জ কত?" latches to Return.
     */
    public function test_topic_switch_precedence_latest_turn_wins(): void
    {
        // Turn 1: Delivery
        $this->createTurn('ডেলিভারি চার্জ কত?', 'outbound', '৭০ টাকা।');

        // Turn 2: Topic switches to Return / Exchange!
        $this->createTurn('সাইজ না মিললে রিটার্ন বা এক্সচেঞ্জ করা যাবে?', 'outbound', 'জি, ৭ দিনের মধ্যে এক্সচেঞ্জ করা যাবে।');

        // Turn 3: "এটার চার্জ কত?" - MUST latch to Return/Exchange, NOT delivery!
        $result = $this->builder->resolveContext('এটার চার্জ কত?', $this->conversation);

        $this->assertSame('Return_Exchange', $result->activeTopic);
        $this->assertSame('resolved', $result->status);
        $this->assertNotNull($result->resolvedQuery);
        $this->assertStringContainsString('রিটার্ন', $result->resolvedQuery);
        $this->assertStringNotContainsString('ডেলিভারি চার্জ কত এবং শিপিং ফি কত', $result->resolvedQuery);
        $this->assertTrue($result->isResolved());
    }

    /**
     * Test 8: Multiple Local Candidate Entities within Margin -> Ambiguous (NO GUESSING).
     * Strictly verifies resolvedQuery is NULL when ambiguous!
     */
    public function test_multiple_competing_entities_marks_ambiguous_and_suppresses_resolved_query(): void
    {
        // Turn discusses two distinct products simultaneously
        $this->createTurn('Black Cotton Panjabi এবং White Silk Panjabi এই দুটির মধ্যে কোনটি ভালো?', 'outbound', 'দুটোই আমাদের প্রিমিয়াম কোয়ালিটি।');

        // User asks "এটার দাম কত?" without specifying which one -> Must NOT guess!
        $result = $this->builder->resolveContext('এটার দাম কত?', $this->conversation);

        $this->assertSame('ambiguous', $result->status);
        $this->assertNull($result->resolvedQuery); // STRICT REQUIREMENT: No guessed query
        $this->assertTrue($result->isAmbiguous());
        $this->assertTrue($result->needsClarification());
        $this->assertFalse($result->isResolved());
        $this->assertCount(2, $result->candidates);
    }

    /**
     * Test 9: Self-Contained Query -> Preserved intact with status 'self_contained'.
     */
    public function test_self_contained_query_is_preserved_intact(): void
    {
        $this->createTurn('ডেলিভারি চার্জ কত?', 'outbound', '৭০ টাকা।');

        $query = 'What are your accepted payment methods and credit card options?';
        $result = $this->builder->resolveContext($query, $this->conversation);

        $this->assertSame('self_contained', $result->status);
        $this->assertSame('self_contained', $result->source);
        $this->assertSame(1.0, $result->confidence);
        $this->assertSame($query, $result->rawQuery);
        $this->assertSame($query, $result->resolvedQuery);
        $this->assertTrue($result->isSelfContained());
        $this->assertFalse($result->needsClarification());
    }

    /**
     * Test 10: KGM Entity-Type Compatibility:
     * User asks "ওটার দাম কত?" (Product inquiry). KGM has an Order (#1042) and a Product (Panjabi).
     * Order must be filtered out by entity-type compatibility, and Product must win!
     */
    public function test_kgm_entity_type_compatibility_filters_incompatible_types(): void
    {
        $kgmMemoryCandidates = [
            ['type' => 'Order', 'id' => '1042', 'name' => '#1042', 'score' => 0.95],
            ['type' => 'Product', 'id' => 'p_99', 'name' => 'Premium Panjabi', 'score' => 0.88],
        ];

        // Inquiry about price -> Entity-Type compatibility strictly demands 'Product'
        $result = $this->builder->resolveContext(
            query: 'ওটার দাম কত?',
            conversation: $this->conversation,
            memoryCandidates: $kgmMemoryCandidates
        );

        $this->assertSame('resolved', $result->status);
        $this->assertSame('kgm', $result->source);
        $this->assertSame('Product', $result->resolvedEntity['type']);
        $this->assertSame('Premium Panjabi', $result->resolvedEntity['name']);
        $this->assertNotNull($result->resolvedQuery);
        $this->assertStringContainsString('Premium Panjabi এর দাম কত', $result->resolvedQuery);
    }

    /**
     * Test 11: KGM Winner Score & Margin Check:
     * Winner has 0.92, Second has 0.60 (Margin 0.32 >= 0.20) -> RESOLVED.
     */
    public function test_kgm_candidate_with_sufficient_margin_resolves_cleanly(): void
    {
        $kgmCandidates = [
            ['type' => 'Product', 'id' => 'p_1', 'name' => 'Royal Blue Panjabi', 'score' => 0.92],
            ['type' => 'Product', 'id' => 'p_2', 'name' => 'Casual Shirt', 'score' => 0.60],
        ];

        $result = $this->builder->resolveContext(
            query: 'এটার দাম কত?',
            conversation: $this->conversation,
            memoryCandidates: $kgmCandidates
        );

        $this->assertSame('resolved', $result->status);
        $this->assertSame('kgm', $result->source);
        $this->assertSame('Royal Blue Panjabi', $result->resolvedEntity['name']);
        $this->assertSame(0.92, $result->confidence);
    }

    /**
     * Test 12: KGM Winner Score with Insufficient Margin -> AMBIGUOUS (resolvedQuery = null).
     * Panjabi 0.82 vs Shirt 0.78 (Margin 0.04 < 0.20) -> Must NOT guess!
     */
    public function test_kgm_candidate_with_insufficient_margin_marks_ambiguous(): void
    {
        $kgmCandidates = [
            ['type' => 'Product', 'id' => 'p_1', 'name' => 'Royal Blue Panjabi', 'score' => 0.82],
            ['type' => 'Product', 'id' => 'p_2', 'name' => 'Silk Panjabi', 'score' => 0.78],
        ];

        $result = $this->builder->resolveContext(
            query: 'এটার দাম কত?',
            conversation: $this->conversation,
            memoryCandidates: $kgmCandidates
        );

        $this->assertSame('ambiguous', $result->status);
        $this->assertSame('kgm', $result->source);
        $this->assertNull($result->resolvedQuery);
        $this->assertTrue($result->needsClarification());
        $this->assertCount(2, $result->candidates);
    }

    /**
     * Test 13: Standalone Subject-less query without turns or memory -> UNRESOLVED.
     */
    public function test_standalone_subjectless_query_without_prior_turns_is_unresolved(): void
    {
        $emptyConv = Conversation::create([
            'channel_account_id' => $this->conversation->channel_account_id,
            'external_user_id'   => 'user_brand_new',
            'status'             => 'active',
            'last_direction'     => 'inbound',
        ]);

        $result = $this->builder->resolveContext('কতদিন লাগবে?', $emptyConv);

        $this->assertSame('unresolved', $result->status);
        $this->assertSame('unresolved', $result->source);
        $this->assertNull($result->resolvedQuery);
        $this->assertTrue($result->needsClarification());
    }

    /**
     * Test 14: Guaranteed Raw Query Immutability in Database Persistence.
     */
    public function test_raw_query_remains_strictly_immutable_in_database(): void
    {
        $this->createTurn('ডেলিভারি চার্জ কত?', 'outbound', 'ঢাকার ভেতরে ৭০ টাকা।');

        $rawInput = 'কতদিন লাগবে?';
        $result = $this->builder->resolveContext($rawInput, $this->conversation);

        // Raw query must match original user input character-by-character
        $this->assertSame($rawInput, $result->rawQuery);
        $this->assertNotSame($rawInput, $result->resolvedQuery);

        // Database message body is ALWAYS the raw input
        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => $result->rawQuery,
        ]);

        $this->assertSame($rawInput, $message->fresh()->body);
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
