<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use App\Services\Memory\MemoryRelevanceGate;
use Illuminate\Console\Command;

class AuditStep2MultilingualLinguisticCommand extends Command
{
    protected $signature = 'debug:audit-step2-linguistic';
    protected $description = 'Comprehensive STEP 2 Multilingual, Banglish, Typo & Morphological Linguistic Baseline Audit';

    public function handle(FAQSearch $faqSearch, MemoryRelevanceGate $gate, HybridRouter $router): int
    {
        $this->info('========================================================================================');
        $this->info('   STEP 2 MULTILINGUAL LINGUISTIC BASELINE & SCIENTIFIC FAILURE ATTRIBUTION AUDIT');
        $this->info('========================================================================================');

        $workspace = Workspace::first();
        $conv = new Conversation(['external_user_id' => 'u_test_audit']);

        // ─────────────────────────────────────────────────────────────────────
        // 1. INVARIANT VERIFICATION (D1 Memory Gate + D2 Router)
        // ─────────────────────────────────────────────────────────────────────
        $e2ePath = base_path('tests/Datasets/e2e_multiturn_100_scenarios_dataset.json');
        $e2eData = json_decode((string) file_get_contents($e2ePath), true);

        $staticTotal = 0; $staticSkipped = 0;
        $personalTotal = 0; $personalUsed = 0;
        $routingTotal = 0; $routingCorrect = 0;

        foreach ($e2eData['scenarios'] as $sc) {
            foreach ($sc['turns'] as $t) {
                $q = $t['user_message'];
                $expMem = $t['expected_memory_action'];
                $actMem = $gate->shouldRetrieve($q, $conv) ? 'USE' : 'SKIP';

                if ($expMem === 'SKIP') {
                    $staticTotal++;
                    if ($actMem === 'SKIP') $staticSkipped++;
                } else {
                    $personalTotal++;
                    if ($actMem === 'USE') $personalUsed++;
                }

                $routingTotal++;
                $intent = $t['intent_type'];
                $r = $router->route($q, $conv, $workspace->id);
                $ok = ($intent === 'ood' && $r->route === RouteType::OOD) ||
                      ($intent === 'uncertain' && ($r->route === RouteType::UNCERTAIN || $r->route === RouteType::KNOWLEDGE)) ||
                      ($intent === 'chat' && ($r->route === RouteType::CHAT || $r->route === RouteType::ACTION)) ||
                      ($intent === 'action' && $r->route === RouteType::ACTION) ||
                      ($intent === 'knowledge' && ($r->route === RouteType::KNOWLEDGE || $r->route === RouteType::CHAT));
                if ($ok) $routingCorrect++;
            }
        }

        $this->info("\n1. CORE INVARIANTS STATUS (Must remain 100% Frozen):");
        $this->table(
            ['Invariant Dimension', 'Expected', 'Measured', 'Status'],
            [
                ['D1 Static Policy Memory Isolation', '100.0%', round(($staticSkipped / $staticTotal) * 100, 1) . "% ({$staticSkipped}/{$staticTotal})", $staticSkipped === $staticTotal ? '🟢 PASS' : '🔴 REGRESSION'],
                ['D1 Personal Memory Recall Gate', '100.0%', round(($personalUsed / $personalTotal) * 100, 1) . "% ({$personalUsed}/{$personalTotal})", $personalUsed === $personalTotal ? '🟢 PASS' : '🔴 REGRESSION'],
                ['D2 HybridRouter Accuracy', '100.0%', round(($routingCorrect / $routingTotal) * 100, 1) . "% ({$routingCorrect}/{$routingTotal})", $routingCorrect === $routingTotal ? '🟢 PASS' : '🔴 REGRESSION'],
            ]
        );

        // ─────────────────────────────────────────────────────────────────────
        // 2. E2E MULTI-TURN RETRIEVAL PERFORMANCE (Across 4 Language Dimensions)
        // ─────────────────────────────────────────────────────────────────────
        $langStats = [
            'en'         => ['name' => 'English', 'total' => 0, 'top1' => 0, 'top3' => 0, 'failures' => []],
            'bn'         => ['name' => 'Native Bangla', 'total' => 0, 'top1' => 0, 'top3' => 0, 'failures' => []],
            'banglish'   => ['name' => 'Banglish (Phonetic/SMS)', 'total' => 0, 'top1' => 0, 'top3' => 0, 'failures' => []],
            'code_mixed' => ['name' => 'Code-Mixed (BN + EN)', 'total' => 0, 'top1' => 0, 'top3' => 0, 'failures' => []],
        ];

        $contextualBuilder = app(\App\Services\AI\ContextualQueryBuilder::class);

        foreach ($e2eData['scenarios'] as $sc) {
            $lang = $sc['language'];
            $scId = $sc['id'];
            $scenarioHistory = [];

            foreach ($sc['turns'] as $turnIdx => $turn) {
                $expected = $turn['expected_document_types'];
                $q = $turn['user_message'];

                if (empty($expected)) {
                    $scenarioHistory[] = ['user_message' => $q];
                    continue; // Skip personal / general turns without static KB targets
                }

                $langStats[$lang]['total']++;
                $ctxSignal = $contextualBuilder->resolveContextualSignal($q, null, $scenarioHistory);
                $hits = $faqSearch->search(
                    query: $q,
                    perPage: 3,
                    workspaceId: $workspace->id,
                    contextualSignal: $ctxSignal,
                );

                $scenarioHistory[] = ['user_message' => $q];

                $top1 = $hits->first();
                $top1Doc = $top1?->faq?->document_type;
                $top3Docs = $hits->map(fn($h) => $h->faq->document_type)->toArray();

                $isTop1 = $top1Doc && in_array($top1Doc, $expected, true);
                $isTop3 = !empty(array_intersect($top3Docs, $expected));

                if ($isTop1) $langStats[$lang]['top1']++;
                if ($isTop3) $langStats[$lang]['top3']++;

                if (!$isTop1) {
                    $langStats[$lang]['failures'][] = [
                        'source'    => "E2E {$scId} Turn #{$turnIdx}",
                        'query'     => $q,
                        'expected'  => implode('|', $expected),
                        'actual'    => $top1Doc ?? 'NONE',
                        'score'     => round($top1?->finalScore ?? 0.0, 4),
                        'in_top3'   => $isTop3,
                        'lang'      => $lang,
                    ];
                }
            }
        }

        $this->info("\n2. E2E MULTI-TURN RETRIEVAL BASELINE (100 Scenarios / 196 Knowledge Turns):");
        $e2eRows = [];
        $totalE2ETurns = 0; $totalE2ETop1 = 0; $totalE2ETop3 = 0;

        foreach ($langStats as $lCode => $data) {
            $totalE2ETurns += $data['total'];
            $totalE2ETop1 += $data['top1'];
            $totalE2ETop3 += $data['top3'];
            $p1 = round(($data['top1'] / max(1, $data['total'])) * 100, 1);
            $p3 = round(($data['top3'] / max(1, $data['total'])) * 100, 1);
            $failCount = count($data['failures']);

            $e2eRows[] = [
                $data['name'],
                $data['total'] . ' turns',
                "{$p1}% ({$data['top1']}/{$data['total']})",
                "{$p3}% ({$data['top3']}/{$data['total']})",
                "{$failCount} turns",
                $p1 >= 75.0 ? '🟢 STRONG' : ($p1 >= 65.0 ? '🟡 MODERATE' : '🔴 WEAK'),
            ];
        }

        $overallE2ETop1 = round(($totalE2ETop1 / max(1, $totalE2ETurns)) * 100, 1);
        $overallE2ETop3 = round(($totalE2ETop3 / max(1, $totalE2ETurns)) * 100, 1);
        $e2eRows[] = [
            'OVERALL E2E',
            "{$totalE2ETurns} turns",
            "{$overallE2ETop1}% ({$totalE2ETop1}/{$totalE2ETurns})",
            "{$overallE2ETop3}% ({$totalE2ETop3}/{$totalE2ETurns})",
            ($totalE2ETurns - $totalE2ETop1) . ' turns',
            $overallE2ETop1 >= 70.0 ? '🟢 SOLID' : '🟡 NEEDS WORK',
        ];
        $this->table(['Language Dimension', 'Sample Size', 'Top-1 Accuracy', 'Top-3 Accuracy', 'Failures', 'Assessment'], $e2eRows);

        // ─────────────────────────────────────────────────────────────────────
        // 3. STATIC KB-200 BENCHMARK (50 Queries Each Language)
        // ─────────────────────────────────────────────────────────────────────
        $kb200Path = base_path('tests/Datasets/static_kb_200_evaluation_dataset.json');
        $kb200Data = json_decode((string) file_get_contents($kb200Path), true);

        $kbStats = [
            'en'         => ['name' => 'English', 'total' => 0, 'top1' => 0, 'top3' => 0, 'failures' => []],
            'bn'         => ['name' => 'Native Bangla', 'total' => 0, 'top1' => 0, 'top3' => 0, 'failures' => []],
            'banglish'   => ['name' => 'Banglish (Phonetic/SMS)', 'total' => 0, 'top1' => 0, 'top3' => 0, 'failures' => []],
            'code_mixed' => ['name' => 'Code-Mixed (BN + EN)', 'total' => 0, 'top1' => 0, 'top3' => 0, 'failures' => []],
        ];

        foreach ($kb200Data['queries'] as $item) {
            $lang = $item['language'];
            $qId = $item['id'];
            $q = $item['query'];
            $expected = $item['expected_document_types'];

            $kbStats[$lang]['total']++;
            $hits = $faqSearch->search($q, 3, $workspace->id);

            $top1 = $hits->first();
            $top1Doc = $top1?->faq?->document_type;
            $top3Docs = $hits->map(fn($h) => $h->faq->document_type)->toArray();

            $isTop1 = $top1Doc && in_array($top1Doc, $expected, true);
            $isTop3 = !empty(array_intersect($top3Docs, $expected));

            if ($isTop1) $kbStats[$lang]['top1']++;
            if ($isTop3) $kbStats[$lang]['top3']++;

            if (!$isTop1) {
                $kbStats[$lang]['failures'][] = [
                    'source'    => "KB-200 #{$qId}",
                    'query'     => $q,
                    'expected'  => implode('|', $expected),
                    'actual'    => $top1Doc ?? 'NONE',
                    'score'     => round($top1?->finalScore ?? 0.0, 4),
                    'in_top3'   => $isTop3,
                    'lang'      => $lang,
                ];
            }
        }

        $this->info("\n3. STATIC KB-200 RETRIEVAL BASELINE (200 Queries / 50 Per Language):");
        $kbRows = [];
        $totalKB = 0; $totalKBTop1 = 0; $totalKBTop3 = 0;

        foreach ($kbStats as $lCode => $data) {
            $totalKB += $data['total'];
            $totalKBTop1 += $data['top1'];
            $totalKBTop3 += $data['top3'];
            $p1 = round(($data['top1'] / max(1, $data['total'])) * 100, 1);
            $p3 = round(($data['top3'] / max(1, $data['total'])) * 100, 1);
            $failCount = count($data['failures']);

            $kbRows[] = [
                $data['name'],
                $data['total'] . ' queries',
                "{$p1}% ({$data['top1']}/{$data['total']})",
                "{$p3}% ({$data['top3']}/{$data['total']})",
                "{$failCount} queries",
                $p1 >= 85.0 ? '🟢 EXCELLENT' : ($p1 >= 75.0 ? '🟡 GOOD' : '🔴 WEAK'),
            ];
        }

        $overallKBTop1 = round(($totalKBTop1 / max(1, $totalKB)) * 100, 1);
        $overallKBTop3 = round(($totalKBTop3 / max(1, $totalKB)) * 100, 1);
        $kbRows[] = [
            'OVERALL KB-200',
            "{$totalKB} queries",
            "{$overallKBTop1}% ({$totalKBTop1}/{$totalKB})",
            "{$overallKBTop3}% ({$totalKBTop3}/{$totalKB})",
            ($totalKB - $totalKBTop1) . ' queries',
            $overallKBTop1 >= 85.0 ? '🟢 EXCELLENT' : '🟡 SOLID',
        ];
        $this->table(['Language Dimension', 'Sample Size', 'Top-1 Accuracy', 'Top-3 Accuracy', 'Failures', 'Assessment'], $kbRows);

        // ─────────────────────────────────────────────────────────────────────
        // 4. LINGUISTIC PHENOMENON FAILURE ATTRIBUTION (Consolidated Failures)
        // ─────────────────────────────────────────────────────────────────────
        $allFailures = array_merge(
            $langStats['en']['failures'],
            $langStats['bn']['failures'],
            $langStats['banglish']['failures'],
            $langStats['code_mixed']['failures'],
            $kbStats['en']['failures'],
            $kbStats['bn']['failures'],
            $kbStats['banglish']['failures'],
            $kbStats['code_mixed']['failures']
        );

        $categories = [
            'L1_banglish_cross_script' => ['count' => 0, 'desc' => 'Banglish phonetic/SMS terms missing canonical concept mapping (e.g. koto din lagbe, refund pabo)'],
            'L2_bangla_morphology'     => ['count' => 0, 'desc' => 'Bangla case inflection / verb morphology mismatch (e.g. ডেলিভারিতে, ফেরতযোগ্য, পরিবর্তন করা)'],
            'L3_retail_typo_spelling'  => ['count' => 0, 'desc' => 'Common phonetic retail typos (e.g. রিটারন vs রিটার্ন, পেমেনট vs পেমেন্ট, কুরিয়ার)'],
            'L4_colloquial_phrasing'   => ['count' => 0, 'desc' => 'Colloquial / F-Commerce informal expressions (e.g. পার্সেল কই, টাকা ফেরত পাব?, কত দিনে আসবে)'],
            'L5_code_mixed_lexical'    => ['count' => 0, 'desc' => 'Code-mixed English noun + Bengali verb/particles blend (e.g. same day delivery ache?)'],
            'L6_ood_or_unanswerable'   => ['count' => 0, 'desc' => 'Out-of-domain or unanswerable query falsely retrieved against commerce policies'],
            'L7_context_or_anaphora'   => ['count' => 0, 'desc' => 'Multi-turn ellipsis / anaphora pronoun without antecedent resolution'],
        ];

        $classifiedFailures = [];

        foreach ($allFailures as $fail) {
            $q = $fail['query'];
            $qLower = mb_strtolower($q);
            $lang = $fail['lang'];

            // Classification heuristic
            if ($lang === 'banglish' || preg_match('/\b(koto|lagbe|kivabe|pabo|kora|jabe|ache|hobe|chai|korbo|kori)\b/i', $qLower)) {
                $cat = 'L1_banglish_cross_script';
            } elseif (str_contains($qLower, 'রিটারন') || str_contains($qLower, 'পেমেনট') || str_contains($qLower, 'ডেলিভারী')) {
                $cat = 'L3_retail_typo_spelling';
            } elseif (str_contains($qLower, 'কই') || str_contains($qLower, 'কেমনে') || str_contains($qLower, 'পাব?') || str_contains($qLower, 'আসবে?')) {
                $cat = 'L4_colloquial_phrasing';
            } elseif (preg_match('/(তে|র|এর|টা|গুলো|পাতে|যোগ্য|যোগ্যতা)\b/u', $qLower) && $lang === 'bn') {
                $cat = 'L2_bangla_morphology';
            } elseif ($lang === 'code_mixed') {
                $cat = 'L5_code_mixed_lexical';
            } elseif (str_contains($qLower, 'বাস') || str_contains($qLower, 'আবহাওয়া') || str_contains($qLower, 'বৃষ্টি')) {
                $cat = 'L6_ood_or_unanswerable';
            } else {
                $cat = 'L7_context_or_anaphora';
            }

            $categories[$cat]['count']++;
            $classifiedFailures[] = array_merge($fail, ['category' => $cat]);
        }

        $this->info("\n4. STEP 2 LINGUISTIC FAILURE ATTRIBUTION MATRIX (Total Unique Failure Turns: " . count($allFailures) . "):");
        $catRows = [];
        $totalFails = count($allFailures);

        foreach ($categories as $catKey => $info) {
            $pct = round(($info['count'] / max(1, $totalFails)) * 100, 1);
            $catRows[] = [
                $catKey,
                "{$info['count']} queries",
                "{$pct}%",
                $info['desc'],
            ];
        }
        $this->table(['Linguistic Failure Class', 'Failure Count', 'Percentage', 'Impact Description'], $catRows);

        // ─────────────────────────────────────────────────────────────────────
        // 5. SAMPLE DETAILED INVENTORY
        // ─────────────────────────────────────────────────────────────────────
        $this->info("\n5. TOP SAMPLE LINGUISTIC FAILURES (Detailed Diagnostics):");
        $sampleRows = [];
        foreach (array_slice($classifiedFailures, 0, 15) as $f) {
            $sampleRows[] = [
                $f['source'],
                mb_substr($f['query'], 0, 45) . (mb_strlen($f['query']) > 45 ? '...' : ''),
                $f['expected'],
                $f['actual'],
                $f['category'],
            ];
        }
        $this->table(['Source', 'Query', 'Expected', 'Actual Top-1', 'Linguistic Category'], $sampleRows);

        return 0;
    }
}
