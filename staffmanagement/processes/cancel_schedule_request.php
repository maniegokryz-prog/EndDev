<?php
session_start();
require_once '../../auth_guard.php';
require_once '../../db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$request_id = $_POST['request_id'] ?? null;
if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required.']);
    exit;
}

$employee_id = $_SESSION['user_id']; // This is the ID from employees table (internal ID)

try {
    // Make sure the request belongs to this user and is still pending
    $stmt = $conn->prepare("SELECT id FROM schedule_requests WHERE id = ? AND employee_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $request_id, $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Request not found or it is no longer pending.']);
        exit;
    }
    $stmt->close();

    // Ensure the 'status' column ENUM includes 'cancelled' to prevent truncation errors on different PCs
    $enumCheck = $conn->query("SHOW COLUMNS FROM schedule_requests LIKE 'status'");
    if ($enumCheck && $row = $enumCheck->fetch_assoc()) {
        if (strpos($row['Type'], "'cancelled'") === false) {
            $conn->query("ALTER TABLE schedule_requests MODIFY COLUMN status enum('pending','approved','rejected','cancelled') DEFAULT 'pending'");
        }
    }

    // Soft-delete: mark as cancelled so it syncs to Hostinger properly
    $syncResult = $conn->query("SHOW COLUMNS FROM schedule_requests LIKE 'sync_status'");
    $hasSyncCol = $syncResult && $syncResult->num_rows > 0;
    
    $updateQuery = $hasSyncCol
        ? "UPDATE schedule_requests SET status = 'cancelled', sync_status = 0 WHERE id = ?"
        : "UPDATE schedule_requests SET status = 'cancelled' WHERE id = ?";

    $delStmt = $conn->prepare($updateQuery);
    $delStmt->bind_param("i", $request_id);
    if ($delStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Schedule request cancelled successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel the schedule request.']);
    }
    $delStmt->close();

} catch (Throwable $e) {
    error_log("Error cancelling schedule request: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal error occurred: ' . $e->getMessage()]);
}
?>
