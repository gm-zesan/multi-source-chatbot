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

class ConversationMemoryServiceTest extends TestCase
{
    private ConversationMemoryService $service;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $gate = new MemoryRelevanceGate();
        $this->service = new ConversationMemoryService($client, $gate);

        $this->conversation = new Conversation([
            'id'               => 42,
            'external_user_id' => 'cust_mem_unit_test_999',
        ]);
    }

    public function test_retrieve_context_returns_null_when_gated_out(): void
    {
        // "hi" is pure chit-chat, gated out
        $res = $this->service->retrieveContext($this->conversation, 'hi', 1);
        $this->assertNull($res);
    }

    public function test_retrieve_context_returns_null_when_conversation_null(): void
    {
        $res = $this->service->retrieveContext(null, 'What is my size?', 1);
        $this->assertNull($res);
    }

    public function test_retrieve_context_returns_formatted_string_when_memories_found(): void
    {
        Http::fake([
            '*/memory/search' => Http::response([
                'success'                  => true,
                'customer_id'              => 'cust_mem_unit_test_999',
                'has_memories'             => true,
                'memories_count'           => 1,
                'memories'                 => [
                    [
                        'type'       => 'preference',
                        'subject'    => 'Customer',
                        'relation'   => 'PREFERS',
                        'object'     => 'bKash',
                        'status'     => 'current',
                        'confidence' => 0.95,
                    ]
                ],
                'formatted_memory_context' => "Customer Historical Preferences:\n- Preferred Payment: bKash",
                'latency_ms'               => 1.8,
            ], 200),
        ]);

        $res = $this->service->retrieveContext($this->conversation, 'What is my payment preference?', 1);
        $this->assertNotNull($res);
        $this->assertStringContainsString('bKash', $res);
    }

    public function test_retrieve_context_returns_null_when_no_memories(): void
    {
        Http::fake([
            '*/memory/search' => Http::response([
                'success'                  => true,
                'customer_id'              => 'cust_mem_unit_test_999',
                'has_memories'             => false,
                'memories_count'           => 0,
                'memories'                 => [],
                'formatted_memory_context' => '',
                'latency_ms'               => 1.2,
            ], 200),
        ]);

        $res = $this->service->retrieveContext($this->conversation, 'What size should I wear?', 1);
        $this->assertNull($res);
    }

    public function test_ingest_conversation_dispatches_job(): void
    {
        Queue::fake();

        $this->service->ingestConversation($this->conversation);

        Queue::assertPushed(IngestConversationMemoryJob::class);
    }
}
