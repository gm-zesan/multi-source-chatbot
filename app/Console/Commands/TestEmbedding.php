<?php

namespace App\Console\Commands;

use App\Services\ContextRouter;
use App\Services\VectorEmbeddingService;
use Illuminate\Console\Command;

class TestEmbedding extends Command
{
    protected $signature = 'chatbot:test-embedding
                            {query : The natural language query to test}
                            {--threshold=0.65 : Minimum confidence threshold}
                            {--top=5 : Number of top candidates to show}';

    protected $description = 'Test the context routing with a sample query and display detailed results';

    public function handle(ContextRouter $router, VectorEmbeddingService $embeddingService): int
    {
        $query     = $this->argument('query');
        $threshold = (float) $this->option('threshold');
        $top       = (int) $this->option('top');

        $this->info("🔍 Testing query: \"{$query}\"");
        $this->newLine();

        // ── Run Context Router ──
        $this->line('📡 Running ContextRouter...');
        $result = $router->route($query);

        $this->table(
            ['Property', 'Value'],
            [
                ['Source ID',  $result->sourceId ?? '—'],
                ['Table',      $result->table ?? '—'],
                ['Query Type', $result->queryType ?? '—'],
                ['Confidence', number_format($result->confidence * 100, 2) . '%'],
                ['Fallback',   $result->usedFallback ? 'Yes (keyword)' : 'No (semantic)'],
                ['Elapsed',    ($result->metadata['elapsed_ms'] ?? '?') . ' ms'],
            ]
        );

        $this->newLine();

        // ── Show level scores ──
        $levelScores = $result->metadata['level_scores'] ?? [];
        if (!empty($levelScores)) {
            $this->line('📊 Level Scores:');
            $rows = [];
            foreach ($levelScores as $level => $score) {
                $rows[] = [$level, number_format($score * 100, 2) . '%'];
            }
            $this->table(['Level', 'Score'], $rows);
        }

        $this->newLine();

        // ── Show top-N candidates ──
        $this->line("🏆 Top {$top} candidate matches:");
        $this->showTopCandidates($embeddingService, $query, $top);

        $this->newLine();

        if ($result->isActionable($threshold)) {
            $this->info("✅ Result is actionable (confidence ≥ " . ($threshold * 100) . "%)");
        } else {
            $this->warn("⚠️  Result is NOT actionable (confidence < " . ($threshold * 100) . "%)");
            $this->line("   Consider lowering the threshold or adding more training data.");
        }

        return Command::SUCCESS;
    }

    protected function showTopCandidates(VectorEmbeddingService $service, string $query, int $top): void
    {
        try {
            // Test against database candidates
            $sources = config('chatbot.sources', []);
            $dbCandidates = [];

            foreach ($sources as $sourceId => $config) {
                $dbCandidates[] = [
                    'key'   => $sourceId,
                    'text'  => $config['description'] ?? '',
                    'vector' => [], // Will be loaded from embeddings if available
                ];
            }

            // Load actual embeddings
            $embeddings = \App\Models\Embedding::ofType('database')->get();
            foreach ($embeddings as $emb) {
                foreach ($dbCandidates as &$c) {
                    if ($c['key'] === $emb->entity_key) {
                        $c['vector'] = $emb->embedding;
                        break;
                    }
                }
            }

            $matches = $service->findTopN($query, $dbCandidates, $top);

            if (empty($matches)) {
                $this->line('   No database matches found. Run `php artisan chatbot:generate-embeddings` first.');
                return;
            }

            $rows = [];
            foreach ($matches as $match) {
                $rows[] = [
                    $match['key'],
                    number_format($match['similarity'] * 100, 2) . '%',
                ];
            }
            $this->table(['Candidate', 'Similarity'], $rows);

        } catch (\Throwable $e) {
            $this->error('   Error: ' . $e->getMessage());
        }
    }
}
