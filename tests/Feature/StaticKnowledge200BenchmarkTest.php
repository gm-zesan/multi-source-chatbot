<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Services\Memory\MemoryRelevanceGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StaticKnowledge200BenchmarkTest extends TestCase
{
    use RefreshDatabase;
    public function test_200_dataset_exists_and_has_balanced_dimensions(): void
    {
        $path = base_path('tests/Datasets/static_kb_200_evaluation_dataset.json');
        $this->assertFileExists($path);

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertNotNull($data);
        $this->assertCount(200, $data['queries'] ?? []);

        $counts = ['en' => 0, 'bn' => 0, 'banglish' => 0, 'code_mixed' => 0];
        foreach ($data['queries'] as $q) {
            $lang = $q['language'] ?? 'unknown';
            if (isset($counts[$lang])) {
                $counts[$lang]++;
            }
        }

        $this->assertSame(50, $counts['en']);
        $this->assertSame(50, $counts['bn']);
        $this->assertSame(50, $counts['banglish']);
        $this->assertSame(50, $counts['code_mixed']);
    }

    public function test_benchmark_200_command_executes_successfully(): void
    {
        $code = Artisan::call('knowledge:benchmark-200', [
            '--limit' => 10,
        ]);

        $this->assertSame(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('EXECUTIVE BENCHMARK SCORECARD', $output);
        $this->assertStringContainsString('MULTILINGUAL PERFORMANCE BREAKDOWN', $output);
        $this->assertStringContainsString('CONTROLLED A/B MEMORY COMPARISON', $output);
    }

    public function test_gate_strict_isolation_on_generic_corporate_policies(): void
    {
        $gate = new MemoryRelevanceGate();
        $conversation = new Conversation(['external_user_id' => 'u_test_policy_isolation']);

        // Must strictly SKIP for generic corporate questions
        $this->assertFalse($gate->shouldRetrieve('What are your delivery charges inside Dhaka?', $conversation));
        $this->assertFalse($gate->shouldRetrieve('Where is your flagship retail store located?', $conversation));
        $this->assertFalse($gate->shouldRetrieve('What are your customer support working hours?', $conversation));
        $this->assertFalse($gate->shouldRetrieve('Tell me about Entrepreneurs Automation company history.', $conversation));
        $this->assertFalse($gate->shouldRetrieve('Do you accept Cash on Delivery (COD)?', $conversation));

        // Must strictly USE for personal context
        $this->assertTrue($gate->shouldRetrieve('I want to return my previous XL order', $conversation));
        $this->assertTrue($gate->shouldRetrieve('Where is my order #1042?', $conversation));
        $this->assertTrue($gate->shouldRetrieve('আমার আগের কেনা পাঞ্জাবিটা রিটার্ন করতে চাই', $conversation));
    }

    public function test_phase_c_baseline_scorecard_is_immutable_and_valid(): void
    {
        $path = base_path('tests/Datasets/phase_c_baseline_scorecard.json');
        $this->assertFileExists($path);

        $baseline = json_decode((string) file_get_contents($path), true);
        $this->assertNotNull($baseline);
        $this->assertSame('phase_c_baseline_v1', $baseline['benchmark_version']);
        $this->assertSame(0.73, $baseline['metrics']['top_1_accuracy']);
        $this->assertSame(0.88, $baseline['metrics']['top_3_accuracy']);
        $this->assertSame(0.76, $baseline['language_breakdown']['banglish']['top_1_accuracy']);
        $this->assertSame(0.76, $baseline['language_breakdown']['native_bangla']['top_1_accuracy']);
    }
}
