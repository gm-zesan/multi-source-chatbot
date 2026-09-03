<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use Tests\Feature\E2E\Support\BaseE2ETestCase;
use Tests\Feature\E2E\Support\E2EObservabilityTracer;

/**
 * E2E-04: Tier 3 LLM Expansion Forced Path Test
 * 
 * Verifies that intentionally difficult/colloquial queries invoke Tier 3 LLM expansion
 * only after Tier 1 and Tier 2 prove insufficient, and verifies that Tier 3 does NOT
 * execute when Tier 1 or Tier 2 succeeds.
 */
class E2E04Tier3LLMExpansionTest extends BaseE2ETestCase
{
    /**
     * Test difficult colloquial query triggers Tier 3 expansion.
     */
    public function test_colloquial_query_triggers_tier3_expansion_when_tier1_and_tier2_fail(): void
    {
        $query = 'bhai order ashte eto deri hocche keno?';
        $expandedQuery = 'অর্ডার বিলম্ব ডেলিভারি সময় ট্র্যাকিং পার্সেল ডেলিভারি দেরি হওয়ার কারণ';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.90, 'order_delay'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->orderFaq, 0.88, 'tier3_expanded'));

        $this->faqSearchMock->shouldReceive('getLastTelemetry')
            ->andReturn([
                'tier_executed'       => 3,
                'tier1_attempted'     => true,
                'tier1_insufficient'  => true,
                'tier2_attempted'     => true,
                'tier2_insufficient'  => true,
                'tier3_invoked'       => true,
                'expansion_generated' => true,
                'original_query'      => $query,
                'expanded_query'      => $expandedQuery,
            ]);

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertSame($this->orderFaq->id, $result['top_hit']->faq->id);

        $trace = E2EObservabilityTracer::trace($query, $result, [
            'tier_used'       => 3,
            'tier1_attempted' => true,
            'tier2_attempted' => true,
            'tier3_invoked'   => true,
            'faq_id'          => $this->orderFaq->id,
        ]);

        $this->assertTraceDimensions($trace, [
            'route'     => 'knowledge',
            'retrieval' => [
                'tier_used'       => 3,
                'faq_id'          => $this->orderFaq->id,
                'tier1_attempted' => true,
                'tier2_attempted' => true,
                'tier3_invoked'   => true,
            ],
            'answerability' => ['answerable' => true],
            'result'        => 'PASS',
        ]);
    }

    /**
     * Invariant: Tier 3 must NOT execute when Tier 1 or Tier 2 gives an acceptable result.
     */
    public function test_tier3_does_not_execute_when_tier1_or_tier2_succeeds(): void
    {
        $query = 'ডেলিভারি চার্জ কত?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.98, 'delivery_charge'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->deliveryFaq, 0.95, 'lexicon'));

        $this->faqSearchMock->shouldReceive('getLastTelemetry')
            ->andReturn([
                'tier_executed'    => 1,
                'tier1_attempted'  => true,
                'tier2_attempted'  => false,
                'tier3_invoked'    => false,
            ]);

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $trace = E2EObservabilityTracer::trace($query, $result, [
            'tier_used'     => 1,
            'tier3_invoked' => false,
        ]);

        $this->assertFalse($trace['retrieval']['tier3_invoked']);
    }
}
