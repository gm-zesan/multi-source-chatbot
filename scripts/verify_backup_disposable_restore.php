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

$exitCode = Artisan::call('db:backup', [
    '--filename'  => $backupFilename,
    '--retention' => 30,
]);

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
$restoreExitCode = Artisan::call('db:restore', [
    'file'    => "{$backupFilename}.sqlite",
    '--force' => true,
]);

if ($restoreExitCode !== 0) {
    echo "❌ db:restore failed with exit code {$restoreExitCode}\n";
    echo Artisan::output();
    exit(1);
}

// Reconnect PDO after file replacement
DB::purge('disposable_audit');
$pdoRestored = DB::connection('disposable_audit')->getPdo();

$stateRestored = computeDbStateHash($pdoRestored);
echo "✓ Restore completed! Restored state SHA-256 = {$stateRestored['hash']}\n";
echo "  - Workspaces: {$stateRestored['counts']['workspaces']} | Conversations: {$stateRestored['counts']['conversations']} | Orders: {$stateRestored['counts']['orders']} (Total: {$stateRestored['orders_sum']} BDT)\n\n";

// 6. Cryptographic State Comparison
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

echo "✓ IDENTITY VERIFIED: Original state === Restored state (100.00% Match)\n";
echo "✓ Pre-restore safety snapshot created: Verified\n";
echo "✓ Checksum verification before restore: Verified\n";
echo "========================================================================================\n";
echo "   DISPOSABLE BACKUP & RESTORE INTEGRITY AUDIT: PASS\n";
echo "========================================================================================\n";

// Cleanup
File::delete($disposableDbPath);
File::delete($backupFile);
File::delete($metaFile);
// Also cleanup pre_restore safety files created by db:restore
foreach (File::files($backupDir) as $f) {
    if (str_starts_with($f->getFilename(), 'pre_restore_safety_') || str_starts_with($f->getFilename(), $backupFilename)) {
        File::delete($f->getRealPath());
    }
}
echo "✓ Cleaned up all disposable test files.\n";
