<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Services\Memory\MemoryRelevanceGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class E2EMultiTurnBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_100_multiturn_scenarios_dataset_is_valid_and_balanced(): void
    {
        $path = base_path('tests/Datasets/e2e_multiturn_100_scenarios_dataset.json');
        $this->assertFileExists($path);

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertNotNull($data);
        $this->assertCount(100, $data['scenarios'] ?? []);

        $counts = ['bn' => 0, 'en' => 0, 'banglish' => 0, 'code_mixed' => 0];
        $totalTurns = 0;
        foreach ($data['scenarios'] as $sc) {
            $lang = $sc['language'] ?? 'unknown';
            if (isset($counts[$lang])) {
                $counts[$lang]++;
            }
            $totalTurns += count($sc['turns'] ?? []);
        }

        $this->assertSame(25, $counts['bn']);
        $this->assertSame(25, $counts['en']);
        $this->assertSame(25, $counts['banglish']);
        $this->assertSame(25, $counts['code_mixed']);
        $this->assertGreaterThanOrEqual(200, $totalTurns);
    }

    public function test_benchmark_e2e_command_executes_successfully(): void
    {
        $code = Artisan::call('conversation:benchmark-e2e', [
            '--limit' => 4,
        ]);

        $this->assertSame(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('EXECUTIVE E2E MULTI-TURN SCORECARD', $output);
        $this->assertStringContainsString('MULTILINGUAL MULTI-TURN PERFORMANCE BREAKDOWN', $output);
        $this->assertStringContainsString('CONTROLLED A/B MULTI-TURN EVALUATION', $output);
    }

    public function test_context_switching_zero_leakage_in_multiturn_dialogue(): void
    {
        $gate = new MemoryRelevanceGate();
        $conversation = new Conversation(['external_user_id' => 'u_test_multiturn_isolation']);

        // Turn 1: User declares personal preference (Must USE memory)
        $this->assertTrue($gate->shouldRetrieve('আমার পছন্দের পেমেন্ট মাধ্যম বিকাশ।', $conversation));

        // Turn 2: User asks static corporate policy question (Must SKIP memory - 0 token leakage!)
        $this->assertFalse($gate->shouldRetrieve('ঢাকার বাইরে ডেলিভারি চার্জ কত?', $conversation));

        // Turn 3: User asks about previous preference (Must USE memory)
        $this->assertTrue($gate->shouldRetrieve('আমার পেমেন্ট প্রেফারেন্স কী ছিল?', $conversation));
    }
}
