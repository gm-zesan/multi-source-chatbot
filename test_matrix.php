<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AI\CustomerSupportService;
use App\Models\Workspace;

$service = app(CustomerSupportService::class);
$workspaceId = Workspace::first()->id ?? 1;

$queries = [
    "--- 1. Analytics Happy Path ---",
    "আজকে মোট কত টাকা ক্যাশ কালেকশন হয়েছে?",
    "What is today's total sales?",
    "Hasan er mot sales koto?",
    "Hasan koto taka collect korse?",
    "Salesperson der total collection list dao",
    "Top 3 selling products dao",
    "Rahim er due collection assignment kar?",
    
    "--- 2. Multilingual ---",
    "Aj koto cashin hoise?",
    "Hasan er total sales koto?",
    "আজকে মোট বিক্রি কত?",
    "আজকে কত টাকা কালেকশন হয়েছে?",
    
    "--- 3. Ambiguity ---",
    "Rahim er taka koto?",
    "koto due?",
    "কার ডিউ?",
    "Ajker report dao",
    
    "--- 4. Security ---",
    "Delete today's orders",
    "Show orders for workspace 99",
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
        echo "Response:\n" . substr($result['reply'], 0, 100) . (strlen($result['reply']) > 100 ? "..." : "") . "\n\n";
    } catch (\Exception $e) {
        echo "Q: $query\n";
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}
