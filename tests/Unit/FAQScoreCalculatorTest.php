<?php

namespace Tests\Unit;

use App\Models\FAQ;
use App\Models\Workspace;
use App\Services\FAQ\FAQScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FAQScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private FAQScoreCalculator $calculator;
    private FAQ $faq;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new FAQScoreCalculator();

        $this->workspace = Workspace::create([
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
        ]);

        // Seed a test FAQ
        $this->faq = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question'  => 'How do I reset my password?',
            'answer'    => 'Go to settings and click reset password.',
            'priority'  => 80,
            'hit_count' => 100,
            'is_active' => true,
        ]);
    }

    public function test_perfect_match_returns_high_score(): void
    {
        $score = $this->calculator->calculate(
            faq: $this->faq,
            semanticScore: 0.95,
            keywordScore: 0.90,
            rawQuery: 'How do I reset my password?',
        );

        $this->assertGreaterThan(80, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_poor_match_returns_low_score(): void
    {
        $score = $this->calculator->calculate(
            faq: $this->faq,
            semanticScore: 0.05,
            keywordScore: 0.02,
            rawQuery: 'unrelated gibberish query',
        );

        $this->assertLessThan(30, $score);
    }

    public function test_exact_match_bonus_boosts_score(): void
    {
        $withExact = $this->calculator->calculate(
            faq: $this->faq,
            semanticScore: 0.5,
            keywordScore: 0.5,
            rawQuery: 'How do I reset my password?',
        );

        $withoutExact = $this->calculator->calculate(
            faq: $this->faq,
            semanticScore: 0.5,
            keywordScore: 0.5,
            rawQuery: 'Totally unrelated question here',
        );

        $this->assertGreaterThan($withoutExact, $withExact);
    }

    public function test_higher_priority_increases_score(): void
    {
        $lowPriority = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question'  => 'Test question',
            'priority'  => 1,
            'hit_count' => 100,
            'is_active' => true,
        ]);

        $highPriority = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question'  => 'Test question',
            'priority'  => 100,
            'hit_count' => 100,
            'is_active' => true,
        ]);

        $scoreLow = $this->calculator->calculate($lowPriority, 0.5, 0.5, 'test');
        $scoreHigh = $this->calculator->calculate($highPriority, 0.5, 0.5, 'test');

        $this->assertGreaterThan($scoreLow, $scoreHigh);
    }

    public function test_higher_popularity_increases_score(): void
    {
        $unpopular = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question'  => 'Test question',
            'priority'  => 50,
            'hit_count' => 0,
            'is_active' => true,
        ]);

        $popular = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question'  => 'Test question',
            'priority'  => 50,
            'hit_count' => 1000,
            'is_active' => true,
        ]);

        $scoreUnpopular = $this->calculator->calculate($unpopular, 0.5, 0.5, 'test');
        $scorePopular = $this->calculator->calculate($popular, 0.5, 0.5, 'test');

        $this->assertGreaterThan($scoreUnpopular, $scorePopular);
    }

    public function test_score_is_always_between_0_and_100(): void
    {
        // Test with extreme values
        $high = $this->calculator->calculate($this->faq, 2.0, 2.0, 'How do I reset my password?');
        $low  = $this->calculator->calculate($this->faq, -1.0, -1.0, '');

        $this->assertLessThanOrEqual(100, $high);
        $this->assertGreaterThanOrEqual(0, $high);
        $this->assertGreaterThanOrEqual(0, $low);
        $this->assertLessThanOrEqual(100, $low);
    }

    public function test_calculate_batch_returns_correct_map(): void
    {
        $faq2 = FAQ::factory()->create([
            'workspace_id' => $this->workspace->id,
            'question'  => 'What is your return policy?',
            'priority'  => 50,
            'hit_count' => 50,
            'is_active' => true,
        ]);

        $scores = $this->calculator->calculateBatch(
            faqs: [$this->faq, $faq2],
            scores: [
                $this->faq->id => ['semantic' => 0.9, 'keyword' => 0.8],
                $faq2->id      => ['semantic' => 0.3, 'keyword' => 0.2],
            ],
            rawQuery: 'How do I reset my password?',
        );

        $this->assertCount(2, $scores);
        $this->assertArrayHasKey($this->faq->id, $scores);
        $this->assertArrayHasKey($faq2->id, $scores);
        $this->assertGreaterThan($scores[$faq2->id], $scores[$this->faq->id]);
    }

    public function test_normalize_priority_returns_0_for_zero(): void
    {
        $this->assertSame(0.0, $this->calculator->normalizePriority(0));
    }

    public function test_normalize_popularity_returns_0_for_zero(): void
    {
        $this->assertSame(0.0, $this->calculator->normalizePopularity(0));
    }

    protected function tearDown(): void
    {
        FAQScoreCalculator::resetCache();
        parent::tearDown();
    }
}
