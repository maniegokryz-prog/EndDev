<?php
session_start();
header('Content-Type: application/json');

// Error logging setup
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/process_errors.log');
error_reporting(E_ALL);

// Security dependencies
require_once '../../auth_guard.php';
require_once '../../db_connection.php';

// Validate Admin Access
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Validate Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get raw input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['record_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing record ID.']);
    exit;
}

$recordId = intval($input['record_id']);

if ($recordId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
    exit;
}

try {
    // 1. Verify the record exists and is actually a 'visit'
    // We check case-insensitive just in case, though usually lowercase in DB
    $checkStmt = $conn->prepare("SELECT id, status, notes, time_in, time_out FROM daily_attendance WHERE id = ?");
    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $checkStmt->bind_param("i", $recordId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    $row = $result->fetch_assoc();
    $currentStatus = strtolower(trim($row['status']));

    if ($currentStatus !== 'visit') {
        echo json_encode(['success' => false, 'message' => 'Record is not a visit record (Current status: ' . $row['status'] . ').']);
        exit;
    }

    if (empty($row['time_in']) || empty($row['time_out']) || trim($row['time_in']) === '00:00:00' || trim($row['time_out']) === '00:00:00' || trim($row['time_in']) === '00:00' || trim($row['time_out']) === '00:00') {
        echo json_encode(['success' => false, 'message' => 'Cannot offset. The visit record must be complete with both Time In and Time Out.']);
        exit;
    }

    $checkStmt->close();

    // 2. Update the record
    $newStatus = 'complete';
    $currentNotes = $row['notes'] ?? '';
    // Prevent double appending if ran multiple times (though status check prevents this usually)
    $appendNote = ' [Offset]';
    $newNotes = $currentNotes . $appendNote;

    $updateStmt = $conn->prepare("UPDATE daily_attendance SET status = ?, notes = ? WHERE id = ?");
    if (!$updateStmt) {
        throw new Exception("Prepare update failed: " . $conn->error);
    }

    $updateStmt->bind_param("ssi", $newStatus, $newNotes, $recordId);

    if (!$updateStmt->execute()) {
        throw new Exception("Execute update failed: " . $updateStmt->error);
    }

    if ($updateStmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Visit successfully set as Offset.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made.']);
    }

    $updateStmt->close();

} catch (Exception $e) {
    error_log("Set Offset Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>