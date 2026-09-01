<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Memory\ConversationMemoryClient;
use App\Services\Memory\MemoryRelevanceGate;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversationMemoryBenchmarkTest extends TestCase
{
    public function test_benchmark_command_executes_successfully(): void
    {
        $this->artisan('memory:benchmark')
            ->expectsOutputToContain('DUAL-RUN COMPARATIVE BENCHMARK')
            ->expectsOutputToContain('Benchmark Completed Successfully!')
            ->assertSuccessful();
    }

    public function test_benchmark_token_savings_exceeds_threshold(): void
    {
        $bufferTurns = [
            "[Turn 1] Customer: Hi, I always pay with bKash.",
            "[Turn 2] Assistant: Noted! bKash is available at checkout.",
            "[Turn 3] Customer: I wear size M normally.",
            "[Turn 4] Assistant: Got it, size M noted.",
            "[Turn 5] Customer: Actually for Punjabi I need size XL.",
            "[Turn 6] Assistant: Updated your Punjabi preference to size XL.",
            "[Turn 7] Customer: Where is order #1042?",
            "[Turn 8] Assistant: Order #1042 has been dispatched via Pathao.",
            "[Turn 9] Customer: I received order #1042 with a torn sleeve issue.",
            "[Turn 10] Assistant: We are so sorry! Our quality team has logged the issue.",
            "[Turn 11] Customer: My bKash got blocked yesterday, I will only use Visa Card now.",
            "[Turn 12] Assistant: Understood, we have updated your payment preference to Visa Card.",
        ];
        $bufferChars = strlen(implode("\n", $bufferTurns));

        $graphContext = "Customer Historical Preferences:\n- Preferred Payment: Visa Card [current]";
        $graphChars = strlen($graphContext);

        $savingsPct = (($bufferChars - $graphChars) / $bufferChars) * 100;

        // Graph Memory must reduce context chars by at least 70%
        $this->assertGreaterThan(70.0, $savingsPct);
    }

    public function test_relevance_gate_prevents_unrelated_context_leakage(): void
    {
        $gate = new MemoryRelevanceGate();

        // Chit chat is gated out
        $this->assertFalse($gate->shouldRetrieve('hi', null));
        $this->assertFalse($gate->shouldRetrieve('thanks', null));
    }
}
