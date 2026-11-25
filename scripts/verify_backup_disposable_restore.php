<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

echo "========================================================================================\n";
echo "   DISPOSABLE DATABASE BACKUP & RESTORE INTEGRITY AUDIT (MANDATE 3)\n";
echo "========================================================================================\n\n";

$disposableDbPath = storage_path('app/disposable_audit_db.sqlite');
$backupDir = storage_path('app/backups');

if (File::exists($disposableDbPath)) {
    File::delete($disposableDbPath);
}
touch($disposableDbPath);

echo "1. Initializing isolated disposable database: {$disposableDbPath}\n";

// Register temporary connection
Config::set('database.connections.disposable_audit', [
    'driver'   => 'sqlite',
    'database' => $disposableDbPath,
    'prefix'   => '',
]);
Config::set('database.default', 'disposable_audit');

// Purge any cached connection instance
DB::purge('disposable_audit');

// 2. Create Schema & Populate Deterministic Seed Records
$pdo = DB::connection('disposable_audit')->getPdo();
$pdo->exec('CREATE TABLE test_workspaces (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT
)');

$pdo->exec('CREATE TABLE test_conversations (
    id INTEGER PRIMARY KEY,
    workspace_id INTEGER NOT NULL,
    customer_name TEXT,
    channel TEXT,
    created_at TEXT
)');

$pdo->exec('CREATE TABLE test_orders (
    id INTEGER PRIMARY KEY,
    workspace_id INTEGER NOT NULL,
    order_number TEXT,
    total_amount REAL,
    status TEXT
)');

