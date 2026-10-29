<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\LLM\LLMClient;
use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use Tests\TestCase;

use App\Models\Conversation;

class HybridRouterTest extends TestCase
{
    protected function tearDown(): void
    {
        LLMClient::resetFake();
        parent::tearDown();
    }

    public function test_empty_query_routes_to_chat(): void
    {
        $router = new HybridRouter();
        $result = $router->route('   ');

        $this->assertSame(RouteType::CHAT, $result->route);
        $this->assertSame(1.0, $result->confidence);
        $this->assertSame('empty_query', $result->intent);
    }

    public function test_deterministic_chat_greetings_routing(): void
    {
        $router = new HybridRouter();
        $result = $router->route('Hi, hello!');

        $this->assertSame(RouteType::CHAT, $result->route);
        $this->assertTrue($result->isChat());
        $this->assertSame(1.0, $result->confidence);
        $this->assertSame('greeting', $result->intent);
        $this->assertSame('layer0_greeting_fastpath', $result->signals['layer'] ?? null);
        $this->assertTrue($result->signals['fastpath_matched'] ?? false);
    }

    public function test_standalone_greetings_bypass_llm_call(): void
    {
        $mockLlm = $this->createMock(LLMClient::class);
        $mockLlm->expects($this->never())->method('generate');

        $router = new HybridRouter(llmClient: $mockLlm);

        $standalonePhrases = [
            'hi',
            'Hello',
            'hey there',
            'Good Morning!',
            'how are you',
            'salam',
            'assalamu alaikum',
            'Assalamu alaikum, kemon achen?',
            'kemon achen?',
            'ki khobor',
            'thanks',
            'thank you so much!',
            'Dhonnobad',
            'ধন্যবাদ',
            'অনেক ধন্যবাদ',
            'bye',
            'goodbye',
            'Allah hafez',
            'আল্লাহ হাফেজ',
        ];

        foreach ($standalonePhrases as $phrase) {
            $result = $router->route($phrase);
            $this->assertSame(RouteType::CHAT, $result->route, "Failed for phrase: {$phrase}");
            $this->assertSame(1.0, $result->confidence, "Confidence must be 1.0 for: {$phrase}");
            $this->assertSame('layer0_greeting_fastpath', $result->signals['layer'] ?? null, "Must use fastpath for: {$phrase}");
            $this->assertTrue($result->signals['fastpath_matched'] ?? false);
        }
    }

    public function test_conversational_chat_without_fastpath_uses_llm_router(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'chat',
                'confidence' => 0.95,
                'intent' => 'purchase_intent',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('I would like to know what you can do for my business');

        $this->assertSame(RouteType::CHAT, $result->route);
        $this->assertTrue($result->isChat());
        $this->assertSame(0.95, $result->confidence);
    }

