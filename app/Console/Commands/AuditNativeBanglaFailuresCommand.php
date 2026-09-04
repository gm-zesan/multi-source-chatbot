<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use Illuminate\Console\Command;

class AuditNativeBanglaFailuresCommand extends Command
{
    protected $signature = 'debug:audit-bangla-failures';
    protected $description = 'Generate exact D3 Native Bangla Failure Baseline & Attribution Matrix';

    public function handle(FAQSearch $faqSearch): int
    {
        $this->info('===============================================================================');
        $this->info('   D3 NATIVE BANGLA FAILURE BASELINE & SCIENTIFIC ATTRIBUTION MATRIX');
        $this->info('===============================================================================');

        $workspace = Workspace::first();

        // 1. Audit E2E Multi-turn Native Bangla turns (25 scenarios, 61 turns)
        $e2ePath = base_path('tests/Datasets/e2e_multiturn_100_scenarios_dataset.json');
        $e2eData = json_decode((string) file_get_contents($e2ePath), true);

        $e2eTotalTurns = 0;
        $e2eKnowledgeTurns = 0;
        $e2eTop1Hits = 0;
        $e2eTop3Hits = 0;
        $e2eFailures = [];

        foreach ($e2eData['scenarios'] as $sc) {
            if ($sc['language'] !== 'bn') {
                continue;
            }

            foreach ($sc['turns'] as $turn) {
                $e2eTotalTurns++;
                $expectedDocs = $turn['expected_document_types'];
                if (empty($expectedDocs)) {
                    continue; // Skip personal / chit-chat / OOD turns
                }

                $e2eKnowledgeTurns++;
                $q = $turn['user_message'];
                $hits = $faqSearch->search($q, 3, $workspace->id);

                $top1 = $hits->first();
                $top1Doc = $top1?->faq?->document_type;
                $top3Docs = $hits->map(fn($h) => $h->faq->document_type)->toArray();

                $inTop1 = $top1Doc && in_array($top1Doc, $expectedDocs, true);
                $inTop3 = !empty(array_intersect($top3Docs, $expectedDocs));

                if ($inTop1) {
                    $e2eTop1Hits++;
                }
                if ($inTop3) {
                    $e2eTop3Hits++;
                }

                if (!$inTop1) {
                    // Attribute the failure
                    $attribution = 'lexicon_gap';
                    $top1Title = $top1?->faq?->question ?? 'NONE';
                    $top1IsLegacySaaS = in_array($top1Title, [
                        'How do I view my invoices?',
                        'What are the API rate limits?',
                        'How do I create an account?',
                        'What do I do after logging in for the first time?',
                        'How do I update my payment method?',
                    ], true);

                    if ($top1IsLegacySaaS) {
                        $attribution = 'legacy_saas_noise';
                    } elseif ($top1Title === 'If I return an item, do I get a cash refund?') {
                        $attribution = 'super_faq_dominance';
                    } elseif ($inTop3) {
                        $attribution = 'ranking_priority_gap';
                    }

                    $e2eFailures[] = [
                        'source'        => 'E2E Turn ' . $turn['turn_id'],
                        'query'         => $q,
                        'expected'      => implode('|', $expectedDocs),
                        'actual_top1'   => $top1Doc ?? 'NONE',
                        'top1_title'    => mb_substr($top1Title, 0, 38),
                        'in_top3'       => $inTop3 ? 'YES (Rank ' . (array_search(array_values(array_intersect($top3Docs, $expectedDocs))[0], $top3Docs) + 1) . ')' : 'NO',
                        'top1_score'    => round($top1?->finalScore ?? 0.0, 4),
                        'attribution'   => $attribution,
                    ];
                }
            }
        }

        // 2. Audit 200-Query Benchmark Native Bangla queries (50 queries)
        $kb200Path = base_path('tests/Datasets/static_kb_200_evaluation_dataset.json');
        $kb200Data = json_decode((string) file_get_contents($kb200Path), true);

        $kb200Total = 0;
        $kb200Top1Hits = 0;
        $kb200Top3Hits = 0;
        $kb200Failures = [];

        foreach ($kb200Data['queries'] as $item) {
            if ($item['language'] !== 'bn') {
                continue;
            }

            $kb200Total++;
            $q = $item['query'];
            $expectedDocs = $item['expected_document_types'] ?? (isset($item['document_type']) ? [$item['document_type']] : []);
            $expectedFaq = $item['faq_question'] ?? null;

            $hits = $faqSearch->search($q, 3, $workspace->id);
            $top1 = $hits->first();
            $top1Doc = $top1?->faq?->document_type;
            $top3Docs = $hits->map(fn($h) => $h->faq->document_type)->toArray();

            $inTop1 = $top1Doc && in_array($top1Doc, $expectedDocs, true);
            $inTop3 = !empty(array_intersect($top3Docs, $expectedDocs));

            if ($inTop1) {
                $kb200Top1Hits++;
            }
            if ($inTop3) {
                $kb200Top3Hits++;
            }

            if (!$inTop1) {
                $top1Title = $top1?->faq?->question ?? 'NONE';
                $attribution = 'lexicon_gap';
                if (in_array($top1Title, [
                    'How do I view my invoices?',
                    'What are the API rate limits?',
                    'How do I create an account?',
                    'What do I do after logging in for the first time?',
                    'How do I update my payment method?',
                ], true)) {
                    $attribution = 'legacy_saas_noise';
                } elseif ($top1Title === 'If I return an item, do I get a cash refund?') {
                    $attribution = 'super_faq_dominance';
                } elseif ($inTop3) {
                    $attribution = 'ranking_priority_gap';
                }

                $kb200Failures[] = [
                    'source'        => 'KB-200 #' . $item['id'],
                    'query'         => $q,
                    'expected'      => implode('|', $expectedDocs),
                    'actual_top1'   => $top1Doc ?? 'NONE',
                    'top1_title'    => mb_substr($top1Title, 0, 38),
                    'in_top3'       => $inTop3 ? 'YES (Rank ' . (array_search(array_values(array_intersect($top3Docs, $expectedDocs))[0], $top3Docs) + 1) . ')' : 'NO',
                    'top1_score'    => round($top1?->finalScore ?? 0.0, 4),
                    'attribution'   => $attribution,
                ];
            }
        }

        // Summary Scores
        $e2eTop1Pct = round(($e2eTop1Hits / max(1, $e2eKnowledgeTurns)) * 100, 1);
        $e2eTop3Pct = round(($e2eTop3Hits / max(1, $e2eKnowledgeTurns)) * 100, 1);
        $kb200Top1Pct = round(($kb200Top1Hits / max(1, $kb200Total)) * 100, 1);
        $kb200Top3Pct = round(($kb200Top3Hits / max(1, $kb200Total)) * 100, 1);

        $this->warn(PHP_EOL . "1. NATIVE BANGLA CURRENT BASELINE METRICS:");
        $this->table(
            ['Dataset / Benchmark', 'Sample Size', 'Top-1 Accuracy', 'Top-3 Accuracy', 'Failures Count'],
            [
                ['E2E Multi-turn (Native Bangla)', "{$e2eKnowledgeTurns} turns", "{$e2eTop1Pct}% ({$e2eTop1Hits}/{$e2eKnowledgeTurns})", "{$e2eTop3Pct}% ({$e2eTop3Hits}/{$e2eKnowledgeTurns})", count($e2eFailures) . ' turns'],
                ['Static KB-200 (Native Bangla)', "{$kb200Total} queries", "{$kb200Top1Pct}% ({$kb200Top1Hits}/{$kb200Total})", "{$kb200Top3Pct}% ({$kb200Top3Hits}/{$kb200Total})", count($kb200Failures) . ' queries'],
            ]
        );

        $allFailures = array_merge($e2eFailures, $kb200Failures);
        $attributionCounts = [];
        foreach ($allFailures as $f) {
            $attributionCounts[$f['attribution']] = ($attributionCounts[$f['attribution']] ?? 0) + 1;
        }

        $this->error(PHP_EOL . "2. FAILURE ATTRIBUTION DISTRIBUTION (Total Failures: " . count($allFailures) . "):");
        $this->table(
            ['Attribution Category', 'Failure Count', 'Percentage', 'Impact Description'],
            [
                ['legacy_saas_noise', $attributionCounts['legacy_saas_noise'] ?? 0, round((($attributionCounts['legacy_saas_noise'] ?? 0) / count($allFailures)) * 100, 1) . '%', 'Outdated SaaS developer FAQ (e.g. invoices, API rate limits) matched over retail policy'],
                ['super_faq_dominance', $attributionCounts['super_faq_dominance'] ?? 0, round((($attributionCounts['super_faq_dominance'] ?? 0) / count($allFailures)) * 100, 1) . '%', 'High-priority cash refund super-FAQ hijacked pure payment or return policy query'],
                ['ranking_priority_gap', $attributionCounts['ranking_priority_gap'] ?? 0, round((($attributionCounts['ranking_priority_gap'] ?? 0) / count($allFailures)) * 100, 1) . '%', 'Correct document retrieved in Rank #2 or #3, but lost #1 to related FAQ'],
                ['lexicon_gap', $attributionCounts['lexicon_gap'] ?? 0, round((($attributionCounts['lexicon_gap'] ?? 0) / count($allFailures)) * 100, 1) . '%', 'Bengali retail vocabulary mismatch (tracking, claim, sizing, warranty morphology)'],
            ]
        );

        $this->info(PHP_EOL . "3. DETAILED FAILURE INVENTORY TABLE (Query-by-Query):");
        $this->table(
            ['Source', 'Query', 'Expected', 'Actual Top-1', 'Top-1 Doc Question', 'In Top-3?', 'Attribution'],
            $allFailures
        );

        return 0;
    }
}
