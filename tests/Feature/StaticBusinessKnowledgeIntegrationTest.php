<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\KnowledgeSupportAgent;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use App\Services\Memory\MemoryRelevanceGate;
use App\Services\Retrieval\RetrievalClient;
use Database\Seeders\StaticBusinessKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaticBusinessKnowledgeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Conversation $conversation;
    private MemoryRelevanceGate $gate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StaticBusinessKnowledgeSeeder::class);
        $this->workspace = Workspace::first();

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Widget', 'driver' => 'web', 'is_active' => true]
        );

        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Web Widget Account',
            'external_id'  => 'web_static_kb_101',
            'access_token' => 'tok_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_static_kb_101',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $this->gate = new MemoryRelevanceGate();
    }

    public function test_documents_seeded_with_valid_document_types(): void
    {
        $expectedTypes = [
            'about_us',
            'terms',
            'privacy_policy',
            'refund_policy',
            'return_policy',
            'exchange_policy',
            'delivery_policy',
            'payment_policy',
            'cancellation_policy',
            'warranty_policy',
            'contact',
            'customer_support',
            'social_media_policy',
        ];

        foreach ($expectedTypes as $type) {
            $doc = FAQ::where('workspace_id', $this->workspace->id)
                ->where('document_type', $type)
                ->first();

            $this->assertNotNull($doc, "Document of type {$type} should exist in database.");
            $this->assertNotEmpty($doc->documentTypeLabel());
            $this->assertTrue($doc->is_active);
        }
    }

    public function test_retrieval_client_sync_includes_document_type(): void
    {
        Http::fake([
            '*/api/v1/faqs/sync' => Http::response(['status' => 'synced', 'id' => '1', 'workspace_id' => $this->workspace->id], 200),
        ]);

        $client = app(RetrievalClient::class);
        $refundDoc = FAQ::where('document_type', 'refund_policy')->first();

        $success = $client->syncFaq($refundDoc);
        $this->assertTrue($success);

        Http::assertSent(function ($request) {
            return ($request['document_type'] ?? '') === 'refund_policy';
        });
    }

    public function test_multilingual_queries_retrieve_correct_official_policy(): void
    {
        $refundDoc = FAQ::where('document_type', 'refund_policy')->first();

        Http::fake([
            '*/api/v1/search' => Http::response([
                'results' => [
                    [
                        'id'            => $refundDoc->id,
                        'question'      => $refundDoc->question,
                        'answer'        => $refundDoc->answer,
                        'document_type' => 'refund_policy',
                        'score'         => 0.88,
                        'match_type'    => 'hybrid',
                    ]
                ]
            ], 200),
        ]);

        $search = app(FAQSearch::class);

        // 1. Direct English
        $hitsEn = $search->search('What is your refund policy?', 1, $this->workspace->id);
        $this->assertNotEmpty($hitsEn);
        $this->assertSame('refund_policy', $hitsEn->first()->faq->document_type);

        // 2. Native Bangla
        $hitsBn = $search->search('টাকা ফেরত পাওয়ার নিয়ম কী?', 1, $this->workspace->id);
        $this->assertNotEmpty($hitsBn);
        $this->assertSame('refund_policy', $hitsBn->first()->faq->document_type);

        // 3. Banglish
        $hitsBanglish = $search->search('refund koto diner moddhe pabo?', 1, $this->workspace->id);
        $this->assertNotEmpty($hitsBanglish);
        $this->assertSame('refund_policy', $hitsBanglish->first()->faq->document_type);

        // 4. Code-mixed
        $hitsMixed = $search->search('amar refund er policy ta ki?', 1, $this->workspace->id);
        $this->assertNotEmpty($hitsMixed);
        $this->assertSame('refund_policy', $hitsMixed->first()->faq->document_type);
    }

    public function test_memory_relevance_gate_isolation_for_static_knowledge(): void
    {
        // Pure generic business knowledge inquiries MUST bypass memory (SKIP)
        $this->assertFalse($this->gate->shouldRetrieve('What is your return policy?', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('Return policy কী?', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('What is your warranty policy?', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('Where is your office address?', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('What are the store opening hours?', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('Tell me about your company / about us', $this->conversation));
    }

    public function test_memory_and_static_knowledge_interaction_on_personal_request(): void
    {
        // When customer attaches personal reference + commercial intent, memory MUST be triggered (USE)
        $this->assertTrue($this->gate->shouldRetrieve('আমার আগের order-এর মতো product return করতে চাই', $this->conversation));
        $this->assertTrue($this->gate->shouldRetrieve('I want to return my previous order #1042', $this->conversation));
    }

    public function test_knowledge_support_agent_renders_document_type_badge(): void
    {
        $refundDoc = FAQ::where('document_type', 'refund_policy')->first();
        $hit = new FAQSearchResult($refundDoc, 0.9, 0.9, 0.9, 'hybrid');
        $hits = new \Illuminate\Database\Eloquent\Collection([$hit]);

        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievedKnowledge: $hits,
        );

        $instructions = (string) $agent->instructions();

        // Must display the human-readable document badge label
        $this->assertStringContainsString('Refund Policy', $instructions);
        $this->assertStringContainsString($refundDoc->question, $instructions);
        $this->assertStringContainsString($refundDoc->answer, $instructions);
    }
}
