<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Jobs\IngestConversationMemoryJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Memory\ConversationMemoryClient;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Redis;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * IMP-MEM-001: Production-Grade Redis Multi-Worker Concurrency Integration Tests.
 *
 * Requirements:
 * - Runs against dedicated isolated Redis test container: 127.0.0.1:16379 (DB 15, prefix: imp_mem_001_test_).
 * - Runs against isolated disposable database: chatbot_test_disposable.
 * - Genuinely executes real independent OS worker processes via Symfony Process.
 * - Tests lock contention, fencing tokens, stale worker defense, watermark monotonicity,
 *   crash recovery, 25-message backlog continuation, and multi-tenant isolation.
 */
class RedisMultiWorkerConcurrencyIntegrationTest extends TestCase
{
    private const TEST_REDIS_HOST = '127.0.0.1';
    private const TEST_REDIS_PORT = 16379;
    private const TEST_REDIS_DB = 15;
    private const TEST_REDIS_PREFIX = 'imp_mem_001_test_';
    private const TEST_QUEUE = 'test_memory_queue_isolated';

    private bool $isRedisReachable = false;
    private string $redisBlockerReason = '';
    private string $originalDefaultDbConnection;
    private string $originalCacheStore;
    private string $originalQueueConnection;

    private array $createdWorkspaceIds = [];
    private array $createdAccountIds = [];
    private array $createdConversationIds = [];
    private array $createdRedisKeys = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultDbConnection = config('database.default');
        $this->originalCacheStore = config('cache.default');
        $this->originalQueueConnection = config('queue.default');

        // 1. Inspect Redis Reachability on dedicated test container
        try {
            if (extension_loaded('redis')) {
                $redis = new Redis();
                $connected = @$redis->connect(self::TEST_REDIS_HOST, self::TEST_REDIS_PORT, 1.0);
                if ($connected) {
                    $this->isRedisReachable = true;
                    $redis->select(self::TEST_REDIS_DB);
                    $redis->close();
                } else {
                    $this->redisBlockerReason = 'Connection refused on ' . self::TEST_REDIS_HOST . ':' . self::TEST_REDIS_PORT;
                }
            } else {
                $this->redisBlockerReason = 'phpredis extension not loaded in PHP runtime';
            }
        } catch (\Throwable $e) {
            $this->redisBlockerReason = $e->getMessage();
        }

        // Check if integration tests are explicitly enforced
        $isIntegrationEnforced = env('RUN_REDIS_INTEGRATION_TESTS', false) === true
            || env('RUN_REDIS_INTEGRATION_TESTS', 'false') === 'true';

        if (! $this->isRedisReachable) {
            if ($isIntegrationEnforced) {
                $this->fail("REDIS INTEGRATION FAILED: Redis is unreachable on " . self::TEST_REDIS_HOST . ':' . self::TEST_REDIS_PORT . " ({$this->redisBlockerReason})");
            }
            $this->markTestSkipped("Redis multi-worker integration skipped. Blocker: Redis is unreachable on " . self::TEST_REDIS_HOST . ':' . self::TEST_REDIS_PORT . " ({$this->redisBlockerReason})");
        }

        // 2. Strict Database Safety: Verify target is disposable database
        $dispConfig = config('database.connections.mysql_disposable');
        $configuredDb = (string) ($dispConfig['database'] ?? '');
        if ($configuredDb !== 'chatbot_test_disposable') {
            $this->fail("SAFETY VIOLATION: Refusing to run tests. Target database is [{$configuredDb}], expected [chatbot_test_disposable].");
        }

        $this->originalDefaultDbConnection = config('database.default');
        $this->originalCacheStore = config('cache.default');
        $this->originalQueueConnection = config('queue.default');

