<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FAQ;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AuditD4ScoreFusionCommand extends Command
{
    protected $signature = 'debug:audit-d4-score-fusion';
    protected $description = 'Generate exact D4 Score Fusion, Reranking & Threshold Baseline Audit for Native Bangla Failures';

    public function handle(): int
    {
        $this->info('========================================================================================');
        $this->info('   PHASE D4: SCORE FUSION, RERANKING & THRESHOLD CALIBRATION BASELINE AUDIT');
        $this->info('========================================================================================');

        $workspace = Workspace::first();
        $workspaceId = $workspace ? $workspace->id : 1;

        // Load FAQs for doc_type lookup
        $allFaqs = FAQ::all()->keyBy('id');

        // 1. Gather all Native Bangla queries from E2E and KB-200
        $e2ePath = base_path('tests/Datasets/e2e_multiturn_100_scenarios_dataset.json');
        $e2eData = json_decode((string) file_get_contents($e2ePath), true);

        $kbPath = base_path('tests/Datasets/static_kb_200_evaluation_dataset.json');
        $kbData = json_decode((string) file_get_contents($kbPath), true);

        $testCases = [];

        // E2E Multi-turn Bangla turns
        foreach ($e2eData['scenarios'] as $sc) {
            if ($sc['language'] !== 'bn') {
                continue;
            }
            foreach ($sc['turns'] as $tIndex => $turn) {
                $expected = $turn['expected_document_types'];
                if (empty($expected)) {
                    continue; // Skip chitchat/action
                }
                $testCases[] = [
                    'source' => "E2E {$turn['turn_id']}",
                    'query'  => $turn['user_message'],
                    'expected_types' => $expected,
                    'is_ood' => false,
                ];
            }
        }

        // KB-200 Bangla queries
        foreach ($kbData['queries'] as $item) {
            if ($item['language'] !== 'bn') {
                continue;
            }
            $expected = $item['expected_document_types'] ?? [];
            if (!empty($item['expected_document_type'])) {
                $expected[] = $item['expected_document_type'];
            }
            $testCases[] = [
                'source' => "KB-200 #{$item['id']}",
                'query'  => $item['query'],
                'expected_types' => array_values(array_unique($expected)),
                'is_ood' => ($item['id'] === 'BN049' || $item['id'] === 'BN050'),
            ];
        }

        $this->info(sprintf('Loaded %d Native Bangla test queries for D4 Audit.', count($testCases)));

        $failures = [];
        $categoryCounts = [
            'A_dense_semantic_weakness'   => 0,
            'B_keyword_bm25_weakness'     => 0,
            'C_score_fusion_imbalance'    => 0,
            'D_reranker_weakness'         => 0,
            'E_threshold_problem'         => 0,
            'F_candidate_gen_failure'     => 0,
            'G_dataset_faq_ambiguity'     => 0,
        ];

        $bar = $this->output->createProgressBar(count($testCases));
        $bar->start();

        foreach ($testCases as $tc) {
            $query = $tc['query'];
            $expectedTypes = $tc['expected_types'];
            $isOod = $tc['is_ood'];

            // Query Python search endpoint with top_k = 10 for deep pool inspection
            $resp = Http::timeout(5)->post('http://127.0.0.1:8001/api/v1/search', [
                'query' => $query,
                'workspace_id' => $workspaceId,
                'top_k' => 10,
            ]);

            if (!$resp->successful()) {
                $bar->advance();
                continue;
            }

            $body = $resp->json();
            $results = $body['results'] ?? [];
            $telemetry = $body['telemetry'] ?? [];

            // Map doc types for all results
            $mappedResults = [];
            foreach ($results as $idx => $r) {
                $faqModel = $allFaqs->get($r['id']);
                $docType = $faqModel ? ($faqModel->document_type ?? 'faq') : ($r['document_type'] ?? 'faq');
                $mappedResults[] = array_merge($r, [
                    'rank' => $idx + 1,
                    'document_type' => $docType,
                ]);
            }

            $top1 = $mappedResults[0] ?? null;
            $top1DocType = $top1['document_type'] ?? 'none';
            $inTop1 = $top1 && in_array($top1DocType, $expectedTypes, true);

            if ($isOod) {
                // For OOD queries, success means top score < threshold (no false positive policy matched)
                // If top score >= 0.55, it is a false positive retrieval
                $inTop1 = ($top1['score'] ?? 0.0) < 0.35;
            }

            if (!$inTop1) {
                // Find where the expected document appears in top 10
                $expectedHit = null;
                $expectedRank = 'Not in Top 10';
                foreach ($mappedResults as $mr) {
                    if (in_array($mr['document_type'], $expectedTypes, true)) {
                        $expectedHit = $mr;
                        $expectedRank = "Rank #{$mr['rank']}";
                        break;
                    }
                }

                // Compute telemetry & deltas
                $top1Score = $top1['score'] ?? 0.0;
                $expectedScore = $expectedHit['score'] ?? 0.0;
                $scoreDelta = $expectedHit ? round($top1Score - $expectedScore, 4) : 1.0;
                $denseScore = $expectedHit['semantic_score'] ?? 0.0;
                $keywordScore = $expectedHit['keyword_score'] ?? 0.0;
                $matchType = $expectedHit['match_type'] ?? 'none';
                $rerankerApplied = $telemetry['reranker_applied'] ?? false;
                $rerankerReason = $telemetry['reranker_reason'] ?? 'none';

                // Threshold classification
                $thresholdDecision = ($top1Score >= 0.55) ? 'Accepted (High Conf)' : (($top1Score >= 0.35) ? 'Accepted (Low Conf)' : 'Rejected (<0.35)');

                // Failure Classification (A to G)
                if ($isOod) {
                    $category = 'G_dataset_faq_ambiguity';
                    $reason = 'OOD out-of-domain query matched commercial policy (threshold too permissive for OOD)';
                } elseif (!$expectedHit) {
                    $category = 'F_candidate_gen_failure';
                    $reason = 'Expected document completely absent from Top-10 hybrid candidate pool';
                } elseif ($expectedHit['rank'] <= 3 && $scoreDelta <= 0.15 && !$rerankerApplied) {
                    $category = 'D_reranker_weakness';
                    $reason = "Expected doc reached {$expectedRank} within close delta ({$scoreDelta}) but reranker lacked tie-break rule";
                } elseif ($expectedHit['rank'] <= 3 && $scoreDelta <= 0.15 && $rerankerApplied) {
                    $category = 'C_score_fusion_imbalance';
                    $reason = "Expected doc in Top-3 ({$expectedRank}) with close delta, but fusion score weights favored competitor";
                } elseif ($denseScore < 0.40 && $keywordScore == 0.0) {
                    $category = 'A_dense_semantic_weakness';
                    $reason = "Dense vector representation was too weak ({$denseScore}) and no keyword matched";
                } elseif ($keywordScore == 0.0 && $denseScore >= 0.40) {
                    $category = 'B_keyword_bm25_weakness';
                    $reason = "Semantic score was decent ({$denseScore}) but zero BM25 lexical token match allowed competitor to win";
                } elseif ($top1Score < 0.35 && $expectedScore < 0.35) {
                    $category = 'E_threshold_problem';
                    $reason = 'Both top-1 and expected scores fell below retrieval acceptance threshold';
                } else {
                    $category = 'C_score_fusion_imbalance';
                    $reason = "Score fusion delta ({$scoreDelta}) favored competitor over target document";
                }

                $categoryCounts[$category]++;

                $failures[] = [
                    'source'             => $tc['source'],
                    'query'              => $query,
                    'expected_types'     => implode('|', $expectedTypes),
                    'correct_rank'       => $expectedRank,
                    'dense_score'        => $denseScore,
                    'keyword_score'      => $keywordScore,
                    'fused_score'        => $expectedScore,
                    'top1_score'         => $top1Score,
                    'top1_doc_type'      => $top1DocType,
                    'top1_question'      => mb_substr($top1['question'] ?? 'NONE', 0, 35),
                    'score_delta'        => $scoreDelta,
                    'reranker_applied'   => $rerankerApplied ? "YES ({$rerankerReason})" : 'NO',
                    'threshold_decision' => $thresholdDecision,
                    'category'           => $category,
                    'root_cause'         => $reason,
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // 2. Summary Breakdown
        $this->info('1. FAILURE CLASSIFICATION DISTRIBUTION (Total Failures: ' . count($failures) . '):');
        $catRows = [
            ['A. Dense/semantic weakness', $categoryCounts['A_dense_semantic_weakness'], round(($categoryCounts['A_dense_semantic_weakness'] / max(1, count($failures))) * 100, 1) . '%', 'Dense vector similarity failed to represent domain nuance'],
            ['B. Keyword/BM25 weakness', $categoryCounts['B_keyword_bm25_weakness'], round(($categoryCounts['B_keyword_bm25_weakness'] / max(1, count($failures))) * 100, 1) . '%', 'Query terms missing from document BM25 token index'],
            ['C. Score-fusion imbalance', $categoryCounts['C_score_fusion_imbalance'], round(($categoryCounts['C_score_fusion_imbalance'] / max(1, count($failures))) * 100, 1) . '%', 'Expected doc had valid signal but rank fusion/weights penalized it'],
            ['D. Reranker weakness', $categoryCounts['D_reranker_weakness'], round(($categoryCounts['D_reranker_weakness'] / max(1, count($failures))) * 100, 1) . '%', 'In Top-3 candidate set with delta <= 0.15, but missing reranker rule'],
            ['E. Threshold problem', $categoryCounts['E_threshold_problem'], round(($categoryCounts['E_threshold_problem'] / max(1, count($failures))) * 100, 1) . '%', 'Scores below threshold or threshold decision inappropriate'],
            ['F. Candidate-generation failure', $categoryCounts['F_candidate_gen_failure'], round(($categoryCounts['F_candidate_gen_failure'] / max(1, count($failures))) * 100, 1) . '%', 'Expected doc missed completely from 10-candidate retrieval pool'],
            ['G. Dataset/FAQ ambiguity', $categoryCounts['G_dataset_faq_ambiguity'], round(($categoryCounts['G_dataset_faq_ambiguity'] / max(1, count($failures))) * 100, 1) . '%', 'Out-of-domain query or dataset annotation ambiguity'],
        ];

        $this->table(['Classification Category', 'Failure Count', 'Percentage', 'Scientific Description'], $catRows);

        // 3. Detailed Per-Query Scientific Audit Table
        $this->newLine();
        $this->info('2. PER-QUERY AUDIT MATRIX (Query-by-Query Evidence):');

        $headers = [
            'Source', 'Query', 'Expected', 'Rank', 'Dense', 'BM25', 'Fused', 'Top1', 'Competitor Top1', 'Delta', 'Rerank', 'Thresh', 'Category'
        ];

        $tableData = [];
        foreach ($failures as $f) {
            $tableData[] = [
                $f['source'],
                mb_substr($f['query'], 0, 32),
                $f['expected_types'],
                $f['correct_rank'],
                $f['dense_score'],
                $f['keyword_score'],
                $f['fused_score'],
                $f['top1_score'],
                mb_substr("[{$f['top1_doc_type']}] {$f['top1_question']}", 0, 24),
                $f['score_delta'],
                $f['reranker_applied'],
                $f['threshold_decision'],
                substr($f['category'], 0, 2),
            ];
        }

        $this->table($headers, $tableData);

        // Output detailed root cause list
        $this->newLine();
        $this->info('3. SCIENTIFIC ATTRIBUTION & ROOT CAUSE ANALYSIS:');
        foreach ($failures as $idx => $f) {
            $num = $idx + 1;
            $this->line("<comment>[#{$num}] {$f['source']}</comment> '{$f['query']}'");
            $this->line("     Expected: {$f['expected_types']} | Actual: [{$f['top1_doc_type']}] {$f['top1_question']}");
            $this->line("     Scores: Dense={$f['dense_score']} | BM25={$f['keyword_score']} | Fused={$f['fused_score']} | Top-1={$f['top1_score']} (Delta={$f['score_delta']})");
            $this->line("     Category: <info>{$f['category']}</info> -> {$f['root_cause']}");
            $this->newLine();
        }

        return Command::SUCCESS;
    }
}
