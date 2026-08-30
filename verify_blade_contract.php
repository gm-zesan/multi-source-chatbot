<?php

declare(strict_types=1);

/**
 * Verify Blade UI Backend Contract for all 5 Route Types
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\ChatSimulatorController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "=================================================================================================\n";
echo "🌐 BLADE BACKEND RESPONSE CONTRACT AUDIT & VERIFICATION\n";
echo "=================================================================================================\n";

$admin = User::first();
Auth::login($admin);

$controller = app(ChatSimulatorController::class);

$testQueries = [
    [
        'category'     => 'CHAT',
        'query'        => 'Hello! How are you today?',
        'expectRoute'  => 'chat',
        'expectHandoff'=> false,
        'hasSources'   => false,
        'hasSuggestions'=> false,
    ],
    [
        'category'     => 'KNOWLEDGE',
        'query'        => 'How is my data encrypted?',
        'expectRoute'  => 'knowledge',
        'expectHandoff'=> false,
        'hasSources'   => true,
        'hasSuggestions'=> false,
    ],
    [
        'category'     => 'OOD',
        'query'        => 'What is the weather in Dhaka today?',
        'expectRoute'  => 'ood',
        'expectHandoff'=> false,
        'hasSources'   => false,
        'hasSuggestions'=> false,
    ],
    [
        'category'     => 'UNCERTAIN',
        'query'        => 'cancel',
        'expectRoute'  => 'uncertain',
        'expectHandoff'=> false,
        'hasSources'   => false,
        'hasSuggestions'=> true,
    ],
    [
        'category'     => 'ACTION [DEFERRED]',
        'query'        => 'Please cancel my order #1024',
        'expectRoute'  => 'action',
        'expectHandoff'=> true,
        'hasSources'   => false,
        'hasSuggestions'=> false,
    ],
];

$allPassed = true;

foreach ($testQueries as $item) {
    $req = Request::create('/admin/simulator/send', 'POST', ['message' => $item['query']]);
    $t_start = microtime(true);
    $response = $controller->send($req);
    $lat = round((microtime(true) - $t_start) * 1000, 2);

    $data = $response->getData(true);

    $routeOk = ($data['route'] === $item['expectRoute']);
    $handoffOk = ($data['is_handoff'] === $item['expectHandoff']);
    $sourcesOk = $item['hasSources'] ? (!empty($data['sources'])) : (empty($data['sources']));
    $sugOk = $item['hasSuggestions'] ? (!empty($data['suggestions'])) : (empty($data['suggestions']));

    $pass = $routeOk && $handoffOk && $sourcesOk && $sugOk && $data['success'];
    if (!$pass) $allPassed = false;

    $icon = $pass ? '✅' : '❌';
    echo "\n{$icon} [{$item['category']}] \"{$item['query']}\" ({$lat} ms)\n";
    echo "   Route:       {$data['route']} (Expected: {$item['expectRoute']})\n";
    echo "   Is Handoff:  " . ($data['is_handoff'] ? 'true' : 'false') . " (Expected: " . ($item['expectHandoff'] ? 'true' : 'false') . ")\n";
    echo "   Suggestions: " . count($data['suggestions']) . " items " . json_encode($data['suggestions'], JSON_UNESCAPED_UNICODE) . "\n";
    echo "   Sources:     " . count($data['sources']) . " items\n";
    if (!empty($data['sources'])) {
        foreach ($data['sources'] as $s) {
            echo "      • FAQ: {$s['question']} (Score: {$s['score']}%)\n";
        }
    }
    echo "   Reply:       \"" . mb_substr(str_replace("\n", " ", $data['reply']), 0, 85) . "...\"\n";
}

echo "\n=================================================================================================\n";
echo "OVERALL CONTRACT STATUS: " . ($allPassed ? "🟢 100% FAITHFUL & OPTIMAL" : "❌ ATTENTION NEEDED") . "\n";
echo "=================================================================================================\n";
