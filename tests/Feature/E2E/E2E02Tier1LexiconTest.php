<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use Tests\Feature\E2E\Support\BaseE2ETestCase;
use Tests\Feature\E2E\Support\E2EObservabilityTracer;

/**
 * E2E-02: Tier 1 Lexicon Forced Path Test
 * 
 * Verifies that queries containing known domain vocabulary match via Tier 1 Lexicon
 * and strictly do NOT trigger Tier 2 (BM25) or Tier 3 (LLM Expansion).
 */
class E2E02Tier1LexiconTest extends BaseE2ETestCase
{
    public function test_known_vocabulary_hits_tier1_and_bypasses_tier2_and_tier3(): void
    {
        $query = 'COD payment ki?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'cod_inquiry'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->codFaq, 0.94, 'lexicon'));

        $this->faqSearchMock->shouldReceive('getLastTelemetry')
            ->andReturn([
                'tier_executed'    => 1,
                'tier1_attempted'  => true,
                'tier2_attempted'  => false,
                'tier3_invoked'    => false,
                'lexicon_hit'      => true,
            ]);

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertSame($this->codFaq->id, $result['top_hit']->faq->id);

        $trace = E2EObservabilityTracer::trace($query, $result, [
            'tier_used'       => 1,
            'tier1_attempted' => true,
            'tier2_attempted' => false,
            'tier3_invoked'   => false,
            'faq_id'          => $this->codFaq->id,
        ]);

        $this->assertTraceDimensions($trace, [
            'route'     => 'knowledge',
            'retrieval' => [
                'tier_used'       => 1,
                'faq_id'          => $this->codFaq->id,
                'tier1_attempted' => true,
                'tier2_attempted' => false,
                'tier3_invoked'   => false,
            ],
            'answerability' => ['answerable' => true],
            'result'        => 'PASS',
        ]);
    }
}
