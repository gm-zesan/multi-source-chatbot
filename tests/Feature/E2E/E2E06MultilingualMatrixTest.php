<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\FAQ;
use Tests\Feature\E2E\Support\BaseE2ETestCase;
use Tests\Feature\E2E\Support\E2EObservabilityTracer;

/**
 * E2E-06: Multilingual Matrix Test
 * 
 * Verifies that contextual pronoun resolution and retrieval behave consistently across
 * all four supported language profiles:
 * - Bengali (বাংলা)
 * - Banglish (Phonetic English script)
 * - English
 * - Code-mixed
 */
class E2E06MultilingualMatrixTest extends BaseE2ETestCase
{
    private FAQ $panjabiFaq;

    protected function setUp(): void
    {
        parent::setUp();

        $this->panjabiFaq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'Black Cotton Panjabi এর দাম কত?',
            'answer'       => 'আমাদের Black Cotton Panjabi এর দাম ১,৮৫০ টাকা। সাইজ M, L, XL পাওয়া যাবে।',
            'is_active'    => true,
        ]);
    }

    /**
     * Bengali 2-turn dialogue
     */
    public function test_bengali_pronoun_resolution(): void
    {
        $this->recordTurn(
            'আমি কালো পাঞ্জাবিটা পছন্দ করেছি।',
            'outbound',
            'জি, আমাদের এই কালো পাঞ্জাবি প্রিমিয়াম কাপড়ে তৈরি।'
        );

        $query = 'ওটার দাম কত?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'product_pricing'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($q, $perPage, $wsId, $conv, $signal) {
                // Must have resolved to the panjabi entity!
                return str_contains((string) $signal, 'পাঞ্জাবি') || str_contains((string) $signal, 'Black Cotton');
            })
            ->andReturn($this->createHitCollection($this->panjabiFaq, 0.92, 'lexicon'));

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);

        $trace = E2EObservabilityTracer::trace($query, $result, [
            'language'       => 'bengali',
            'context_status' => 'resolved',
            'entity'         => ['type' => 'Product', 'name' => 'Black Cotton Panjabi'],
            'tier_used'      => 1,
        ]);

        $this->assertTraceDimensions($trace, [
            'context' => ['status' => 'resolved'],
            'result'  => 'PASS',
        ]);
    }

    /**
     * Banglish 2-turn dialogue
     */
    public function test_banglish_pronoun_resolution(): void
    {
        $this->recordTurn(
            'ami black panjabi ta pochondo korechi',
            'outbound',
            'Ji, Black Cotton Panjabi khub popular ebong comfortable.'
        );

        $query = 'otar dam koto?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.94, 'product_pricing'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($q, $perPage, $wsId, $conv, $signal) {
                return str_contains((string) $signal, 'Black Cotton') || str_contains((string) $signal, 'Panjabi');
            })
            ->andReturn($this->createHitCollection($this->panjabiFaq, 0.91, 'lexicon'));

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
    }

    /**
     * English 2-turn dialogue
     */
    public function test_english_pronoun_resolution(): void
    {
        $this->recordTurn(
            'I like the black panjabi.',
            'outbound',
            'Great choice! Our Black Cotton Panjabi is 100% pure cotton.'
        );

        $query = 'How much is that one?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.96, 'product_pricing'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($q, $perPage, $wsId, $conv, $signal) {
                return str_contains((string) $signal, 'Black Cotton') || str_contains((string) $signal, 'Panjabi');
            })
            ->andReturn($this->createHitCollection($this->panjabiFaq, 0.93, 'lexicon'));

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
    }

    /**
     * Code-mixed 2-turn dialogue
     */
    public function test_code_mixed_pronoun_resolution(): void
    {
        $this->recordTurn(
            'ei black panjabi ta order korte chai',
            'outbound',
            'Apni shundor product select korechen.'
        );

        $query = 'otar price ta koto?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.93, 'product_pricing'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($q, $perPage, $wsId, $conv, $signal) {
                return str_contains(mb_strtolower((string) $signal), 'panjabi');
            })
            ->andReturn($this->createHitCollection($this->panjabiFaq, 0.90, 'lexicon'));

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertTrue($result['answered']);
    }
}
