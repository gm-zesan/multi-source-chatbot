<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\IngestConversationMemoryJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Memory\ConversationMemoryClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MemoryJobConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Concurrency Test WS',
            'slug' => 'concurrency-test-ws',
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]
        );

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Test Web Channel',
            'external_id'  => 'web_channel_101',
            'access_token' => 'token_101',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_concurrent_99',
            'contact_id'         => 'cust_concurrent_99',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_job_implements_unique_until_processing_contract(): void
    {
        $job = new IngestConversationMemoryJob($this->conversation);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame((string) $this->conversation->id, $job->uniqueId());
        $this->assertSame(60, $job->uniqueFor);
    }

    public function test_watermark_prevents_reingesting_overlapping_messages(): void
    {
        // 1. Create initial 6 turns
        $createdMessages = [];
        for ($i = 1; $i <= 6; $i++) {
            $createdMessages[] = Message::create([
                'conversation_id' => $this->conversation->id,
                'direction'       => $i % 2 === 1 ? 'inbound' : 'outbound',
                'type'            => 'text',
                'body'            => "Message turn {$i}: Customer lives in Mirpur and likes blue shirts.",
                'sender_type'     => $i % 2 === 1 ? 'user' : 'agent',
            ]);
        }

        $capturedCalls = [];
        $mockClient = $this->createMock(ConversationMemoryClient::class);
        $mockClient->expects($this->exactly(2))
            ->method('ingest')
            ->willReturnCallback(function (...$args) use (&$capturedCalls) {
                $capturedCalls[] = $args;
                return [
                    'success'            => true,
                    'edges_created'      => 2,
                    'entities_extracted' => 2,
                    'llm_usage'          => [
                        'provider'          => 'deepseek',
                        'prompt_tokens'     => 100,
                        'completion_tokens' => 30,
                        'total_tokens'      => 130,
                    ],
                ];
            });

        // 2. Worker 1 runs on the conversation
        $job1 = new IngestConversationMemoryJob($this->conversation);
        $job1->handle($mockClient);

        // Verify watermark after Job 1
        $this->conversation->refresh();
        $watermark1 = $this->conversation->metadata['last_memory_ingested_message_id'] ?? null;
        $this->assertNotNull($watermark1);
        $this->assertSame($createdMessages[5]->id, $watermark1);

        // 3. Customer sends Turn 7
        $turn7 = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Message turn 7: Customer also requests delivery by tomorrow.',
            'sender_type'     => 'user',
        ]);

        // 4. Worker 2 runs subsequently
        $job2 = new IngestConversationMemoryJob($this->conversation);
        $job2->handle($mockClient);

        $this->assertCount(2, $capturedCalls);

        $messagesBatch1 = $capturedCalls[0][4];
        $messagesBatch2 = $capturedCalls[1][4];

        // Batch 1 had 6 messages
        $this->assertCount(6, $messagesBatch1);

        // Batch 2 MUST contain ONLY message 7! (Zero overlap!)
        $this->assertCount(1, $messagesBatch2, 'Second job must only ingest new turns since watermark');
        $this->assertSame('Message turn 7: Customer also requests delivery by tomorrow.', $messagesBatch2[0]['body']);

        // Check intersection of message bodies: MUST BE 0!
        $bodies1 = array_column($messagesBatch1, 'body');
        $bodies2 = array_column($messagesBatch2, 'body');
        $overlap = array_intersect($bodies1, $bodies2);
        $this->assertEmpty($overlap, 'Overlapping messages must be completely eliminated between consecutive jobs');

        // Verify updated watermark
        $this->conversation->refresh();
        $watermark2 = $this->conversation->metadata['last_memory_ingested_message_id'] ?? null;
        $this->assertSame($turn7->id, $watermark2);
    }

    public function test_no_new_messages_skips_ingestion_completely(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Initial single message.',
        ]);

        $mockClient = $this->createMock(ConversationMemoryClient::class);
        $mockClient->expects($this->once())
            ->method('ingest')
            ->willReturn([
                'success'            => true,
                'edges_created'      => 1,
                'entities_extracted' => 1,
            ]);

        $job1 = new IngestConversationMemoryJob($this->conversation);
        $job1->handle($mockClient);

        // Execute job again without creating any new messages
        $job2 = new IngestConversationMemoryJob($this->conversation);
        $job2->handle($mockClient); // Mock expectations will fail if ingest is called a 2nd time!

        $this->assertTrue(true, 'Second job cleanly exited with 0 ingestion calls');
    }

    public function test_failed_ingestion_does_not_advance_watermark(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Failing ingestion message.',
        ]);

        $mockClient = $this->createMock(ConversationMemoryClient::class);
        $mockClient->expects($this->once())
            ->method('ingest')
            ->willReturn([
                'success' => false,
                'error'   => 'Neo4j connection timeout',
            ]);

        $job = new IngestConversationMemoryJob($this->conversation);
        $job->handle($mockClient);

        $this->conversation->refresh();
        $this->assertNull(
            $this->conversation->metadata['last_memory_ingested_message_id'] ?? null,
            'Watermark must NOT be advanced when ingestion reports failure'
        );
    }

    public function test_concurrency_lock_prevents_simultaneous_execution(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Concurrent message.',
        ]);

        // Manually acquire lock simulating an in-progress worker
        $lockKey = "memory_ingest:conv:{$this->conversation->id}";
        $lock = Cache::lock($lockKey, 60);
        $this->assertTrue($lock->get(), 'Failed to acquire simulated lock');

        $mockClient = $this->createMock(ConversationMemoryClient::class);
        $mockClient->expects($this->never())->method('ingest');

        // Worker 2 attempts to run while lock is held
        $job = new IngestConversationMemoryJob($this->conversation);
        $job->handle($mockClient);

        // Ingest should NOT have been called
        $this->conversation->refresh();
        $this->assertNull($this->conversation->metadata['last_memory_ingested_message_id'] ?? null);

        // Release the simulated lock
        $lock->release();

        // Worker 2 runs after lock is released
        $mockClient2 = $this->createMock(ConversationMemoryClient::class);
        $mockClient2->expects($this->once())
            ->method('ingest')
            ->willReturn(['success' => true]);

        $job->handle($mockClient2);

        $this->conversation->refresh();
        $this->assertNotNull($this->conversation->metadata['last_memory_ingested_message_id'] ?? null);
    }

    public function test_atomic_lock_preserves_token_usage_history(): void
    {
        $msg1 = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Turn 1 token usage.',
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Turn 1 reply.',
        ]);

        $mockClient = $this->createMock(ConversationMemoryClient::class);
        $mockClient->method('ingest')->willReturn([
            'success'   => true,
            'llm_usage' => [
                'provider'          => 'deepseek',
                'model'             => 'deepseek-chat',
                'prompt_tokens'     => 50,
                'completion_tokens' => 20,
                'total_tokens'      => 70,
            ],
        ]);

        $job1 = new IngestConversationMemoryJob($this->conversation);
        $job1->handle($mockClient);

        // Turn 2
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Turn 2 token usage.',
        ]);

        $job2 = new IngestConversationMemoryJob($this->conversation);
        $job2->handle($mockClient);

        $this->conversation->refresh();
        $history = $this->conversation->metadata['llm_usage_history'] ?? [];

        $this->assertCount(2, $history, 'Both jobs must safely record usage without overwriting history');
        $this->assertSame(70, $history[0]['total_tokens']);
        $this->assertSame(70, $history[1]['total_tokens']);
    }
}