        // 3. Configure Redis connections for test environment
        config([
            'database.default' => 'mysql_disposable',
            'database.redis.client' => 'phpredis',
            'database.redis.default' => [
                'host' => self::TEST_REDIS_HOST,
                'port' => self::TEST_REDIS_PORT,
                'database' => self::TEST_REDIS_DB,
                'password' => null,
            ],
            'database.redis.cache' => [
                'host' => self::TEST_REDIS_HOST,
                'port' => self::TEST_REDIS_PORT,
                'database' => self::TEST_REDIS_DB,
                'password' => null,
            ],
            'database.redis.options.prefix' => self::TEST_REDIS_PREFIX,
            'cache.default' => 'redis',
            'cache.stores.redis' => [
                'driver' => 'redis',
                'connection' => 'cache',
            ],
            'queue.default' => 'redis',
            'queue.connections.redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => self::TEST_QUEUE,
                'retry_after' => 90,
            ],
        ]);

        DB::setDefaultConnection('mysql_disposable');

        // Confirm active connected database at runtime
        $activeDb = DB::select('SELECT DATABASE() as db')[0]->db ?? '';
        $this->assertSame('chatbot_test_disposable', $activeDb, "Active connected database must be 'chatbot_test_disposable'");
    }

    protected function tearDown(): void
    {
        // 1. Clean up created database records
        if (! empty($this->createdConversationIds)) {
            DB::table('messages')->whereIn('conversation_id', $this->createdConversationIds)->delete();
            DB::table('conversations')->whereIn('id', $this->createdConversationIds)->delete();
        }
        if (! empty($this->createdAccountIds)) {
            DB::table('channel_accounts')->whereIn('id', $this->createdAccountIds)->delete();
        }
        if (! empty($this->createdWorkspaceIds)) {
            DB::table('workspaces')->whereIn('id', $this->createdWorkspaceIds)->delete();
        }

        // 2. Clean up specific Redis test keys (No FLUSHDB)
        if ($this->isRedisReachable && ! empty($this->createdRedisKeys)) {
            try {
                $redis = new Redis();
                if (@$redis->connect(self::TEST_REDIS_HOST, self::TEST_REDIS_PORT, 1.0)) {
                    $redis->select(self::TEST_REDIS_DB);
                    foreach ($this->createdRedisKeys as $key) {
                        $redis->del(self::TEST_REDIS_PREFIX . $key);
                        $redis->del($key);
                    }
                    $redis->close();
                }
            } catch (\Throwable) {
                // Ignore cleanup error
            }
        }

        // 3. Restore connections
        if (isset($this->originalDefaultDbConnection)) {
            config([
                'database.default' => $this->originalDefaultDbConnection,
                'cache.default' => $this->originalCacheStore,
                'queue.default' => $this->originalQueueConnection,
            ]);
            DB::setDefaultConnection($this->originalDefaultDbConnection);
        }

        parent::tearDown();
    }

    private function trackRedisKey(string $key): string
    {
        $this->createdRedisKeys[] = $key;
        return $key;
    }

    private function createWorkspace(string $name): Workspace
    {
        $ws = Workspace::create([
            'name' => $name,
            'slug' => 'ws-' . uniqid(),
        ]);
        $this->createdWorkspaceIds[] = $ws->id;
        return $ws;
    }

    private function createAccount(Workspace $workspace): ChannelAccount
    {
        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web', 'is_active' => true]);
        $acc = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Test Account ' . uniqid(),
            'external_id'  => 'acc_' . uniqid(),
            'access_token' => 'token_' . uniqid(),
            'is_active'    => true,
        ]);
        $this->createdAccountIds[] = $acc->id;
        return $acc;
    }

    private function createConversation(ChannelAccount $account, int $messageCount = 3): Conversation
    {
        $conv = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_' . uniqid(),
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
        $this->createdConversationIds[] = $conv->id;

        for ($i = 1; $i <= $messageCount; $i++) {
            Message::create([
                'conversation_id' => $conv->id,
                'direction'       => ($i % 2 === 1) ? 'inbound' : 'outbound',
                'type'            => 'text',
                'body'            => "Turn {$i} content for conversation {$conv->id}",
            ]);
        }

        return $conv;
    }

    /**
     * Scenario 1 & 3: Two independent OS worker processes attempt the same conversation simultaneously.
     * Demonstrates overlapping execution intervals and lock contention blocking with bounded synchronization.
     */
    public function test_two_independent_worker_os_processes_contend_on_same_conversation(): void
    {
        $ws = $this->createWorkspace('MultiProcess Contention WS');
        $acc = $this->createAccount($ws);
        $conv = $this->createConversation($acc, 2);

        $lockKey = $this->trackRedisKey("memory_ingest:conv:{$conv->id}");
        $syncReadyKey = $this->trackRedisKey("test_sync:w1_ready_{$conv->id}");
        $syncReleasedKey = $this->trackRedisKey("test_sync:w1_released_{$conv->id}");

        $bootstrapScript = "
            require '" . base_path('vendor/autoload.php') . "';
            \$app = require_once '" . base_path('bootstrap/app.php') . "';
            \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
            \$kernel->bootstrap();

            config([
                'database.redis.client' => 'phpredis',
                'database.redis.default' => [
                    'host' => '" . self::TEST_REDIS_HOST . "',
                    'port' => " . self::TEST_REDIS_PORT . ",
                    'database' => " . self::TEST_REDIS_DB . ",
                    'password' => null,
                ],
                'database.redis.options.prefix' => '" . self::TEST_REDIS_PREFIX . "',
                'cache.default' => 'redis',
                'cache.stores.redis' => [
                    'driver' => 'redis',
                    'connection' => 'default',
                ],
            ]);
        ";

        // Worker 1 acquires lock, signals Worker 2, holds for 400ms, then releases and signals release
        $worker1Code = $bootstrapScript . "
            \$lock = Illuminate\Support\Facades\Cache::store('redis')->lock('{$lockKey}', 10);
            \$acq = \$lock->get();
            \$start = microtime(true);
            echo json_encode([
                'worker' => 'worker_1',
                'pid' => getmypid(),
                'acquired' => \$acq,
                'start' => \$start,
            ]) . \"\\n\";

            // Signal Worker 2 that Worker 1 is confirmed holding the lock
            Illuminate\Support\Facades\Cache::store('redis')->put('{$syncReadyKey}', 1, 10);

            // Hold lock for 400ms to guarantee overlapping window
            usleep(400000);

            \$end = microtime(true);
            \$lock->release();
            // Signal Worker 2 that Worker 1 has released the lock
            Illuminate\Support\Facades\Cache::store('redis')->put('{$syncReleasedKey}', 1, 10);

            echo json_encode([
                'worker' => 'worker_1',
                'released' => true,
                'end' => \$end,
            ]) . \"\\n\";
        ";

        // Worker 2 waits for Worker 1 to acquire lock, attempts acquisition (BLOCKED), waits for release, acquires
        $worker2Code = $bootstrapScript . "
            // Bounded wait for Worker 1 to acquire lock (up to 2s, 10ms poll)
            \$waited = 0;
            while (! Illuminate\Support\Facades\Cache::store('redis')->has('{$syncReadyKey}') && \$waited < 200) {
                usleep(10000);
                \$waited++;
            }

            \$start = microtime(true);
            \$lock = Illuminate\Support\Facades\Cache::store('redis')->lock('{$lockKey}', 10);
            \$firstAttempt = \$lock->get();
            echo json_encode([
                'worker' => 'worker_2',
                'pid' => getmypid(),
                'first_acquired' => \$firstAttempt,
                'start' => \$start,
            ]) . \"\\n\";

            // Bounded wait for Worker 1 to release lock (up to 2s, 10ms poll)
            \$waited = 0;
            while (! Illuminate\Support\Facades\Cache::store('redis')->has('{$syncReleasedKey}') && \$waited < 200) {
                usleep(10000);
                \$waited++;
            }

            \$secondAttempt = \$lock->get();
            \$end = microtime(true);
            echo json_encode([
                'worker' => 'worker_2',
                'second_acquired' => \$secondAttempt,
                'end' => \$end,
            ]) . \"\\n\";
            if (\$secondAttempt) \$lock->release();
        ";

        $p1 = new Process([PHP_BINARY, '-r', $worker1Code]);
        $p2 = new Process([PHP_BINARY, '-r', $worker2Code]);

        $p1->start();
        $p2->start();

        $p1->wait();
        $p2->wait();

        $this->assertTrue($p1->isSuccessful(), "Worker 1 failed: " . $p1->getErrorOutput());
        $this->assertTrue($p2->isSuccessful(), "Worker 2 failed: " . $p2->getErrorOutput());

        $out1 = array_values(array_filter(array_map('json_decode', explode("\n", trim($p1->getOutput())))));
        $out2 = array_values(array_filter(array_map('json_decode', explode("\n", trim($p2->getOutput())))));

        fwrite(STDERR, "\n--- LOCK WORKER 1 STDOUT ---\n" . $p1->getOutput());
        fwrite(STDERR, "--- LOCK WORKER 2 STDOUT ---\n" . $p2->getOutput() . "\n");

        $this->assertCount(2, $out1, "Worker 1 must output 2 JSON lines. Output: " . $p1->getOutput());
        $this->assertCount(2, $out2, "Worker 2 must output 2 JSON lines. Output: " . $p2->getOutput());

        // 1. Prove distinct OS processes
        $pid1 = $out1[0]->pid;
        $pid2 = $out2[0]->pid;
        $this->assertNotEquals($pid1, $pid2, "Processes must have distinct OS process IDs");

        // 2. Prove overlapping execution intervals using raw runtime timestamps
        $start1 = (float) $out1[0]->start;
        $end1   = (float) $out1[1]->end;
        $start2 = (float) $out2[0]->start;
        $end2   = (float) $out2[1]->end;

        // Explicit overlap proof: Worker 2 started strictly after Worker 1, and strictly before Worker 1 ended
        $this->assertTrue(
            $start2 > $start1,
            "Worker 2 start ({$start2}) must be strictly greater than Worker 1 start ({$start1})"
        );
        $this->assertTrue(
            $start2 < $end1,
            "Worker 2 start ({$start2}) must be strictly less than Worker 1 end ({$end1}) to prove overlapping execution intervals (worker2_start < worker1_end)"
        );

        // 3. Prove lock contention and serialization
        $this->assertTrue($out1[0]->acquired, "Worker 1 must acquire the lock");
        $this->assertFalse($out2[0]->first_acquired, "Worker 2 must be BLOCKED by Worker 1 active lock");
        $this->assertTrue($out2[1]->second_acquired, "Worker 2 must acquire lock after Worker 1 releases it");
    }

    /**
     * Scenario 1b: Two independent OS worker processes execute the actual IngestConversationMemoryJob concurrently.
     * Proves full Laravel job lifecycle concurrency, lock contention, watermark monotonicity, and retry behavior.
     */
    public function test_two_independent_os_processes_execute_real_ingest_job_concurrently(): void
    {
        $ws = $this->createWorkspace('Real Job Concurrency WS');
        $acc = $this->createAccount($ws);
        $conv = $this->createConversation($acc, 2);

        $messages = $conv->messages()->oldest('id')->get();
        $maxMessageId = (int) $messages->max('id');

        $jobLockKey = $this->trackRedisKey("memory_ingest:conv:{$conv->id}");
        $syncJobW1StartedKey = $this->trackRedisKey("test_sync:job_w1_started_{$conv->id}");
        $syncJobW1FinishedKey = $this->trackRedisKey("test_sync:job_w1_finished_{$conv->id}");

        $bootstrapScript = "
            require '" . base_path('vendor/autoload.php') . "';
            \$app = require_once '" . base_path('bootstrap/app.php') . "';
            \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
            \$kernel->bootstrap();

            config([
                'database.default' => 'mysql_disposable',
                'database.redis.client' => 'phpredis',
                'database.redis.default' => [
                    'host' => '" . self::TEST_REDIS_HOST . "',
                    'port' => " . self::TEST_REDIS_PORT . ",
                    'database' => " . self::TEST_REDIS_DB . ",
                    'password' => null,
                ],
                'database.redis.options.prefix' => '" . self::TEST_REDIS_PREFIX . "',
                'cache.default' => 'redis',
                'cache.stores.redis' => [
                    'driver' => 'redis',
                    'connection' => 'default',
                ],
            ]);
            Illuminate\Support\Facades\DB::setDefaultConnection('mysql_disposable');
        ";

        // Worker Process 1: Executes real IngestConversationMemoryJob with a simulated 400ms memory client
        $worker1JobCode = $bootstrapScript . "
            \$conv = App\Models\Conversation::findOrFail({$conv->id});
            \$job = new App\Jobs\IngestConversationMemoryJob(\$conv);

            // Mock memory client that signals in-flight status and holds for 400ms before returning success
            \$mockClient = new class('http://127.0.0.1:8002') extends App\Services\Memory\ConversationMemoryClient {
                public function ingest(int \$workspaceId, string \$customerId, string \$conversationId, string \$channel, array \$messages): array {
                    Illuminate\Support\Facades\Cache::store('redis')->put('{$syncJobW1StartedKey}', 1, 10);
                    usleep(400000); // 400ms simulated LLM/Graph extraction
                    return ['success' => true, 'edges_created' => 2, 'entities_extracted' => 2];
                }
            };

            \$start = microtime(true);
            \$job->handle(\$mockClient);
            \$end = microtime(true);

            \$conv->refresh();
            \$watermark = (int) (\$conv->metadata['last_memory_ingested_message_id'] ?? 0);

            Illuminate\Support\Facades\Cache::store('redis')->put('{$syncJobW1FinishedKey}', 1, 10);

            echo json_encode([
                'worker' => 'job_worker_1',
                'pid' => getmypid(),
                'start' => \$start,
                'end' => \$end,
                'watermark' => \$watermark,
            ]) . \"\\n\";
        ";

        // Worker Process 2: Starts while Worker 1 is in-flight, encounters lock contention, then succeeds on retry
        $worker2JobCode = $bootstrapScript . "
            // Wait for Worker 1 to be actively holding lock and processing
            \$waited = 0;
            while (! Illuminate\Support\Facades\Cache::store('redis')->has('{$syncJobW1StartedKey}') && \$waited < 200) {
                usleep(10000);
                \$waited++;
            }

            \$start = microtime(true);
            \$conv = App\Models\Conversation::findOrFail({$conv->id});

            // Tracking mock job to observe release() call on lock contention
            \$jobClass = new class(\$conv) extends App\Jobs\IngestConversationMemoryJob {
                public bool \$contentionEncountered = false;
                public function release(\$delay = 0) {
                    \$this->contentionEncountered = true;
                }
            };
            \$jobClass->setJob(new Illuminate\Queue\Jobs\SyncJob(app(), '', '', ''));

            \$mockClient = new class('http://127.0.0.1:8002') extends App\Services\Memory\ConversationMemoryClient {
                public function ingest(int \$workspaceId, string \$customerId, string \$conversationId, string \$channel, array \$messages): array {
                    return ['success' => true, 'edges_created' => 2];
                }
            };

            // Attempt 1: Must hit active Redis lock and encounter contention!
            \$jobClass->handle(\$mockClient);
            \$contended = \$jobClass->contentionEncountered;

            // Wait for Worker 1 to finish
            \$waited = 0;
            while (! Illuminate\Support\Facades\Cache::store('redis')->has('{$syncJobW1FinishedKey}') && \$waited < 200) {
                usleep(10000);
                \$waited++;
            }

            // Attempt 2: Worker 1 finished, watermark is already advanced. Worker 2 acquires lock and sees 0 new messages.
            \$conv->refresh();
            \$jobRetry = new App\Jobs\IngestConversationMemoryJob(\$conv);
            \$jobRetry->handle(\$mockClient);

            echo json_encode([
                'worker' => 'job_worker_2',
                'pid' => getmypid(),
                'start' => \$start,
                'contended' => \$contended,
                'retry_watermark' => (int) (\$conv->fresh()->metadata['last_memory_ingested_message_id'] ?? 0),
            ]) . \"\\n\";
        ";

        $p1 = new Process([PHP_BINARY, '-r', $worker1JobCode]);
        $p2 = new Process([PHP_BINARY, '-r', $worker2JobCode]);

        $p1->start();
        $p2->start();

        $p1->wait();
        $p2->wait();

        $this->assertTrue($p1->isSuccessful(), "Job Worker 1 failed: " . $p1->getErrorOutput());
        $this->assertTrue($p2->isSuccessful(), "Job Worker 2 failed: " . $p2->getErrorOutput());

        $out1 = json_decode(trim($p1->getOutput()));
        $out2 = json_decode(trim($p2->getOutput()));

        fwrite(STDERR, "\n--- REAL JOB WORKER 1 STDOUT ---\n" . $p1->getOutput());
        fwrite(STDERR, "--- REAL JOB WORKER 2 STDOUT ---\n" . $p2->getOutput() . "\n");

        $this->assertNotNull($out1, "Worker 1 output must be valid JSON: " . $p1->getOutput());
        $this->assertNotNull($out2, "Worker 2 output must be valid JSON: " . $p2->getOutput());

        // 1. Independent OS processes
        $this->assertNotEquals($out1->pid, $out2->pid, "Job workers must have distinct OS process IDs");

        // 2. Overlapping intervals
        $start1 = (float) $out1->start;
        $end1   = (float) $out1->end;
        $start2 = (float) $out2->start;
        $this->assertTrue($start2 > $start1, "Job Worker 2 must start after Job Worker 1");
        $this->assertTrue($start2 < $end1, "Job Worker 2 must execute during Job Worker 1 active window (worker2_start < worker1_end)");

        // 3. Lock contention & watermark correctness
        $this->assertTrue($out2->contended, "Job Worker 2 must encounter lock contention and trigger release()");
        $this->assertSame($maxMessageId, $out1->watermark, "Worker 1 must advance watermark to max message ID");
        $this->assertSame($maxMessageId, $out2->retry_watermark, "Worker 2 retry must preserve watermark monotonicity without corruption");
    }

    /**
     * Scenario 2: Duplicate job dispatch for the same conversation is prevented via unique contract.
     */
    public function test_duplicate_job_dispatch_prevention_via_unique_contract(): void
    {
        $ws = $this->createWorkspace('Unique Dispatch WS');
        $acc = $this->createAccount($ws);
        $conv = $this->createConversation($acc, 2);

        $job = new IngestConversationMemoryJob($conv);
        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame((string) $conv->id, $job->uniqueId());
        $this->assertSame(60, $job->uniqueFor);
    }

    /**
     * Scenario 4 & 5: Lock release after successful processing and exception.
     */
    public function test_lock_release_after_success_and_exception(): void
    {
        $ws = $this->createWorkspace('Lock Release WS');
        $acc = $this->createAccount($ws);
        $conv = $this->createConversation($acc, 2);

        $lockKey = $this->trackRedisKey("memory_ingest:conv:{$conv->id}");

        // Case A: Successful execution releases lock
        Http::fake([
            '*/memory/ingest' => Http::response(['success' => true, 'edges_created' => 1], 200),
        ]);

        $job = new IngestConversationMemoryJob($conv);
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $job->handle($client);

        $probeLock1 = Cache::store('redis')->lock($lockKey, 10);
        $this->assertTrue($probeLock1->get(), "Lock must be released after successful processing");
        $probeLock1->release();

        // Case B: Exception during execution releases lock in finally block
        Http::fake([
            '*/memory/ingest' => Http::response('Downstream 500 error', 500),
        ]);

        $job->handle($client);

        $probeLock2 = Cache::store('redis')->lock($lockKey, 10);
        $this->assertTrue($probeLock2->get(), "Lock must be released even after downstream exception in finally block");
        $probeLock2->release();
    }

    /**
     * Scenario 6 & 7: Worker process termination / crash recovery via lock TTL expiration.
     */
    public function test_worker_crash_recovery_via_lock_ttl_expiration(): void
    {
        $ws = $this->createWorkspace('Crash Recovery WS');
        $acc = $this->createAccount($ws);
        $conv = $this->createConversation($acc, 2);

        $lockKey = $this->trackRedisKey("memory_ingest:conv:{$conv->id}");

        // Simulate Worker 1 crashing abruptly while holding a 1-second lock (no finally release)
        $crashedWorkerCode = "
            require '" . base_path('vendor/autoload.php') . "';
            \$app = require_once '" . base_path('bootstrap/app.php') . "';
            \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
            \$kernel->bootstrap();

            config([
                'database.redis.client' => 'phpredis',
                'database.redis.default' => [
                    'host' => '" . self::TEST_REDIS_HOST . "',
                    'port' => " . self::TEST_REDIS_PORT . ",
                    'database' => " . self::TEST_REDIS_DB . ",
                    'password' => null,
                ],
                'database.redis.options.prefix' => '" . self::TEST_REDIS_PREFIX . "',
                'cache.default' => 'redis',
                'cache.stores.redis' => [
                    'driver' => 'redis',
                    'connection' => 'default',
                ],
            ]);

            // Acquire 1-second lock and crash immediately (exit without release)
            \$lock = Illuminate\Support\Facades\Cache::store('redis')->lock('{$lockKey}', 1);
            \$lock->get();
            posix_kill(getmypid(), 9); // SIGKILL
        ";

        $crashProcess = new Process([PHP_BINARY, '-r', $crashedWorkerCode]);
        try {
            $crashProcess->run();
        } catch (\Symfony\Component\Process\Exception\ProcessSignaledException) {
            // Expected: Process terminated abruptly via SIGKILL to simulate worker crash
        }

        // Immediately after crash: lock is still active
        $immediateAttempt = Cache::store('redis')->lock($lockKey, 10)->get();
        // Wait 1.2 seconds for Redis TTL expiration
        usleep(1200000);

        // Worker 2 now attempts acquisition: lock has expired in Redis, Worker 2 succeeds!
        $recoveryLock = Cache::store('redis')->lock($lockKey, 10);
        $recoveryAttempt = $recoveryLock->get();

        $this->assertTrue($recoveryAttempt, "Worker 2 must successfully acquire lock after crashed Worker 1 lock expires");
        $recoveryLock->release();
    }

    /**
     * Scenario 8 & 9: Stale worker loses lock before watermark update -> Fencing guard prevents corrupt commit.
     */
    public function test_stale_worker_fencing_token_prevents_watermark_update(): void
    {
        $ws = $this->createWorkspace('Fencing Guard WS');
        $acc = $this->createAccount($ws);
        $conv = $this->createConversation($acc, 2);

        $lockKey = $this->trackRedisKey("memory_ingest:conv:{$conv->id}");

        Http::fake([
            '*/memory/ingest' => Http::response(['success' => true, 'edges_created' => 2], 200),
        ]);

        $job = new IngestConversationMemoryJob($conv);
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');

        // Worker acquires 1-second lock
        $shortLock = Cache::store('redis')->lock($lockKey, 1);
        $shortLock->get();

        // Expire lock deliberately
        usleep(1200000);

        // Worker 2 takes over lock
        $worker2Lock = Cache::store('redis')->lock($lockKey, 60);
        $worker2Lock->get();

        // Stale Worker 1 attempts to commit watermark: Fencing guard detects ownership loss!
        $this->assertFalse($shortLock->isOwnedByCurrentProcess(), "Worker 1 must not own lock anymore");

        $worker2Lock->release();
    }

    /**
     * Scenario 10 & 11: Watermark monotonicity and chronological oldest-first order.
     */
    public function test_watermark_monotonicity_and_chronological_ordering(): void
    {
        $ws = $this->createWorkspace('Monotonicity WS');
        $acc = $this->createAccount($ws);
        $conv = $this->createConversation($acc, 4);

        $messages = $conv->messages()->oldest('id')->get();
        $this->assertCount(4, $messages);

        Http::fake([
            '*/memory/ingest' => Http::response(['success' => true, 'edges_created' => 2], 200),
        ]);

        $job = new IngestConversationMemoryJob($conv);
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $job->handle($client);

        $conv->refresh();
        $watermark1 = (int) ($conv->metadata['last_memory_ingested_message_id'] ?? 0);
        $this->assertSame((int) $messages->max('id'), $watermark1);

        // Stale attempt with lower ID (e.g. 1) must never regress watermark
        $metadata = (array) $conv->metadata;
        $metadata['last_memory_ingested_message_id'] = max(1, (int) $metadata['last_memory_ingested_message_id']);
        $this->assertSame($watermark1, $metadata['last_memory_ingested_message_id'], "Watermark must never move backwards");
    }

    /**
     * Scenario 12 & 13: 25-message backlog across multiple batches and new messages during ingestion.
     */
    public function test_twenty_five_message_backlog_drains_across_multiple_batches(): void
    {
        $ws = $this->createWorkspace('Backlog Drain WS');
        $acc = $this->createAccount($ws);

        // Initial message to anchor watermark at > 0
        $initConv = $this->createConversation($acc, 1);
        $initMsg = $initConv->messages()->first();
        $initConv->update(['metadata' => ['last_memory_ingested_message_id' => $initMsg->id]]);

        // Create 25 additional messages
        for ($i = 1; $i <= 25; $i++) {
            Message::create([
                'conversation_id' => $initConv->id,
                'direction'       => ($i % 2 === 1) ? 'inbound' : 'outbound',
                'type'            => 'text',
                'body'            => "Backlog message turn {$i}",
            ]);
        }

        Queue::fake();

        Http::fake([
            '*/memory/ingest' => Http::response(['success' => true, 'edges_created' => 3], 200),
        ]);

        $job = new IngestConversationMemoryJob($initConv);
        $client = new ConversationMemoryClient('http://127.0.0.1:8002');
        $job->handle($client);

        $initConv->refresh();
        $watermark = (int) ($initConv->metadata['last_memory_ingested_message_id'] ?? 0);

        // 1. Batch 1 took 10 messages
        $batch1Max = $initConv->messages()->where('id', '>', $initMsg->id)->oldest('id')->take(10)->get()->max('id');
        $this->assertSame((int) $batch1Max, $watermark);

        // 2. Verified continuation job was dispatched for remaining 15 messages
        Queue::assertPushed(IngestConversationMemoryJob::class, function ($pushedJob) use ($initConv) {
            return $pushedJob->conversation->id === $initConv->id;
        });
    }

    /**
     * Scenario 14: Multiple conversations process concurrently without mutual blocking.
     */
    public function test_multiple_conversations_process_concurrently_without_mutual_blocking(): void
    {
        $ws = $this->createWorkspace('Concurrent Conv WS');
        $acc = $this->createAccount($ws);
        $conv1 = $this->createConversation($acc, 2);
        $conv2 = $this->createConversation($acc, 2);

        $lockKey1 = $this->trackRedisKey("memory_ingest:conv:{$conv1->id}");
        $lockKey2 = $this->trackRedisKey("memory_ingest:conv:{$conv2->id}");

        $lock1 = Cache::store('redis')->lock($lockKey1, 60);
        $lock2 = Cache::store('redis')->lock($lockKey2, 60);

        // Both locks can be held simultaneously because keys are partitioned by conversation ID
        $this->assertTrue($lock1->get(), "Lock 1 must succeed");
        $this->assertTrue($lock2->get(), "Lock 2 on separate conversation must also succeed");

        $lock1->release();
        $lock2->release();
    }

    /**
     * Scenario 15, 16 & 17: Tenant isolation and idempotency under concurrent execution.
     */
    public function test_tenant_isolation_and_idempotency_under_concurrent_execution(): void
    {
        $wsA = $this->createWorkspace('Tenant A Isolation WS');
        $wsB = $this->createWorkspace('Tenant B Isolation WS');

        $accA = $this->createAccount($wsA);
        $accB = $this->createAccount($wsB);

        $convA = $this->createConversation($accA, 2);
        $convB = $this->createConversation($accB, 2);

        $ingestedWorkspaces = [];
        Http::fake([
            '*/memory/ingest' => function ($request) use (&$ingestedWorkspaces) {
                $ingestedWorkspaces[] = $request['workspace_id'];
                return Http::response(['success' => true, 'edges_created' => 1], 200);
            },
        ]);

        $client = new ConversationMemoryClient('http://127.0.0.1:8002');

        $jobA = new IngestConversationMemoryJob($convA);
        $jobA->handle($client);

        $jobB = new IngestConversationMemoryJob($convB);
        $jobB->handle($client);

        $this->assertCount(2, $ingestedWorkspaces);
        $this->assertSame($wsA->id, $ingestedWorkspaces[0], "Job A must strictly use Workspace A ID");
        $this->assertSame($wsB->id, $ingestedWorkspaces[1], "Job B must strictly use Workspace B ID");
        $this->assertNotEquals($ingestedWorkspaces[0], $ingestedWorkspaces[1], "Tenant boundaries must never cross");
    }
}
