<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use App\Services\Memory\MemoryRelevanceGate;
use Illuminate\Console\Command;

class DiagnoseFailuresCommand extends Command
{
    protected $signature = 'debug:diagnose-failures';
    protected $description = 'Perform deep scientific failure attribution across routing, retrieval, memory gate, and ranking';

    public function handle(
        HybridRouter $router,
        FAQSearch $faqSearch,
        MemoryRelevanceGate $gate
    ): int {
        $this->info('===============================================================================');
        $this->info('   PHASE D1: SCIENTIFIC FAILURE ATTRIBUTION & ROOT CAUSE AUDIT');
        $this->info('===============================================================================');

        $datasetPath = base_path('tests/Datasets/e2e_multiturn_100_scenarios_dataset.json');
        $data = json_decode((string) file_get_contents($datasetPath), true);
        $scenarios = $data['scenarios'] ?? [];

        $workspace = Workspace::first();
        $conversation = new Conversation(['external_user_id' => 'u_diagnose']);

        $routingFailures = [];
        $banglaRetrievalFailures = [];
        $rankingGapFailures = []; // in top 3 but not #1
        $memoryGateFalsePositives = []; // static turn where gate returned USE

        foreach ($scenarios as $sc) {
            $lang = $sc['language'];
            $scId = $sc['id'];

            foreach ($sc['turns'] as $turn) {
                $turnId = $turn['turn_id'];
                $q = $turn['user_message'];
                $intent = $turn['intent_type'];
                $expectedGate = $turn['expected_memory_action'];
                $expectedDocs = $turn['expected_document_types'];

                // 1. Audit Routing
                $routing = $router->route($q, $conversation, $workspace->id);
                $isRouteCorrect = ($intent === 'ood' && $routing->route === RouteType::OOD) ||
                                  ($intent === 'uncertain' && ($routing->route === RouteType::UNCERTAIN || $routing->route === RouteType::KNOWLEDGE)) ||
                                  ($intent === 'chat' && ($routing->route === RouteType::CHAT || $routing->route === RouteType::ACTION)) ||
                                  ($intent === 'action' && $routing->route === RouteType::ACTION) ||
                                  ($intent === 'knowledge' && ($routing->route === RouteType::KNOWLEDGE || $routing->route === RouteType::CHAT));

                if (!$isRouteCorrect) {
                    $routingFailures[] = [
                        'turn_id' => $turnId,
                        'lang' => $lang,
                        'query' => $q,
                        'expected_intent' => $intent,
                        'actual_route' => $routing->route->value,
                        'confidence' => $routing->confidence,
                        'intent' => $routing->intent,
                    ];
                }

                // 2. Audit Memory Gate
                $actualGate = $gate->shouldRetrieve($q, $conversation) ? 'USE' : 'SKIP';
                if ($expectedGate === 'SKIP' && $actualGate === 'USE') {
                    $memoryGateFalsePositives[] = [
                        'turn_id' => $turnId,
                        'lang' => $lang,
                        'query' => $q,
                        'expected' => 'SKIP',
                        'actual' => 'USE',
                    ];
                }

                // 3. Audit Retrieval
                if (!empty($expectedDocs)) {
                    $hits = $faqSearch->search($q, 3, $workspace->id);
                    $topDoc = $hits->first()?->faq?->document_type;
                    $top3Docs = $hits->map(fn($h) => $h->faq->document_type)->toArray();

                    $inTop1 = $topDoc && in_array($topDoc, $expectedDocs, true);
                    $inTop3 = !empty(array_intersect($top3Docs, $expectedDocs));

                    if (!$inTop1 && $inTop3) {
                        $rankingGapFailures[] = [
                            'turn_id' => $turnId,
                            'lang' => $lang,
                            'query' => $q,
                            'expected' => implode(', ', $expectedDocs),
                            'top1_actual' => $topDoc,
                            'top1_score' => $hits->first()?->finalScore,
                            'top1_title' => mb_substr($hits->first()?->faq?->question ?? '', 0, 45),
                            'correct_rank' => array_search(array_values(array_intersect($top3Docs, $expectedDocs))[0], $top3Docs) + 1,
                        ];
                    }

                    if ($lang === 'bn' && !$inTop1) {
                        $banglaRetrievalFailures[] = [
                            'turn_id' => $turnId,
                            'query' => $q,
                            'expected' => implode(', ', $expectedDocs),
                            'top1_actual' => $topDoc ?? 'none',
                            'in_top3' => $inTop3 ? 'YES' : 'NO',
                            'top1_score' => $hits->first()?->finalScore ?? 0,
                            'top1_title' => mb_substr($hits->first()?->faq?->question ?? '', 0, 45),
                        ];
                    }
                }
            }
        }

        // Print Summary Tables
        $this->error(PHP_EOL . '1. ROUTING FAILURES: ' . count($routingFailures) . ' / 244 turns');
        $this->table(['Turn ID', 'Lang', 'Query', 'Expected Intent', 'Actual Route', 'Matched Intent'], array_slice($routingFailures, 0, 15));

        $this->error(PHP_EOL . '2. MEMORY GATE FALSE POSITIVES (Static Turns where Gate Triggered USE): ' . count($memoryGateFalsePositives) . ' turns');
        $this->table(['Turn ID', 'Lang', 'Query', 'Expected', 'Actual'], array_slice($memoryGateFalsePositives, 0, 15));

        $this->warn(PHP_EOL . '3. RANKING GAP FAILURES (Correct in Top-3, but Lost #1 Rank): ' . count($rankingGapFailures) . ' turns');
        $this->table(['Turn ID', 'Lang', 'Query', 'Expected', 'Actual #1', 'Rank of Correct', 'Actual #1 Doc Title'], array_slice($rankingGapFailures, 0, 15));

        $this->error(PHP_EOL . '4. NATIVE BANGLA RETRIEVAL FAILURES: ' . count($banglaRetrievalFailures) . ' turns');
        $this->table(['Turn ID', 'Query', 'Expected', 'Actual #1', 'In Top-3?', 'Score', 'Actual #1 Doc Title'], array_slice($banglaRetrievalFailures, 0, 15));

        return 0;
    }
}
