<?php

declare(strict_types=1);

namespace Tests\Scratch;

use App\Jobs\IngestConversationMemoryJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\Memory\ConversationMemoryClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class test_concurrent_memory_job extends TestCase
{
    use RefreshDatabase;

    public function test_memory_job_lacks_concurrency_guards_and_duplicates_overlapping_turns(): void
    {
        $workspace = Workspace::create(['name' => 'Mem WS', 'slug' => 'mem-ws']);
        $channel = Channel::create(['name' => 'Web', 'slug' => 'web-mem', 'is_active' => true]);
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel_id' => $channel->id,
            'name' => 'Acc',
            'external_id' => 'acc_mem',
            'access_token' => 'tok',
        ]);
        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id' => 'user_mem',
            'customer_name' => 'Customer Mem',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);

        // Add 6 messages
        for ($i = 1; $i <= 6; $i++) {
            $conversation->messages()->create([
                'direction' => $i % 2 === 1 ? 'inbound' : 'outbound',
                'body' => "Message turn {$i}: Customer lives in Dhaka and likes blue shirts.",
                'type' => 'text',
                'sender_type' => $i % 2 === 1 ? 'user' : 'agent',
            ]);
        }

        $job = new IngestConversationMemoryJob($conversation);

        // 1. Check interface: Does the job implement ShouldBeUnique?
        $isUnique = $job instanceof ShouldBeUnique;
        $this->assertFalse($isUnique, 'IngestConversationMemoryJob does NOT implement ShouldBeUnique');

        // 2. Track calls to ConversationMemoryClient
        $capturedCalls = [];
        $mockClient = $this->createMock(ConversationMemoryClient::class);
        $mockClient->expects($this->exactly(2))
            ->method('ingest')
            ->willReturnCallback(function (...$args) use (&$capturedCalls) {
                $capturedCalls[] = $args;
                return [
                    'success' => true,
                    'llm_usage' => [
                        'provider' => 'deepseek',
                        'prompt_tokens' => 150,
                        'completion_tokens' => 50,
                        'total_tokens' => 200,
                    ],
                ];
            });

        // Worker 1 runs
        $job1 = new IngestConversationMemoryJob($conversation);
        $job1->handle($mockClient);

        // Turn 7 arrives
        $conversation->messages()->create([
            'direction' => 'inbound',
            'body' => "Message turn 7: Customer also wants delivery tomorrow.",
            'type' => 'text',
            'sender_type' => 'user',
        ]);

        // Worker 2 runs concurrently or in rapid succession
        $job2 = new IngestConversationMemoryJob($conversation);
        $job2->handle($mockClient);

        $this->assertCount(2, $capturedCalls);

        // Verify message overlap between the two jobs
        $messagesJob1 = $capturedCalls[0][4]; // 'messages' parameter
        $messagesJob2 = $capturedCalls[1][4];

        $bodies1 = array_column($messagesJob1, 'body');
        $bodies2 = array_column($messagesJob2, 'body');

        // Turns 2, 3, 4, 5, 6 were ingested by Job 1 AND are ingested AGAIN by Job 2!
        $overlap = array_intersect($bodies1, $bodies2);
        $this->assertGreaterThanOrEqual(4, count($overlap), 'Multiple message turns are sent repeatedly to LLM extraction!');
    }
}
