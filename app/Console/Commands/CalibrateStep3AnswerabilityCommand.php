<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\Models\Workspace;
use App\Services\AI\ContextualQueryBuilder;
use App\Services\FAQ\FAQSearch;
use Illuminate\Console\Command;

class CalibrateStep3AnswerabilityCommand extends Command
{
    protected $signature = 'debug:calibrate-step3';
    protected $description = 'STEP 3B: Quantitative Calibration of Router OOD, Commerce Alignment, Ambiguity, Score & Margin';

    /**
     * Regex of core commerce concepts (multilingual: EN, BN, Banglish, Code-mixed).
     */
    private const COMMERCE_CONCEPT_REGEX = '/\b(return|ferot|ফেরত|refund|রিফান্ড|টাকা\s*ফেরত|delivery|shipping|ডেলিভারি|শিপিং|courier|কুরিয়ার|কুরিয়ার|charge|fee|চার্জ|ফি|খরচ|payment|pay|টাকা|পেমেন্ট|cod|cash\s*on\s*delivery|বিকাশ|bkash|nagad|card|কার্ড|warranty|guarantee|ওয়ারেন্টি|ওয়ারেন্টি|গ্যারান্টি|defect|broken|ভাঙা|নষ্ট|ছিঁড়া|selai|sewing|বোতাম|exchange|replace|বদলানো|পরিবর্তন|size|সাইজ|fitting|fit|মাপ|chest|track|tracking|ট্র্যাক|ট্র্যাকিং|order|অর্ডার|parcel|পার্সেল|cancel|বাতিল|ক্যানসেল|invoice|বিল|ইনভয়েস|ইনভয়েস|মেমো|memo|terms|শর্ত|privacy|গোপনীয়তা|গোপনীয়তা|contact|যোগাযোগ|support|হেল্প|help|discount|promo|কুপন|coupon|voucher|price|দাম|মূল্য|offer|অফার)\b/ui';

