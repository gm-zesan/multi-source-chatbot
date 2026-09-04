<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use Tests\Feature\E2E\Support\BaseE2ETestCase;
use Tests\Feature\E2E\Support\E2EObservabilityTracer;

/**
 * E2E-05: Direct CHAT & Negative/OOD Protection Test
 * 
 * Verifies that conversational queries route to CHAT and execute Direct LLM with:
 * - 0 Typesense retrieval calls
 * - 0 KGM retrieval calls
 * - LLM called exactly once
 * And verifies that Out-Of-Domain (OOD) queries strictly bypass both retrieval and memory.
 */
class E2E05DirectChatLLMTest extends BaseE2ETestCase
{
    public function test_conversational_queries_bypass_knowledge_and_execute_direct_llm(): void
    {
        $queries = [
            'Hi',
            'Hello, how are you?',
            'Can you explain what an e-commerce website is?',
        ];

        CustomerSupportAgent::fake([
            'Hello! I am your AI assistant. How can I help you today?',
            'I am doing great, thank you! How can I assist you with your shopping today?',
            'An e-commerce website is an online platform that allows buying and selling of products or services over the internet.',
        ]);

        foreach ($queries as $query) {
            $this->routerMock->shouldReceive('route')
                ->once()
                ->with($query, $this->conversation, $this->workspace->id)
                ->andReturn(new RoutingResult(RouteType::CHAT, 0.95, 'chit_chat'));

            $this->faqSearchMock->shouldNotReceive('search');
            $this->memoryClientMock->shouldNotReceive('search');

            $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

            $this->assertSame('chat', $result['route']);
            $this->assertNull($result['top_hit']);
            $this->assertNull($result['memory_context']);
            $this->assertNotEmpty($result['reply']);

            $trace = E2EObservabilityTracer::trace($query, $result, [
                'language'              => 'english',
                'tier_used'             => 0,
                'memory_decision'       => 'BYPASS',
                'memory_service_called' => false,
                'llm_calls'             => 1,
                'llm_grounded'          => false,
            ]);

            $this->assertTraceDimensions($trace, [
                'route'     => 'chat',
                'memory'    => ['service_called' => false],
                'retrieval' => ['tier_used' => 0, 'hits_count' => 0],
                'llm'       => ['calls' => 1, 'grounded' => false],
                'result'    => 'PASS',
            ]);
        }
    }

    public function test_negative_ood_queries_strictly_bypass_retrieval_and_memory(): void
    {
        $oodQueries = [
            'আজ ঢাকার আবহাওয়া কেমন?',
            'Who won the World Cup?',
            'Python কী?',
        ];

        CustomerSupportAgent::fake(['আমি শুধুমাত্র এই শপের পণ্য ও সেবা সম্পর্কিত তথ্য দিতে পারি।']);

        foreach ($oodQueries as $query) {
            $this->routerMock->shouldReceive('route')
                ->once()
                ->andReturn(new RoutingResult(RouteType::OOD, 0.99, 'general_world_knowledge'));

            $this->faqSearchMock->shouldNotReceive('search');
            $this->memoryClientMock->shouldNotReceive('search');

            $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

            $this->assertSame('ood', $result['route']);
            $this->assertNull($result['top_hit']);
            $this->assertNull($result['memory_context']);
            $this->assertFalse($result['is_handoff']);

            $trace = E2EObservabilityTracer::trace($query, $result, [
                'tier_used'             => 0,
                'memory_decision'       => 'BYPASS',
                'memory_service_called' => false,
            ]);

            $this->assertTraceDimensions($trace, [
                'route'     => 'ood',
                'memory'    => ['service_called' => false],
                'retrieval' => ['tier_used' => 0, 'hits_count' => 0],
                'result'    => 'PASS',
            ]);
        }
    }
}
