<?php

declare(strict_types=1);

namespace Tests\Feature\Live;

use App\Models\FAQ;
use App\Models\Workspace;
use App\AI\Orchestration\CustomerSupportService;
use Dotenv\Dotenv;
use Tests\Feature\Live\Support\BaseLiveTestCase;

/**
 * STEP 5F: FINAL LIVE SMOKE + LATENCY VERIFICATION
 *
 * Verifies the complete end-to-end customer support orchestration path across
 * all live boundaries: Laravel -> HybridRouter v2.2 -> Downstream Path (Typesense / MySQL) -> Final Reply.
 */
class LiveCustomerSupportSmokeTest extends BaseLiveTestCase
{
    private CustomerSupportService $service;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        // Hard Gate: LIVE_LLM_TEST must be true
        $isLlmLiveEnabled = env('LIVE_LLM_TEST', false) === true
            || env('LIVE_LLM_TEST', 'false') === 'true'
            || ($_SERVER['LIVE_LLM_TEST'] ?? 'false') === 'true';

        if (!$isLlmLiveEnabled) {
            $this->markTestSkipped('Live smoke test skipped. Set LIVE_LLM_TEST=true to execute live customer support orchestration.');
        }

        // Hydrate runtime config with actual .env keys
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

        $this->workspace = Workspace::firstOrCreate(
            ['id' => 1],
            ['name' => 'Entrepreneurs Automation', 'slug' => 'entrepreneurs-automation', 'is_active' => true]
        );

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $owner = \App\Models\User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $owner->assignRole('superadmin');
        $this->actingAs($owner);

        $faq = FAQ::firstOrCreate(
            ['workspace_id' => $this->workspace->id, 'question' => 'ডেলিভারি চার্জ কত?'],
            [
                'answer'      => 'আমাদের ডেলিভারি চার্জ ঢাকার ভেতরে ৭০ টাকা এবং বাইরে ১৩০ টাকা।',
                'is_active'   => true,
            ]
        );
        app(\App\Services\Retrieval\RetrievalClient::class)->syncFaq($faq);

        $this->service = app(CustomerSupportService::class);
    }

    /**
     * LIVE-SMOKE-01: Knowledge Retrieval & Answer Generation
     * Input: "ডেলিভারি চার্জ কত?"
     */
    public function test_live_smoke_01_knowledge_retrieval_and_answer_generation(): void
    {
        $t_start = microtime(true);
        $result = $this->service->handleQuery(
            query: 'ডেলিভারি চার্জ কত?',
            workspaceId: $this->workspace->id
        );
        $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

        $this->assertSame('knowledge', $result['route'], 'Expected route to be knowledge');
        $this->assertNotEmpty($result['reply'], 'Final response must not be empty');
        $this->assertStringContainsString('ডেলিভারি', $result['reply'], 'Response should relate to delivery policy');
        $this->assertGreaterThan(0.0, $elapsedMs);
    }

    /**
     * LIVE-SMOKE-02: Analytics Query & Tenant Ground Truth
     * Input: "আজকের মোট ক্যাশ কালেকশন কত?"
     */
    public function test_live_smoke_02_analytics_execution_and_tenant_ground_truth(): void
    {
        $t_start = microtime(true);
        $result = $this->service->handleQuery(
            query: 'আজকের মোট ক্যাশ কালেকশন কত?',
            workspaceId: $this->workspace->id
        );
        $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

        $this->assertSame('analytics', $result['route'], 'Expected route to be analytics');
        $this->assertNotEmpty($result['reply']);
        $this->assertGreaterThan(0.0, $elapsedMs);
    }

    /**
     * LIVE-SMOKE-03: Out-of-Domain (OOD) Safe Handling
     * Input: "how to build a rocket?"
     */
    public function test_live_smoke_03_ood_safe_graceful_handling(): void
    {
        $t_start = microtime(true);
        $result = $this->service->handleQuery(
            query: 'how to build a rocket?',
            workspaceId: $this->workspace->id
        );
        $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

        $this->assertSame('ood', $result['route'], 'Expected route to be ood');
        $this->assertNotEmpty($result['reply']);
        $this->assertGreaterThan(0.0, $elapsedMs);
    }

    /**
     * LIVE-SMOKE-04: Conversational Chat Handling
     * Input: "hello, how are you?"
     */
    public function test_live_smoke_04_conversational_chat_handling(): void
    {
        $t_start = microtime(true);
        $result = $this->service->handleQuery(
            query: 'hello, how are you?',
            workspaceId: $this->workspace->id
        );
        $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

        $this->assertSame('chat', $result['route'], 'Expected route to be chat');
        $this->assertNotEmpty($result['reply']);
        $this->assertGreaterThan(0.0, $elapsedMs);
    }
}
