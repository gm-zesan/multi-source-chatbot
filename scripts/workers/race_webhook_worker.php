<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\ReceiveMessengerWebhookJob;
use App\Models\Message;

$options = getopt('', ['account-id:', 'user-id:', 'mid:', 'text:']);

$accountId = (int) ($options['account-id'] ?? 0);
$userId = (string) ($options['user-id'] ?? '');
$mid = (string) ($options['mid'] ?? '');
$text = (string) ($options['text'] ?? 'Race test');

$payload = [
    'external_user_id'    => $userId,
    'external_message_id' => $mid,
    'text'                => $text,
    'type'                => 'text',
];

$result = [
    'pid'                  => getmypid(),
    'mid'                  => $mid,
    'success'              => false,
    'exception'            => null,
    'exception_code'       => null,
    'was_duplicate_caught' => false,
];

try {
    $job = new ReceiveMessengerWebhookJob($accountId, 'facebook', $payload);
    $job->handle();
    $result['success'] = true;
} catch (\Throwable $e) {
    $result['exception'] = get_class($e) . ': ' . $e->getMessage();
    $result['exception_code'] = $e->getCode();
}

echo json_encode($result);
