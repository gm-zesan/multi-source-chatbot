<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\FAQ;
use Tests\Feature\E2E\Support\BaseE2ETestCase;
use Tests\Feature\E2E\Support\E2EObservabilityTracer;

/**
 * E2E-10: Full Cross-Feature Multi-Turn Orchestration & Observability Trace Test
 * 
 * Executes a realistic 6-turn continuous multi-turn dialogue exercising all pipeline branches:
 * - Turn 1: User introduces product & size preference
 * - Turn 2: Pronoun inquiry ("ওটার ডেলিভারি কবে?")
 * - Turn 3: Implicit parcel tracking ("amar parcel ta kothay?")
 * - Turn 4: Multiple competing entities ("parcel ta kothay?") -> M4-A Short-Circuit
 * - Turn 5: User selection ("#1042") -> Intent restored & resolved
 * - Turn 6: Follow-up pricing inquiry ("আর ওটার দাম কত?")
 * 
 * Every turn captures and asserts a full E2E Observability Contract structured trace.
 */
class E2E10FullOrchestrationTraceTest extends BaseE2ETestCase
{
    private FAQ $panjabiPricingFaq;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panjabiPricingFaq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'Black Cotton Panjabi এর মূল্য কত?',
            'answer'       => 'Black Cotton Panjabi এর দাম ১,৮৫০ টাকা।',
            'is_active'    => true,
        ]);
    }

    public function test_full_six_turn_cross_feature_scenario_with_observability_traces(): void
    {
        $traces = [];

        CustomerSupportAgent::fake([
            'আপনার Black Cotton Panjabi অর্ডারটি গ্রহণ করা হয়েছে।',
            'আমাদের ডেলিভারি চার্জ ঢাকার মধ্যে ৬০ টাকা এবং ঢাকার বাইরে ১২০ টাকা।',
            'আপনার অর্ডার স্ট্যাটাস প্রক্রিয়াধীন আছে।',
            'আপনার অর্ডার #1042 এর Black Cotton Panjabi কুরিয়ারে হস্তান্তর করা হয়েছে।',
            'Black Cotton Panjabi এর মূল্য ১,৮৫০ টাকা।',
        ]);

        // ── Turn 1: User introduces order and product ─────────────────────────
        $t1Query = 'আমি XL সাইজের একটা Black Cotton Panjabi অর্ডার করেছি।';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'order_confirmation'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->orderFaq, 0.92, 'lexicon'));

        $t1Result = $this->service->handleQuery($t1Query, $this->workspace->id, $this->conversation);
        $this->recordTurn($t1Query, 'outbound', $t1Result['reply']);

        $traces['turn_1'] = E2EObservabilityTracer::trace($t1Query, $t1Result, [
            'language'  => 'bengali',
            'tier_used' => 1,
            'entity'    => ['type' => 'Product', 'name' => 'Black Cotton Panjabi'],
        ]);
        $this->assertTraceDimensions($traces['turn_1'], [
            'route'         => 'knowledge',
            'retrieval'     => ['tier_used' => 1],
            'answerability' => ['answerable' => true],
            'result'        => 'PASS',
        ]);

        // ── Turn 2: Pronoun inquiry ("ওটার ডেলিভারি কবে?") ───────────────────
        $t2Query = 'ওটার ডেলিভারি কবে?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.94, 'delivery_timeline'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($q, $perPage, $wsId, $conv, $signal) {
                return str_contains((string) $signal, 'Black Cotton Panjabi');
            })
            ->andReturn($this->createHitCollection($this->deliveryFaq, 0.90, 'semantic'));

        $t2Result = $this->service->handleQuery($t2Query, $this->workspace->id, $this->conversation);
        $this->recordTurn($t2Query, 'outbound', $t2Result['reply']);

        $traces['turn_2'] = E2EObservabilityTracer::trace($t2Query, $t2Result, [
            'language'       => 'bengali',
            'context_status' => 'resolved',
            'tier_used'      => 1,
            'entity'         => ['type' => 'Product', 'name' => 'Black Cotton Panjabi'],
        ]);
        $this->assertTraceDimensions($traces['turn_2'], [
            'route'   => 'knowledge',
            'context' => ['status' => 'resolved'],
            'result'  => 'PASS',
        ]);

        // ── Turn 3: Implicit parcel tracking ("amar parcel ta kothay?") ───────
        $t3Query = 'amar parcel ta kothay?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'order_tracking'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->orderFaq, 0.91, 'semantic'));

        $t3Result = $this->service->handleQuery($t3Query, $this->workspace->id, $this->conversation);
        $this->recordTurn($t3Query, 'outbound', $t3Result['reply']);

        $traces['turn_3'] = E2EObservabilityTracer::trace($t3Query, $t3Result, [
            'language'  => 'banglish',
            'tier_used' => 1,
        ]);
        $this->assertTraceDimensions($traces['turn_3'], [
            'route'         => 'knowledge',
            'answerability' => ['answerable' => true],
            'result'        => 'PASS',
        ]);

        // ── Turn 4: Competing orders introduced -> Ambiguity Short-Circuit ────
        // Local context now has two competing orders (#1042 and #2088)
        $this->recordTurn(
            'আমি #1042 এবং #2088 দুইটাই চেক করতে চাই।',
            'outbound',
            'আপনার দুইটি অর্ডারই সিস্টেমে দৃশ্যমান আছে।'
        );

        $t4Query = 'otar parcel ta kothay?';

        // M4-A Short-Circuit: ZERO FAQ calls
        $this->faqSearchMock->shouldNotReceive('search');

        $t4Result = $this->service->handleQuery($t4Query, $this->workspace->id, $this->conversation);

        $traces['turn_4'] = E2EObservabilityTracer::trace($t4Query, $t4Result, [
            'tier_used' => 0,
            'llm_calls' => 0,
        ]);
        $this->assertTraceDimensions($traces['turn_4'], [
            'route'         => 'uncertain',
            'context'       => ['status' => 'ambiguous'],
            'retrieval'     => ['tier_used' => 0],
            'llm'           => ['calls' => 0],
            'clarification' => ['requested' => true, 'short_circuited' => true],
            'result'        => 'PASS',
        ]);

        // ── Turn 5: Selection ("#1042") -> Intent restored & resolved ─────────
        $t5Query = '#1042';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.96, 'order_tracking'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($q, $perPage, $wsId, $conv, $signal) {
                return str_contains((string) $signal, '#1042');
            })
            ->andReturn($this->createHitCollection($this->orderFaq, 0.95, 'semantic'));

        $t5Result = $this->service->handleQuery($t5Query, $this->workspace->id, $this->conversation);
        $this->recordTurn($t5Query, 'outbound', $t5Result['reply']);

        $traces['turn_5'] = E2EObservabilityTracer::trace($t5Query, $t5Result, [
            'context_status' => 'resolved',
            'tier_used'      => 1,
            'entity'         => ['type' => 'Order', 'id' => '1042'],
        ]);
        $this->assertTraceDimensions($traces['turn_5'], [
            'route'         => 'knowledge',
            'context'       => ['status' => 'resolved'],
            'clarification' => ['requested' => false],
            'result'        => 'PASS',
        ]);

        // ── Turn 6: Follow-up pricing ("আর ওটার দাম কত?") ─────────────────────
        $t6Query = 'আর ওটার দাম কত?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.94, 'product_pricing'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($q, $perPage, $wsId, $conv, $signal) {
                return str_contains((string) $signal, 'Black Cotton Panjabi') || str_contains((string) $signal, '1042');
            })
            ->andReturn($this->createHitCollection($this->panjabiPricingFaq, 0.93, 'lexicon'));

        $t6Result = $this->service->handleQuery($t6Query, $this->workspace->id, $this->conversation);

        $traces['turn_6'] = E2EObservabilityTracer::trace($t6Query, $t6Result, [
            'tier_used'      => 1,
            'context_status' => 'resolved',
        ]);
        $this->assertTraceDimensions($traces['turn_6'], [
            'route'         => 'knowledge',
            'answerability' => ['answerable' => true],
            'result'        => 'PASS',
        ]);

        // Verify all 6 traces were successfully captured
        $this->assertCount(6, $traces);
    }
}
