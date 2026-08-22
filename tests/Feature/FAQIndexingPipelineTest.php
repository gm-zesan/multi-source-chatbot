<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\FAQIndexJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\FAQ\FAQIndexer;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use App\Services\FAQ\FAQService;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FAQIndexingPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private FAQ $faq;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Test', 'slug' => 'test']);
        $channel = Channel::create(['slug' => 'test', 'name' => 'Test', 'is_active' => true]);
        $account = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $this->workspace->id,
            'name'         => 'Test',
            'external_id'  => 'ext_1',
            'access_token' => 'token',
        ]);
        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'test_user',
            'customer_name'      => 'Test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
        Message::create([
            'conversation_id' => $conversation->id,
            'body'            => 'Test message',
            'direction'       => 'inbound',
            'type'            => 'text',
        ]);

        $user = \App\Models\User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        \Illuminate\Support\Facades\Auth::login($user);

        $this->faq = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'How do I reset my password?',
            'answer'       => 'Go to settings and click reset password.',
            'priority'     => 80,
            'hit_count'    => 100,
            'is_active'    => true,
        ]);
    }

    public function test_faq_index_job_syncs_to_retrieval_client(): void
    {
        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('syncFaq')
            ->with($this->callback(function (FAQ $faq) {
                return $faq->id === $this->faq->id;
            }))
            ->willReturn(true);

        $job = new FAQIndexJob($this->faq, 'index');
        $job->handle($mockClient);
    }

    public function test_faq_index_job_deletes_when_action_is_delete(): void
    {
        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('deleteFaq')
            ->with($this->faq->id, $this->workspace->id)
            ->willReturn(true);

        $mockClient->expects($this->never())
            ->method('syncFaq');

        $job = new FAQIndexJob($this->faq, 'delete');
        $job->handle($mockClient);
    }

    public function test_faq_index_job_deletes_when_faq_is_inactive(): void
    {
        $this->faq->update(['is_active' => false]);

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('deleteFaq')
            ->with($this->faq->id, $this->workspace->id)
            ->willReturn(true);

        $mockClient->expects($this->never())
            ->method('syncFaq');

        $job = new FAQIndexJob($this->faq, 'index');
        $job->handle($mockClient);
    }

    public function test_faq_service_dispatches_index_job_on_create(): void
    {
        Queue::fake();

        $indexer = new FAQIndexer();
        $service = new FAQService($indexer);

        $faq = $service->create([
            'question'  => 'New question?',
            'answer'    => 'New answer.',
            'is_active' => true,
        ]);

        Queue::assertPushed(FAQIndexJob::class, function (FAQIndexJob $job) use ($faq) {
            return $job->faq->id === $faq->id && $job->action === 'index';
        });
    }

    public function test_faq_service_dispatches_index_job_on_update(): void
    {
        Queue::fake();

        $indexer = new FAQIndexer();
        $service = new FAQService($indexer);

        $service->update($this->faq, [
            'question' => 'Updated question?',
        ]);

        Queue::assertPushed(FAQIndexJob::class, function (FAQIndexJob $job) {
            return $job->faq->id === $this->faq->id && $job->action === 'update';
        });
    }

    public function test_faq_service_dispatches_delete_job_on_delete(): void
    {
        Queue::fake();

        $indexer = new FAQIndexer();
        $service = new FAQService($indexer);

        $service->delete($this->faq);

        Queue::assertPushed(FAQIndexJob::class, function (FAQIndexJob $job) {
            return $job->faq->id === $this->faq->id && $job->action === 'delete';
        });
    }

    public function test_faq_search_delegates_to_retrieval_client(): void
    {
        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('search')
            ->with('how do I reset password', $this->workspace->id, 5)
            ->willReturn(new Collection([
                new FAQSearchResult(
                    faq: $this->faq,
                    keywordScore: 0.0,
                    semanticScore: 0.95,
                    finalScore: 0.95,
                    matchType: 'hybrid',
                ),
            ]));

        $faqSearch = new FAQSearch($mockClient);
        $results = $faqSearch->search('how do I reset password', perPage: 5, workspaceId: $this->workspace->id);

        $this->assertCount(1, $results);
        $this->assertEquals('How do I reset my password?', $results->first()->faq->question);
        $this->assertEquals(0.95, $results->first()->finalScore);
    }
}
