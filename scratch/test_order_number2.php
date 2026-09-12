<?php
$service = app(\App\Services\AI\CustomerSupportService::class);
$conversation = \App\Models\Conversation::firstOrCreate(['id' => 9999, 'workspace_id' => 1]);

$query = "#1024";

// Test contextual resolution directly
$builder = app(\App\Services\AI\ContextualQueryBuilder::class);
$result = $builder->resolveContext($query, $conversation, []);

echo "Raw Query: " . $result->rawQuery . "\n";
echo "Resolved Query: " . $result->resolvedQuery . "\n";
echo "Active Topic: " . $result->activeTopic . "\n";

$router = app(\App\AI\Routing\HybridRouter::class);
$route = $router->route($result->resolvedQuery, $conversation);
echo "Route: " . $route->route->value . "\n";
echo "Intent: " . $route->intent . "\n";
