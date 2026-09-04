<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\AI\Evaluation\GenerationFaithfulnessEvaluator;
use App\Services\FAQ\FAQSearch;
use Illuminate\Console\Command;

class AuditStep4GenerationFaithfulnessCommand extends Command
{
    protected $signature = 'debug:audit-step4-generation
                            {--limit= : Maximum number of scenarios to evaluate (default: all)}
                            {--scenario= : Specific scenario ID to evaluate (e.g. EN_SC_01)}
                            {--lang= : Filter by language (en, bn, banglish, code_mixed)}
                            {--export : Export full audit findings to JSON report}';

    protected $description = 'STEP 4: E2E Generation Baseline & Faithfulness Attribution Audit across 100 Multi-turn Scenarios';

    public function handle(
        CustomerSupportService $supportService,
        FAQSearch $faqSearch,
        HybridRouter $router,
        GenerationFaithfulnessEvaluator $evaluator,
    ): int {
        $this->info('========================================================================================');
        $this->info('   STEP 4: E2E GENERATION BASELINE & FAITHFULNESS ATTRIBUTION AUDIT');
        $this->info('========================================================================================');

        $datasetPath = base_path('tests/Datasets/e2e_multiturn_100_scenarios_dataset.json');
        if (!file_exists($datasetPath)) {
            $this->error("Dataset not found at: {$datasetPath}");
            return 1;
        }

        $dataset = json_decode((string) file_get_contents($datasetPath), true);
        $scenarios = $dataset['scenarios'] ?? [];

        // Apply filters
        if ($scenarioId = $this->option('scenario')) {
            $scenarios = array_filter($scenarios, fn ($s) => $s['id'] === $scenarioId);
        }

        if ($langFilter = $this->option('lang')) {
            $scenarios = array_filter($scenarios, fn ($s) => $s['language'] === $langFilter);
        }

        if ($limit = $this->option('limit')) {
            $limitInt = (int) $limit;
            if (!$scenarioId && !$langFilter && $limitInt < count($scenarios)) {
                $perLang = (int) max(1, ceil($limitInt / 4));
                $grouped = [];
                foreach ($scenarios as $s) {
                    $grouped[$s['language']][] = $s;
                }
                $balanced = [];
                foreach ($grouped as $lScenarios) {
                    $balanced = array_merge($balanced, array_slice($lScenarios, 0, $perLang));
                }
                $scenarios = array_slice($balanced, 0, $limitInt);
            } else {
                $scenarios = array_slice($scenarios, 0, $limitInt);
            }
        }

        $scenarios = array_values($scenarios);
        $this->info("Evaluating " . count($scenarios) . " scenarios..." . PHP_EOL);

        $workspace = Workspace::first() ?? Workspace::create([
            'name' => 'Step 4 Benchmark Workspace',
            'slug' => 'step4-bench-workspace',
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Widget', 'driver' => 'web', 'is_active' => true]
        );

        $channelAccount = ChannelAccount::firstOrCreate(
            ['workspace_id' => $workspace->id, 'channel_id' => $channel->id],
            [
                'name'         => 'Step 4 Benchmark Channel',
                'external_id'  => 'step4_bench_channel',
                'access_token' => 'step4_token',
                'is_active'    => true,
            ]
        );

        $totalTurns = 0;
        $groundedTurns = 0;
        $faithfulTurns = 0;
        $unsupportedTurns = 0;
        $contradictionTurns = 0;
        $oodTotalTurns = 0;
        $oodSafeAbstainedTurns = 0;
        $ambiguousTotalTurns = 0;
        $ambiguousClarifiedTurns = 0;

        $totalClaimsSum = 0;
        $supportedClaimsSum = 0;

        $latencies = [];

        $langStats = [
            'en'         => ['turns' => 0, 'grounded' => 0, 'faithful' => 0, 'unsupported' => 0, 'supported_claims' => 0, 'total_claims' => 0],
            'bn'         => ['turns' => 0, 'grounded' => 0, 'faithful' => 0, 'unsupported' => 0, 'supported_claims' => 0, 'total_claims' => 0],
            'banglish'   => ['turns' => 0, 'grounded' => 0, 'faithful' => 0, 'unsupported' => 0, 'supported_claims' => 0, 'total_claims' => 0],
            'code_mixed' => ['turns' => 0, 'grounded' => 0, 'faithful' => 0, 'unsupported' => 0, 'supported_claims' => 0, 'total_claims' => 0],
        ];

        $detailedLogs = [];
        $unsupportedSamples = [];

        $bar = $this->output->createProgressBar(count($scenarios));
        $bar->start();

        foreach ($scenarios as $sc) {
            $lang = $sc['language'];
            $extUserId = 'step4_' . $sc['id'];

            $conversation = Conversation::firstOrCreate(
                ['channel_account_id' => $channelAccount->id, 'external_user_id' => $extUserId],
                ['status' => 'open', 'last_direction' => 'inbound']
            );
            $conversation->messages()->delete();

            foreach ($sc['turns'] as $turnIdx => $turn) {
                $totalTurns++;
                $langStats[$lang]['turns']++;

                $query = $turn['user_message'];
                $intentType = $turn['intent_type'] ?? 'knowledge';
                $expectedDocs = $turn['expected_document_types'] ?? [];

                $t0 = microtime(true);

                // Run real E2E pipeline
                $result = $supportService->handleQuery(
                    query: $query,
                    workspaceId: $workspace->id,
                    conversation: $conversation,
                );

                $elapsedMs = round((microtime(true) - $t0) * 1000, 2);
                $latencies[] = $elapsedMs;

                $replyText = $result['reply'] ?? '';
                $retrievalHits = $result['retrieval_hits'];
                $decision = null;
                $groundedHits = new \Illuminate\Database\Eloquent\Collection();

                if (!empty($result['answerability_decision'])) {
                    $statusVal = $result['answerability_decision']['status'];
                    $status = \App\Services\AI\Enums\AnswerabilityStatus::from($statusVal);
                    $groundedHits = $retrievalHits->filter(fn ($h) => $h->finalScore >= 0.45);
                    $decision = new \App\Services\AI\DTOs\AnswerabilityDecision(
                        status: $status,
                        groundedHits: $groundedHits,
                        confidenceScore: $result['answerability_decision']['confidence_score'] ?? 0.0,
                        reasons: $result['answerability_decision']['reasons'] ?? [],
                    );
                }

                // Evaluate generation faithfulness
                $eval = $evaluator->evaluateTurn(
                    query: $query,
                    generatedReply: $replyText,
                    groundedHits: $groundedHits,
                    decision: $decision,
                    intentType: $intentType,
                    expectedDocTypes: $expectedDocs,
                );

                // Inbound message tracking
                $conversation->messages()->create([
                    'body'        => $query,
                    'direction'   => 'inbound',
                    'type'        => 'text',
                    'sender_type' => 'user',
                ]);

                // Outbound reply tracking
                $conversation->messages()->create([
                    'body'        => $replyText,
                    'direction'   => 'outbound',
                    'type'        => 'text',
                    'sender_type' => 'agent',
                ]);

                // Metrics accumulation
                if ($eval['is_grounded']) {
                    $groundedTurns++;
                    $langStats[$lang]['grounded']++;
                }

                if ($eval['is_faithful']) {
                    $faithfulTurns++;
                    $langStats[$lang]['faithful']++;
                }

                if ($eval['has_unsupported_claims']) {
                    $unsupportedTurns++;
                    $langStats[$lang]['unsupported']++;
                    $unsupportedSamples[] = [
                        'scenario'     => "{$sc['id']} #{$turnIdx}",
                        'lang'         => $lang,
                        'query'        => $query,
                        'reply'        => mb_substr($replyText, 0, 150) . '...',
                        'unsupported'  => $eval['unsupported_numbers'],
                        'external'     => $eval['external_entities'],
                    ];
                }

                if ($eval['has_policy_contradiction']) {
                    $contradictionTurns++;
                }

                if ($intentType === 'ood') {
                    $oodTotalTurns++;
                    if ($eval['is_safe_abstained']) {
                        $oodSafeAbstainedTurns++;
                    }
                }

                if ($intentType === 'uncertain') {
                    $ambiguousTotalTurns++;
                    if ($eval['is_ambiguous_clarified']) {
                        $ambiguousClarifiedTurns++;
                    }
                }

                $totalClaimsSum += $eval['total_claims_evaluated'];
                $supportedClaimsSum += $eval['supported_claims_count'];
                $langStats[$lang]['total_claims'] += $eval['total_claims_evaluated'];
                $langStats[$lang]['supported_claims'] += $eval['supported_claims_count'];

                $detailedLogs[] = [
                    'scenario_id' => $sc['id'],
                    'turn_index'  => $turnIdx,
                    'lang'        => $lang,
                    'query'       => $query,
                    'intent'      => $intentType,
                    'status'      => $decision?->status->value ?? 'chat_or_action',
                    'latency_ms'  => $elapsedMs,
                    'eval'        => $eval,
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line(PHP_EOL);

        // Calculate statistics
        sort($latencies);
        $p50Index = (int) floor(count($latencies) * 0.50);
        $p95Index = (int) floor(count($latencies) * 0.95);
        $p50Latency = $latencies[$p50Index] ?? 0.0;
        $p95Latency = $latencies[$p95Index] ?? 0.0;

        $groundedRate = round(($groundedTurns / max(1, $totalTurns)) * 100, 1);
        $faithfulRate = round(($faithfulTurns / max(1, $totalTurns)) * 100, 1);
        $unsupportedRate = round(($unsupportedTurns / max(1, $totalTurns)) * 100, 1);
        $contradictionRate = round(($contradictionTurns / max(1, $totalTurns)) * 100, 1);
        $oodAbstainRate = $oodTotalTurns > 0 ? round(($oodSafeAbstainedTurns / $oodTotalTurns) * 100, 1) : 100.0;
        $ambiguousClarifyRate = $ambiguousTotalTurns > 0 ? round(($ambiguousClarifiedTurns / $ambiguousTotalTurns) * 100, 1) : 100.0;
        $evidenceSupportedRatio = round(($supportedClaimsSum / max(1, $totalClaimsSum)) * 100, 1);

        // ─────────────────────────────────────────────────────────────────────
        // 1. EXECUTIVE SCORECARD
        // ─────────────────────────────────────────────────────────────────────
        $this->info('1. STEP 4 EXECUTIVE GENERATION & FAITHFULNESS SCORECARD:');
        $this->table(
            ['Metric Dimension', 'Target Requirement', 'Measured Result', 'Status'],
            [
                ['Groundedness Rate', '>= 95.0%', "{$groundedTurns}/{$totalTurns} ({$groundedRate}%)", $groundedRate >= 95.0 ? '🟢 PASS' : '🟡 MONITOR'],
                ['Faithfulness Rate', '>= 95.0%', "{$faithfulTurns}/{$totalTurns} ({$faithfulRate}%)", $faithfulRate >= 95.0 ? '🟢 PASS' : '🟡 MONITOR'],
                ['Evidence-Supported Claim Ratio', '>= 95.0%', "{$supportedClaimsSum}/{$totalClaimsSum} ({$evidenceSupportedRatio}%)", $evidenceSupportedRatio >= 95.0 ? '🟢 STRONG' : '🟡 ATTENTION'],
                ['Unsupported Claim Rate', '<= 3.0%', "{$unsupportedTurns}/{$totalTurns} ({$unsupportedRate}%)", $unsupportedRate <= 3.0 ? '🟢 EXCELLENT' : '🟡 ATTENTION'],
                ['Policy Contradiction Rate', '0.0%', "{$contradictionTurns}/{$totalTurns} ({$contradictionRate}%)", $contradictionTurns === 0 ? '🟢 ZERO CONTRADICTION' : '🔴 VIOLATION'],
                ['OOD Safe Abstention Rate', '100.0%', "{$oodSafeAbstainedTurns}/{$oodTotalTurns} ({$oodAbstainRate}%)", $oodSafeAbstainedTurns === $oodTotalTurns ? '🟢 100% SAFE' : '🔴 LEAKAGE'],
                ['Ambiguous Clarification Rate', '100.0%', "{$ambiguousClarifiedTurns}/{$ambiguousTotalTurns} ({$ambiguousClarifyRate}%)", $ambiguousClarifiedTurns === $ambiguousTotalTurns ? '🟢 100% CLARIFIED' : '🟡 ATTENTION'],
                ['E2E Latency (P50)', '<= 3,500 ms', "{$p50Latency} ms", $p50Latency <= 3500 ? '🟢 RESPONSIVE' : '🟡 HIGH'],
                ['E2E Latency (P95)', '<= 6,500 ms', "{$p95Latency} ms", $p95Latency <= 6500 ? '🟢 STABLE' : '🟡 HIGH'],
            ]
        );

        // ─────────────────────────────────────────────────────────────────────
        // 2. MULTILINGUAL GENERATION BREAKDOWN
        // ─────────────────────────────────────────────────────────────────────
        $this->info(PHP_EOL . '2. MULTILINGUAL SCRIPT & FIDELITY BREAKDOWN:');
        $langRows = [];
        foreach ($langStats as $langKey => $stats) {
            $t = $stats['turns'];
            $gRate = round(($stats['grounded'] / max(1, $t)) * 100, 1);
            $fRate = round(($stats['faithful'] / max(1, $t)) * 100, 1);
            $cRatio = round(($stats['supported_claims'] / max(1, $stats['total_claims'])) * 100, 1);

            $langRows[] = [
                strtoupper($langKey),
                "{$t} turns",
                "{$stats['grounded']}/{$t} ({$gRate}%)",
                "{$stats['faithful']}/{$t} ({$fRate}%)",
                "{$stats['unsupported']} turns",
                "{$cRatio}%",
            ];
        }

        $this->table(
            ['Language / Script', 'Turns Evaluated', 'Groundedness', 'Faithfulness', 'Unsupported Turns', 'Claim Support Ratio'],
            $langRows
        );

        // ─────────────────────────────────────────────────────────────────────
        // 3. SAMPLE UNSUPPORTED CLAIMS (DIAGNOSTICS)
        // ─────────────────────────────────────────────────────────────────────
        if (!empty($unsupportedSamples)) {
            $this->warn(PHP_EOL . '3. SAMPLE UNSUPPORTED CLAIMS IDENTIFIED (FOR STEP 4A GUARDRAILS):');
            foreach (array_slice($unsupportedSamples, 0, 5) as $sample) {
                $this->line("  • [{$sample['scenario']}] Lang: {$sample['lang']}");
                $this->line("    Query: {$sample['query']}");
                $this->line("    Reply Snippet: {$sample['reply']}");
                if (!empty($sample['unsupported'])) {
                    $this->line("    Unsupported Numbers: " . implode(', ', $sample['unsupported']));
                }
                if (!empty($sample['external'])) {
                    $this->line("    External Entities: " . implode(', ', $sample['external']));
                }
                $this->line('');
            }
        }

        // Export JSON report if requested
        if ($this->option('export')) {
            $exportPath = base_path('tests/Datasets/step4_generation_baseline_report.json');
            file_put_contents($exportPath, json_encode([
                'timestamp'          => date('c'),
                'total_turns'        => $totalTurns,
                'groundedness_rate'  => $groundedRate,
                'faithfulness_rate'  => $faithfulRate,
                'claim_support_ratio'=> $evidenceSupportedRatio,
                'unsupported_rate'   => $unsupportedRate,
                'contradiction_rate' => $contradictionRate,
                'ood_abstention_rate'=> $oodAbstainRate,
                'p50_latency_ms'     => $p50Latency,
                'p95_latency_ms'     => $p95Latency,
                'language_breakdown' => $langStats,
                'detailed_logs'      => $detailedLogs,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Full audit report exported to: {$exportPath}");
        }

        return 0;
    }
}
