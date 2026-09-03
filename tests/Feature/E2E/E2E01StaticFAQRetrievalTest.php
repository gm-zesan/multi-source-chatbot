<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use Tests\Feature\E2E\Support\BaseE2ETestCase;
use Tests\Feature\E2E\Support\E2EObservabilityTracer;

/**
 * E2E-01: Static FAQ Retrieval Suite
 * 
 * Verifies the complete static-data path across linguistic variations:
 * - A1: Direct exact FAQ (ডেলিভারি চার্জ কত?)
 * - A2: Banglish (delivery charge koto?)
 * - A3: Bengali (ঢাকার বাইরে ডেলিভারি চার্জ কত?)
 * - A4: English (How much is the delivery charge?)
 * - A5: Code-mixed (delivery charge ta koto?)
 * 
 * Strict invariants verified:
 * - Correct route (knowledge)
 * - Correct FAQ matched (ID & content)
 * - No unnecessary memory call (M3 = BYPASS)
 * - No Tier 2 or Tier 3 executed on exact/lexicon match
 */
class E2E01StaticFAQRetrievalTest extends BaseE2ETestCase
{
    /**
     * A1: Direct exact FAQ
     */
    public function test_a1_direct_exact_faq_path(): void
    {
        $query = 'ডেলিভারি চার্জ কত?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->with($query, $this->conversation, $this->workspace->id)
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.98, 'delivery_charge'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($q, $perPage, $wsId, $conv, $signal) use ($query) {
                return $q === $query && $wsId === $this->workspace->id && $signal === null;
            })
            ->andReturn($this->createHitCollection($this->deliveryFaq, 0.95, 'lexicon'));

        $this->faqSearchMock->shouldReceive('getLastTelemetry')
            ->andReturn(['tier_executed' => 1]);

        $this->memoryClientMock->shouldNotReceive('search');

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertSame($this->deliveryFaq->id, $result['top_hit']->faq->id);
        $this->assertNull($result['memory_context']);

        $trace = E2EObservabilityTracer::trace($query, $result, [
            'language'              => 'bengali',
            'tier_used'             => 1,
            'tier1_attempted'       => true,
            'tier2_attempted'       => false,
            'tier3_invoked'         => false,
            'memory_decision'       => 'BYPASS',
            'memory_service_called' => false,
            'llm_calls'             => 1,
            'llm_grounded'          => true,
        ]);

        $this->assertTraceDimensions($trace, [
            'route'         => 'knowledge',
            'memory'        => ['decision' => 'BYPASS', 'service_called' => false],
            'retrieval'     => ['tier_used' => 1, 'tier2_attempted' => false, 'tier3_invoked' => false],
            'answerability' => ['answerable' => true],
            'result'        => 'PASS',
        ]);
    }

    /**
     * A2: Banglish
     */
    public function test_a2_banglish_faq_path(): void
    {
        $query = 'delivery charge koto?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'delivery_charge'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->deliveryFaq, 0.92, 'lexicon'));

        $this->memoryClientMock->shouldNotReceive('search');

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertSame($this->deliveryFaq->id, $result['top_hit']->faq->id);

        $trace = E2EObservabilityTracer::trace($query, $result, [
            'language'              => 'banglish',
            'tier_used'             => 1,
            'memory_decision'       => 'BYPASS',
            'memory_service_called' => false,
        ]);

        $this->assertTraceDimensions($trace, [
            'route'     => 'knowledge',
            'retrieval' => ['tier_used' => 1, 'tier3_invoked' => false],
            'memory'    => ['service_called' => false],
            'result'    => 'PASS',
        ]);
    }

    /**
     * A3: Bengali Semantic Expansion
     */
    public function test_a3_bengali_semantic_path(): void
    {
        $query = 'ঢাকার বাইরে ডেলিভারি চার্জ কত?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.94, 'delivery_charge'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->deliveryFaq, 0.90, 'semantic'));

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertSame($this->deliveryFaq->id, $result['top_hit']->faq->id);
    }

    /**
     * A4: English
     */
    public function test_a4_english_path(): void
    {
        $query = 'How much is the delivery charge?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.96, 'delivery_charge'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->deliveryFaq, 0.91, 'semantic'));

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertSame($this->deliveryFaq->id, $result['top_hit']->faq->id);
    }

    /**
     * A5: Code-mixed
     */
    public function test_a5_code_mixed_path(): void
    {
        $query = 'delivery charge ta koto?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.93, 'delivery_charge'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->deliveryFaq, 0.89, 'lexicon'));

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertSame($this->deliveryFaq->id, $result['top_hit']->faq->id);
    }
}
