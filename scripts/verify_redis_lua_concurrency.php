<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Redis;

echo "=== Testing Redis Lua Concurrency & Quota Safety ===" . PHP_EOL;

$luaAcquireQuota = <<<'LUA'
local key = KEYS[1]
local limit = tonumber(ARGV[1])
local current = tonumber(redis.call('get', key) or '0')
if current >= limit then
    return {0, current}
else
    local next_val = redis.call('incr', key)
    return {1, next_val}
end
LUA;

$luaRollbackQuota = <<<'LUA'
local key = KEYS[1]
local current = tonumber(redis.call('get', key) or '0')
if current > 0 then
    local next_val = redis.call('decr', key)
    if next_val < 0 then
        redis.call('set', key, 0)
        return 0
    end
    return next_val
else
    redis.call('set', key, 0)
    return 0
end
LUA;

$luaAcquireSlot = <<<'LUA'
local key = KEYS[1]
local limit = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2])
local current = tonumber(redis.call('get', key) or '0')
if current >= limit then
    return {0, current}
else
    local next_val = redis.call('incr', key)
    redis.call('expire', key, ttl)
    return {1, next_val}
end
LUA;

$luaReleaseSlot = <<<'LUA'
local key = KEYS[1]
local current = tonumber(redis.call('get', key) or '0')
if current > 0 then
    local next_val = redis.call('decr', key)
    if next_val < 0 then
        redis.call('set', key, 0)
        return 0
    end
    return next_val
else
    redis.call('set', key, 0)
    return 0
end
LUA;

// 1. Quota 99/100 Boundary Test
$testQuotaKey = 'test_lua_quota_ws_1';
Redis::connection()->del($testQuotaKey);
Redis::connection()->set($testQuotaKey, 99);

$res100 = Redis::connection()->eval($luaAcquireQuota, 1, $testQuotaKey, 100);
echo "Acquire 100th slot: allowed={$res100[0]}, current={$res100[1]}" . PHP_EOL;
assert($res100[0] === 1 && $res100[1] === 100, "100th slot must be accepted");

$res101 = Redis::connection()->eval($luaAcquireQuota, 1, $testQuotaKey, 100);
echo "Acquire 101st slot: allowed={$res101[0]}, current={$res101[1]}" . PHP_EOL;
assert($res101[0] === 0 && $res101[1] === 100, "101st slot must be rejected without incrementing");

$valInRedis = (int) Redis::connection()->get($testQuotaKey);
echo "Raw key in Redis after 101st attempt: {$valInRedis}" . PHP_EOL;
assert($valInRedis === 100, "Raw key must remain strictly at 100, never 101");

// Rollback test
Redis::connection()->eval($luaRollbackQuota, 1, $testQuotaKey);
$valAfterRollback = (int) Redis::connection()->get($testQuotaKey);
echo "Raw key after 1 rollback: {$valAfterRollback}" . PHP_EOL;
assert($valAfterRollback === 99, "Counter after rollback must be 99");

// Underflow test
Redis::connection()->set($testQuotaKey, 0);
Redis::connection()->eval($luaRollbackQuota, 1, $testQuotaKey);
$valAfterUnderflow = (int) Redis::connection()->get($testQuotaKey);
echo "Raw key after underflow rollback attempt: {$valAfterUnderflow}" . PHP_EOL;
assert($valAfterUnderflow === 0, "Counter must never go negative (min 0)");

// 2. Concurrency 5-Slot Live Test
$testSlotKey = 'test_lua_in_flight_ws_1';
Redis::connection()->del($testSlotKey);

for ($i = 1; $i <= 5; $i++) {
    $res = Redis::connection()->eval($luaAcquireSlot, 1, $testSlotKey, 5, 60);
    assert($res[0] === 1 && $res[1] === $i, "Slot {$i} must be acquired");
}
echo "Successfully acquired 5 slots sequentially in Redis." . PHP_EOL;

// 6th attempt must be rejected
$res6 = Redis::connection()->eval($luaAcquireSlot, 1, $testSlotKey, 5, 60);
echo "Attempt slot 6: allowed={$res6[0]}, current={$res6[1]}" . PHP_EOL;
assert($res6[0] === 0 && $res6[1] === 5, "Slot 6 must be rejected and counter must stay 5");

$rawSlots = (int) Redis::connection()->get($testSlotKey);
echo "Raw in-flight slots in Redis: {$rawSlots}" . PHP_EOL;
assert($rawSlots === 5, "Counter must be 5");

// Release all 5 slots
for ($i = 1; $i <= 5; $i++) {
    Redis::connection()->eval($luaReleaseSlot, 1, $testSlotKey);
}
$remSlots = (int) Redis::connection()->get($testSlotKey);
echo "Raw in-flight slots after releasing 5: {$remSlots}" . PHP_EOL;
assert($remSlots === 0, "Counter must return to 0");

// Underflow release test (e.g. key expired after 60s)
Redis::connection()->del($testSlotKey);
$resUnderflow = Redis::connection()->eval($luaReleaseSlot, 1, $testSlotKey);
$remUnderflow = (int) Redis::connection()->get($testSlotKey);
echo "Raw in-flight slots after releasing expired key: {$remUnderflow}" . PHP_EOL;
assert($remUnderflow === 0, "Counter must remain 0, never -1");

// Clean up
Redis::connection()->del($testQuotaKey);
Redis::connection()->del($testSlotKey);

echo "=== All Redis Lua Safety Assertions Passed Successfully! ===" . PHP_EOL;
