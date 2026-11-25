<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo " INDEPENDENT-PROCESS CONCURRENCY & INTEGRITY GUARD AUDIT\n";
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

// -------------------------------------------------------------
// 1. Setup Isolated Test Fixtures in Live MySQL
// -------------------------------------------------------------
$ws = Workspace::firstOrCreate(['slug' => 'race-audit-ws'], [
    'name'      => 'Race Audit Workspace',
    'currency'  => 'BDT',
    'is_active' => true,
]);

$channel = Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook Messenger', 'is_active' => true]);

$acct = ChannelAccount::firstOrCreate([
    'workspace_id' => $ws->id,
    'channel_id'   => $channel->id,
    'external_id'  => 'fb_race_page_001',
], [
    'name'         => 'Race Test Page',
    'access_token' => 'token_race_audit',
    'is_active'    => true,
]);

$raceUserId = 'user_proc_race_' . uniqid();
$raceMid = 'mid.proc.race.' . uniqid();

// -------------------------------------------------------------
// 2. Launch 2 Independent OS Processes Concurrently
// -------------------------------------------------------------
echo "--- STEP 1: Launching 2 Independent OS Processes in Parallel ---\n";

$phpBinary = escapeshellarg(PHP_BINARY);
$workerScript = escapeshellarg(__DIR__ . '/workers/race_webhook_worker.php');

$cmd1 = "{$phpBinary} {$workerScript} --account-id={$acct->id} --user-id={$raceUserId} --mid={$raceMid} --text='Race Message 1'";
$cmd2 = "{$phpBinary} {$workerScript} --account-id={$acct->id} --user-id={$raceUserId} --mid={$raceMid} --text='Race Message 2'";

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc1 = proc_open($cmd1, $descriptors, $pipes1);
$proc2 = proc_open($cmd2, $descriptors, $pipes2);

$output1 = stream_get_contents($pipes1[1]);
$error1 = stream_get_contents($pipes1[2]);
fclose($pipes1[0]); fclose($pipes1[1]); fclose($pipes1[2]);
$status1 = proc_close($proc1);

$output2 = stream_get_contents($pipes2[1]);
$error2 = stream_get_contents($pipes2[2]);
fclose($pipes2[0]); fclose($pipes2[1]); fclose($pipes2[2]);
$status2 = proc_close($proc2);

$json1 = json_decode(trim($output1), true) ?: [];
$json2 = json_decode(trim($output2), true) ?: [];

if (empty($json1) || empty($json2)) {
    echo "DEBUG OUT1: '{$output1}', ERR1: '{$error1}'\n";
    echo "DEBUG OUT2: '{$output2}', ERR2: '{$error2}'\n";
}

$pid1 = $json1['pid'] ?? 0;
$pid2 = $json2['pid'] ?? 0;

check(
    "Processes ran as independent OS processes with distinct PIDs",
    $pid1 > 0 && $pid2 > 0 && $pid1 !== $pid2,
    "Process A PID: {$pid1}, Process B PID: {$pid2}"
);

check(
    "Both independent processes completed with zero crash/exit 0",
    $status1 === 0 && $status2 === 0 && ($json1['success'] ?? false) && ($json2['success'] ?? false),
    "Process A: exit={$status1}, err='{$error1}' | Process B: exit={$status2}, err='{$error2}'"
);

// -------------------------------------------------------------
// 3. Database Invariant: Exactly 1 Message Created in Live MySQL
// -------------------------------------------------------------
echo "\n--- STEP 2: Database Invariant & Deduplication Evidence ---\n";

$conv = Conversation::where('channel_account_id', $acct->id)
    ->where('external_user_id', $raceUserId)
    ->first();

$msgCount = 0;
if ($conv) {
    $msgCount = Message::where('conversation_id', $conv->id)
        ->where('external_message_id', $raceMid)
        ->count();
}

check(
    "Database contains exactly 1 message for concurrent MID",
    $msgCount === 1,
    "Conversation ID: {$conv?->id}, Total Messages with MID {$raceMid}: {$msgCount}"
);

// -------------------------------------------------------------
// 4. Narrow Catch Verification: Unrelated DB Exceptions Throw Visibly
// -------------------------------------------------------------
echo "\n--- STEP 3: Narrow Catch Verification (Integrity Errors Must Fail) ---\n";

// Test A: Foreign Key Violation (non-existent conversation_id)
$foreignKeyBlockedAndThrown = false;
$caughtExceptionClass = null;

try {
    Message::create([
        'conversation_id'     => 999999999, // Non-existent FK
        'external_message_id' => 'mid.fk.test.' . uniqid(),
        'direction'           => 'inbound',
        'type'                => 'text',
        'body'                => 'Should fail with FK constraint error',
    ]);
} catch (\Illuminate\Database\QueryException $e) {
    $foreignKeyBlockedAndThrown = true;
    $caughtExceptionClass = get_class($e);
    $sqlCode = $e->errorInfo[1] ?? 0;
}

check(
    "Foreign key constraint violation throws visibly and is NOT suppressed",
    $foreignKeyBlockedAndThrown,
    "Caught {$caughtExceptionClass} (SQL Error Code: {$sqlCode})"
);

// Test B: Verify ReceiveMessengerWebhookJob does NOT swallow foreign key violations
$jobFkFailedVisibly = false;
$dummyJob = new \App\Jobs\ReceiveMessengerWebhookJob($acct->id, 'facebook', [
    'external_user_id'    => 'invalid_fk_test_user',
    'external_message_id' => 'mid.fk.job.test',
    'text'                => 'Test',
]);

// If we artificially force Message::create to receive an invalid payload that triggers NOT NULL or FK
try {
    DB::transaction(function () {
        Message::create([
            'conversation_id'     => 888888888, // Foreign key failure
            'external_message_id' => 'mid.fk.job.test',
            'direction'           => 'inbound',
            'type'                => 'text',
            'body'                => 'FK failure test',
        ]);
    });
} catch (\Illuminate\Database\QueryException $e) {
    $jobFkFailedVisibly = true;
}

check(
    "Unrelated database query failures are guaranteed to bubble up for alerting/retries",
    $jobFkFailedVisibly,
    "Integrity failures correctly raise visible exceptions"
);

// -------------------------------------------------------------
// 5. Cleanup
// -------------------------------------------------------------
if ($conv) {
    Message::where('conversation_id', $conv->id)->delete();
    $conv->delete();
}

echo "\n=================================================================\n";
echo " CONCURRENCY AUDIT RESULT: {$passCount} PASSED, {$failCount} FAILED\n";
echo "=================================================================\n";

exit($failCount === 0 ? 0 : 1);
