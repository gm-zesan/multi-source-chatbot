<?php

declare(strict_types=1);

namespace Tests\Feature\Live;

use App\AI\Decision\Contracts\DecisionEngineInterface;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Routing\RouteType;
use Dotenv\Dotenv;
use Tests\Feature\Live\Support\BaseLiveTestCase;

/**
 * STEP 5E: GATED LIVE LLM API VERIFICATION
 *
 * Verifies that the real configured upstream LLM provider can execute the production
 * UnifiedDecisionEngine semantic decision path across 4 core capabilities.
 */
class LiveLlmRouterTest extends BaseLiveTestCase
{
    private DecisionEngineInterface $engine;

    protected function setUp(): void
    {
        parent::setUp();

        // Hard Gate: LIVE_LLM_TEST must be explicitly set to true
        $isLlmLiveEnabled = env('LIVE_LLM_TEST', false) === true
            || env('LIVE_LLM_TEST', 'false') === 'true'
            || ($_SERVER['LIVE_LLM_TEST'] ?? 'false') === 'true';

        if (!$isLlmLiveEnabled) {
            $this->markTestSkipped('Gated live LLM test skipped. Set LIVE_LLM_TEST=true to execute live provider API calls.');
        }

        // Hydrate runtime config with actual .env keys (bypassing PHPUnit fake-key placeholders in phpunit.xml)
        if (file_exists(base_path('.env'))) {
            $parsed = Dotenv::parse(file_get_contents(base_path('.env')));
            if (!empty($parsed['LLM_API_KEY'])) {
                config(['ai.providers.deepseek.key' => $parsed['LLM_API_KEY']]);
                config(['ai.providers.openrouter.key' => $parsed['LLM_API_KEY']]);
            }
            if (!empty($parsed['DEEPSEEK_API_KEY'])) {
                config(['ai.providers.deepseek.key' => $parsed['DEEPSEEK_API_KEY']]);
            }
            if (!empty($parsed['OPENROUTER_API_KEY'])) {
                config(['ai.providers.openrouter.key' => $parsed['OPENROUTER_API_KEY']]);
            }
            if (!empty($parsed['LLM_BASE_URL'])) {
                config(['ai.providers.deepseek.url' => $parsed['LLM_BASE_URL']]);
            }
            if (!empty($parsed['LLM_MODEL'])) {
                config(['ai.default_model' => $parsed['LLM_MODEL']]);
            }
            if (!empty($parsed['LLM_PROVIDER'])) {
                config(['ai.default' => $parsed['LLM_PROVIDER']]);
            }
        }

        $this->engine = app(DecisionEngineInterface::class);
    }

    /**
     * LIVE-LLM-01: Analytics Query Classification
     * Input: "আজকের বিক্রি কত?"
     * Expected: ANALYTICS
     */
    public function test_live_llm_01_analytics_classification(): void
    {
        $t_start = microtime(true);
        $decision = $this->engine->decide(new DecisionRequest(
            query: 'আজকের বিক্রি কত?',
            workspaceId: 1,
            isCustomer: false
        ));
        $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

        $this->assertSame(RouteType::ANALYTICS, $decision->toRouteType(), 'Natural language sales query must be classified as ANALYTICS.');
        $this->assertGreaterThan(0.0, $elapsedMs);
    }

    /**
     * LIVE-LLM-02: Knowledge Query Classification
     * Input: "রিটার্ন পলিসি কি?"
     * Expected: KNOWLEDGE
     */
    public function test_live_llm_02_knowledge_classification(): void
    {
        $t_start = microtime(true);
        $decision = $this->engine->decide(new DecisionRequest(
            query: 'রিটার্ন পলিসি কি?',
            workspaceId: 1,
            isCustomer: false
        ));
        $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

        $this->assertSame(RouteType::KNOWLEDGE, $decision->toRouteType(), 'Policy/FAQ query must be classified as KNOWLEDGE.');
        $this->assertGreaterThan(0.0, $elapsedMs);
    }

    /**
     * LIVE-LLM-03: Out-of-Domain (OOD) Classification
     * Input: "how to build a rocket?"
     * Expected: OOD
     */
    public function test_live_llm_03_ood_classification(): void
    {
        $t_start = microtime(true);
        $decision = $this->engine->decide(new DecisionRequest(
            query: 'how to build a rocket?',
            workspaceId: 1,
            isCustomer: false
        ));
        $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

        $this->assertSame(RouteType::OOD, $decision->toRouteType(), 'Unrelated technical question must be classified as OOD.');
        $this->assertGreaterThan(0.0, $elapsedMs);
    }

    /**
     * LIVE-LLM-04: Conversational Chat Classification
     * Input: "hello, how are you?"
     * Expected: CHAT
     */
    public function test_live_llm_04_chat_classification(): void
    {
        $t_start = microtime(true);
        $decision = $this->engine->decide(new DecisionRequest(
            query: 'hello, how are you?',
            workspaceId: 1,
            isCustomer: false
        ));
        $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

        $this->assertSame(RouteType::CHAT, $decision->toRouteType(), 'Greeting / chit-chat must be classified as CHAT.');
        $this->assertGreaterThan(0.0, $elapsedMs);
    }
}
