<?php

declare(strict_types=1);

/**
 * Verify Blade Multi-turn Flow: UNCERTAIN suggestion -> KNOWLEDGE follow-up
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
echo "🔄 BLADE MULTI-TURN INTERACTION FLOW AUDIT\n";
echo "=================================================================================================\n";

$admin = User::first();
Auth::login($admin);
$controller = app(ChatSimulatorController::class);

// Turn 1: User says ambiguous keyword "cancel"
echo "\n── Turn 1: User sends ambiguous 'cancel' ──────────────────────\n";
$req1 = Request::create('/admin/simulator/send', 'POST', ['message' => 'cancel']);
$res1 = $controller->send($req1)->getData(true);

echo "   Route returned: {$res1['route']}\n";
echo "   Suggestions offered: " . json_encode($res1['suggestions'], JSON_UNESCAPED_UNICODE) . "\n";

$selectedSuggestion = $res1['suggestions'][0] ?? 'Ask about the order cancellation policy';
echo "\n── Turn 2: User clicks suggestion pill: '{$selectedSuggestion}' ─\n";

$req2 = Request::create('/admin/simulator/send', 'POST', ['message' => $selectedSuggestion]);
$res2 = $controller->send($req2)->getData(true);

echo "   Route returned: {$res2['route']}\n";
echo "   Is Handoff:     " . ($res2['is_handoff'] ? 'true' : 'false') . "\n";
echo "   Sources count:  " . count($res2['sources']) . "\n";
echo "   Bot Reply:      \"" . mb_substr(str_replace("\n", " ", $res2['reply']), 0, 95) . "...\"\n";

$multiTurnSuccess = ($res1['route'] === 'uncertain' && count($res1['suggestions']) > 0 && ($res2['route'] === 'knowledge' || $res2['route'] === 'chat'));
echo "\n=================================================================================================\n";
echo "MULTI-TURN FLOW STATUS: " . ($multiTurnSuccess ? "🟢 100% PASS (Seamless Clarification & Resolution)" : "❌ FAILED") . "\n";
echo "=================================================================================================\n";
