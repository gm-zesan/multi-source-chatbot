<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\IngestConversationMemoryJob;
use App\Models\Conversation;
use App\Services\Memory\ConversationMemoryClient;
use App\Services\Memory\ConversationMemoryService;
use App\Services\Memory\MemoryRelevanceGate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase M1 Acceptance Characterization Tests:
 * Verifies persistent memory foundation, customer identity resolution across sessions,
 * conflict precedence (latest valid fact wins), and policy isolation (zero context contamination).
 */
class M1PersistentMemoryCharacterizationTest extends TestCase
{
    private ConversationMemoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $gate = new MemoryRelevanceGate();
        $this->service = new ConversationMemoryService($client, $gate);
    }

    /**
     * Test 1: Same customer, same ID across different sessions persists identity & retrieves memory.
     */
    public function test_same_customer_same_id_across_different_sessions_retrieves_same_memory(): void
    {
        $session1 = new Conversation([
            'id'               => 101,
            'external_user_id' => 'cust_persistent_777',
        ]);

        $session2 = new Conversation([
            'id'               => 202,
            'external_user_id' => 'cust_persistent_777',
        ]);

        $this->assertSame('cust_persistent_777', $this->service->resolveCustomerId($session1));
        $this->assertSame('cust_persistent_777', $this->service->resolveCustomerId($session2));

        Http::fake([
            '*/memory/search' => function ($request) {
                $payload = $request->data();
                $this->assertSame('cust_persistent_777', $payload['customer_id']);

                return Http::response([
                    'success'                  => true,
                    'customer_id'              => 'cust_persistent_777',
                    'has_memories'             => true,
                    'memories_count'           => 1,
                    'memories'                 => [
                        [
                            'type'       => 'preference',
                            'subject'    => 'Customer',
                            'relation'   => 'PREFERS_SIZE',
                            'object'     => 'XL',
                            'status'     => 'current',
                            'confidence' => 0.95,
                        ]
                    ],
                    'formatted_memory_context' => "Customer Historical Preferences:\n- Prefers size: XL",
                ], 200);
            },
        ]);

        $res1 = $this->service->retrieveContext($session1, 'What is my preferred size?', 1);
        $res2 = $this->service->retrieveContext($session2, 'What was my size?', 1);

        $this->assertNotNull($res1);
        $this->assertNotNull($res2);
        $this->assertStringContainsString('XL', $res1);
        $this->assertStringContainsString('XL', $res2);
    }

    /**
     * Test 2: New customer with a different ID does not leak another customer's memory.
     */
    public function test_new_customer_does_not_receive_unrelated_memory(): void
    {
        $newCustomerConv = new Conversation([
            'id'               => 303,
            'external_user_id' => 'cust_fresh_888',
        ]);

        $this->assertSame('cust_fresh_888', $this->service->resolveCustomerId($newCustomerConv));

        Http::fake([
            '*/memory/search' => function ($request) {
                $payload = $request->data();
                $this->assertSame('cust_fresh_888', $payload['customer_id']);

                return Http::response([
                    'success'                  => true,
                    'customer_id'              => 'cust_fresh_888',
                    'has_memories'             => false,
                    'memories_count'           => 0,
                    'memories'                 => [],
                    'formatted_memory_context' => '',
                ], 200);
            },
        ]);

        $res = $this->service->retrieveContext($newCustomerConv, 'What is my size?', 1);
        $this->assertNull($res);
    }

    /**
     * Test 3: Existing preference vs new preference -> Latest fact wins (Conflict Precedence).
     * Older fact is kept in superseded_facts for auditability without deletion.
     */
    public function test_conflict_precedence_latest_fact_wins_while_preserving_history(): void
    {
        $rawMemories = [
            [
                'type'       => 'preference',
                'subject'    => 'Customer',
                'relation'   => 'PREFERS_SIZE',
                'object'     => 'M',
                'timestamp'  => '2026-08-01T10:00:00Z',
                'updated_at' => 1722506400,
                'status'     => 'past',
            ],
            [
                'type'       => 'preference',
                'subject'    => 'Customer',
                'relation'   => 'PREFERS_SIZE',
                'object'     => 'XL',
                'timestamp'  => '2026-09-01T10:00:00Z',
                'updated_at' => 1725184800,
                'status'     => 'current',
            ],
            [
                'type'       => 'preference',
                'subject'    => 'Customer',
                'relation'   => 'PREFERS_PAYMENT',
                'object'     => 'bKash',
                'timestamp'  => '2026-09-02T12:00:00Z',
                'updated_at' => 1725278400,
                'status'     => 'current',
            ],
        ];

        $resolved = $this->service->resolveConflictPrecedence($rawMemories);

        // 1. Active facts contain the latest valid preference for size (XL) and payment (bKash)
        $this->assertCount(2, $resolved['active_facts']);
        $activeRelations = array_column($resolved['active_facts'], 'relation');
        $this->assertContains('PREFERS_SIZE', $activeRelations);
        $this->assertContains('PREFERS_PAYMENT', $activeRelations);

        $sizeFact = collect($resolved['active_facts'])->firstWhere('relation', 'PREFERS_SIZE');
        $this->assertSame('XL', $sizeFact['object']);

        // 2. Older conflicting fact (M) is preserved in superseded_facts for auditability
        $this->assertCount(1, $resolved['superseded_facts']);
        $this->assertSame('M', $resolved['superseded_facts'][0]['object']);

        // 3. Formatted context presents XL prominently and does not leak M as current
        $this->assertStringContainsString('XL', $resolved['formatted_context']);
        $this->assertStringNotContainsString('Size: M', $resolved['formatted_context']);
    }

    /**
     * Test 4: Old preference + unrelated generic policy question -> No memory contamination.
     */
    public function test_generic_policy_query_bypasses_memory_retrieval(): void
    {
        $conv = new Conversation([
            'id'               => 404,
            'external_user_id' => 'cust_has_profile_555',
        ]);

        // Generic static FAQ queries must strictly return null without making network calls
        $unrelatedQueries = [
            'What is your return policy?',
            'ডেলিভারি পলিসি কি?',
            'Terms and conditions of service',
            'How to contact customer support?',
        ];

        foreach ($unrelatedQueries as $q) {
            $res = $this->service->retrieveContext($conv, $q, 1);
            $this->assertNull($res, "Failed asserting that generic query '{$q}' bypasses memory.");
        }
    }

    /**
     * Test 5: Customer-specific recall inquiry -> Relevant memory retrieved.
     */
    public function test_customer_specific_inquiry_triggers_memory_retrieval(): void
    {
        $conv = new Conversation([
            'id'               => 505,
            'external_user_id' => 'cust_with_memory_999',
        ]);

        Http::fake([
            '*/memory/search' => Http::response([
                'success'                  => true,
                'customer_id'              => 'cust_with_memory_999',
                'has_memories'             => true,
                'memories_count'           => 1,
                'memories'                 => [
                    [
                        'type'       => 'preference',
                        'subject'    => 'Customer',
                        'relation'   => 'PREFERS_PAYMENT',
                        'object'     => 'bKash',
                        'status'     => 'current',
                        'confidence' => 0.98,
                    ]
                ],
                'formatted_memory_context' => "Customer Historical Preferences:\n- Preferred Payment: bKash",
            ], 200),
        ]);

        $res = $this->service->retrieveContext($conv, 'amar payment preference mone ache?', 1);
        $this->assertNotNull($res);
        $this->assertStringContainsString('bKash', $res);
    }

    /**
     * Test 6: Missing customer identity -> Safe graceful fallback.
     */
    public function test_missing_customer_identity_safely_falls_back(): void
    {
        // Null conversation
        $this->assertNull($this->service->retrieveContext(null, 'Remember my order?', 1));
        $this->assertNull($this->service->resolveCustomerId(null));

        // Conversation without any ID
        $emptyConv = new Conversation();
        $this->assertNull($this->service->resolveCustomerId($emptyConv));
        $this->assertNull($this->service->retrieveContext($emptyConv, 'Remember my size?', 1));
    }

    /**
     * Test 7: Async ingestion -> Existing response path unaffected.
     */
    public function test_async_ingestion_dispatches_job_without_blocking(): void
    {
        Queue::fake();

        $conv = new Conversation(['id' => 707, 'external_user_id' => 'cust_test_707']);
        $this->service->ingestConversation($conv);

        Queue::assertPushed(IngestConversationMemoryJob::class, function ($job) use ($conv) {
            return $job->conversation->id === $conv->id;
        });
    }
}
