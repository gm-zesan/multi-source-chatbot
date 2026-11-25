<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AnalyticsOrder;
use App\Models\AnalyticsPayment;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Analytics\OrderAnalyticsProjectionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo " FINANCIAL CONCURRENCY, LEDGER IMMUTABILITY & RACE AUDIT\n";
echo " Independent OS Processes on Live MySQL InnoDB\n";
echo "=================================================================\n\n";

$passCount = 0;
$failCount = 0;

function check(string $title, bool $condition, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$title}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$title}\n";
    }
    if ($detail !== '') {
        echo "         -> {$detail}\n";
    }
}

$service = app(OrderAnalyticsProjectionService::class);
$phpBinary = escapeshellarg(PHP_BINARY);
$workerScript = escapeshellarg(__DIR__ . '/workers/financial_race_worker.php');

$ws = Workspace::firstOrCreate(['slug' => 'financial-race-ws'], [
    'name'      => 'Financial Concurrency Workspace',
    'currency'  => 'BDT',
    'is_active' => true,
]);

// -------------------------------------------------------------
// SCENARIO 1: Concurrent Partial Refund Race (Over-Limit Guard)
// -------------------------------------------------------------
echo "--- SCENARIO 1: Concurrent Partial Refund Race on Live MySQL InnoDB ---\n";
$order1Number = 'ORD-RACE-REF-' . uniqid();
$wsOrder1 = WorkspaceOrder::create([
    'workspace_id'   => $ws->id,
    'order_number'   => $order1Number,
    'order_amount'   => 5000.00,
    'delivery_fee'   => 0.00,
    'status'         => 'delivered',
    'captured_at'    => Carbon::today(),
]);
$anOrder1 = $service->project($wsOrder1);

// Record initial payment of ৳5,000
$initPmt = $service->recordPayment(
    order: $anOrder1,
    amount: 5000.00,
    paymentMethod: 'bkash',
    transactionRef: 'INIT-PMT-' . uniqid(),
    transactionType: 'customer_advance',
    provider: 'bkash'
);

echo "  Initial Payment: ৳{$initPmt->amount} recorded for Order #{$order1Number} (ID: {$anOrder1->id})\n";
echo "  Launching 2 simultaneous processes attempting to refund ৳3,000 each (Total requested: ৳6,000 on ৳5,000 balance)...\n";

$refA = 'REF-PROC-A-' . uniqid();
$refB = 'REF-PROC-B-' . uniqid();

$cmd1 = "{$phpBinary} {$workerScript} --action=refund --order-id={$anOrder1->id} --amount=3000.00 --ref={$refA} --reason='race_part_1'";
$cmd2 = "{$phpBinary} {$workerScript} --action=refund --order-id={$anOrder1->id} --amount=3000.00 --ref={$refB} --reason='race_part_2'";

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc1 = proc_open($cmd1, $descriptors, $pipes1);
$proc2 = proc_open($cmd2, $descriptors, $pipes2);

$out1 = stream_get_contents($pipes1[1]); fclose($pipes1[0]); fclose($pipes1[1]); fclose($pipes1[2]);
$status1 = proc_close($proc1);

$out2 = stream_get_contents($pipes2[1]); fclose($pipes2[0]); fclose($pipes2[1]); fclose($pipes2[2]);
$status2 = proc_close($proc2);

$json1 = json_decode(trim($out1), true) ?: [];
$json2 = json_decode(trim($out2), true) ?: [];

$successCount = ($json1['success'] ? 1 : 0) + ($json2['success'] ? 1 : 0);
$failedCount = (!$json1['success'] ? 1 : 0) + (!$json2['success'] ? 1 : 0);

check("Exactly one process succeeded", $successCount === 1, "Success count: {$successCount}");
check("Exactly one process failed with over-limit guard", $failedCount === 1, "Failed count: {$failedCount}");

$failedJson = !$json1['success'] ? $json1 : $json2;
check(
    "Rejection reason was balance exceeded guard",
    str_contains($failedJson['exception'] ?? '', 'exceeds the remaining refundable balance'),
    $failedJson['exception'] ?? 'None'
);

// Verify live DB balance
$netPaid = (float) AnalyticsPayment::where('order_id', $anOrder1->id)->sum('amount');
check("Remaining refundable balance is exactly ৳2,000", $netPaid === 2000.00, "Live DB Net Balance: ৳{$netPaid}");

$totalRefundRows = AnalyticsPayment::where('order_id', $anOrder1->id)->where('amount', '<', 0)->count();
check("Exactly 1 refund compensating entry created", $totalRefundRows === 1, "Refund entries: {$totalRefundRows}");


// -------------------------------------------------------------
// SCENARIO 2: Concurrent Duplicate Settlement Webhooks (Idempotency)
// -------------------------------------------------------------
echo "\n--- SCENARIO 2: Concurrent Duplicate Settlement Webhooks on Live MySQL InnoDB ---\n";
$order2Number = 'ORD-RACE-SETTLE-' . uniqid();
$wsOrder2 = WorkspaceOrder::create([
    'workspace_id'   => $ws->id,
    'order_number'   => $order2Number,
    'order_amount'   => 4000.00,
    'delivery_fee'   => 0.00,
    'status'         => 'delivered',
    'captured_at'    => Carbon::today(),
]);
$anOrder2 = $service->project($wsOrder2);

