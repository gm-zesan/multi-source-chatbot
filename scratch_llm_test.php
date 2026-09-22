<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Event;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Events\ConnectionFailed;

$llmCalls = 0;
Event::listen(ResponseReceived::class, function ($event) use (&$llmCalls) {
    if (str_contains($event->request->url(), 'openrouter') || str_contains($event->request->url(), 'deepseek') || str_contains($event->request->url(), 'openai')) {
        $llmCalls++;
        echo "HTTP Call to: " . $event->request->url() . "\n";
    }
});

$workspaceId = 1; 
$conversation = App\Models\Conversation::create([
    'id' => \Illuminate\Support\Str::uuid(),
    'workspace_id' => $workspaceId,
    'session_id' => 'test-session',
    'contact_id' => 'test-contact'
]);
$service = app(App\Services\AI\CustomerSupportService::class);

echo "Sending query 1: 'What is the refund policy?'\n";
$result = $service->handleQuery('What is the refund policy?', $workspaceId, $conversation);
echo "LLM calls for Q1: {$llmCalls}\n";
echo "Q1 Route: " . $result['route'] . "\n\n";

$llmCalls = 0;
echo "Sending query 2: 'hello there'\n";
$result = $service->handleQuery('hello there', $workspaceId, $conversation);
echo "LLM calls for Q2: {$llmCalls}\n";
echo "Q2 Route: " . $result['route'] . "\n\n";

$llmCalls = 0;
echo "Sending query 3: 'make him admin'\n";
$result = $service->handleQuery('make him admin', $workspaceId, $conversation);
echo "LLM calls for Q3: {$llmCalls}\n";
echo "Q3 Route: " . $result['route'] . "\n\n";
