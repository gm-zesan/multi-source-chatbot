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

    private function createFakeHit(
        float $score,
        string $docType = 'delivery_policy',
        string $question = 'FAQ',
        string $answer = 'Default FAQ answer text',
        int $workspaceId = 1,
        ?float $semanticScore = null,
        ?float $keywordScore = null,
        string $matchType = 'hybrid',
    ): object {
        $faq = new FAQ();
        $faq->document_type = $docType;
        $faq->question = $question;
        $faq->answer = $answer;
        $faq->workspace_id = $workspaceId;

        $obj = (object) [
            'faq'        => $faq,
            'finalScore' => $score,
            'matchType'  => $matchType,
        ];
        if ($semanticScore !== null) {
            $obj->semanticScore = $semanticScore;
        }
        if ($keywordScore !== null) {
            $obj->keywordScore = $keywordScore;
        }

        return $obj;
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

        $decision = $this->gate->evaluate('What is the current flight schedule from Dhaka to Coxs Bazar?', $hits, $routing, workspaceId: 1);

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

            $decision = $this->gate->evaluate($q, $hits, null, workspaceId: 1);

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

        $decision = $this->gate->evaluate('Can you generate Python code for quick sort algorithm?', $hits, $routing, workspaceId: 1);

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

        $decision = $this->gate->evaluate('What is your official return policy?', $hits, $routing, workspaceId: 1);

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

        $decision = $this->gate->evaluate('কুরিয়ার ডেলিভারি চার্জ কত হবে?', $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertTrue($decision->groundedHits->isEmpty());
        $this->assertSame('insufficient_evidence', $decision->reasons['rule']);
    }

    public function test_empty_retrieval_collection_is_unanswerable(): void
    {
        $hits = new Collection([]);
        $decision = $this->gate->evaluate('What is your refund policy?', $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
        $this->assertTrue($decision->groundedHits->isEmpty());
        $this->assertSame(0.0, $decision->confidenceScore);
    }

    public function test_malformed_hit_objects_fail_safely(): void
    {
        $hits = new Collection([
            (object) ['finalScore' => 0.85, 'faq' => null],
        ]);

        $decision = $this->gate->evaluate('What is your warranty terms?', $hits, null, workspaceId: 1);
        $this->assertInstanceOf(AnswerabilityDecision::class, $decision);
    }

    /**
     * Acceptance Criteria 1: Relevant medical/clinic FAQ accepted without commerce keywords.
     */
    public function test_medical_clinic_faq_accepted_without_commerce_keywords(): void
    {
        // Query has zero commerce keywords and zero exact token overlap with FAQ (synonyms: child vs pediatric minor dependent)
        $query = 'Can I bring my child along for a checkup?';
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.6850,
                docType: 'clinic_policy',
                question: 'Pediatric Accompaniment Guidelines',
                answer: 'Patients may be accompanied by one minor dependent during routine consultations.',
                workspaceId: 1,
                semanticScore: 0.6850,
                keywordScore: 0.0,
                matchType: 'hybrid',
            ),
            $this->createFakeHit(
                score: 0.4200,
                docType: 'clinic_policy',
                question: 'General Visiting Hours',
                answer: 'Visiting hours are 9am to 5pm daily.',
                workspaceId: 1,
                semanticScore: 0.4200,
            ),
        ]);

        $routing = new RoutingResult(
            route: RouteType::KNOWLEDGE,
            confidence: 0.90,
            intent: 'knowledge_clinic',
        );

        $decision = $this->gate->evaluate($query, $hits, $routing, workspaceId: 1);

        $this->assertTrue($decision->isConfident());
        $this->assertFalse($decision->isUnanswerable());
        $this->assertTrue($decision->reasons['authorized']);
        $this->assertSame('evidence_sufficient', $decision->reasons['rule']);
        $this->assertCount(1, $decision->groundedHits);
    }

    public function test_medical_clinic_faq_accepted_with_lexical_alignment_and_moderate_score(): void
    {
        // Query has clinic lexical overlap ("consultation", "hours") but zero commerce keywords
        $query = 'What are your doctor consultation hours?';
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.5500,
                docType: 'clinic_policy',
                question: 'Doctor Consultation Operating Hours',
                answer: 'Physicians consult between 9:00 AM and 5:00 PM.',
                workspaceId: 1,
                semanticScore: 0.5000,
                keywordScore: 0.7000,
                matchType: 'hybrid',
            ),
        ]);

        $decision = $this->gate->evaluate($query, $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isConfident());
        $this->assertTrue($decision->reasons['authorized']);
        $this->assertSame('evidence_sufficient', $decision->reasons['rule']);
    }

    /**
     * Acceptance Criteria 2: Relevant education/service FAQ accepted without commerce keywords.
     */
    public function test_education_service_faq_accepted_without_commerce_keywords(): void
    {
        // Query has zero commerce keywords and zero literal word overlap (synonyms: prerequisites vs course eligibility prior experience)
        $query = 'Are prerequisites required before enrolling in the introductory coding boot camp?';
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.6650,
                docType: 'academic_policy',
                question: 'Course Eligibility and Prior Experience',
                answer: 'Beginner tracks require no prior programming experience or technical background.',
                workspaceId: 1,
                semanticScore: 0.6650,
                keywordScore: 0.0,
                matchType: 'hybrid',
            ),
            $this->createFakeHit(
                score: 0.4100,
                docType: 'academic_policy',
                question: 'Graduation Requirements',
                answer: 'Students must complete all modules.',
                workspaceId: 1,
                semanticScore: 0.4100,
            ),
        ]);

        $routing = new RoutingResult(
            route: RouteType::KNOWLEDGE,
            confidence: 0.92,
            intent: 'knowledge_education',
        );

        $decision = $this->gate->evaluate($query, $hits, $routing, workspaceId: 1);

        $this->assertTrue($decision->isConfident());
        $this->assertFalse($decision->isUnanswerable());
        $this->assertTrue($decision->reasons['authorized']);
        $this->assertSame('evidence_sufficient', $decision->reasons['rule']);
    }

    /**
     * Acceptance Criteria 3: Existing relevant retail FAQs remain answerable.
     */
    public function test_existing_retail_faqs_remain_answerable(): void
    {
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.7200,
                docType: 'delivery_policy',
                question: 'ডেলিভারি চার্জ সংক্রান্ত নিয়মাবলী',
                answer: 'ঢাকার ভিতরে ৬০ টাকা, ঢাকার বাইরে ১২০ টাকা।',
                workspaceId: 1,
                semanticScore: 0.7000,
                keywordScore: 0.8000,
                matchType: 'hybrid',
            ),
        ]);

        $decision = $this->gate->evaluate('কুরিয়ার ডেলিভারি চার্জ কত হবে?', $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isConfident());
        $this->assertTrue($decision->reasons['authorized']);
        $this->assertSame('evidence_sufficient', $decision->reasons['rule']);
    }

    /**
     * Acceptance Criteria 4: Unrelated query rejected even if tenant has indexed documents.
     */
    public function test_unrelated_query_rejected_even_if_tenant_has_indexed_documents(): void
    {
        $query = 'What is the capital city of France?';
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.2800,
                docType: 'clinic_policy',
                question: 'Emergency Room Admission',
                answer: 'Emergency patients are admitted 24/7 through triage.',
                workspaceId: 1,
            ),
        ]);

        $decision = $this->gate->evaluate($query, $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
        $this->assertFalse($decision->reasons['authorized']);
    }

    public function test_degraded_bm25_mode_without_alignment_is_rejected_even_with_high_score(): void
    {
        // In degraded BM25 mode, score formula yields >= 0.65, but query has no domain/lexical alignment.
        // It must NOT be treated as a high-confidence semantic match.
        $query = 'Can you generate Python code for quick sort algorithm?';
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.7200,
                docType: 'payment_policy',
                question: 'Accepted Payment Methods',
                answer: 'We accept credit cards and mobile payments.',
                workspaceId: 1,
                semanticScore: null,
                keywordScore: 0.6500,
                matchType: 'bm25',
            ),
        ]);

        $decision = $this->gate->evaluate($query, $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
        $this->assertSame('non_commerce_off_topic_guard', $decision->reasons['rule']);
    }

    public function test_degraded_bm25_mode_with_lexical_alignment_is_accepted_when_score_sufficient(): void
    {
        // In degraded BM25 mode, query has clear lexical alignment ("payment", "methods") and score >= 0.45
        $query = 'What payment methods do you accept?';
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.7500,
                docType: 'payment_policy',
                question: 'Accepted Payment Methods',
                answer: 'We accept credit cards and mobile payments.',
                workspaceId: 1,
                semanticScore: null,
                keywordScore: 0.7500,
                matchType: 'bm25',
            ),
        ]);

        $decision = $this->gate->evaluate($query, $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isConfident());
        $this->assertTrue($decision->reasons['authorized']);
        $this->assertSame('evidence_sufficient', $decision->reasons['rule']);
    }

    /**
     * Acceptance Criteria 5: Low-confidence or conflicting retrieval evidence rejected.
     */
    public function test_conflicting_retrieval_evidence_nearly_tied_candidates_rejected(): void
    {
        // Two candidates are nearly tied (margin < 0.03) with moderate score, indicating conflicting/ambiguous evidence
        $query = 'How do I submit insurance claims for specialist visits?';
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.5120,
                docType: 'clinic_faq',
                question: 'Direct In-Network Insurance Billing',
                answer: 'We bill in-network providers directly.',
                workspaceId: 1,
            ),
            $this->createFakeHit(
                score: 0.5090, // Margin is 0.0030 (nearly tied)
                docType: 'clinic_faq',
                question: 'Out-of-Pocket Reimbursement Claim Form',
                answer: 'Patients must submit manual receipts.',
                workspaceId: 1,
            ),
        ]);

        $decision = $this->gate->evaluate($query, $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
        $this->assertSame('conflicting_retrieval_evidence', $decision->reasons['rule']);
    }

    public function test_missing_second_candidate_with_marginal_score_cannot_use_fabricated_margin(): void
    {
        // Only 1 hit returned, score is 0.39 (below standard 0.45).
        // Since second candidate is missing, margin is NOT 0.39 and must not authorize an answer.
        $query = 'Do you provide dental implants?';
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.3900,
                docType: 'clinic_faq',
                question: 'Dental Cleaning Services',
                answer: 'Routine dental cleanings are scheduled on Tuesdays.',
                workspaceId: 1,
            ),
        ]);

        $decision = $this->gate->evaluate($query, $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
    }

    /**
     * Acceptance Criteria 6: Query with strong lexical overlap but wrong semantic meaning rejected.
     */
    public function test_query_with_strong_lexical_overlap_but_wrong_semantic_meaning_rejected(): void
    {
        // Query has strong keyword overlap ("sort", "laboratory", "results"),
        // but user is asking to write Python code (semantic meaning is mismatched, low dense score)
        $query = 'Can I write Python code to sort my laboratory results?';
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.4600, // Hybrid score boosted by keyword overlap
                docType: 'lab_policy',
                question: 'Laboratory Test Results Processing and Sorting Procedure',
                answer: 'Laboratory specimens are sorted by urgency category.',
                workspaceId: 1,
                semanticScore: 0.2200, // Low dense similarity proves wrong meaning
                keywordScore: 0.8500,
                matchType: 'hybrid',
            ),
        ]);

        $decision = $this->gate->evaluate($query, $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
        $this->assertSame('semantic_meaning_mismatch', $decision->reasons['rule']);
    }

    public function test_query_with_zero_semantic_score_and_lexical_overlap_is_rejected(): void
    {
        // Query has lexical overlap, but vector similarity is completely zero (0.0).
        // Must be rejected as semantic meaning mismatch, not bypass because of score == 0.0.
        $query = 'Can I write Python code to sort my laboratory results?';
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.4600,
                docType: 'lab_policy',
                question: 'Laboratory Test Results Processing and Sorting Procedure',
                answer: 'Laboratory specimens are sorted by urgency category.',
                workspaceId: 1,
                semanticScore: 0.0,
                keywordScore: 0.8500,
                matchType: 'hybrid',
            ),
        ]);

        $decision = $this->gate->evaluate($query, $hits, null, workspaceId: 1);

        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
        $this->assertSame('semantic_meaning_mismatch', $decision->reasons['rule']);
    }

    /**
     * Acceptance Criteria 7: Missing or invalid workspace context fails closed.
     */
    public function test_missing_workspace_id_fails_closed(): void
    {
        $hits = new Collection([
            $this->createFakeHit(0.75, 'clinic_policy', 'Consultation Hours', 'Open 9am-5pm'),
        ]);

        // Missing workspace context (null) must fail closed
        $decisionNull = $this->gate->evaluate('What are clinic hours?', $hits, null, workspaceId: null);
        $this->assertTrue($decisionNull->isUnanswerable());
        $this->assertSame('invalid_workspace_context', $decisionNull->reasons['rule']);
    }

    public function test_invalid_workspace_id_fails_closed(): void
    {
        $hits = new Collection([
            $this->createFakeHit(0.75, 'clinic_policy', 'Consultation Hours', 'Open 9am-5pm'),
        ]);

        // Non-positive workspace context (0 or negative) must fail closed
        $decisionZero = $this->gate->evaluate('What are clinic hours?', $hits, null, workspaceId: 0);
        $this->assertTrue($decisionZero->isUnanswerable());
        $this->assertSame('invalid_workspace_context', $decisionZero->reasons['rule']);

        $decisionNeg = $this->gate->evaluate('What are clinic hours?', $hits, null, workspaceId: -1);
        $this->assertTrue($decisionNeg->isUnanswerable());
        $this->assertSame('invalid_workspace_context', $decisionNeg->reasons['rule']);
    }

    public function test_cross_tenant_document_leakage_fails_closed(): void
    {
        // Top hit belongs to Workspace 2, but gate evaluates for Workspace 1
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.85,
                docType: 'clinic_policy',
                question: 'Confidential Patient Policy',
                answer: 'Private data for Clinic B',
                workspaceId: 2,
            ),
        ]);

        $decision = $this->gate->evaluate('Confidential question', $hits, null, workspaceId: 1);
        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
        $this->assertSame('tenant_isolation_violation', $decision->reasons['rule']);
    }

    public function test_cross_tenant_leakage_in_secondary_hit_fails_closed(): void
    {
        // Hit 1 belongs to Workspace 1, but Hit 2 belongs to Workspace 2 (cross-tenant leak in candidate pool)
        $hits = new Collection([
            $this->createFakeHit(
                score: 0.85,
                docType: 'clinic_policy',
                question: 'Consultation Hours',
                answer: 'Open 9am-5pm',
                workspaceId: 1,
            ),
            $this->createFakeHit(
                score: 0.70,
                docType: 'clinic_policy',
                question: 'Confidential Patient Records',
                answer: 'Private data for Clinic B',
                workspaceId: 2,
            ),
        ]);

        $decision = $this->gate->evaluate('What are clinic hours?', $hits, null, workspaceId: 1);
        $this->assertTrue($decision->isUnanswerable());
        $this->assertFalse($decision->isConfident());
        $this->assertSame('tenant_isolation_violation', $decision->reasons['rule']);
    }
}
