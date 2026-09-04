<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\FAQ;
use App\Services\AI\DTOs\AnswerabilityDecision;
use App\Services\AI\Enums\AnswerabilityStatus;
use App\Services\AI\SemanticAnswerabilityGate;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class SemanticAnswerabilityGateTest extends TestCase
{
    private SemanticAnswerabilityGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = new SemanticAnswerabilityGate();
    }

    private function createFakeHit(float $score, string $docType = 'delivery_policy', string $question = 'FAQ'): object
    {
        $faq = new FAQ();
        $faq->document_type = $docType;
        $faq->question = $question;

        return (object) [
            'faq'        => $faq,
            'finalScore' => $score,
        ];
    }

    public function test_router_ood_hard_blocks_even_with_very_high_retrieval_score(): void
    {
        $hits = new Collection([
            $this->createFakeHit(0.7212, 'delivery_policy', 'Flight Schedule Delivery Rates'),
            $this->createFakeHit(0.5443, 'delivery_policy'),
        ]);

        $routing = new RoutingResult(
            route: RouteType::OOD,
            confidence: 0.95,
            intent: 'ood_negative',
            signals: [],
            entities: [],
            routerLatencyMs: 0.1,
        );

        $decision = $this->gate->evaluate('What is the current flight schedule from Dhaka to Coxs Bazar?', $hits, $routing);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
        $this->assertFalse($decision->isAmbiguous());
        $this->assertSame(0.0, $decision->confidenceScore);
        $this->assertTrue($decision->groundedHits->isEmpty());
        $this->assertSame('router_ood_hard_block', $decision->reasons['rule']);
    }

    public function test_ambiguous_fee_query_triggers_ambiguous_status(): void
    {
        $queries = [
            'How much is the fee?',
            'চার্জ কত?',
            'charge koto?',
            'Service fee koto?',
        ];

        foreach ($queries as $q) {
            $hits = new Collection([
                $this->createFakeHit(0.4642, 'faq', 'Delivery charge FAQ'),
                $this->createFakeHit(0.2000, 'faq'),
            ]);

            $decision = $this->gate->evaluate($q, $hits, null);

            $this->assertTrue($decision->isAmbiguous(), "Failed for query: {$q}");
            $this->assertFalse($decision->isConfident());
            $this->assertSame(AnswerabilityStatus::AMBIGUOUS, $decision->status);
            $this->assertTrue($decision->groundedHits->isEmpty());
            $this->assertSame('clarify', $decision->reasons['suggested_action']);
        }
    }

    public function test_non_commerce_off_topic_query_is_rejected_as_unanswerable(): void
    {
        $hits = new Collection([
            $this->createFakeHit(0.5862, 'payment_policy', 'Accepted Payment Methods'),
            $this->createFakeHit(0.4030, 'payment_policy'),
        ]);

        $routing = new RoutingResult(
            route: RouteType::KNOWLEDGE,
            confidence: 0.85,
            intent: 'knowledge_general',
            signals: [],
            entities: [],
            routerLatencyMs: 0.1,
        );

        $decision = $this->gate->evaluate('Can you generate Python code for quick sort algorithm?', $hits, $routing);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
        $this->assertTrue($decision->groundedHits->isEmpty());
        $this->assertSame('non_commerce_off_topic_guard', $decision->reasons['rule']);
    }

    public function test_valid_commerce_policy_query_is_authorized_as_confident(): void
    {
        $hits = new Collection([
            $this->createFakeHit(0.6850, 'return_policy', 'Official Return Policy & Eligibility'),
            $this->createFakeHit(0.5120, 'refund_policy'),
        ]);

        $routing = new RoutingResult(
            route: RouteType::KNOWLEDGE,
            confidence: 0.95,
            intent: 'knowledge_policy',
            signals: [],
            entities: [],
            routerLatencyMs: 0.1,
        );

        $decision = $this->gate->evaluate('What is your official return policy?', $hits, $routing);

        $this->assertTrue($decision->isConfident());
        $this->assertFalse($decision->isUnanswerable());
        $this->assertNotEmpty($decision->groundedHits);
        $this->assertGreaterThan(0.70, $decision->confidenceScore);
        $this->assertSame('evidence_sufficient', $decision->reasons['rule']);
    }

    public function test_insufficient_evidence_low_score_is_unanswerable(): void
    {
        $hits = new Collection([
            $this->createFakeHit(0.2450, 'delivery_policy'),
            $this->createFakeHit(0.2100, 'faq'),
        ]);

        $decision = $this->gate->evaluate('কুরিয়ার ডেলিভারি চার্জ কত হবে?', $hits, null);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertTrue($decision->groundedHits->isEmpty());
        $this->assertSame('insufficient_evidence', $decision->reasons['rule']);
    }
}
