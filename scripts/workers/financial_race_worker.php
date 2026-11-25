<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AnalyticsOrder;
use App\Services\Analytics\OrderAnalyticsProjectionService;

$options = getopt('', [
    'action:',        // 'refund' or 'payment'
    'order-id:',
    'amount:',
    'method:',
    'type:',
    'provider:',
    'ref:',
    'reason:',
]);

$action = (string) ($options['action'] ?? 'refund');
$orderId = (int) ($options['order-id'] ?? 0);
$amount = (float) ($options['amount'] ?? 0.0);
$method = (string) ($options['method'] ?? 'refund');
$type = (string) ($options['type'] ?? 'customer_refund');
$provider = (string) ($options['provider'] ?? 'manual');
$ref = (string) ($options['ref'] ?? '');
$reason = (string) ($options['reason'] ?? 'race_test');

$result = [
    'pid'            => getmypid(),
    'action'         => $action,
    'order_id'       => $orderId,
    'amount'         => $amount,
    'ref'            => $ref,
    'provider'       => $provider,
    'success'        => false,
    'payment_id'     => null,
    'exception'      => null,
    'exception_code' => null,
];

try {
    $service = app(OrderAnalyticsProjectionService::class);
    $order = AnalyticsOrder::findOrFail($orderId);

    if ($action === 'refund') {
        $payment = $service->recordRefund(
            order: $order,
            refundAmount: $amount,
            reason: $reason,
            transactionRef: $ref,
            provider: $provider
        );
    } else {
        $payment = $service->recordPayment(
            order: $order,
            amount: $amount,
            paymentMethod: $method,
            transactionRef: $ref,
            collectedAt: null,
            transactionType: $type,
            provider: $provider
        );
    }

    $result['success'] = true;
    $result['payment_id'] = $payment->id;
} catch (\Throwable $e) {
    $result['exception'] = get_class($e) . ': ' . $e->getMessage();
    $result['exception_code'] = $e->getCode();
}

echo json_encode($result);
