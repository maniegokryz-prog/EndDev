<?php
// Prevent any HTML warnings from breaking the JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once '../../auth_guard.php';

// Only admins can process approvals
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$requestId = intval($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($requestId <= 0 || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

try {
    // Fetch the pending request
    $stmt = $conn->prepare("SELECT * FROM schedule_requests WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found or already processed']);
        exit;
    }

    // Determine if sync_status column exists
    $hasSyncColumn = false;
    $syncCheck = $conn->query("SHOW COLUMNS FROM schedule_requests LIKE 'sync_status'");
    if ($syncCheck && $syncCheck->num_rows > 0) {
        $hasSyncColumn = true;
    }

    if ($action === 'reject') {
        // Update status to rejected
        $updateQuery = $hasSyncColumn ? 
            "UPDATE schedule_requests SET status = 'rejected', sync_status = 0 WHERE id = ?" : 
            "UPDATE schedule_requests SET status = 'rejected' WHERE id = ?";
            
        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error (reject init): ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $requestId);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error (reject exec): ' . $stmt->error]);
            exit;
        }

        // Notify employee
        sendEmployeeNotification($conn, $request['employee_id'], "Your schedule edit request has been rejected by the admin.");

        echo json_encode(['success' => true, 'message' => 'Request rejected successfully']);
        exit;

    } elseif ($action === 'approve') {
        // Update status to approved
        $updateQuery = $hasSyncColumn ? 
            "UPDATE schedule_requests SET status = 'approved', sync_status = 0 WHERE id = ?" : 
            "UPDATE schedule_requests SET status = 'approved' WHERE id = ?";

        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error (approve init): ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $requestId);
        if (!$stmt->execute()) {
             echo json_encode(['success' => false, 'message' => 'Database error (approve exec): ' . $stmt->error]);
             exit;
        }

        // Notify employee
        sendEmployeeNotification($conn, $request['employee_id'], "Your schedule edit request has been approved and applied.");

        // Hand over to the existing schedule updater logic
        // We simulate the POST request so EmployeeScheduleUpdater handles it perfectly
        $_POST['employee_id'] = $request['employee_id_string'];
        $_POST['first_name'] = $request['first_name'];
        $_POST['last_name'] = $request['last_name'];
        $_POST['schedule_data'] = $request['schedule_data'];

        // Include the actual updater script. 
        // Note: The updater script outputs JSON and calls exit() internally.
        require 'update_employee_schedule.php';
        exit;
    }

} catch (Throwable $e) {
    error_log("Error processing schedule approval: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

function sendEmployeeNotification($db, $employeeId, $message) {
    try {
        $check_table = $db->query("SHOW TABLES LIKE 'notifications'");
        if ($check_table->num_rows == 0) return;

        // Determine if employee is admin to set proper link
        $stmt = $db->prepare("SELECT employee_id as string_id, roles FROM employees WHERE id = ?");
        $stmt->bind_param("i", $employeeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        
        $link = "#";
        if ($employee && stripos(strtolower($employee['roles']), 'admin') !== false) {
            $link = "/EndDev/staffmanagement/staff_profile.php?id=" . $employee['string_id'];
        }
        
        $check_column = $db->query("SHOW COLUMNS FROM notifications LIKE 'link'");
        if ($check_column->num_rows > 0) {
            $stmt = $db->prepare("INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'schedule_change', ?, ?, 'employee', 0)");
            $stmt->bind_param("iss", $employeeId, $message, $link);
        } else {
            $stmt = $db->prepare("INSERT INTO notifications (employee_id, type, message, target, is_read) VALUES (?, 'schedule_change', ?, 'employee', 0)");
            $stmt->bind_param("is", $employeeId, $message);
        }
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Notification Error: " . $e->getMessage());
    }
}
?>
