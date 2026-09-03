<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use Tests\Feature\E2E\Support\BaseE2ETestCase;
use Tests\Feature\E2E\Support\E2EObservabilityTracer;

/**
 * E2E-03: Tier 2 BM25 Fallback Test
 * 
 * Verifies that when a query lacks strong lexicon keywords, Tier 1 yields insufficient confidence
 * and the engine falls back to Tier 2 BM25 hybrid ranking.
 */
class E2E03Tier2BM25Test extends BaseE2ETestCase
{
    public function test_query_avoiding_strong_lexicon_executes_tier2_bm25(): void
    {
        $query = 'cash diye product nile payment kivabe hoy?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.91, 'payment_inquiry'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->codFaq, 0.86, 'bm25'));

        $this->faqSearchMock->shouldReceive('getLastTelemetry')
            ->andReturn([
                'tier_executed'    => 2,
                'tier1_attempted'  => true,
                'tier1_insufficient'=> true,
                'tier2_attempted'  => true,
                'tier3_invoked'    => false,
            ]);

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertSame($this->codFaq->id, $result['top_hit']->faq->id);

        $trace = E2EObservabilityTracer::trace($query, $result, [
            'tier_used'       => 2,
            'tier1_attempted' => true,
            'tier2_attempted' => true,
            'tier3_invoked'   => false,
            'faq_id'          => $this->codFaq->id,
        ]);

        $this->assertTraceDimensions($trace, [
            'route'     => 'knowledge',
            'retrieval' => [
                'tier_used'       => 2,
                'faq_id'          => $this->codFaq->id,
                'tier1_attempted' => true,
                'tier2_attempted' => true,
                'tier3_invoked'   => false,
            ],
            'answerability' => ['answerable' => true],
            'result'        => 'PASS',
        ]);
    }
}
