<?php
/**
 * ONE-TIME FIX: Reset notification sync_status to 0 on this server
 * Run on BOTH localhost and Hostinger to force all notifications to re-sync.
 * DELETE this file after running.
 * 
 * Usage:
 *   Localhost: http://localhost/EndDev/fix_notif_sync.php
 *   Hostinger: https://srv1412737.hstgr.cloud/fix_notif_sync.php
 */

require_once 'db_connection.php';

$results = [];

// 1. Ensure sync_status column exists with DEFAULT 0
$check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'sync_status'");
if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE notifications ADD COLUMN sync_status TINYINT(1) DEFAULT 0");
    $results[] = "✅ Added sync_status column (DEFAULT 0)";
} else {
    $col = $check->fetch_assoc();
    $results[] = "ℹ️ sync_status column already exists. Current default: " . $col['Default'];
    
    // Fix default if it was 1
    $conn->query("ALTER TABLE notifications MODIFY COLUMN sync_status TINYINT(1) DEFAULT 0");
    $results[] = "✅ Updated sync_status default to 0";
}

// 2. Ensure last_sync column exists
$check2 = $conn->query("SHOW COLUMNS FROM notifications LIKE 'last_sync'");
if ($check2 && $check2->num_rows === 0) {
    $conn->query("ALTER TABLE notifications ADD COLUMN last_sync DATETIME DEFAULT NULL");
    $results[] = "✅ Added last_sync column";
}

// 3. Reset ALL notifications to sync_status = 0 so they get re-synced
$res = $conn->query("UPDATE notifications SET sync_status = 0 WHERE sync_status != 0 OR sync_status IS NULL");
$updated = $conn->affected_rows;
$results[] = "✅ Reset {$updated} notifications to sync_status = 0 (will be re-synced)";

// 4. Show current state
$count_res = $conn->query("SELECT sync_status, COUNT(*) as cnt FROM notifications GROUP BY sync_status");
$counts = [];
while ($r = $count_res->fetch_assoc()) {
    $counts[] = "sync_status={$r['sync_status']}: {$r['cnt']} rows";
}
$results[] = "📊 Current state: " . implode(", ", $counts);

$server = $_SERVER['HTTP_HOST'] ?? 'unknown';
?>
<!DOCTYPE html>
<html>
<head><title>Notification Sync Fix</title>
<style>body{font-family:monospace;padding:20px;background:#1a1a1a;color:#e0e0e0;}
.ok{color:#4ade80;}.warn{color:#facc15;}
pre{background:#2a2a2a;padding:15px;border-radius:8px;}</style>
</head>
<body>
<h2>🔧 Notification Sync Fix — Server: <?= htmlspecialchars($server) ?></h2>
<pre>
<?php foreach ($results as $r): ?>
<span class="ok"><?= htmlspecialchars($r) ?></span>
<?php endforeach; ?>

<span class="warn">⚠️  DELETE this file (fix_notif_sync.php) after running!</span>
</pre>
<p>Next steps:
<ol>
<li>Run this page on <strong>Hostinger</strong> too: <a href="#" style="color:#60a5fa">https://your-hostinger-url/fix_notif_sync.php</a></li>
<li>Wait ~30 seconds for run_sync.php to run a sync cycle</li>
<li>Delete this file from both servers</li>
</ol>
</p>
</body>
</html>