$sharedRef = 'ST-SETTLE-DUP-' . uniqid();
echo "  Launching 2 simultaneous processes delivering identical settlement ref '{$sharedRef}'...\n";

$cmdSettle1 = "{$phpBinary} {$workerScript} --action=payment --order-id={$anOrder2->id} --amount=4000.00 --method=courier_settlement --type=courier_settlement --provider=steadfast --ref={$sharedRef}";
$cmdSettle2 = "{$phpBinary} {$workerScript} --action=payment --order-id={$anOrder2->id} --amount=4000.00 --method=courier_settlement --type=courier_settlement --provider=steadfast --ref={$sharedRef}";

$pSettle1 = proc_open($cmdSettle1, $descriptors, $pipesS1);
$pSettle2 = proc_open($cmdSettle2, $descriptors, $pipesS2);

$outS1 = stream_get_contents($pipesS1[1]); fclose($pipesS1[0]); fclose($pipesS1[1]); fclose($pipesS1[2]);
proc_close($pSettle1);

$outS2 = stream_get_contents($pipesS2[1]); fclose($pipesS2[0]); fclose($pipesS2[1]); fclose($pipesS2[2]);
proc_close($pSettle2);

$jsonS1 = json_decode(trim($outS1), true) ?: [];
$jsonS2 = json_decode(trim($outS2), true) ?: [];

check("Both duplicate settlement processes exited cleanly", ($jsonS1['success'] ?? false) && ($jsonS2['success'] ?? false));
check("Both processes returned the same payment ID", ($jsonS1['payment_id'] ?? null) === ($jsonS2['payment_id'] ?? null), "PID 1: {$jsonS1['payment_id']}, PID 2: {$jsonS2['payment_id']}");

$settleCount = AnalyticsPayment::where('order_id', $anOrder2->id)->where('transaction_ref', $sharedRef)->count();
check("Database holds exactly 1 settlement record", $settleCount === 1, "Records in DB: {$settleCount}");

$receivable2 = $service->getOrderCourierReceivable($anOrder2);
check("Courier receivable is exactly ৳0.00 (cleared)", $receivable2 === 0.00, "Receivable: ৳{$receivable2}");


// -------------------------------------------------------------
// SCENARIO 3: Cross-Provider Identical Reference Concurrency
// -------------------------------------------------------------
echo "\n--- SCENARIO 3: Cross-Provider Identical Reference Concurrency ---\n";
$order3Number = 'ORD-RACE-MULTI-PROV-' . uniqid();
$wsOrder3 = WorkspaceOrder::create([
    'workspace_id'   => $ws->id,
    'order_number'   => $order3Number,
    'order_amount'   => 5000.00,
    'delivery_fee'   => 0.00,
    'status'         => 'delivered',
    'captured_at'    => Carbon::today(),
]);
$anOrder3 = $service->project($wsOrder3);

$identicalRef = 'INV-COMMON-' . uniqid();
echo "  Launching 2 concurrent processes using identical reference '{$identicalRef}' under bkash and steadfast...\n";

$cmdP1 = "{$phpBinary} {$workerScript} --action=payment --order-id={$anOrder3->id} --amount=1000.00 --method=bkash --type=customer_advance --provider=bkash --ref={$identicalRef}";
$cmdP2 = "{$phpBinary} {$workerScript} --action=payment --order-id={$anOrder3->id} --amount=4000.00 --method=courier_settlement --type=courier_settlement --provider=steadfast --ref={$identicalRef}";

$pM1 = proc_open($cmdP1, $descriptors, $pipesM1);
$pM2 = proc_open($cmdP2, $descriptors, $pipesM2);

$outM1 = stream_get_contents($pipesM1[1]); fclose($pipesM1[0]); fclose($pipesM1[1]); fclose($pipesM1[2]);
proc_close($pM1);

$outM2 = stream_get_contents($pipesM2[1]); fclose($pipesM2[0]); fclose($pipesM2[1]); fclose($pipesM2[2]);
proc_close($pM2);

$jsonM1 = json_decode(trim($outM1), true) ?: [];
$jsonM2 = json_decode(trim($outM2), true) ?: [];

check("Process 1 (bKash advance) succeeded", $jsonM1['success'] ?? false, $jsonM1['exception'] ?? '');
check("Process 2 (Steadfast settlement) succeeded", $jsonM2['success'] ?? false, $jsonM2['exception'] ?? '');
check("Different payment IDs generated without unique conflict", ($jsonM1['payment_id'] ?? null) !== ($jsonM2['payment_id'] ?? null));

$order3Payments = AnalyticsPayment::where('order_id', $anOrder3->id)->get();
check("Database holds both provider records (bKash & Steadfast)", $order3Payments->count() === 2, "Count: " . $order3Payments->count());

