<?php

declare(strict_types=1);

/**
 * Audit and Test Context Memory & Understanding in Multi-Turn Conversations
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\Chat\ConversationService;

echo "=================================================================================================\n";
echo "🧠 CONTEXT MEMORY & CONTEXT UNDERSTANDING AUDIT\n";
echo "=================================================================================================\n";

$customerSupportService = app(CustomerSupportService::class);
$conversationService = app(ConversationService::class);

$ws = Workspace::first() ?? Workspace::create(['name' => 'Context Test WS', 'slug' => 'context-ws']);
$channel = Channel::first() ?? Channel::create(['name' => 'Web Chat', 'slug' => 'web', 'driver' => 'web']);
$acc = ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws->id, 'channel_id' => $channel->id],
    ['name' => 'Context Test Acc', 'account_identifier' => 'ctx_acc_' . uniqid()]
);

// Create a real active conversation
$conversation = Conversation::create([
    'channel_account_id' => $acc->id,
    'external_user_id'   => 'user_ctx_' . uniqid(),
    'status'             => 'active',
    'customer_name'      => 'Alice Wonderland',
]);

echo "\nConversation ID: #{$conversation->id} created for Customer: 'Alice Wonderland'\n";

// Multi-turn dialog sequence
$turns = [
    [
        'turn'  => 1,
        'user'  => 'Hi, my name is Alice and I am a software engineer from Dhaka.',
        'check' => 'Conversational greeting & introduction',
    ],
    [
        'turn'  => 2,
        'user'  => 'What plans do you offer?',
        'check' => 'Knowledge inquiry (Plans)',
    ],
    [
        'turn'  => 3,
        'user'  => 'Do you remember what my name is and where I am from?',
        'check' => 'Context Memory Recall (Name & Location from Turn 1)',
    ],
];

foreach ($turns as $step) {
    echo "\n── Turn {$step['turn']}: User: \"{$step['user']}\" ──\n";
    
    // Save inbound user message to conversation history
    $inbound = Message::create([
        'conversation_id' => $conversation->id,
        'direction'       => 'inbound',
        'type'            => 'text',
        'body'            => $step['user'],
    ]);

    $t_start = microtime(true);
    $replyText = $customerSupportService->generateReply(
        conversation: $conversation,
        query: $step['user'],
        workspaceId: $ws->id,
    );
    $lat = round((microtime(true) - $t_start) * 1000, 2);

    // Save AI outbound reply to conversation history
    $outbound = $customerSupportService->saveOutboundReply(
        conversation: $conversation,
        replyText: $replyText,
    );

    echo "   🤖 Assistant ({$lat} ms):\n";
    echo "      \"" . wordwrap(str_replace("\n", " ", trim($replyText)), 90, "\n      ") . "\"\n";
}

// Evaluate Turn 3 response
$lastReply = $conversation->messages()->where('direction', 'outbound')->latest('id')->first()?->body ?? '';
$recalledAlice = (stripos($lastReply, 'Alice') !== false);
$recalledDhaka = (stripos($lastReply, 'Dhaka') !== false || stripos($lastReply, 'ঢাকা') !== false);

echo "\n=================================================================================================\n";
echo "📊 CONTEXT RECALL EVALUATION RESULT:\n";
echo "   - Recalled User Name ('Alice'): " . ($recalledAlice ? "✅ YES" : "❌ NO") . "\n";
echo "   - Recalled User Location ('Dhaka'): " . ($recalledDhaka ? "✅ YES" : "❌ NO") . "\n";
echo "   - History Window Length in DB: " . $conversation->messages()->count() . " messages\n";
echo "=================================================================================================\n";
