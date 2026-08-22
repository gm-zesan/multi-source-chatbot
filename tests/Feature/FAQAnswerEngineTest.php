<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\FAQ\FAQAnswerEngine;
use App\Services\FAQ\FAQScoreCalculator;
use App\Services\FAQ\FAQSearch;
use App\Services\NLP\TextPreprocessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FAQAnswerEngineTest extends TestCase
{
    use RefreshDatabase;

    private FAQAnswerEngine $engine;
    private FAQ $faq;
    private Workspace $workspace;
    private Conversation $conversation;
    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $preprocessor = TextPreprocessor::make();

        // Mock FAQSearch — returns empty (no Typesense running)
        $searchMock = $this->createMock(FAQSearch::class);
        $searchMock->method('search')->willReturn(
            new \Illuminate\Database\Eloquent\Collection([])
        );

        $calculator = new FAQScoreCalculator();

        $this->engine = new FAQAnswerEngine(
            preprocessor: $preprocessor,
            search: $searchMock,
            calculator: $calculator,
        );

        $this->workspace = Workspace::create(['name' => 'Test', 'slug' => 'test']);
        $channel = Channel::create(['slug' => 'test', 'name' => 'Test', 'is_active' => true]);
        $account = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $this->workspace->id,
            'name'         => 'Test',
            'external_id'  => 'ext_1',
            'access_token' => 'token',
        ]);
        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'test_user',
            'customer_name'      => 'Test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
        $this->message = Message::create([
            'conversation_id' => $this->conversation->id,
            'body'            => 'Test message',
            'direction'       => 'inbound',
            'type'            => 'text',
        ]);

        // Seed a test FAQ
        $this->faq = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question'  => 'How do I reset my password?',
            'answer'    => 'Go to settings and click reset password.',
            'priority'  => 80,
            'hit_count' => 100,
            'is_active' => true,
        ]);
    }

    public function test_engine_returns_unanswered_for_empty_query(): void
    {
        $result = $this->engine->answer(
            query: '',
            workspaceId: $this->workspace->id,
        );

        $this->assertFalse($result->answered);
        $this->assertNull($result->faq);
        $this->assertSame(0.0, $result->confidence);
    }

    public function test_engine_returns_unanswered_when_search_empty(): void
    {
        $result = $this->engine->answer(
            query: 'How do I reset my password?',
            workspaceId: $this->workspace->id,
            threshold: 50,
        );

        // No results from mock search → unanswered
        $this->assertFalse($result->answered);
        $this->assertNull($result->faq);
    }

    public function test_engine_logs_to_knowledge_search_logs(): void
    {
        $this->engine->answer(
            query: 'Some customer query',
            workspaceId: $this->workspace->id,
            conversationId: $this->conversation->id,
            messageId: $this->message->id,
        );

        $this->assertDatabaseHas('knowledge_search_logs', [
            'customer_query' => 'Some customer query',
            'answer_source'  => 'none',
        ]);
    }

    public function test_engine_saves_unanswered_question_when_below_threshold(): void
    {
        $this->engine->answer(
            query: 'Very unique question never seen before',
            workspaceId: $this->workspace->id,
            conversationId: $this->conversation->id,
            messageId: $this->message->id,
            threshold: 90,
        );

        $this->assertDatabaseHas('unanswered_questions', [
            'original_question' => 'Very unique question never seen before',
            'status'            => 'pending',
        ]);
    }

    public function test_engine_increments_existing_unanswered_question(): void
    {
        // First call
        $this->engine->answer('What is your return policy?', $this->workspace->id, $this->conversation->id, $this->message->id, 90);

        // Second call with same question
        $this->engine->answer('What is your return policy?', $this->workspace->id, $this->conversation->id, $this->message->id, 90);

        $this->assertDatabaseHas('unanswered_questions', [
            'original_question' => 'What is your return policy?',
            'occurrence_count'  => 2,
            'status'            => 'pending',
        ]);
    }

    public function test_answer_result_returns_correct_dto(): void
    {
        $result = $this->engine->answer('', $this->workspace->id);

        $this->assertInstanceOf(\App\Services\FAQ\FAQAnswerResult::class, $result);
        $this->assertIsArray($result->toArray());
        $this->assertArrayHasKey('answered', $result->toArray());
        $this->assertArrayHasKey('confidence', $result->toArray());
        $this->assertArrayHasKey('response_time_ms', $result->toArray());
    }

    protected function tearDown(): void
    {
        FAQScoreCalculator::resetCache();
        parent::tearDown();
    }
}
