<?php
// A quick script to simulate the exact failure without login guard.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../db_connection.php';

$requestId = 3; 

// Just testing the UPDATE logic here
$stmt = $conn->prepare("UPDATE schedule_requests SET status = 'rejected', sync_status = 0 WHERE id = ?");
if (!$stmt) {
    die("Database error (reject init): " . $conn->error);
}
$stmt->bind_param("i", $requestId);
if (!$stmt->execute()) {
    die("Database error (reject exec): " . $stmt->error);
}
echo "Reject success!";
?>
