<?php
require 'db_connection.php';

echo "NOTIFICATIONS WITH SYNC_STATUS=0:\n";
$r = $conn->query("SELECT id, type, sync_status, deleted_by FROM notifications WHERE sync_status = 0");
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "No notifications with sync_status=0 found.\n";
}

echo "LATEST NOTIFICATIONS:\n";
$r = $conn->query("SELECT id, type, sync_status, deleted_by FROM notifications ORDER BY id DESC LIMIT 5");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}
?>
