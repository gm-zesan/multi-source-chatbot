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
use App\Services\Memory\DTOs\MemoryGateDecision;
use App\Services\Memory\MemoryRelevanceGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase M3 Acceptance & Characterization Tests:
 * Verifies Memory Relevance Gate contract, coupling with M2 ContextualResolutionResult,
 * bypass on generic policies and OOD inquiries, and absolute preservation of M2's resolvedQuery.
 */
class M3MemoryRelevanceGateContractTest extends TestCase
{
    use RefreshDatabase;

    private MemoryRelevanceGate $gate;
    private ContextualQueryBuilder $contextBuilder;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gate = new MemoryRelevanceGate();
        $this->contextBuilder = new ContextualQueryBuilder();

        $workspace = Workspace::create(['name' => 'Commerce WS', 'slug' => 'commerce-ws-m3']);
        $channel = Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Web',
            'external_id'  => 'acc_storefront_m3',
            'access_token' => 'tok_storefront_m3',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_m3_test',
            'status'             => 'active',
            'customer_name'      => 'Rahim Uddin',
            'last_direction'     => 'inbound',
        ]);
    }

    /**
     * Case 1: "রিটার্ন পলিসি কি?" -> Memory BYPASS (Generic static policy).
     */
    public function test_generic_return_policy_bypasses_memory(): void
    {
        $query = 'রিটার্ন পলিসি কি?';
        $m2Result = $this->contextBuilder->resolveContext($query, $this->conversation);

        $decision = $this->gate->evaluate($query, $this->conversation, $m2Result);

        $this->assertInstanceOf(MemoryGateDecision::class, $decision);
        $this->assertTrue($decision->isBypassed());
        $this->assertFalse($decision->shouldRetrieve());
        $this->assertTrue(in_array($decision->reason, ['generic_faq_policy', 'self_contained_policy'], true));
        $this->assertLessThanOrEqual(0.10, $decision->relevanceScore);
    }

    /**
     * Case 2: "ডেলিভারি চার্জ কত?" -> Memory BYPASS if self-contained.
     */
    public function test_self_contained_delivery_charge_bypasses_memory(): void
    {
        $query = 'ঢাকার ভেতরে ডেলিভারি চার্জ কত?';
        $m2Result = $this->contextBuilder->resolveContext($query, $this->conversation);

        $decision = $this->gate->evaluate($query, $this->conversation, $m2Result);

        $this->assertTrue($decision->isBypassed());
        $this->assertFalse($this->gate->shouldRetrieve($query, $this->conversation, $m2Result));
        $this->assertSame('self_contained_policy', $decision->reason);
    }

    /**
     * Case 3: "আমার আগের অর্ডারটা কোথায়?" -> Memory RETRIEVE.
     */
    public function test_personal_previous_order_inquiry_triggers_memory_retrieval(): void
    {
        $query = 'আমার আগের অর্ডারটা কোথায়?';
        $m2Result = $this->contextBuilder->resolveContext($query, $this->conversation);

        $decision = $this->gate->evaluate($query, $this->conversation, $m2Result);

        $this->assertTrue($decision->shouldRetrieve());
        $this->assertFalse($decision->isBypassed());
        $this->assertSame('personal_recall_cue', $decision->reason);
        $this->assertGreaterThanOrEqual(0.90, $decision->relevanceScore);
    }

    /**
     * Case 4: "আমার আগের সাইজটা কী ছিল?" -> Memory RETRIEVE.
     */
    public function test_personal_previous_size_inquiry_triggers_memory_retrieval(): void
    {
        $query = 'আমার আগের সাইজটা কী ছিল?';
        $m2Result = $this->contextBuilder->resolveContext($query, $this->conversation);

        $decision = $this->gate->evaluate($query, $this->conversation, $m2Result);

        $this->assertTrue($decision->shouldRetrieve());
        $this->assertSame('personal_recall_cue', $decision->reason);
        $this->assertGreaterThanOrEqual(0.90, $decision->relevanceScore);
    }

    /**
     * Case 5: "এটার দাম কত?" + local product in recent turns -> Local Context Priority (KGM BYPASSED).
     */
    public function test_anaphora_with_local_product_in_recent_turns_prioritizes_local_and_bypasses_kgm(): void
    {
        // Turn 1: Product mentioned in recent turns
        $this->createTurn('iPhone 15 এর স্পেসিফিকেশন কী?', 'outbound', 'এটি অ্যাপলের লেটেস্ট ফ্ল্যাগশিপ ফোন।');

        // Turn 2: Anaphora referencing immediate local turn
        $query = 'এটার দাম কত?';
        $m2Result = $this->contextBuilder->resolveContext($query, $this->conversation);

        $this->assertSame('resolved', $m2Result->status);
        $this->assertSame('local_turns', $m2Result->source);
        $this->assertNotNull($m2Result->resolvedQuery);

        // M3 gate evaluation: Local context already satisfied the antecedent -> BYPASS redundant KGM network call
        $decision = $this->gate->evaluate($query, $this->conversation, $m2Result);

        $this->assertTrue($decision->isBypassed());
        $this->assertSame('local_context_satisfied', $decision->reason);
        $this->assertFalse($this->gate->shouldRetrieve($query, $this->conversation, $m2Result));
    }

    /**
     * Case 6: "এটার দাম কত?" + no local entity + relevant KGM memory -> Memory RETRIEVE.
     */
    public function test_anaphora_without_local_entity_triggers_memory_retrieval(): void
    {
        // Brand new conversation or conversation without any product in recent turns
        $query = 'এটার দাম কত?';
        $m2Result = $this->contextBuilder->resolveContext($query, $this->conversation);

        // M2 could not find a local turn entity
        $this->assertTrue(in_array($m2Result->status, ['unresolved', 'ambiguous'], true));

        $decision = $this->gate->evaluate($query, $this->conversation, $m2Result);

        // Gate triggers RETRIEVE so that KGM persistent memory can be queried
        $this->assertTrue($decision->shouldRetrieve());
        $this->assertSame('anaphora_needing_memory', $decision->reason);
    }

    /**
     * Case 7: "ওটা কবে পাবো?" + multiple orders in KGM -> No Guessing policy verified.
     */
    public function test_tracking_anaphora_with_competing_kgm_orders_marks_ambiguous_and_avoids_guessing(): void
    {
        $kgmMemoryCandidates = [
            ['type' => 'Order', 'id' => '1042', 'name' => '#1042', 'score' => 0.88],
            ['type' => 'Order', 'id' => '2088', 'name' => '#2088', 'score' => 0.85],
        ];

        $query = 'ওটা কবে পাবো?';
        $m2Result = $this->contextBuilder->resolveContext(
            query: $query,
            conversation: $this->conversation,
            memoryCandidates: $kgmMemoryCandidates
        );

        // M2 Margin Check fails because 0.88 - 0.85 = 0.03 < 0.20
        $this->assertSame('ambiguous', $m2Result->status);
        $this->assertNull($m2Result->resolvedQuery); // STRICT NO GUESSING
        $this->assertTrue($m2Result->needsClarification());

        $decision = $this->gate->evaluate($query, $this->conversation, $m2Result);
        $this->assertTrue($decision->shouldRetrieve());
    }

    /**
     * Case 8: Unrelated Old Memory must not contaminate generic questions.
     */
    public function test_unrelated_old_memory_is_shielded_from_generic_office_inquiries(): void
    {
        // Generic office hours query
        $query = 'আপনাদের অফিস কখন খোলা থাকে?';
        $m2Result = $this->contextBuilder->resolveContext($query, $this->conversation);

        $decision = $this->gate->evaluate($query, $this->conversation, $m2Result);

        // Memory must be bypassed so old preferences (e.g. Size XL) don't contaminate the prompt
        $this->assertTrue($decision->isBypassed());
        $this->assertFalse($decision->shouldRetrieve());
    }

    /**
     * Case 9: Out-of-Domain (OOD) queries strictly BYPASS memory.
     */
    public function test_out_of_domain_query_strictly_bypasses_memory(): void
    {
        $queries = [
            'আজকের আবহাওয়া কেমন?',
            'who won the football world cup?',
            'tell me a joke',
            'write a python script for sorting',
        ];

        foreach ($queries as $ood) {
            $m2Result = $this->contextBuilder->resolveContext($ood, $this->conversation);
            $decision = $this->gate->evaluate($ood, $this->conversation, $m2Result);

            $this->assertTrue($decision->isBypassed(), "Failed to bypass OOD query: {$ood}");
            $this->assertSame('ood_query', $decision->reason);
        }
    }

    /**
     * Case 10: Architectural Rule Freeze: M3 NEVER overwrites M2's resolvedQuery.
     */
    public function test_m3_gate_never_mutates_or_overwrites_m2_resolved_query(): void
    {
        $this->createTurn('Royal Silk Panjabi কি অ্যাভেইলেবল?', 'outbound', 'জি, স্টক আছে।');

        $query = 'ওটার সাইজ আছে?';
        $m2Result = $this->contextBuilder->resolveContext($query, $this->conversation);

        $originalResolved = $m2Result->resolvedQuery;
        $this->assertNotNull($originalResolved);
        $this->assertSame('resolved', $m2Result->status);

        // Run M3 Gate evaluation
        $decision = $this->gate->evaluate($query, $this->conversation, $m2Result);

        // Assert M2 resolvedQuery was not altered by M3 Gate
        $this->assertSame($originalResolved, $m2Result->resolvedQuery);
        $this->assertSame('Royal Silk Panjabi', $m2Result->resolvedEntity['name']);
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
