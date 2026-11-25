<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Redis;

echo "=== Testing Redis Lease Ownership, Stale TTL, Duplicate Release & Rollback ===\n";

$luaAcquire = <<<'LUA'
local counter_key = KEYS[1]
local lease_key = KEYS[2]
local limit = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2])

local current = tonumber(redis.call('get', counter_key) or '0')
if current >= limit then
    return {0, current}
else
    local next_val = redis.call('incr', counter_key)
    redis.call('expire', counter_key, ttl)
    redis.call('set', lease_key, '1', 'EX', ttl)
    return {1, next_val}
end
LUA;

$luaRelease = <<<'LUA'
local counter_key = KEYS[1]
local lease_key = KEYS[2]

local lease_existed = redis.call('del', lease_key)
if lease_existed == 1 then
    local current = tonumber(redis.call('get', counter_key) or '0')
    if current > 0 then
        local next_val = redis.call('decr', counter_key)
        if next_val < 0 then
            redis.call('set', counter_key, 0)
            return 0
        end
        return next_val
    else
        redis.call('set', counter_key, 0)
        return 0
    end
else
    local current = tonumber(redis.call('get', counter_key) or '0')
    return current
end
LUA;

$counterKey = 'test_in_flight_ws_1';
Redis::connection()->del($counterKey);

// --- 1. Normal Acquire & Release ---
echo "\n--- 1. Normal Acquire & Release ---\n";
$lease1 = 'req_1_uuid';
$lease1Key = "{$counterKey}:lease:{$lease1}";
Redis::connection()->del($lease1Key);

$resAcq1 = Redis::connection()->eval($luaAcquire, 2, $counterKey, $lease1Key, 5, 60);
echo "Req 1 acquire: allowed={$resAcq1[0]}, current={$resAcq1[1]}\n";
assert($resAcq1[0] === 1 && $resAcq1[1] === 1);

$resRel1 = Redis::connection()->eval($luaRelease, 2, $counterKey, $lease1Key);
echo "Req 1 release: current={$resRel1}\n";
assert($resRel1 === 0);

// --- 2. Duplicate Release Protection ---
echo "\n--- 2. Duplicate Release Protection ---\n";
$leaseDup = 'req_dup_uuid';
$leaseDupKey = "{$counterKey}:lease:{$leaseDup}";
Redis::connection()->del($leaseDupKey);

// Acquire
Redis::connection()->eval($luaAcquire, 2, $counterKey, $leaseDupKey, 5, 60);
assert((int)Redis::connection()->get($counterKey) === 1);

// First release
$rel1 = Redis::connection()->eval($luaRelease, 2, $counterKey, $leaseDupKey);
echo "First release: counter={$rel1}\n";
assert($rel1 === 0);

// Duplicate release (must be idempotent, not decrement below 0 or decrement other requests)
$rel2 = Redis::connection()->eval($luaRelease, 2, $counterKey, $leaseDupKey);
echo "Duplicate release: counter={$rel2}\n";
assert($rel2 === 0);
assert((int)Redis::connection()->get($counterKey) === 0);

// --- 3. Stale TTL Expiry Protection (The Slot Stealing Bug) ---
echo "\n--- 3. Stale TTL Expiry Protection ---\n";
$leaseSlow = 'req_slow_uuid';
$leaseSlowKey = "{$counterKey}:lease:{$leaseSlow}";

// Req Slow acquires slot with 1-second TTL
Redis::connection()->eval($luaAcquire, 2, $counterKey, $leaseSlowKey, 5, 1);
echo "Slow request acquired with 1s TTL. Counter = " . Redis::connection()->get($counterKey) . "\n";

// Wait 1.5 seconds for Req Slow lease to expire
echo "Sleeping 1.5s to let slow request lease expire...\n";
usleep(1500000);

// Now Req Fast arrives and acquires slot with 60s TTL
$leaseFast = 'req_fast_uuid';
$leaseFastKey = "{$counterKey}:lease:{$leaseFast}";
Redis::connection()->set($counterKey, 0); // simulate counter refresh if counter expired
$resFast = Redis::connection()->eval($luaAcquire, 2, $counterKey, $leaseFastKey, 5, 60);
echo "Fast request acquired. Counter = {$resFast[1]}\n";
assert($resFast[1] === 1);

// Now Req Slow finally wakes up and tries to release its expired slot!
$resSlowRelease = Redis::connection()->eval($luaRelease, 2, $counterKey, $leaseSlowKey);
echo "Slow request tries to release expired lease: counter = {$resSlowRelease}\n";
$currentInRedis = (int) Redis::connection()->get($counterKey);
echo "Counter in Redis after slow release: {$currentInRedis}\n";

// CRITICAL ASSERTION: The counter must STILL be 1 because Fast request is still running!
// Slow request must NOT have stolen/decremented Fast request's slot!
assert($currentInRedis === 1, "CRITICAL: Slow request must not decrement Fast request's active slot!");
echo "SUCCESS: Stale TTL release did NOT steal active request's slot!\n";

// Clean up Fast request
Redis::connection()->eval($luaRelease, 2, $counterKey, $leaseFastKey);
assert((int)Redis::connection()->get($counterKey) === 0);

// Clean up
Redis::connection()->del($counterKey);
Redis::connection()->del($leaseSlowKey);
Redis::connection()->del($leaseFastKey);

echo "\n=== All Redis Lease Ownership & Invariant Tests Passed! ===\n";
