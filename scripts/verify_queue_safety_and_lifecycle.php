<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\ReceiveMessengerWebhookJob;
use App\Jobs\TestFailingQueueJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=================================================================\n";
echo " ENTREPRENEURS AUTOMATION — QUEUE SAFETY & LIFECYCLE AUDIT\n";
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
// SECTION 1: Queue Timeout Invariants & Retry Configuration
// -------------------------------------------------------------
echo "--- SECTION 1: Timeout & Retry Safety Invariants ---\n";

$dbRetryAfter = Config::get('queue.connections.database.retry_after');
$messengerRetryAfter = Config::get('queue.connections.messenger.retry_after');
$crmRetryAfter = Config::get('queue.connections.crm.retry_after');
$docsRetryAfter = Config::get('queue.connections.documents.retry_after');

check(
    "Database connection retry_after >= 400s",
    $dbRetryAfter >= 400,
    "Configured: {$dbRetryAfter}s (Minimum: 400s)"
);

check(
    "Messenger connection retry_after >= 400s",
    $messengerRetryAfter >= 400,
    "Configured: {$messengerRetryAfter}s"
);

check(
    "CRM connection retry_after >= 400s",
    $crmRetryAfter >= 400,
    "Configured: {$crmRetryAfter}s"
);

check(
    "Documents connection retry_after >= 400s",
    $docsRetryAfter >= 400,
    "Configured: {$docsRetryAfter}s"
);

// Worker Pool Timeout Math
$realtimeWorkerTimeout = 120;
$bgWorkerTimeout = 320;
$maxJobTimeout = 300; // ProcessSpreadsheetImportJob

$realtimeBuffer = $dbRetryAfter - $realtimeWorkerTimeout;
$bgBuffer = $dbRetryAfter - $bgWorkerTimeout;
$maxJobBuffer = $dbRetryAfter - $maxJobTimeout;

check(
    "Realtime worker safety buffer >= 60s",
    $realtimeBuffer >= 60,
    "retry_after ({$dbRetryAfter}s) - timeout ({$realtimeWorkerTimeout}s) = {$realtimeBuffer}s buffer"
);

check(
    "Background worker safety buffer >= 60s",
    $bgBuffer >= 60,
    "retry_after ({$dbRetryAfter}s) - timeout ({$bgWorkerTimeout}s) = {$bgBuffer}s buffer"
);

check(
    "Max job (spreadsheet 300s) safety buffer >= 60s",
    $maxJobBuffer >= 60,
    "retry_after ({$dbRetryAfter}s) - job timeout ({$maxJobTimeout}s) = {$maxJobBuffer}s buffer"
);

// -------------------------------------------------------------
// SECTION 2: Worker Restart Signal Verification
// -------------------------------------------------------------
echo "\n--- SECTION 2: Worker Restart Signal Lifecycle ---\n";

$beforeRestart = Cache::get('illuminate:queue:restart');
Artisan::call('queue:restart');
$afterRestart = Cache::get('illuminate:queue:restart');
$now = time();

check(
    "Artisan queue:restart updates restart cache timestamp",
    $afterRestart !== null && abs($now - (int) $afterRestart) <= 5,
    "Cache 'illuminate:queue:restart' timestamp: {$afterRestart} (now: {$now}, delta: " . abs($now - (int) $afterRestart) . "s)"
);

// -------------------------------------------------------------
// SECTION 3: Worker Crash, Dead-Letter & Retry Recovery
// -------------------------------------------------------------
echo "\n--- SECTION 3: Worker Crash, Dead-Letter & Retry Recovery ---\n";

// Clean up any stale test markers
DB::table('jobs')->where('payload', 'like', "%marker_crash_test%")->delete();
DB::table('failed_jobs')->where('payload', 'like', "%marker_crash_test%")->delete();

$testMarker = 'marker_crash_test_' . uniqid();
TestFailingQueueJob::dispatch($testMarker);

// Check job is in 'jobs' table
$queuedCount = DB::table('jobs')->where('payload', 'like', "%{$testMarker}%")->count();
check(
    "Failing test job successfully enqueued in 'jobs' table",
    $queuedCount === 1,
    "Found {$queuedCount} job with marker {$testMarker}"
);

// Run worker once to process and fail the job
$workExit = Artisan::call('queue:work', [
    'connection' => 'database',
    '--once'     => true,
    '--queue'    => 'default',
]);

// Verify job moved to 'failed_jobs' table
$failedJob = DB::table('failed_jobs')->where('payload', 'like', "%{$testMarker}%")->first();
check(
    "Job failure moved job to 'failed_jobs' table",
    $failedJob !== null,
    $failedJob ? "Failed Job ID: {$failedJob->id} (UUID: {$failedJob->uuid}), Exception: " . substr($failedJob->exception, 0, 70) . "..." : "Job not found in failed_jobs"
);

