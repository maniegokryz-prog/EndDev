<?php
/**
 * clear_broken_makeup_attachment.php
 * One-time script: Clears the attachment_path for makeup_class_requests
 * where the attachment file is missing on BOTH local and Hostinger.
 * 
 * Run on localhost: http://localhost/EndDev/clear_broken_makeup_attachment.php
 */
require_once __DIR__ . '/db_connection.php';

// IDs of records whose attachment files are confirmed missing everywhere
// (identified by repair_makeup_attachments.php)
$broken_ids = [3];

if (empty($broken_ids)) {
    die("No broken IDs specified.");
}

$id_list = implode(',', array_map('intval', $broken_ids));

// Clear attachment_path and re-flag for sync so Hostinger also gets cleared
$result = $conn->query("
    UPDATE makeup_class_requests
    SET attachment_path = NULL, sync_status = 0
    WHERE id IN ($id_list)
");

if ($result) {
    echo "<p style='color:green'>✅ Cleared attachment_path for record(s): $id_list</p>";
    echo "<p>These records will be re-synced to Hostinger with no attachment link.</p>";
    echo "<p>The employee will need to re-upload their attachment when editing the request.</p>";
    echo "<p><a href='/EndDev/api/run_sync.php'>Run sync now to push the change to Hostinger →</a></p>";
} else {
    echo "<p style='color:red'>❌ Failed: " . $conn->error . "</p>";
}

$conn->close();
?>
