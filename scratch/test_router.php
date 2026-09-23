<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$router = app(\App\AI\Routing\HybridRouter::class);
$result = $router->route("howmany salesperson here?", null, 1);

echo json_encode([
    'route' => $result->route,
    'confidence' => $result->confidence,
    'intent' => $result->intent,
    'signals' => $result->signals,
    'securityStatus' => $result->securityStatus,
    'ambiguityType' => $result->ambiguityType
], JSON_PRETTY_PRINT);
