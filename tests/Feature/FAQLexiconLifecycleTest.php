<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\FAQIndexJob;
use App\Models\FAQ;
use App\Models\FaqLexicon;
use App\Models\Workspace;
use App\Services\FAQ\CommerceOntology;
use App\Services\FAQ\FaqLexiconGeneratorService;
use App\Services\FAQ\FaqLexiconValidator;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FAQLexiconLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::create(['name' => 'Commerce Test WS', 'slug' => 'commerce-test-ws']);
    }

    public function test_faq_creation_generates_and_validates_lexicon(): void
    {
        $faq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'How can customers track their parcel after placing an order?',
            'answer'       => 'You can track your order using the tracking number sent via SMS.',
            'is_active'    => true,
            'priority'     => 100,
        ]);

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('syncFaq')
            ->with($this->callback(function (FAQ $syncedFaq) {
                return $syncedFaq->relationLoaded('lexicon')
                    && $syncedFaq->lexicon !== null
                    && in_array('Delivery & Shipping', [$syncedFaq->lexicon->domain, CommerceOntology::DOMAIN_DELIVERY_SHIPPING, CommerceOntology::DOMAIN_ORDER_MANAGEMENT], true);
            }))
            ->willReturn(true);

        $job = new FAQIndexJob($faq, 'index');
        $job->handle($mockClient);

        $this->assertDatabaseHas('faq_lexicons', [
            'faq_id'       => $faq->id,
            'workspace_id' => $this->workspace->id,
            'is_validated' => true,
        ]);

        $lexicon = FaqLexicon::where('faq_id', $faq->id)->first();
        $this->assertNotNull($lexicon);
        $this->assertTrue(CommerceOntology::isValidDomain($lexicon->domain));
        $this->assertNotEmpty($lexicon->allTerms());

        // Assert all terms are keywords (<= 6 words)
        foreach ($lexicon->allTerms() as $term) {
            $words = count(preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: []);
            $this->assertLessThanOrEqual(6, $words, "Term '{$term}' exceeds max word count of 6");
        }
    }

    public function test_faq_update_regenerates_and_replaces_old_lexicon(): void
    {
        $faq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'How do I track my order?',
            'answer'       => 'Use your tracking code.',
            'is_active'    => true,
        ]);

        $job1 = new FAQIndexJob($faq, 'index');
        $job1->handle($this->createMock(RetrievalClient::class));

        $initialLexicon = FaqLexicon::where('faq_id', $faq->id)->first();
        $this->assertNotNull($initialLexicon);

        // Update FAQ to completely different domain: Return & Refund
        $faq->update([
            'question' => 'What is the return and refund policy for damaged clothing items?',
            'answer'   => 'You can exchange damaged clothing within 7 days with original invoice.',
        ]);

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())->method('syncFaq')->willReturn(true);

        $job2 = new FAQIndexJob($faq, 'update');
        $job2->handle($mockClient);

        $updatedLexicon = FaqLexicon::where('faq_id', $faq->id)->first();
        $this->assertNotNull($updatedLexicon);
        $this->assertEquals(CommerceOntology::DOMAIN_RETURN_REFUND_EXCHANGE, $updatedLexicon->domain);

        // Verify only 1 lexicon record exists for this FAQ (no duplicate/stale records)
        $this->assertEquals(1, FaqLexicon::where('faq_id', $faq->id)->count());
    }

    public function test_faq_deletion_cascades_and_removes_lexicon(): void
    {
        $faq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'How to cancel my cash on delivery order?',
            'answer'       => 'Call our hotline before shipment.',
            'is_active'    => true,
        ]);

        $job = new FAQIndexJob($faq, 'index');
        $job->handle($this->createMock(RetrievalClient::class));

        $this->assertDatabaseHas('faq_lexicons', ['faq_id' => $faq->id]);

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())
            ->method('deleteFaq')
            ->with($faq->id, $this->workspace->id)
            ->willReturn(true);

        // Delete job
        $deleteJob = new FAQIndexJob($faq, 'delete');
        $deleteJob->handle($mockClient);

        // Force delete FAQ to trigger DB cascade
        $faq->forceDelete();
        $this->assertDatabaseMissing('faq_lexicons', ['faq_id' => $faq->id]);
    }

    public function test_llm_failure_resilience_during_indexing(): void
    {
        $faq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'How do I pay via bKash or Nagad mobile banking?',
            'answer'       => 'Select bKash or Nagad at checkout.',
            'is_active'    => true,
        ]);

        // Fake HTTP failure for LLM provider
        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'Rate limit exceeded'], 429),
        ]);

        $mockClient = $this->createMock(RetrievalClient::class);
        $mockClient->expects($this->once())->method('syncFaq')->willReturn(true);

        $job = new FAQIndexJob($faq, 'index');
        // Must execute cleanly without throwing fatal exception
        $job->handle($mockClient);

        $lexicon = FaqLexicon::where('faq_id', $faq->id)->first();
        $this->assertNotNull($lexicon, "Fallback deterministic lexicon must be generated when LLM is down");
        $this->assertEquals(CommerceOntology::DOMAIN_PAYMENT, $lexicon->domain);
        $this->assertNotEmpty($lexicon->allTerms());
    }

    public function test_validator_filters_sentences_and_policy_claims(): void
    {
        $validator = new FaqLexiconValidator();

        $rawPayload = [
            'domain' => 'Refund and Return Policy',
            'intent' => 'refund_request',
            'canonical_terms' => [
                'refund request',
                'money back',
                'You can easily request a full refund within 14 days by contacting support.', // sentence -> MUST BE FILTERED
                'return damaged parcel',
                'this is a very long term with way more than six words in a single phrase sentence', // > 6 words -> MUST BE FILTERED
            ],
            'bangla_terms' => [
                'টাকা ফেরত',
                'পণ্য পরিবর্তন',
                'আমাদের পলিসি অনুযায়ী ৭ দিনের মধ্যে রিটার্ন করতে পারবেন।', // sentence -> MUST BE FILTERED
                'taka ferot',
            ],
            'commerce_terms' => [
                'COD refund',
                'courier return',
                'bKash reversal',
            ],
        ];

        $validated = $validator->validateAndSanitize($rawPayload);

        $this->assertTrue($validated['is_valid']);
        $this->assertEquals(CommerceOntology::DOMAIN_RETURN_REFUND_EXCHANGE, $validated['domain']);

        // Check canonical terms filtered properly
        $this->assertContains('refund request', $validated['canonical_terms']);
        $this->assertContains('money back', $validated['canonical_terms']);
        $this->assertNotContains('You can easily request a full refund within 14 days by contacting support.', $validated['canonical_terms']);

        // Check bangla terms filtered properly
        $this->assertContains('টাকা ফেরত', $validated['bangla_terms']);
        $this->assertContains('taka ferot', $validated['bangla_terms']);
        $this->assertNotContains('আমাদের পলিসি অনুযায়ী ৭ দিনের মধ্যে রিটার্ন করতে পারবেন।', $validated['bangla_terms']);
    }
}