$receivable3 = $service->getOrderCourierReceivable($anOrder3);
check("Order 3 courier receivable is ৳0.00 (Total 5000 - Adv 1000 - Settle 4000 = 0)", $receivable3 === 0.00, "Receivable: ৳{$receivable3}");


// -------------------------------------------------------------
// SCENARIO 4: Database-Level Trigger Immutability on Live MySQL
// -------------------------------------------------------------
echo "\n--- SCENARIO 4: Database-Level Trigger Immutability on Live MySQL Engine ---\n";
$targetPayment = $order3Payments->first();

$dbDeleteBlocked = false;
$deleteErrorMsg = '';
try {
    DB::table('analytics_payments')->where('id', $targetPayment->id)->delete();
} catch (\Throwable $e) {
    $dbDeleteBlocked = true;
    $deleteErrorMsg = $e->getMessage();
}

check("Raw SQL DELETE blocked by MySQL Trigger", $dbDeleteBlocked, $deleteErrorMsg);
check("Trigger raised SQLSTATE 45000", str_contains($deleteErrorMsg, '45000') || str_contains($deleteErrorMsg, 'immutable'));

$dbUpdateBlocked = false;
$updateErrorMsg = '';
try {
    DB::table('analytics_payments')->where('id', $targetPayment->id)->update(['amount' => 99999.00]);
} catch (\Throwable $e) {
    $dbUpdateBlocked = true;
    $updateErrorMsg = $e->getMessage();
}

check("Raw SQL UPDATE blocked by MySQL Trigger", $dbUpdateBlocked, $updateErrorMsg);
check("Trigger raised SQLSTATE 45000", str_contains($updateErrorMsg, '45000') || str_contains($updateErrorMsg, 'immutable'));


// -------------------------------------------------------------
// SCENARIO 5: Partial Settlement Followed by Post-Delivery Return (Live MySQL)
// -------------------------------------------------------------
echo "\n--- SCENARIO 5: Partial Settlement Followed by Return on Live MySQL ---\n";
$order5Number = 'ORD-RACE-PART-RET-' . uniqid();
$wsOrder5 = WorkspaceOrder::create([
    'workspace_id'   => $ws->id,
    'order_number'   => $order5Number,
    'order_amount'   => 5000.00,
    'delivery_fee'   => 0.00,
    'status'         => 'delivered',
    'captured_at'    => Carbon::today(),
]);
$anOrder5 = $service->project($wsOrder5);

// 1. Courier partial settlement of ৳2,000
$service->recordPayment(
    order: $anOrder5,
    amount: 2000.00,
    paymentMethod: 'courier_settlement',
    transactionRef: 'ST-PART-SETTLE-' . uniqid(),
    collectedAt: null,
    transactionType: 'courier_settlement',
    provider: 'steadfast'
);
$recAfterPart = $service->getOrderCourierReceivable($anOrder5);
check("Receivable is ৳3,000 after partial remittance", $recAfterPart === 3000.00, "Receivable: ৳{$recAfterPart}");

// 2. Customer returns product post-delivery
$wsOrder5->status = 'cancelled';
$wsOrder5->save();
$service->project($wsOrder5); // Projects to returned

$anOrder5->refresh();
check("AnalyticsOrder status is 'returned'", $anOrder5->status === 'returned', "Status: {$anOrder5->status}");

// 3. Crucial Invariant: Courier Receivable must STILL be ৳3,000!
$recAfterReturn = $service->getOrderCourierReceivable($anOrder5);
check("Courier Receivable is STILL ৳3,000 after return (Preserved)", $recAfterReturn === 3000.00, "Receivable: ৳{$recAfterReturn}");

$summary5 = $service->getFinancialSummary($ws->id);
check("Sales returns separated from cash flow", $summary5['sales_returns'] >= 5000.00, "Sales returns: ৳{$summary5['sales_returns']}");

// 4. Courier remits remaining ৳3,000
$service->recordPayment(
    order: $anOrder5,
    amount: 3000.00,
    paymentMethod: 'courier_settlement',
    transactionRef: 'ST-FINAL-SETTLE-' . uniqid(),
    collectedAt: null,
    transactionType: 'courier_settlement',
    provider: 'steadfast'
);
$recFinal = $service->getOrderCourierReceivable($anOrder5);
check("Courier Receivable fully cleared to ৳0.00", $recFinal === 0.00, "Receivable: ৳{$recFinal}");

// -------------------------------------------------------------
// SCENARIO 6: Automated Database Ledger Readiness Verification
// -------------------------------------------------------------
echo "\n--- SCENARIO 6: Automated Database Ledger Readiness Command ---\n";
$exitCode = \Illuminate\Support\Facades\Artisan::call('db:verify-ledger-readiness');
check("Artisan db:verify-ledger-readiness exited with code 0", $exitCode === 0, "Exit code: {$exitCode}");


// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n=================================================================\n";
echo " FINAL CONCURRENCY AUDIT RESULT: {$passCount} PASSED, {$failCount} FAILED\n";
echo "=================================================================\n";

if ($failCount > 0) {
    exit(1);
}
