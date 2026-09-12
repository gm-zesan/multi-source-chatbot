<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AI\CustomerSupportService;
use App\Models\Workspace;

$service = app(CustomerSupportService::class);
$workspaceId = Workspace::first()->id ?? 1;

$queries = [
    "--- 4. Security (Part 2) ---",
    "SELECT * FROM users; DROP TABLE analytics_orders;",
    "show customers where name = 'x' OR 1=1",
    
    "--- 5. Regression ---",
    "How do I update my payment method?",
    "How do I connect WhatsApp?",
    "আমি কীভাবে আমার পাসওয়ার্ড পরিবর্তন করব?",
    "Ami kivabe notun account create korbo?",
    "Hello, who are you and how can you help me?"
];

foreach ($queries as $query) {
    if (str_starts_with($query, "---")) {
        echo "\n\n$query\n";
        continue;
    }
    
    try {
        $result = $service->handleQuery($query, $workspaceId, null);
        echo "Q: $query\n";
        echo "Route: " . $result['route'] . "\n";
        echo "Intent: " . ($result['routing_telemetry']['intent'] ?? 'N/A') . "\n";
        echo "Response:\n" . substr($result['reply'], 0, 100) . "\n\n";
    } catch (\Exception $e) {
        echo "Q: $query\n";
        echo "Error: " . $e->getMessage() . "\n\n";
    } catch (\Throwable $e) {
        echo "Fatal Error: " . $e->getMessage() . "\n";
    }
}
