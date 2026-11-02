<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\IngestConversationMemoryJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Memory\ConversationMemoryClient;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * IMP-MEM-001: In-depth Unit & Adversarial Concurrency Hardening Tests for IngestConversationMemoryJob.
 */
class IngestConversationMemoryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Concurrency WS', 'slug' => 'concurrency-ws']);
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Concurrency Account',
            'external_id'  => 'acc_concurrency',
            'access_token' => 'token_concurrency',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_concurrency_01',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_job_contract_declares_should_be_unique_until_processing(): void
    {
        $job = new IngestConversationMemoryJob($this->conversation);

        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame((string) $this->conversation->id, $job->uniqueId());
        $this->assertSame(60, $job->uniqueFor);
        $this->assertSame(2, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame('memory', $job->queue);
    }

    public function test_lock_contention_triggers_release_back_to_queue_with_delay(): void
    {
        $lockKey = "memory_ingest:conv:{$this->conversation->id}";

        // Worker 1 holds the lock
        $lock1 = Cache::lock($lockKey, 60);
        $this->assertTrue($lock1->get());

        // Worker 2 attempts handle()
        $job = new class($this->conversation) extends IngestConversationMemoryJob {
            public bool $wasReleased = false;
            public int $releaseDelay = 0;

            public function release($delay = 0)
            {
                $this->wasReleased = true;
                $this->releaseDelay = (int) $delay;
            }
        };

        // Inject fake job property to simulate active queue worker context
        $job->setJob(new \Illuminate\Queue\Jobs\SyncJob(app(), '', '', ''));

        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $job->handle($client);

        // Worker 2 must release job back to queue with 3-second delay
        $this->assertTrue($job->wasReleased, 'Job must be released back to queue when lock is held');
        $this->assertSame(3, $job->releaseDelay, 'Release delay must be exactly 3 seconds for lock contention serialization');

        $lock1->release();
    }

    public function test_lock_released_in_finally_block_even_after_memory_service_exception(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Triggering exception test.',
        ]);

        $lockKey = "memory_ingest:conv:{$this->conversation->id}";

        // Simulate 500 error from downstream memory service
        Http::fake([
            '*/memory/ingest' => Http::response('Internal Server Error', 500),
        ]);

        $job = new IngestConversationMemoryJob($this->conversation);
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');

        $job->handle($client);

        // Lock must be released cleanly in finally block
        $probeLock = Cache::lock($lockKey, 60);
        $this->assertTrue($probeLock->get(), 'Lock must be completely free after worker exception');
        $probeLock->release();
    }

    public function test_watermark_monotonicity_under_out_of_order_or_stale_update(): void
    {
        // Setup initial watermark at 15
        $this->conversation->update([
            'metadata' => ['last_memory_ingested_message_id' => 15],
        ]);

        // Create messages with ID lower than 15 (e.g. 5)
        $msg = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Stale turn test.',
        ]);

        Http::fake([
            '*/memory/ingest' => Http::response([
                'success' => true,
                'edges_created' => 1,
                'entities_extracted' => 1,
            ], 200),
        ]);

        $job = new IngestConversationMemoryJob($this->conversation);
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $job->handle($client);

        $this->conversation->refresh();
        $watermark = (int) ($this->conversation->metadata['last_memory_ingested_message_id'] ?? 0);

        // Watermark must NEVER regress below 15!
        $this->assertGreaterThanOrEqual(15, $watermark, 'Watermark must remain strictly monotonic and never regress');
    }

    public function test_chronological_ordering_guarantees_oldest_first(): void
    {
        // Create 3 messages
        $m1 = Message::create(['conversation_id' => $this->conversation->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Turn 1']);
        $m2 = Message::create(['conversation_id' => $this->conversation->id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'Turn 2']);
        $m3 = Message::create(['conversation_id' => $this->conversation->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Turn 3']);

        $sentOrder = [];
        Http::fake([
            '*/memory/ingest' => function ($request) use (&$sentOrder) {
                foreach ($request['messages'] as $m) {
                    $sentOrder[] = $m['body'];
                }
                return Http::response(['success' => true, 'edges_created' => 1], 200);
            },
        ]);

        $job = new IngestConversationMemoryJob($this->conversation);
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $job->handle($client);

        $this->assertSame(['Turn 1', 'Turn 2', 'Turn 3'], $sentOrder, 'Messages must be delivered in strictly ascending chronological order');
    }

    public function test_backlog_exceeding_ten_messages_dispatches_continuation_batch(): void
    {
        // Initial watermark at message 1
        $initialMsg = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Initial turn',
        ]);
        $this->conversation->update([
            'metadata' => ['last_memory_ingested_message_id' => $initialMsg->id],
        ]);

        // Create 15 new messages (IDs > initialMsg->id)
        $newMessages = [];
        for ($i = 1; $i <= 15; $i++) {
            $newMessages[] = Message::create([
                'conversation_id' => $this->conversation->id,
                'direction'       => ($i % 2 === 1) ? 'inbound' : 'outbound',
                'type'            => 'text',
                'body'            => "Batch turn {$i}",
            ]);
        }

        Queue::fake();

        Http::fake([
            '*/memory/ingest' => Http::response([
                'success' => true,
                'edges_created' => 5,
                'entities_extracted' => 3,
            ], 200),
        ]);

        $job = new IngestConversationMemoryJob($this->conversation);
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $job->handle($client);

        // 1. Watermark advances for the first 10 messages (newMessages[0..9])
        $expectedFirstBatchMax = $newMessages[9]->id;
        $this->conversation->refresh();
        $watermark = (int) ($this->conversation->metadata['last_memory_ingested_message_id'] ?? 0);
        $this->assertSame($expectedFirstBatchMax, $watermark);

        // 2. A continuation job was dispatched to drain the remaining 5 messages
        Queue::assertPushed(IngestConversationMemoryJob::class, function ($pushedJob) {
            return $pushedJob->conversation->id === $this->conversation->id;
        });
    }

    public function test_failed_ingestion_does_not_advance_watermark_and_schedules_retry(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Fail retry turn.',
        ]);

        Http::fake([
            '*/memory/ingest' => Http::response('Service Unavailable', 503),
        ]);

        $job = new class($this->conversation) extends IngestConversationMemoryJob {
            public bool $wasRetried = false;
            public int $retryDelay = 0;

            public function release($delay = 0)
            {
                $this->wasRetried = true;
                $this->retryDelay = (int) $delay;
            }

            public function attempts()
            {
                return 1;
            }
        };
        $job->setJob(new \Illuminate\Queue\Jobs\SyncJob(app(), '', '', ''));

        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $job->handle($client);

        // Watermark must NOT advance
        $this->conversation->refresh();
        $watermark = (int) ($this->conversation->metadata['last_memory_ingested_message_id'] ?? 0);
        $this->assertSame(0, $watermark, 'Failed ingestion must not advance the watermark');

        // Job must be released with 10-second retry delay
        $this->assertTrue($job->wasRetried, 'Failed job must be released for retry');
        $this->assertSame(10, $job->retryDelay, 'Retry delay must be 10 seconds');
    }

    public function test_multiple_conversations_do_not_interfere_with_each_other_locks(): void
    {
        $conv2 = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_other',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $lockKey1 = "memory_ingest:conv:{$this->conversation->id}";
        $lockKey2 = "memory_ingest:conv:{$conv2->id}";

        $lock1 = Cache::lock($lockKey1, 60);
        $lock2 = Cache::lock($lockKey2, 60);

        $this->assertTrue($lock1->get(), 'Lock 1 must succeed');
        $this->assertTrue($lock2->get(), 'Lock 2 on different conversation must also succeed simultaneously');

        $lock1->release();
        $lock2->release();
    }
}