    public function test_mixed_queries_with_substantive_intents_bypass_fastpath(): void
    {
        $testCases = [
            [
                'query' => 'Hi, delivery charge koto?',
                'mock_route' => 'knowledge',
                'expected_route' => RouteType::KNOWLEDGE,
            ],
            [
                'query' => 'Hello, amar order kobe ashbe?',
                'mock_route' => 'knowledge',
                'expected_route' => RouteType::KNOWLEDGE,
            ],
            [
                'query' => 'Thanks, return policy ki?',
                'mock_route' => 'knowledge',
                'expected_route' => RouteType::KNOWLEDGE,
            ],
            [
                'query' => 'Show last month\'s sales',
                'mock_route' => 'analytics',
                'expected_route' => RouteType::ANALYTICS,
            ],
            [
                'query' => 'Order #123 kothay?',
                'mock_route' => 'knowledge',
                'expected_route' => RouteType::KNOWLEDGE,
            ],
            [
                'query' => 'Hi, amar product-er price koto?',
                'mock_route' => 'knowledge',
                'expected_route' => RouteType::KNOWLEDGE,
            ],
            [
                'query' => 'Hello, refund niye help chai',
                'mock_route' => 'knowledge',
                'expected_route' => RouteType::KNOWLEDGE,
            ],
            [
                'query' => 'Thanks, ar ekta question ache',
                'mock_route' => 'knowledge',
                'expected_route' => RouteType::KNOWLEDGE,
            ],
            [
                'query' => 'welcome bonus ache?',
                'mock_route' => 'knowledge',
                'expected_route' => RouteType::KNOWLEDGE,
            ],
            [
                'query' => 'okay thanks, ar ekta question ache',
                'mock_route' => 'knowledge',
                'expected_route' => RouteType::KNOWLEDGE,
            ],
        ];

        foreach ($testCases as $case) {
            LLMClient::resetFake();
            LLMClient::fake([
                json_encode([
                    'route' => $case['mock_route'],
                    'confidence' => 0.96,
                    'intent' => 'substantive_query',
                    'security_status' => 'allowed',
                ]),
            ]);

            $router = new HybridRouter();
            $result = $router->route($case['query']);

            $this->assertSame($case['expected_route'], $result->route, "Query '{$case['query']}' must bypass fastpath.");
            $this->assertNotSame('layer0_greeting_fastpath', $result->signals['layer'] ?? null);
        }
    }

    public function test_pending_clarification_takes_precedence_over_fastpath(): void
    {
        $conversation = new Conversation();
        $conversation->metadata = [
            'pending_clarification' => [
                'options' => [
                    [
                        'id' => 'sales_today',
                        'label' => 'Today Sales',
                        'semantic_value' => 'sales_summary_today',
                    ],
                ],
            ],
        ];

        $router = new HybridRouter();
        // User responds with '1' to select option 1
        $result = $router->route('1', $conversation);

        $this->assertSame(RouteType::ANALYTICS, $result->route);
        $this->assertSame(1.0, $result->confidence);
        $this->assertSame('sales_summary_today', $result->intent);
        $this->assertSame('layer0_pending_clarification', $result->signals['layer'] ?? null);
    }

    public function test_deterministic_knowledge_policy_routing(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'knowledge',
                'confidence' => 0.95,
                'intent' => 'delivery_inquiry',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('What are your shipping rates and return rules?');

        $this->assertSame(RouteType::KNOWLEDGE, $result->route);
        $this->assertTrue($result->isKnowledge());
    }

    public function test_deterministic_analytics_routing(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'analytics',
                'confidence' => 0.99,
                'intent' => 'sales_summary',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('Total sales this month and due collections');

        $this->assertSame(RouteType::ANALYTICS, $result->route);
        $this->assertTrue($result->isAnalytics());
    }

    public function test_security_blocked_mutation_enforces_uncertain_route(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'analytics',
                'confidence' => 0.99,
                'intent' => 'delete_orders',
                'security_status' => 'blocked_mutation',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('DELETE FROM orders WHERE id=1');

        $this->assertSame(RouteType::UNCERTAIN, $result->route);
        $this->assertSame('blocked_mutation', $result->securityStatus);
    }

    public function test_security_blocked_scope_override(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'analytics',
                'confidence' => 0.90,
                'intent' => 'switch_workspace',
                'security_status' => 'blocked_scope_override',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('Show me data for workspace 999');

        $this->assertSame('blocked_scope_override', $result->securityStatus);
    }

    public function test_low_confidence_routes_to_uncertain(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'knowledge',
                'confidence' => 0.45, // Below 0.70 threshold
                'intent' => 'unclear_question',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter(confidenceThreshold: 0.70);
        $result = $router->route('charge');

        $this->assertSame(RouteType::UNCERTAIN, $result->route);
        $this->assertTrue($result->isUncertain());
    }

    public function test_malformed_llm_json_falls_back_safely_to_uncertain(): void
    {
        LLMClient::fake([
            'Invalid non-json response text',
        ]);

        $router = new HybridRouter();
        $result = $router->route('some question');

        $this->assertSame(RouteType::UNCERTAIN, $result->route);
    }
}
