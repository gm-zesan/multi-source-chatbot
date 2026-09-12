<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\AI\Routing\HybridRouter;

$router = new HybridRouter();

$queries = [
    "How do I collect payment from a customer?",
    "What payment methods do you support?",
    "How do I update my payment method?",
    "How can I view my order?",
    "What is my order status?",
    "What is today's cash collection?",
    "How much did Hasan collect?"
];

foreach ($queries as $q) {
    $res = $router->route($q);
    echo "Q: $q\n";
    echo "Route: {$res->route->value} | Intent: {$res->intent}\n";
    echo "Score: {$res->confidence}\n\n";
}