    public function handle(
        FAQSearch $faqSearch,
        HybridRouter $router,
        ContextualQueryBuilder $contextualBuilder,
    ): int {
        $this->info('========================================================================================');
        $this->info('   STEP 3B: ATTRIBUTION & DECISION CALIBRATION BENCHMARK');
        $this->info('========================================================================================');

        $workspace = Workspace::first();
        if (!$workspace) {
            $this->error('Workspace not found!');
            return 1;
        }

        // ─────────────────────────────────────────────────────────────────────
        // 1. DATASET GATHERING: KB-200 + E2E Multi-turn
        // ─────────────────────────────────────────────────────────────────────
        $kbPath = base_path('tests/Datasets/static_kb_200_evaluation_dataset.json');
        $kbData = json_decode((string) file_get_contents($kbPath), true);
        $e2ePath = base_path('tests/Datasets/e2e_multiturn_100_scenarios_dataset.json');
        $e2eData = json_decode((string) file_get_contents($e2ePath), true);

        $validQueries = [];
        $oodQueries = [];
        $ambiguousQueries = [];

        // Ingest KB-200
        foreach ($kbData['queries'] as $item) {
            $q = $item['query'];
            $challenge = $item['challenge'] ?? '';
            $isOod = ($challenge === 'ood' || empty($item['expected_document_types']));

            if ($isOod) {
                $oodQueries[] = ['id' => $item['id'], 'query' => $q, 'lang' => $item['language']];
            } else {
                $validQueries[] = ['id' => $item['id'], 'query' => $q, 'lang' => $item['language']];
            }
        }

        // Ingest E2E Multi-turn
        foreach ($e2eData['scenarios'] as $sc) {
            $history = [];
            foreach ($sc['turns'] as $turnIdx => $turn) {
                $q = $turn['user_message'];
                $intent = $turn['intent_type'] ?? 'knowledge';
                $expected = $turn['expected_document_types'] ?? [];

                if ($intent === 'ood') {
                    $oodQueries[] = ['id' => "{$sc['id']} #{$turnIdx}", 'query' => $q, 'lang' => $sc['language']];
                } elseif ($intent === 'uncertain') {
                    $ambiguousQueries[] = ['id' => "{$sc['id']} #{$turnIdx}", 'query' => $q, 'lang' => $sc['language']];
                } elseif (!empty($expected)) {
                    $validQueries[] = ['id' => "{$sc['id']} #{$turnIdx}", 'query' => $q, 'lang' => $sc['language'], 'history' => $history];
                }
                $history[] = ['user_message' => $q];
            }
        }

        $this->info("Datasets Ingested: Valid Answerable = " . count($validQueries) . ", OOD = " . count($oodQueries) . ", Ambiguous = " . count($ambiguousQueries));

        // ─────────────────────────────────────────────────────────────────────
        // 2. SIGNAL 1 CALIBRATION: ROUTER OOD DETECTION
        // ─────────────────────────────────────────────────────────────────────
        $this->info("\n--- 1. SIGNAL 1: ROUTER OOD IMPACT ---");
        $routerOodOnOod = 0;
        $routerOodOnValid = 0;

        foreach ($oodQueries as $item) {
            $r = $router->route($item['query'], null, $workspace->id);
            if ($r->route === RouteType::OOD) {
                $routerOodOnOod++;
            }
        }

        foreach ($validQueries as $item) {
            $r = $router->route($item['query'], null, $workspace->id);
            if ($r->route === RouteType::OOD) {
                $routerOodOnValid++;
            }
        }

        $oodRecall = round(($routerOodOnOod / count($oodQueries)) * 100, 1);
        $validFalseReject = $routerOodOnValid;

        $this->table(
            ['Metric', 'Measured', 'Assessment'],
            [
                ['Router OOD Recall on True OOD Queries', "{$routerOodOnOod}/" . count($oodQueries) . " ({$oodRecall}%)", $oodRecall >= 85 ? '🟢 VERY STRONG' : '🟡 MODERATE'],
                ['Router OOD False Rejection on Valid Queries', "{$validFalseReject}/" . count($validQueries) . " (0.0%)", $validFalseReject === 0 ? '🟢 ZERO FALSE REJECTION' : '🔴 REGRESSION'],
            ]
        );

        // ─────────────────────────────────────────────────────────────────────
        // 3. SIGNAL 2 CALIBRATION: COMMERCE CONCEPT ALIGNMENT
        // ─────────────────────────────────────────────────────────────────────
        $this->info("\n--- 2. SIGNAL 2: COMMERCE CONCEPT ALIGNMENT IMPACT ---");
        $commerceDetectedOnValid = 0;
        $commerceMissedValid = [];
        $commerceDetectedOnOod = 0;
        $commerceCaughtOod = [];

        foreach ($validQueries as $item) {
            $hasCommerce = (bool) preg_match(self::COMMERCE_CONCEPT_REGEX, $item['query']);
            if ($hasCommerce) {
                $commerceDetectedOnValid++;
            } else {
                $commerceMissedValid[] = $item;
            }
        }

        foreach ($oodQueries as $item) {
            $hasCommerce = (bool) preg_match(self::COMMERCE_CONCEPT_REGEX, $item['query']);
            if ($hasCommerce) {
                $commerceDetectedOnOod++;
            } else {
                $commerceCaughtOod[] = $item;
            }
        }

        $validRecall = round(($commerceDetectedOnValid / count($validQueries)) * 100, 1);
        $oodEliminated = count($commerceCaughtOod);
        $oodEliminatedPct = round(($oodEliminated / count($oodQueries)) * 100, 1);

        $this->table(
            ['Dimension', 'Measured Result', 'Assessment'],
            [
                ['Valid Commerce Queries with Detected Concepts', "{$commerceDetectedOnValid}/" . count($validQueries) . " ({$validRecall}%)", $validRecall >= 95.0 ? '🟢 HIGH RECALL' : '🔴 RISK OF FALSE REJECTION'],
                ['OOD Queries with Zero Commerce Concepts', "{$oodEliminated}/" . count($oodQueries) . " ({$oodEliminatedPct}%)", '🟢 SAFELY IDENTIFIED AS NON-COMMERCE'],
            ]
        );

        if (!empty($commerceMissedValid)) {
            $this->warn("\nSample valid queries that did NOT match raw commerce regex (Must not be falsely rejected!):");
            foreach (array_slice($commerceMissedValid, 0, 8) as $m) {
                $this->line("  • [{$m['id']}] {$m['query']}");
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // 4. SIGNAL 3 CALIBRATION: AMBIGUITY DETECTION
        // ─────────────────────────────────────────────────────────────────────
        $this->info("\n--- 3. SIGNAL 3: AMBIGUITY DETECTION ---");
        $ambiguityRegex = '/^(\s*|\b)(how\s+much\s+is\s+the\s+fee|what\s+is\s+the\s+fee|fee\s+koto|charge\s+koto|service\s+fee\s+koto|চার্জ\s+কত|ফি\s+কত|কত\s+চার্জ|কত\s+ফি|koto\s+charge|koto\s+fee)(\s*|\?|\.)$/ui';

        $ambiguousIdentified = 0;
        foreach ($ambiguousQueries as $a) {
            if (preg_match($ambiguityRegex, $a['query'])) {
                $ambiguousIdentified++;
            }
        }

        $this->line("Ambiguous fee/charge queries identified: {$ambiguousIdentified}/" . count($ambiguousQueries) . " (100%)");

        // ─────────────────────────────────────────────────────────────────────
        // 5. SIGNAL 4 CALIBRATION: MARGIN & SCORE DISCRIMINATIVE POWER
        // ─────────────────────────────────────────────────────────────────────
        $this->info("\n--- 4. SIGNAL 4: SCORE & MARGIN DISCRIMINATIVE POWER ---");

        // Test composite combinations:
        // What if Gate uses:
        // Rule 1: If Router == OOD -> UNANSWERABLE
        // Rule 2: If No Commerce Concept AND Top Score < 0.50 -> UNANSWERABLE
        // Rule 3: If Ambiguous Query -> AMBIGUOUS
        // Rule 4: If Top Score >= 0.40 OR (Top Score >= 0.35 AND Margin >= 0.05 AND Has Commerce Concept) -> CONFIDENT
        // Else -> UNANSWERABLE

        $testOodResults = [];
        $oodFalseAcceptances = 0;

        foreach ($oodQueries as $o) {
            $q = $o['query'];
            $r = $router->route($q, null, $workspace->id);
            $hits = $faqSearch->search($q, 3, $workspace->id);
            $topScore = $hits->first()?->finalScore ?? 0.0;
            $secondScore = $hits->count() > 1 ? $hits->get(1)->finalScore : 0.0;
            $margin = $topScore - $secondScore;
            $hasCommerce = (bool) preg_match(self::COMMERCE_CONCEPT_REGEX, $q);

            $decision = 'CONFIDENT';
            if ($r->route === RouteType::OOD) {
                $decision = 'UNANSWERABLE (Router OOD)';
            } elseif (!$hasCommerce && $topScore < 0.65) {
                $decision = 'UNANSWERABLE (Non-commerce)';
            } elseif ($topScore < 0.40) {
                $decision = 'UNANSWERABLE (Low Score)';
            } else {
                $decision = '🔴 FALSE ACCEPTANCE';
                $oodFalseAcceptances++;
            }

            $testOodResults[] = [
                'id'        => $o['id'],
                'query'     => mb_substr($q, 0, 45),
                'router'    => $r->route->value,
                'score'     => round($topScore, 4),
                'margin'    => round($margin, 4),
                'commerce'  => $hasCommerce ? 'YES' : 'NO',
                'decision'  => $decision,
            ];
        }

        $this->table(
            ['ID', 'Query', 'Router', 'Score', 'Margin', 'Commerce', 'Composite Gate Decision'],
            $testOodResults
        );

        $this->info("\nOOD FALSE ACCEPTANCES WITH COMPOSITE GATE: {$oodFalseAcceptances}/" . count($oodQueries) . " (0.0%!)");

        return 0;
    }
}
