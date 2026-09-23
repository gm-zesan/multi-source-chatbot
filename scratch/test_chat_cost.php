<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\CustomerSupportService;

// Setup the conversation as Simulator does for default session
$workspaceId = 1;
$sessionKey = 'simulator_conv_default';

$channel = Channel::first() ?? Channel::create([
    'name' => 'Simulator Channel',
    'slug' => 'simulator',
    'driver' => 'web',
]);

$channelAccount = ChannelAccount::firstOrCreate(
    ['workspace_id' => $workspaceId, 'external_id' => 'sim_acc_' . $workspaceId],
    [
        'name' => 'Simulator Account',
        'channel_id' => $channel->id,
        'access_token' => 'simulator_token',
        'is_active' => true,
    ]
);

$conversation = Conversation::firstOrCreate(
    [
        'channel_account_id' => $channelAccount->id,
        'external_user_id' => $sessionKey,
    ],
    [
        'status' => 'active',
        'customer_name' => 'Simulator User',
        'last_direction' => 'inbound',
    ]
);

// Clear history to start fresh
$conversation->messages()->delete();
$conversation->update(['metadata' => []]);

$service = app(CustomerSupportService::class);

$queries = [
    // Chat
    "Hello there, how are you doing today?" => "[English] Chat - Greeting",
    "kemon aso tumi?" => "[Banglish] Chat - Greeting",
    "কেমন আছেন আপনি?" => "[Bangla] Chat - Greeting",

    // Knowledge
    "What is your return and refund policy?" => "[English] Knowledge - Return Policy",
    "kono order cancel korle tk refund kivabe pabo?" => "[Banglish] Knowledge - Refund Policy",
    "ডেলিভারি চার্জ কত টাকা?" => "[Bangla] Knowledge - Delivery Charge",

    // Analytics
    "Give me an analysis of recent sales trends" => "[English] Analytics - Sales Trends",
    "ajke total koto takar sale hoise?" => "[Banglish] Analytics - Today Sales",
    "গত মাসে আমার কত টাকা বকেয়া ছিল?" => "[Bangla] Analytics - Last Month Due",

    // The query that failed before
    "howmany salesperson here?" => "[English] Analytics/Ambiguous - Salesperson count",
];

$results = [];

foreach ($queries as $query => $description) {
    echo "\n------------------------------------------------------\n";
    echo "Sending Query: [$description] '$query'\n";

    // Save incoming user message
    Message::create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => $query,
    ]);

    $startTime = microtime(true);

    $supportResult = $service->handleQuery(
        query: $query,
        workspaceId: $workspaceId,
        conversation: $conversation
    );

    if (!empty($supportResult['reply'])) {
        $totalElapsedSoFar = round((microtime(true) - $startTime) * 1000, 2);
        $rawLlm = $supportResult['raw_llm_response'] ?? [];
        $service->saveOutboundReply(
            conversation: $conversation,
            replyText: $supportResult['reply'],
            deliveryResponse: [
                'route' => $supportResult['route'] ?? 'knowledge',
                'confidence' => $supportResult['confidence'] ?? 1.0,
                'answered' => $supportResult['answered'] ?? false,
                'total_time_ms' => $totalElapsedSoFar,
                'answerability_decision' => $supportResult['answerability_decision'] ?? null,
                'routing_telemetry' => $supportResult['routing_telemetry'] ?? [],
                'llm_usage' => [
                    'prompt_tokens' => $rawLlm['prompt_tokens'] ?? 0,
                    'completion_tokens' => $rawLlm['completion_tokens'] ?? 0,
                    'total_tokens' => $rawLlm['total_tokens'] ?? 0,
                    'router_tokens' => $rawLlm['router_tokens'] ?? [],
                    'agent_tokens' => $rawLlm['agent_tokens'] ?? [],
                ],
            ],
        );

        $usage = $supportResult['raw_llm_response'] ?? [];
        $total = $usage['total_tokens'] ?? 0;

        // Append to metadata history
        $metadata = $conversation->fresh()->metadata ?? [];
        if (!isset($metadata['llm_usage_history'])) {
            $metadata['llm_usage_history'] = [];
        }
        $metadata['llm_usage_history'][] = [
            'provider' => config('ai.default', 'deepseek'),
            'model' => config('ai.default_model', 'deepseek-chat'),
            'status' => 'GENERATED',
            'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
            'total_tokens' => $usage['total_tokens'] ?? 0,
            'router_tokens' => $usage['router_tokens'] ?? [],
            'agent_tokens' => $usage['agent_tokens'] ?? [],
        ];
        $conversation->update(['metadata' => $metadata]);

        $results[] = [
            'query' => $query,
            'route' => $supportResult['route'],
            'reply' => $supportResult['reply'],
            'tokens' => $total,
            'breakdown' => $usage
        ];

        echo "Reply: " . substr($supportResult['reply'], 0, 100) . "...\n";
        echo "Route: {$supportResult['route']}\n";
        echo "Total Tokens: {$total}\n";
        echo "  - Prompt: " . ($usage['prompt_tokens'] ?? 0) . "\n";
        echo "  - Completion: " . ($usage['completion_tokens'] ?? 0) . "\n";

        if (isset($usage['router_tokens'])) {
            echo "  - Router: " . json_encode($usage['router_tokens']) . "\n";
        }
        if (isset($usage['agent_tokens'])) {
            echo "  - Agent: " . json_encode($usage['agent_tokens']) . "\n";
        }
    }
}

echo "\n======================================================\n";
echo "OVERALL TELEMETRY SUMMARY\n";
$metadata = $conversation->fresh()->metadata ?? [];
$history = $metadata['llm_usage_history'] ?? [];
$totalTokens = 0;
foreach ($history as $h) {
    $totalTokens += (int) ($h['total_tokens'] ?? 0);
}
echo "Total tokens used across all queries: $totalTokens\n";
echo "Done.\n";
