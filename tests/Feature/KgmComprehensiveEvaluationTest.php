<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Services\Memory\MemoryRelevanceGate;
use Tests\TestCase;

class KgmComprehensiveEvaluationTest extends TestCase
{
    private MemoryRelevanceGate $gate;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new MemoryRelevanceGate();
        $this->conversation = new Conversation([
            'external_user_id' => 'eval_test_customer_101',
        ]);
    }

    public function test_kgm_evaluation_command_executes_successfully(): void
    {
        $this->artisan('kgm:evaluate')
            ->expectsOutputToContain('CONVERSATION GRAPH MEMORY (KGM v1) END-TO-END EVALUATION SUITE')
            ->expectsOutputToContain('FINAL EVALUATION SCORECARD MATRIX')
            ->expectsOutputToContain('KGM v1 End-to-End Evaluation Complete!')
            ->assertSuccessful();
    }

    public function test_multilingual_and_phonetic_variations_activate_gate(): void
    {
        // English
        $this->assertTrue($this->gate->shouldRetrieve('I prefer XL size', $this->conversation));

        // Native Bangla
        $this->assertTrue($this->gate->shouldRetrieve('আমি XL সাইজ পছন্দ করি', $this->conversation));
        $this->assertTrue($this->gate->shouldRetrieve('আমার বিকাশ নাম্বার', $this->conversation));

        // Banglish variations
        $this->assertTrue($this->gate->shouldRetrieve('ami bikash e payment korte chai', $this->conversation));
        $this->assertTrue($this->gate->shouldRetrieve('amar ordar ta kothay ase?', $this->conversation));
        $this->assertTrue($this->gate->shouldRetrieve('Can I pay বিকাশ দিয়ে?', $this->conversation));
    }

    public function test_generic_faq_bypass_guarantees_zero_token_leakage(): void
    {
        // Generic FAQs lacking personal reference MUST be gated out (SKIP)
        $this->assertFalse($this->gate->shouldRetrieve('What is the return policy of your store?', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('Return policy কী?', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('How to reset password?', $this->conversation));
        $this->assertFalse($this->gate->shouldRetrieve('What are the store opening hours?', $this->conversation));

        // But personal order return request MUST pass through (USE)
        $this->assertTrue($this->gate->shouldRetrieve('আমার আগের order-এর মতো product return করতে চাই', $this->conversation));
    }
}
