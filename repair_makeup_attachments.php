<?php
/**
 * repair_makeup_attachments.php
 * 
 * One-time repair script. Resets sync_status = 0 for all makeup_class_requests
 * that have an attachment_path so the next sync cycle will re-upload the physical
 * files to the VPS (Hostinger).
 * 
 * Run this ONCE on localhost: http://localhost/EndDev/repair_makeup_attachments.php
 * Then trigger a sync: http://localhost/EndDev/api/run_sync.php
 */

require_once __DIR__ . '/db_connection.php';

$results = [];

// Find all makeup records with an attachment that haven't been properly synced
$res = $conn->query("
    SELECT id, employee_id, attachment_path, sync_status
    FROM makeup_class_requests
    WHERE attachment_path IS NOT NULL AND attachment_path != ''
");

if (!$res) {
    die("Query failed: " . $conn->error);
}

$found = 0;
$missing_files = [];
$reset_ids = [];

while ($row = $res->fetch_assoc()) {
    $found++;
    $db_path   = ltrim($row['attachment_path'], '/');
    // Strip leading 'EndDev/' to get the app-root-relative path
    $fs_rel    = preg_replace('#^EndDev[\\/]#i', '', $db_path);
    $local_path = __DIR__ . '/' . $fs_rel;

    if (!file_exists($local_path)) {
        $missing_files[] = [
            'id'             => $row['id'],
            'attachment_path'=> $row['attachment_path'],
            'local_path'     => $local_path,
            'sync_status'    => $row['sync_status'],
        ];
    } else {
        // File exists locally — flag it for re-sync to push to VPS
        $reset_ids[] = (int) $row['id'];
    }
}

// Reset sync_status = 0 for records whose file still exists locally
if (!empty($reset_ids)) {
    $id_list = implode(',', $reset_ids);
    $conn->query("UPDATE makeup_class_requests SET sync_status = 0 WHERE id IN ($id_list)");
}

echo "<h2>Makeup Attachment Repair Report</h2>";
echo "<p>Total records with attachment_path: <strong>$found</strong></p>";
echo "<p>Re-flagged for sync (file exists locally): <strong>" . count($reset_ids) . "</strong> — IDs: " . implode(', ', $reset_ids) . "</p>";

if (!empty($missing_files)) {
    echo "<h3 style='color:red'>⚠️ Files NOT found locally (" . count($missing_files) . ")</h3>";
    echo "<p>These files are missing on localhost too. They cannot be re-uploaded to Hostinger automatically.</p>";
    echo "<table border='1' cellpadding='6'>";
    echo "<tr><th>ID</th><th>attachment_path (DB)</th><th>Local path checked</th><th>sync_status</th></tr>";
    foreach ($missing_files as $f) {
        echo "<tr>";
        echo "<td>{$f['id']}</td>";
        echo "<td>{$f['attachment_path']}</td>";
        echo "<td>{$f['local_path']}</td>";
        echo "<td>{$f['sync_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:green'>✅ All attachment files found locally and flagged for re-sync.</p>";
}

echo "<hr><h3>Next Step</h3>";
echo "<p>Run the sync engine to push the files to Hostinger: ";
echo "<a href='/EndDev/api/run_sync.php'>/EndDev/api/run_sync.php</a></p>";

$conn->close();
?>
