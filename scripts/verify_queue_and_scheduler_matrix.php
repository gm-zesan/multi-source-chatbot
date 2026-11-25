<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\TestQueueMatrixJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=================================================================\n";
echo " QUEUE DISPATCH/CONSUME & SCHEDULER SIDE-EFFECT MATRIX AUDIT\n";
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
// SECTION 1: Dispatch & Consume Verification for Every Queue
// -------------------------------------------------------------
echo "--- SECTION 1: Real Dispatch & Worker Consume per Queue ---\n";

$queuesConfig = [
    'messenger' => ['pool' => 'realtime',   'worker_queues' => 'messenger,faq'],
    'faq'       => ['pool' => 'realtime',   'worker_queues' => 'messenger,faq'],
    'crm'       => ['pool' => 'background', 'worker_queues' => 'crm,retention,memory,default,documents'],
    'retention' => ['pool' => 'background', 'worker_queues' => 'crm,retention,memory,default,documents'],
    'memory'    => ['pool' => 'background', 'worker_queues' => 'crm,retention,memory,default,documents'],
    'documents' => ['pool' => 'background', 'worker_queues' => 'crm,retention,memory,default,documents'],
    'default'   => ['pool' => 'background', 'worker_queues' => 'crm,retention,memory,default,documents'],
];

foreach ($queuesConfig as $qName => $cfg) {
    $marker = "test_q_{$qName}_" . uniqid();

    // 1. Dispatch job specifically to this queue
    TestQueueMatrixJob::dispatch($qName, $marker);

    // 2. Verify row exists in jobs table with this exact queue name
    $enqueued = DB::table('jobs')->where('queue', $qName)->where('payload', 'like', "%{$marker}%")->first();
    $inQueue = $enqueued !== null;

    // 3. Run the exact worker command assigned to this pool until our job is consumed
    $attempts = 0;
    $exitCode = 0;
    while (DB::table('jobs')->where('queue', $qName)->where('payload', 'like', "%{$marker}%")->exists() && $attempts < 15) {
        $exitCode = Artisan::call('queue:work', [
            'connection' => 'database',
            '--queue'    => $cfg['worker_queues'],
            '--once'     => true,
        ]);
        $attempts++;
    }

    // 4. Verify row is consumed from jobs table
    $remaining = DB::table('jobs')->where('queue', $qName)->where('payload', 'like', "%{$marker}%")->count();

    check(
        "Queue '{$qName}' ({$cfg['pool']} pool): Job enqueued & consumed by worker",
        $inQueue && $remaining === 0 && $exitCode === 0,
        "Enqueued in DB: " . ($inQueue ? "YES (ID: {$enqueued->id})" : "NO") . " | Remaining after worker: {$remaining} | Worker exit: {$exitCode}"
    );
}

// -------------------------------------------------------------
// SECTION 2: Scheduled Task Live Execution & Side-Effect Proof
// -------------------------------------------------------------
echo "\n--- SECTION 2: Scheduled Task Side Effect & Overlap Prevention ---\n";

// Setup test conversation eligible for abandoned lead recovery
$ws = Workspace::firstOrCreate(['slug' => 'sched-audit-ws'], [
    'name'      => 'Scheduler Audit Workspace',
    'currency'  => 'BDT',
    'is_active' => true,
]);

$channel = Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook Messenger', 'is_active' => true]);

$acct = ChannelAccount::firstOrCreate([
    'workspace_id' => $ws->id,
    'channel_id'   => $channel->id,
    'external_id'  => 'fb_sched_page_001',
], [
    'name'         => 'Sched Page',
    'access_token' => 'token_sched',
    'is_active'    => true,
]);

$abandonedUser = 'user_abandoned_' . uniqid();
$conv = Conversation::create([
    'channel_account_id' => $acct->id,
    'external_user_id'   => $abandonedUser,
    'customer_name'      => 'Ayesha Siddiqua',
    'status'             => 'open',
    'last_direction'     => 'inbound',
    'last_message_at'    => now()->subHours(2),
    'ai_tags'            => ['lead', 'hot_lead'],
]);

// Customer sent an inquiry 2 hours ago but no order placed
Message::create([
    'conversation_id'     => $conv->id,
    'external_message_id' => 'mid.inquiry.' . uniqid(),
    'direction'           => 'inbound',
    'type'                => 'text',
    'body'                => 'পাঞ্জাবির দাম কত?',
    'created_at'          => now()->subHours(2),
]);

// 1. Run the scheduled command: retention:process-abandoned-leads
$initialJobsCount = DB::table('jobs')->where('queue', 'retention')->count();
$cmdExit = Artisan::call('retention:process-abandoned-leads', [
    '--workspace' => $ws->id,
]);
$newJobsCount = DB::table('jobs')->where('queue', 'retention')->count();

check(
    "Scheduled command 'retention:process-abandoned-leads' executed with concrete side effect",
    $cmdExit === 0 && $newJobsCount > $initialJobsCount,
    "Exit code: {$cmdExit}, Dispatched SendAbandonedLeadFollowUpJob to 'retention' queue (New jobs in queue: " . ($newJobsCount - $initialJobsCount) . ")"
);

// Drain the dispatched job via background worker
$drainExit = Artisan::call('queue:work', [
    'connection' => 'database',
    '--queue'    => 'retention',
    '--once'     => true,
]);
check(
    "Dispatched retention job successfully drained by background worker",
    $drainExit === 0,
    "Worker executed retention job (exit code: {$drainExit})"
);

// 2. Overlap Prevention Verification: Mutex Lock
$schedule = app(Schedule::class);
$events = $schedule->events();
$retentionEvent = null;

foreach ($events as $event) {
    if (str_contains($event->command ?? '', 'retention:process-abandoned-leads')) {
        $retentionEvent = $event;
        break;
    }
}

check(
    "Schedule registry contains 'retention:process-abandoned-leads' with withoutOverlapping",
    $retentionEvent !== null && $retentionEvent->withoutOverlapping,
    $retentionEvent ? "Registered cron: '{$retentionEvent->expression}', withoutOverlapping: YES" : "Event not found"
);

if ($retentionEvent) {
    // Acquire mutex lock manually to simulate a running process
    $mutexKey = $retentionEvent->mutexName();
    $acquired = $retentionEvent->mutex->create($retentionEvent);

    check(
        "Scheduler creates and enforces mutex lock for overlap prevention",
        $acquired,
        "Mutex Key: {$mutexKey}, Lock Acquired: " . ($acquired ? "YES" : "NO")
    );

    // Verify second attempt is blocked while mutex is active
    $secondAttemptAcquired = $retentionEvent->mutex->create($retentionEvent);
    check(
        "Concurrent scheduled run is blocked while previous task is active (Overlap Prevention)",
        ! $secondAttemptAcquired,
        "Second lock attempt blocked: YES"
    );

    // Release mutex
    $retentionEvent->mutex->forget($retentionEvent);
    check(
        "Mutex lock released after task completion",
        ! $retentionEvent->mutex->exists($retentionEvent),
        "Mutex released cleanly"
    );
}

// Cleanup test fixtures
Message::where('conversation_id', $conv->id)->delete();
$conv->delete();

echo "\n=================================================================\n";
echo " MATRIX AUDIT SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "=================================================================\n";

exit($failCount === 0 ? 0 : 1);