if ($failedJob) {
    // In Laravel 11 DatabaseUuidFailedJobProvider, retry accepts the UUID
    $retryKey = $failedJob->uuid ?: (string) $failedJob->id;
    $retryExit = Artisan::call('queue:retry', ['id' => [$retryKey]]);
    
    // Check job is restored to 'jobs' table and removed from 'failed_jobs'
    $requeuedCount = DB::table('jobs')->where('payload', 'like', "%{$testMarker}%")->count();
    $stillFailed = DB::table('failed_jobs')->where('id', $failedJob->id)->count();

    check(
        "queue:retry restored failed job back to 'jobs' queue",
        $requeuedCount === 1,
        "Job restored to 'jobs' table (count: {$requeuedCount})"
    );

    check(
        "queue:retry removed restored job from 'failed_jobs' table",
        $stillFailed === 0,
        "Remaining in failed_jobs: {$stillFailed}"
    );

    // Clean up test job from 'jobs' table
    DB::table('jobs')->where('payload', 'like', "%{$testMarker}%")->delete();
}

// -------------------------------------------------------------
// SECTION 4: Live MySQL Concurrent Duplicate Webhook Delivery
// -------------------------------------------------------------
echo "\n--- SECTION 4: Live MySQL Duplicate Webhook Protection ---\n";

// Provision test workspace, channel account, conversation
$ws = Workspace::firstOrCreate(['slug' => 'queue-audit-ws'], [
    'name'      => 'Queue Audit Workspace',
    'currency'  => 'BDT',
    'is_active' => true,
]);

$channel = Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook Messenger', 'is_active' => true]);

$acct = ChannelAccount::firstOrCreate([
    'workspace_id' => $ws->id,
    'channel_id'   => $channel->id,
    'external_id'  => 'fb_audit_page_001',
], [
    'name'         => 'Audit Page',
    'access_token' => 'token_audit',
    'is_active'    => true,
]);

$conv = Conversation::firstOrCreate([
    'channel_account_id' => $acct->id,
    'external_user_id'   => 'user_audit_live_01',
], [
    'customer_name'  => 'Audit User',
    'status'         => 'open',
    'last_direction' => 'inbound',
]);

$externalMid = 'mid.live.audit.' . uniqid();
$payload = [
    'external_user_id'    => 'user_audit_live_01',
    'external_message_id' => $externalMid,
    'text'                => 'Live duplicate webhook payload test',
    'type'                => 'text',
];

// Execute 1st delivery
$job1 = new ReceiveMessengerWebhookJob($acct->id, 'facebook', $payload);
$job1->handle();

// Execute 2nd duplicate delivery
$job2 = new ReceiveMessengerWebhookJob($acct->id, 'facebook', $payload);
$job2->handle();

$msgCount = Message::where('conversation_id', $conv->id)
    ->where('external_message_id', $externalMid)
    ->count();

check(
    "Live MySQL deduplication preserves exactly 1 message",
    $msgCount === 1,
    "Message count with MID {$externalMid}: {$msgCount}"
);

// Verify direct DB unique constraint enforcement
$directDbBlocked = false;
try {
    Message::create([
        'conversation_id'     => $conv->id,
        'external_message_id' => $externalMid,
        'direction'           => 'inbound',
        'type'                => 'text',
        'body'                => 'Direct duplicate insertion bypassing application check',
    ]);
} catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
    $directDbBlocked = true;
}

check(
    "Live MySQL messages_conv_external_id_unique constraint blocks duplicate insertion",
    $directDbBlocked,
    "Schema-level unique constraint rejected duplicate key"
);

// Clean up test message and conversation
Message::where('conversation_id', $conv->id)->delete();
$conv->delete();

// -------------------------------------------------------------
// SECTION 5: Task Scheduler Live Execution
// -------------------------------------------------------------
echo "\n--- SECTION 5: Task Scheduler Live Execution ---\n";

$scheduleStartTime = microtime(true);
$scheduleExit = Artisan::call('schedule:run');
$scheduleDuration = round((microtime(true) - $scheduleStartTime) * 1000, 2);
$scheduleOutput = trim(Artisan::output());

check(
    "Artisan schedule:run executed successfully",
    $scheduleExit === 0,
    "Exit code: {$scheduleExit}, Execution time: {$scheduleDuration}ms\n         Output: " . ($scheduleOutput ?: "No scheduled tasks due at this second (standard cron behavior)")
);

// Also list scheduled tasks
Artisan::call('schedule:list');
$listOutput = Artisan::output();
$taskCount = substr_count($listOutput, 'App\\') + substr_count($listOutput, 'Closure') + substr_count($listOutput, 'queue:') + substr_count($listOutput, 'retention:');

check(
    "Schedule registry contains active tasks",
    $taskCount > 0,
    "Found {$taskCount} scheduled tasks registered in Kernel"
);

echo "\n=================================================================\n";
echo " AUDIT SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "=================================================================\n";

exit($failCount === 0 ? 0 : 1);
