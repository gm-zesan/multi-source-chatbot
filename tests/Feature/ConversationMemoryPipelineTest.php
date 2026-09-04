<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\IngestConversationMemoryJob;
use App\Models\Account;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Memory\ConversationMemoryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConversationMemoryPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Memory Test Workspace',
            'slug' => 'memory-test-workspace',
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'facebook'],
            ['name' => 'Facebook Messenger', 'driver' => 'facebook', 'is_active' => true]
        );

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Test Facebook Page',
            'external_id'  => 'page_mem_test_123',
            'access_token' => 'test_token_fb_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'fb_user_karim_999',
            'contact_id'         => 'cust_karim_999',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_memory_client_health_check_with_faked_http(): void
    {
        Http::fake([
            '*/health' => Http::response([
                'status'  => 'ok',
                'service' => 'python-memory-service',
                'neo4j'   => ['status' => 'connected'],
            ], 200),
        ]);

        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $this->assertTrue($client->healthCheck());
    }

    public function test_memory_client_ingest_and_search_with_faked_http(): void
    {
        Http::fake([
            '*/memory/ingest' => Http::response([
                'success'            => true,
                'status'             => 'processed',
                'conversation_id'    => 'conv_123',
                'entities_extracted' => 2,
                'edges_created'      => 2,
            ], 200),
            '*/memory/search' => Http::response([
                'success'                  => true,
                'customer_id'              => 'cust_karim_999',
                'has_memories'             => true,
                'memories_count'           => 1,
                'memories'                 => [
                    [
                        'type'       => 'preference',
                        'subject'    => 'Customer',
                        'relation'   => 'PREFERS',
                        'object'     => 'bKash',
                        'status'     => 'current',
                        'confidence' => 1.0,
                    ]
                ],
                'formatted_memory_context' => "Customer Historical Preferences:\n- Preferred Payment: bKash",
                'latency_ms'               => 12.5,
            ], 200),
        ]);

        $client = new ConversationMemoryClient('http://127.0.0.1:8002');

        $ingestRes = $client->ingest(
            workspaceId: $this->workspace->id,
            customerId: 'cust_karim_999',
            conversationId: 'conv_123',
            channel: 'facebook',
            messages: [
                ['direction' => 'inbound', 'body' => 'I prefer bKash payment.'],
            ]
        );

        $this->assertTrue($ingestRes['success']);
        $this->assertEquals(2, $ingestRes['edges_created']);

        $searchRes = $client->search(
            workspaceId: $this->workspace->id,
            customerId: 'cust_karim_999',
            query: 'payment method'
        );

        $this->assertTrue($searchRes['has_memories']);
        $this->assertStringContainsString('bKash', $searchRes['formatted_memory_context']);
    }

    public function test_ingest_conversation_memory_job_execution(): void
    {
        // Add messages to conversation
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Amar preferred payment hocche bKash, size XL.',
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Apnar payment method ebong size note kora hoyeche.',
        ]);

        Http::fake([
            '*/memory/ingest' => Http::response([
                'success'            => true,
                'status'             => 'processed',
                'conversation_id'    => (string) $this->conversation->id,
                'entities_extracted' => 2,
                'edges_created'      => 2,
            ], 200),
        ]);

        $job = new IngestConversationMemoryJob($this->conversation);
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $job->handle($client);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/memory/ingest')
                && $request['customer_id'] === 'fb_user_karim_999'
                && count($request['messages']) === 2;
        });
    }

    public function test_ingest_job_resilient_to_service_outage(): void
    {
        // Add message
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Test resilience.',
        ]);

        // Fake network failure / 500 error
        Http::fake([
            '*/memory/ingest' => Http::response('Service Unavailable', 503),
        ]);

        $job = new IngestConversationMemoryJob($this->conversation);
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');

        // Should NOT throw exception
        $job->handle($client);
        $this->assertTrue(true);
    }

    public function test_live_integration_against_running_python_memory_service(): void
    {
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        if (! $client->healthCheck()) {
            $this->markTestSkipped('Python Memory Service is not running on port 8002.');
        }

        $liveCustomer = 'live_test_user_rahman_888';
        $client->deleteCustomer(1, $liveCustomer);

        $ingestRes = $client->ingest(
            workspaceId: 1,
            customerId: $liveCustomer,
            conversationId: 'live_conv_turn_1',
            channel: 'web',
            messages: [
                ['direction' => 'inbound', 'body' => 'Amar payment bKash ebong size hocche L. Order #7712.'],
                ['direction' => 'outbound', 'body' => 'Dhonnobad, amra apnar bKash ebong size L note korechi.'],
            ]
        );

        $this->assertTrue($ingestRes['success']);
        $this->assertGreaterThanOrEqual(3, $ingestRes['edges_created']);

        $searchRes = $client->search(
            workspaceId: 1,
            customerId: $liveCustomer,
            query: 'payment and size'
        );

        $this->assertTrue($searchRes['has_memories']);
        $this->assertStringContainsString('bKash', $searchRes['formatted_memory_context']);
        $this->assertStringContainsString('L', $searchRes['formatted_memory_context']);

        // Clean up
        $client->deleteCustomer(1, $liveCustomer);
    }
}
