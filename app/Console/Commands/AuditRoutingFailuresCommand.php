<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\Models\Conversation;
use App\Models\Workspace;
use Illuminate\Console\Command;

class AuditRoutingFailuresCommand extends Command
{
    protected $signature = 'debug:audit-routing-failures';
    protected $description = 'Extract and classify the exact 244-turn routing failure baseline matrix';

    public function handle(HybridRouter $router): int
    {
        $datasetPath = base_path('tests/Datasets/e2e_multiturn_100_scenarios_dataset.json');
        $data = json_decode((string) file_get_contents($datasetPath), true);
        $scenarios = $data['scenarios'] ?? [];

        $workspace = Workspace::first();
        $conversation = new Conversation(['external_user_id' => 'u_router_audit']);

        $chatToKnowledge = [];
        $actionToKnowledge = [];
        $oodToKnowledge = [];
        $otherFailures = [];

        foreach ($scenarios as $sc) {
            $lang = $sc['language'];

            foreach ($sc['turns'] as $turn) {
                $turnId = $turn['turn_id'];
                $q = $turn['user_message'];
                $intent = $turn['intent_type'];

                $routing = $router->route($q, $conversation, $workspace->id);

                $isRouteCorrect = ($intent === 'ood' && $routing->route === RouteType::OOD) ||
                                  ($intent === 'uncertain' && ($routing->route === RouteType::UNCERTAIN || $routing->route === RouteType::KNOWLEDGE)) ||
                                  ($intent === 'chat' && ($routing->route === RouteType::CHAT || $routing->route === RouteType::ACTION)) ||
                                  ($intent === 'action' && $routing->route === RouteType::ACTION) ||
                                  ($intent === 'knowledge' && ($routing->route === RouteType::KNOWLEDGE || $routing->route === RouteType::CHAT));

                if (!$isRouteCorrect) {
                    $item = [
                        'turn_id' => $turnId,
                        'lang' => $lang,
                        'query' => $q,
                        'expected_intent' => $intent,
                        'actual_route' => $routing->route->value,
                        'confidence' => $routing->confidence,
                        'reason' => $routing->signals['reason'] ?? ($routing->signals['layer'] ?? 'unknown'),
                        'entities' => json_encode($routing->entities, JSON_UNESCAPED_UNICODE),
                        'matched_signals' => implode(', ', $routing->signals['matched_signals'] ?? []),
                        'has_entity' => ($routing->signals['has_entity'] ?? false) ? 'YES' : 'NO',
                        'has_question' => ($routing->signals['has_question_marker'] ?? false) ? 'YES' : 'NO',
                        'has_imperative' => ($routing->signals['has_imperative_marker'] ?? false) ? 'YES' : 'NO',
                    ];

                    if ($intent === 'chat' && $routing->route === RouteType::KNOWLEDGE) {
                        $chatToKnowledge[] = $item;
                    } elseif ($intent === 'action' && $routing->route === RouteType::KNOWLEDGE) {
                        $actionToKnowledge[] = $item;
                    } elseif ($intent === 'ood' && $routing->route === RouteType::KNOWLEDGE) {
                        $oodToKnowledge[] = $item;
                    } else {
                        $otherFailures[] = $item;
                    }
                }
            }
        }

        $this->info('===============================================================================');
        $this->info('   D2 ROUTING FAILURE BASELINE & FEATURE ATTRIBUTION MATRIX');
        $this->info('===============================================================================');

        $this->warn(PHP_EOL . 'TOTAL ROUTING FAILURES: ' . (count($chatToKnowledge) + count($actionToKnowledge) + count($oodToKnowledge) + count($otherFailures)) . ' / 244 turns');

        $this->error(PHP_EOL . 'CATEGORY 1: CHAT -> KNOWLEDGE (' . count($chatToKnowledge) . ' turns)');
        $this->table(
            ['Turn ID', 'Lang', 'Query', 'Actual Route', 'Reason', 'Question?', 'Signals'],
            array_map(fn($f) => [
                $f['turn_id'],
                $f['lang'],
                $f['query'],
                $f['actual_route'],
                $f['reason'],
                $f['has_question'],
                $f['matched_signals'],
            ], $chatToKnowledge)
        );

        $this->error(PHP_EOL . 'CATEGORY 2: ACTION -> KNOWLEDGE (' . count($actionToKnowledge) . ' turns)');
        $this->table(
            ['Turn ID', 'Lang', 'Query', 'Actual Route', 'Entities', 'Reason', 'Signals'],
            array_map(fn($f) => [
                $f['turn_id'],
                $f['lang'],
                $f['query'],
                $f['actual_route'],
                $f['entities'],
                $f['reason'],
                $f['matched_signals'],
            ], $actionToKnowledge)
        );

        $this->error(PHP_EOL . 'CATEGORY 3: OOD -> KNOWLEDGE (' . count($oodToKnowledge) . ' turns)');
        $this->table(
            ['Turn ID', 'Lang', 'Query', 'Actual Route', 'Reason', 'Signals'],
            array_map(fn($f) => [
                $f['turn_id'],
                $f['lang'],
                $f['query'],
                $f['actual_route'],
                $f['reason'],
                $f['matched_signals'],
            ], $oodToKnowledge)
        );

        if (!empty($otherFailures)) {
            $this->error(PHP_EOL . 'CATEGORY 4: OTHER MISCLASSIFICATIONS (' . count($otherFailures) . ' turns)');
            $this->table(
                ['Turn ID', 'Lang', 'Query', 'Expected', 'Actual Route'],
                $otherFailures
            );
        }

        return 0;
    }
}