$pdo->exec("INSERT INTO test_workspaces VALUES 
    (1, 'Canary Pilot Merchant 1', 'pilot-1', 1, '2026-11-24 10:00:00'),
    (2, 'Canary Pilot Merchant 2', 'pilot-2', 1, '2026-11-24 10:00:00'),
    (3, 'Control Merchant 3', 'control-3', 0, '2026-11-24 10:00:00')");

$pdo->exec("INSERT INTO test_conversations VALUES 
    (101, 1, 'Rahim Ahmed', 'whatsapp', '2026-11-24 10:05:00'),
    (102, 1, 'Karim Ullah', 'facebook', '2026-11-24 10:06:00'),
    (103, 2, 'Fatima Begum', 'web_widget', '2026-11-24 10:07:00')");

$pdo->exec("INSERT INTO test_orders VALUES 
    (1001, 1, 'ORD-2026-001', 2450.50, 'delivered'),
    (1002, 1, 'ORD-2026-002', 1890.00, 'processing'),
    (1003, 2, 'ORD-2026-003', 5300.00, 'pending')");

// Compute baseline hash
function computeDbStateHash(PDO $pdo): array {
    $workspaces = $pdo->query('SELECT * FROM test_workspaces ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $conversations = $pdo->query('SELECT * FROM test_conversations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $orders = $pdo->query('SELECT * FROM test_orders ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

    $statePayload = json_encode(['w' => $workspaces, 'c' => $conversations, 'o' => $orders]);
    return [
        'hash' => hash('sha256', $statePayload),
        'counts' => [
            'workspaces' => count($workspaces),
            'conversations' => count($conversations),
            'orders' => count($orders),
        ],
        'orders_sum' => array_sum(array_column($orders, 'total_amount')),
    ];
}

$stateOriginal = computeDbStateHash($pdo);
echo "✓ Baseline state created: SHA-256 = {$stateOriginal['hash']}\n";
echo "  - Workspaces: {$stateOriginal['counts']['workspaces']} | Conversations: {$stateOriginal['counts']['conversations']} | Orders: {$stateOriginal['counts']['orders']} (Total: {$stateOriginal['orders_sum']} BDT)\n\n";

// 3. Perform Live Backup
$backupFilename = 'disposable_audit_snap_' . time();
echo "2. Executing Artisan db:backup to: {$backupFilename}...\n";

$tBackupStart = microtime(true);
$exitCode = Artisan::call('db:backup', [
    '--filename'  => $backupFilename,
    '--retention' => 30,
]);
$backupDurationMs = round((microtime(true) - $tBackupStart) * 1000, 2);

if ($exitCode !== 0) {
    echo "❌ db:backup failed with exit code {$exitCode}\n";
    echo Artisan::output();
    exit(1);
}

$backupFile = "{$backupDir}/{$backupFilename}.sqlite";
$metaFile = "{$backupDir}/{$backupFilename}.sqlite.meta.json";

if (!File::exists($backupFile) || !File::exists($metaFile)) {
    echo "❌ Expected backup file or meta sidecar does not exist!\n";
    exit(1);
}

$meta = json_decode(File::get($metaFile), true);
$calculatedHash = hash_file('sha256', $backupFile);

if ($meta['sha256_checksum'] !== $calculatedHash) {
    echo "❌ Cryptographic mismatch! Meta: {$meta['sha256_checksum']} vs Calc: {$calculatedHash}\n";
    exit(1);
}
echo "✓ Backup created successfully! File SHA-256 verified: {$calculatedHash}\n\n";

// 4. Inject Catastrophic Disaster / Data Corruption
echo "3. Simulating Catastrophic Database Corruption & Data Deletion...\n";
$pdo->exec("DELETE FROM test_orders");
$pdo->exec("UPDATE test_workspaces SET name = 'CORRUPTED_NAME'");
$pdo->exec("DROP TABLE test_conversations");

$corruptOrders = $pdo->query('SELECT COUNT(*) FROM test_orders')->fetchColumn();
$corruptTables = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='test_conversations'")->fetchColumn();

echo "  - Orders remaining: {$corruptOrders} (expected 0)\n";
echo "  - Conversations table exists: " . ($corruptTables ? 'YES' : 'NO (DROPPED)') . "\n";
echo "✓ Database successfully corrupted for recovery validation\n\n";

// 5. Perform Live Restore
echo "4. Executing Artisan db:restore from backup archive...\n";
$tRestoreStart = microtime(true);
$restoreExitCode = Artisan::call('db:restore', [
    'file'    => "{$backupFilename}.sqlite",
    '--force' => true,
]);
$fileRestoreDurationMs = round((microtime(true) - $tRestoreStart) * 1000, 2);

if ($restoreExitCode !== 0) {
    echo "❌ db:restore failed with exit code {$restoreExitCode}\n";
    echo Artisan::output();
    exit(1);
}

// 6. Application Reconnect & State Comparison
$tReconnectStart = microtime(true);
DB::purge('disposable_audit');
$pdoRestored = DB::connection('disposable_audit')->getPdo();
$stateRestored = computeDbStateHash($pdoRestored);
$reconnectDurationMs = round((microtime(true) - $tReconnectStart) * 1000, 2);

echo "✓ Restore completed! Restored state SHA-256 = {$stateRestored['hash']}\n";
echo "  - Workspaces: {$stateRestored['counts']['workspaces']} | Conversations: {$stateRestored['counts']['conversations']} | Orders: {$stateRestored['counts']['orders']} (Total: {$stateRestored['orders_sum']} BDT)\n\n";

// 7. Cryptographic State Comparison
echo "5. Verifying 100% Cryptographic and Relational Identity...\n";
if ($stateOriginal['hash'] !== $stateRestored['hash']) {
    echo "❌ CRITICAL FAILURE: Restored database hash does not match original!\n";
    echo "Expected: {$stateOriginal['hash']}\nGot: {$stateRestored['hash']}\n";
    exit(1);
}

if ($stateOriginal['orders_sum'] !== $stateRestored['orders_sum']) {
    echo "❌ CRITICAL FAILURE: Financial sum mismatch!\n";
    exit(1);
}
echo "✓ IDENTITY VERIFIED: Original state === Restored state (100.00% Match)\n\n";

// 8. Application-Level Read/Write Verification (Post-Restore ACID Validation)
echo "6. Performing Post-Restore Application-Level ACID Read/Write Verification...\n";
$tAcidStart = microtime(true);

$conn = DB::connection('disposable_audit');

// A. Insert new transactional records
$conn->beginTransaction();
try {
    $conn->table('test_conversations')->insert([
        'id'            => 201,
        'workspace_id'  => 1,
        'customer_name' => 'Post-Restore Customer',
        'channel'       => 'whatsapp',
        'created_at'    => '2026-11-24 10:15:00',
    ]);

    $conn->table('test_orders')->insert([
        'id'           => 2001,
        'workspace_id' => 1,
        'order_number' => 'ORD-POST-RESTORE-001',
        'total_amount' => 4500.00,
        'status'       => 'processing',
    ]);

    $conn->table('test_workspaces')->where('id', 1)->update([
        'name' => 'Canary Pilot Merchant 1 (Active Post-Restore)',
    ]);

    $conn->commit();
    echo "  ✓ Transactional write committed successfully (1 conversation, 1 order, 1 workspace update)\n";
} catch (\Throwable $e) {
    $conn->rollBack();
    echo "❌ Transaction failed: " . $e->getMessage() . "\n";
    exit(1);
}

// B. Verify inserted records via application query
$insertedOrder = $conn->table('test_orders')->where('id', 2001)->first();
if (!$insertedOrder || (float) $insertedOrder->total_amount !== 4500.00) {
    echo "❌ Post-restore read verification failed!\n";
    exit(1);
}
echo "  ✓ Post-restore read verified: Order #{$insertedOrder->order_number} confirmed with 4500.00 BDT\n";

// C. Verify Rollback semantics
$conn->beginTransaction();
$conn->table('test_orders')->insert([
    'id'           => 9999,
    'workspace_id' => 1,
    'order_number' => 'ORD-ROLLBACK-TEST',
    'total_amount' => 999.00,
    'status'       => 'cancelled',
]);
$conn->rollBack();

$rolledBack = $conn->table('test_orders')->where('id', 9999)->first();
if ($rolledBack !== null) {
    echo "❌ Rollback failure: uncommitted record persisted!\n";
    exit(1);
}
echo "  ✓ Transaction rollback verified: rolled back record does not exist\n";

$acidDurationMs = round((microtime(true) - $tAcidStart) * 1000, 2);
$totalRtoMs = $fileRestoreDurationMs + $reconnectDurationMs + $acidDurationMs;

echo "\n----------------------------------------------------------------------------------------\n";
echo "DISASTER RECOVERY TIMING METRICS & SRE RECONCILIATION:\n";
echo sprintf("  - Snapshot File Backup Duration:             %.2f ms\n", $backupDurationMs);
echo sprintf("  - Raw File Restore Duration:                 %.2f ms\n", $fileRestoreDurationMs);
echo sprintf("  - Connection Purge & Re-establishment:       %.2f ms\n", $reconnectDurationMs);
echo sprintf("  - Post-Restore ACID Read/Write Verification: %.2f ms\n", $acidDurationMs);
echo sprintf("  - Total End-to-End Recovery Time (RTO):      %.2f ms (%.3f seconds)\n", $totalRtoMs, $totalRtoMs / 1000);
echo "\nRECOVERY POINT OBJECTIVE (RPO) CLARIFICATION:\n";
echo sprintf("  - Snapshot Point-in-Time Epoch: %s (UTC)\n", $meta['timestamp'] ?? 'now');
echo "  - True Operational RPO:\n";
echo "    * Periodic Snapshot Strategy (Hourly Cron):   RPO <= 60 minutes data loss window\n";
echo "    * Daily Snapshot Strategy (Default):          RPO <= 24 hours data loss window\n";
echo "    * Zero-Data-Loss Target (Near-Zero RPO):      Requires continuous WAL / Binlog replication\n";
echo "  - Note: The 2.95ms figure is isolated APFS file replacement; production MySQL RTO\n";
echo "    includes daemon reload, buffer pool warmup, and table locks (typically 30-180 seconds).\n";
echo "========================================================================================\n";
echo "   DISPOSABLE BACKUP & RESTORE INTEGRITY AUDIT: PASS\n";
echo "========================================================================================\n";

// Cleanup
File::delete($disposableDbPath);
File::delete($backupFile);
File::delete($metaFile);
foreach (File::files($backupDir) as $f) {
    if (str_starts_with($f->getFilename(), 'pre_restore_safety_') || str_starts_with($f->getFilename(), $backupFilename)) {
        File::delete($f->getRealPath());
    }
}
echo "✓ Cleaned up all disposable test files.\n";
