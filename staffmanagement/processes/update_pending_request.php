<?php
require_once '../../db_connection.php';

// Session is already started in db_connection.php, but just in case
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Basic admin check
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $request_id = $_POST['request_id'] ?? null;
    $schedule_data = $_POST['schedule_data'] ?? '[]';

    if (!$request_id) {
        $response['message'] = 'Missing schedule request ID.';
        echo json_encode($response);
        exit;
    }

    // Verify it exists AND is valid (pending)
    $stmt = $conn->prepare("SELECT id FROM schedule_requests WHERE id = ? AND status = 'pending'");
    if (!$stmt) {
        $response['message'] = 'Database preparation error.';
        echo json_encode($response);
        exit;
    }
    
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        $response['message'] = 'Schedule request not found or not in pending state.';
        echo json_encode($response);
        exit;
    }
    
    // Update data block
    $stmt = $conn->prepare("UPDATE schedule_requests SET schedule_data = ?, sync_status = 0 WHERE id = ?");
    if (!$stmt) {
        $response['message'] = 'Database preparation error on update.';
        echo json_encode($response);
        exit;
    }
    
    $stmt->bind_param("si", $schedule_data, $request_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Request updated successfully.';
    } else {
        $response['message'] = 'Database error updating the request.';
    }
}

echo json_encode($response);