<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(\App\Services\AI\CustomerSupportService::class);
$conversation = \App\Models\Conversation::firstOrCreate(['id' => 9999, 'workspace_id' => 1]);
$conversation->metadata = ['uncertain_count' => 1];
$conversation->save();

// Simulate previous turn
$conversationHistory = [
    ['role' => 'user', 'text' => 'What is my order status?'],
    ['role' => 'bot', 'text' => 'To check your order status, could you please provide your Order ID (e.g. #1024)?']
];

$query = "#1024";

// Test contextual resolution directly
$builder = app(\App\Services\AI\ContextualQueryBuilder::class);
$result = $builder->resolveContext($query, $conversation, $conversationHistory);

echo "Raw Query: " . $result->rawQuery . "\n";
echo "Resolved Query: " . $result->resolvedQuery . "\n";
echo "Active Topic: " . $result->activeTopic . "\n";
