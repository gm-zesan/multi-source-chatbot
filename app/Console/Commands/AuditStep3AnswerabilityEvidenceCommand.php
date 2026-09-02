<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AI\Routing\HybridRouter;
use App\Enums\RouteType;
use App\Models\Workspace;
use App\Services\AI\ContextualQueryBuilder;
use App\Services\FAQ\FAQSearch;
use Illuminate\Console\Command;

class AuditStep3AnswerabilityEvidenceCommand extends Command
{
    protected $signature = 'debug:audit-step3-evidence';
    protected $description = 'STEP 3A: Evidence Distribution & Attribution Audit across KB-200 and E2E Multi-turn datasets';

    public function handle(
        FAQSearch $faqSearch,
        HybridRouter $router,
        ContextualQueryBuilder $contextualBuilder,
    ): int {
        $this->info('========================================================================================');
        $this->info('   STEP 3A: EVIDENCE DISTRIBUTION & ANSWERABILITY ATTRIBUTION AUDIT');
        $this->info('========================================================================================');

        $workspace = Workspace::first();
        if (!$workspace) {
            $this->error('No workspace found!');
            return 1;
        }

        // ─────────────────────────────────────────────────────────────────────
        // 1. AUDIT STATIC KB-200 DATASET (200 Queries: 192 In-Domain, 8 OOD)
        // ─────────────────────────────────────────────────────────────────────
        $kbPath = base_path('tests/Datasets/static_kb_200_evaluation_dataset.json');
        $kbData = json_decode((string) file_get_contents($kbPath), true);
        $kbQueries = $kbData['queries'] ?? [];

        $kbAnswerableScores = [];
        $kbAnswerableMargins = [];
        $kbOodScores = [];
        $kbOodMargins = [];
        $kbOodRecords = [];

        $kbLegacyOodFalsePositives = 0;

        foreach ($kbQueries as $item) {
            $q = $item['query'];
            $lang = $item['language'];
            $challenge = $item['challenge'] ?? '';
            $expectedDocs = $item['expected_document_types'] ?? [];
            $isOod = ($challenge === 'ood' || empty($expectedDocs));

            $routing = $router->route($q, null, $workspace->id);
            $hits = $faqSearch->search($q, 3, $workspace->id);

            $topHit = $hits->first();
            $secondHit = $hits->count() > 1 ? $hits->get(1) : null;

            $topScore = $topHit ? (float) $topHit->finalScore : 0.0;
            $secondScore = $secondHit ? (float) $secondHit->finalScore : 0.0;
            $margin = round($topScore - $secondScore, 4);

            $topDocType = $topHit?->faq?->document_type ?? 'NONE';
            $topTitle = $topHit?->faq?->question ?? 'NONE';

            $legacyAnswered = ($topScore >= 0.45);

            if ($isOod) {
                $kbOodScores[] = $topScore;
                $kbOodMargins[] = $margin;
                if ($legacyAnswered) {
                    $kbLegacyOodFalsePositives++;
                }

                $kbOodRecords[] = [
                    'id'            => $item['id'],
                    'lang'          => $lang,
                    'query'         => $q,
                    'route'         => $routing->route->value,
                    'top_doc_type'  => $topDocType,
                    'top_title'     => mb_substr($topTitle, 0, 40),
                    'top_score'     => round($topScore, 4),
                    'second_score'  => round($secondScore, 4),
                    'margin'        => $margin,
                    'legacy_fp'     => $legacyAnswered ? '🔴 FALSE ANSWER' : '🟢 SAFE REJECT',
                ];
            } else {
                $kbAnswerableScores[] = $topScore;
                $kbAnswerableMargins[] = $margin;
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // 2. AUDIT E2E 100-SCENARIO MULTI-TURN DATASET (244 Turns)
        // ─────────────────────────────────────────────────────────────────────
        $e2ePath = base_path('tests/Datasets/e2e_multiturn_100_scenarios_dataset.json');
        $e2eData = json_decode((string) file_get_contents($e2ePath), true);

        $e2eAnswerableScores = [];
        $e2eAnswerableMargins = [];
        $e2eOodScores = [];
        $e2eOodMargins = [];
        $e2eOodRecords = [];
        $e2eAmbiguousScores = [];
        $e2eAmbiguousMargins = [];
        $e2eAmbiguousRecords = [];

        $e2eLegacyOodFalsePositives = 0;

        foreach ($e2eData['scenarios'] as $sc) {
            $lang = $sc['language'];
            $scId = $sc['id'];
            $history = [];

            foreach ($sc['turns'] as $turnIdx => $turn) {
                $q = $turn['user_message'];
                $intent = $turn['intent_type'] ?? 'knowledge';
                $expectedDocs = $turn['expected_document_types'] ?? [];
                $isOod = ($intent === 'ood' || (empty($expectedDocs) && $intent !== 'chat' && $intent !== 'action'));
                $isAmbiguous = ($intent === 'uncertain');

                $ctxSignal = $contextualBuilder->resolveContextualSignal($q, null, $history);
                $routing = $router->route($q, null, $workspace->id);
                $hits = $faqSearch->search($q, 3, $workspace->id, contextualSignal: $ctxSignal);

                $history[] = ['user_message' => $q];

                $topHit = $hits->first();
                $secondHit = $hits->count() > 1 ? $hits->get(1) : null;

                $topScore = $topHit ? (float) $topHit->finalScore : 0.0;
                $secondScore = $secondHit ? (float) $secondHit->finalScore : 0.0;
                $margin = round($topScore - $secondScore, 4);

                $topDocType = $topHit?->faq?->document_type ?? 'NONE';
                $topTitle = $topHit?->faq?->question ?? 'NONE';
                $legacyAnswered = ($topScore >= 0.45);

                if ($intent === 'ood') {
                    $e2eOodScores[] = $topScore;
                    $e2eOodMargins[] = $margin;
                    if ($legacyAnswered) {
                        $e2eLegacyOodFalsePositives++;
                    }

                    $e2eOodRecords[] = [
                        'source'       => "{$scId} #{$turnIdx}",
                        'lang'         => $lang,
                        'query'        => $q,
                        'route'        => $routing->route->value,
                        'top_doc_type' => $topDocType,
                        'top_score'    => round($topScore, 4),
                        'second_score' => round($secondScore, 4),
                        'margin'       => $margin,
                        'legacy_fp'    => $legacyAnswered ? '🔴 FALSE ANSWER' : '🟢 SAFE REJECT',
                    ];
                } elseif ($isAmbiguous) {
                    $e2eAmbiguousScores[] = $topScore;
                    $e2eAmbiguousMargins[] = $margin;
                    $e2eAmbiguousRecords[] = [
                        'source'       => "{$scId} #{$turnIdx}",
                        'lang'         => $lang,
                        'query'        => $q,
                        'route'        => $routing->route->value,
                        'top_doc_type' => $topDocType,
                        'top_score'    => round($topScore, 4),
                        'margin'       => $margin,
                    ];
                } elseif (!empty($expectedDocs)) {
                    $e2eAnswerableScores[] = $topScore;
                    $e2eAnswerableMargins[] = $margin;
                }
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // 3. STATISTICAL DISTRIBUTIONS PRESENTATION
        // ─────────────────────────────────────────────────────────────────────
        $this->info("\n1. SCORE & MARGIN EMPIRICAL DISTRIBUTION SUMMARY:");
        $distRows = [
            $this->buildStatRow('KB-200 Answerable Policies (N=192)', $kbAnswerableScores, $kbAnswerableMargins),
            $this->buildStatRow('KB-200 OOD / Non-Commerce (N=8)', $kbOodScores, $kbOodMargins),
            $this->buildStatRow('E2E Knowledge Turns (N=196)', $e2eAnswerableScores, $e2eAnswerableMargins),
            $this->buildStatRow('E2E OOD Turns (N=8)', $e2eOodScores, $e2eOodMargins),
            $this->buildStatRow('E2E Ambiguous/Uncertain (N=4)', $e2eAmbiguousScores, $e2eAmbiguousMargins),
        ];

        $this->table(
            ['Query Cohort', 'Min Score', 'P10 Score', 'Median Score', 'P90 Score', 'Max Score', 'Mean Score', 'Median Margin'],
            $distRows
        );

        // ─────────────────────────────────────────────────────────────────────
        // 4. CASE-BY-CASE AUDIT OF ALL OOD QUERIES
        // ─────────────────────────────────────────────────────────────────────
        $this->info("\n2. CASE-BY-CASE AUDIT: STATIC KB-200 OOD QUERIES (Legacy 0.45 Threshold Failure Audit):");
        $this->table(
            ['ID', 'Lang', 'User Query', 'Router', 'Top Doc Type', 'Top Hit Question', 'Top Score', 'Second', 'Margin', 'Legacy Status'],
            $kbOodRecords
        );

        $this->info("\n3. CASE-BY-CASE AUDIT: E2E MULTI-TURN OOD QUERIES (Legacy 0.45 Threshold Failure Audit):");
        $this->table(
            ['Source', 'Lang', 'User Query', 'Router', 'Top Doc Type', 'Top Score', 'Second', 'Margin', 'Legacy Status'],
            $e2eOodRecords
        );

        $this->info("\n4. CASE-BY-CASE AUDIT: E2E AMBIGUOUS QUERIES:");
        $this->table(
            ['Source', 'Lang', 'User Query', 'Router', 'Top Doc Type', 'Top Score', 'Margin'],
            $e2eAmbiguousRecords
        );

        // ─────────────────────────────────────────────────────────────────────
        // 5. EXECUTIVE SUMMARY & ATTRIBUTION
        // ─────────────────────────────────────────────────────────────────────
        $this->info("\n5. STEP 3A EXECUTIVE DISCOVERY & ATTRIBUTION:");
        $this->line("• KB-200 OOD False Positive Rate at legacy 0.45: {$kbLegacyOodFalsePositives}/8 (" . round(($kbLegacyOodFalsePositives / 8) * 100, 1) . "%)");
        $this->line("• E2E OOD False Positive Rate at legacy 0.45: {$e2eLegacyOodFalsePositives}/8 (" . round(($e2eLegacyOodFalsePositives / 8) * 100, 1) . "%)");

        return 0;
    }

    /**
     * Helper to compute percentiles and build summary row.
     */
    private function buildStatRow(string $label, array $scores, array $margins): array
    {
        if (empty($scores)) {
            return [$label, 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A'];
        }

        sort($scores);
        sort($margins);
        $n = count($scores);

        $min = round($scores[0], 4);
        $max = round($scores[$n - 1], 4);
        $mean = round(array_sum($scores) / $n, 4);
        $p10 = round($scores[(int) floor($n * 0.10)], 4);
        $median = round($scores[(int) floor($n * 0.50)], 4);
        $p90 = round($scores[(int) min($n - 1, floor($n * 0.90))], 4);

        $marginMedian = !empty($margins) ? round($margins[(int) floor(count($margins) * 0.50)], 4) : 0.0;

        return [
            $label,
            (string) $min,
            (string) $p10,
            (string) $median,
            (string) $p90,
            (string) $max,
            (string) $mean,
            (string) $marginMedian,
        ];
    }
}
