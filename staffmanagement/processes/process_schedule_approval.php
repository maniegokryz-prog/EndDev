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

    // Find the notification and set actioned_by to current admin so it only shows for them
    try {
        $admin_id = $_SESSION['user_id'] ?? 0;
        $is_system_admin = $_SESSION['is_system_admin'] ?? false;
        $user_marker = $is_system_admin ? 'sys_' . $admin_id : 'emp_' . $admin_id;

        if ($admin_id > 0) {
            $check_col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'actioned_by'");
            if ($check_col && $check_col->num_rows > 0) {
                // The link format used in update_employee_schedule.php is "/EndDev/staffmanagement/review_schedule_request.php?id=[ID]"
                $linkPattern = "%review_schedule_request.php?id=" . $requestId;
                $updNotifStmt = $conn->prepare("UPDATE notifications SET actioned_by = ?, is_read = 1 WHERE type = 'schedule_approval' AND link LIKE ?");
                $updNotifStmt->bind_param("ss", $user_marker, $linkPattern);
                $updNotifStmt->execute();
                $updNotifStmt->close();
            }
        }
    } catch (Throwable $e) {
        error_log("Failed to update notification ownership: " . $e->getMessage());
    }

    // Determine if sync_status column exists
    $hasSyncColumn = false;
    $syncCheck = $conn->query("SHOW COLUMNS FROM schedule_requests LIKE 'sync_status'");
    if ($syncCheck && $syncCheck->num_rows > 0) {
        $hasSyncColumn = true;
    }

    if ($action === 'reject') {
        $remarks = trim($_POST['remarks'] ?? '');

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

        // Build notification message with remarks if provided
        $notifMessage = "Your schedule edit request has been rejected by the admin.";
        if (!empty($remarks)) {
            $notifMessage .= " Reason: " . $remarks;
        }

        // Notify employee with a link that references the request so we can show the schedule
        $notifLink = "/staffmanagement/staff_profile.php?id=" . $request['employee_id_string'];
        sendEmployeeNotification($conn, $request['employee_id'], $notifMessage, $notifLink, $requestId);

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

function sendEmployeeNotification($db, $employeeId, $message, $linkOverride = null, $scheduleRequestId = null) {
    try {
        $check_table = $db->query("SHOW TABLES LIKE 'notifications'");
        if ($check_table->num_rows == 0) return;

        // Determine link
        $link = $linkOverride ?? "#";
        if (!$linkOverride) {
            // Fallback: derive from employee record
            $stmt = $db->prepare("SELECT employee_id as string_id, roles FROM employees WHERE id = ?");
            $stmt->bind_param("i", $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $employee = $result->fetch_assoc();
            if ($employee && stripos(strtolower($employee['roles']), 'admin') !== false) {
                $link = "/staffmanagement/staff_profile.php?id=" . $employee['string_id'];
            }
        }

        // Check if schedule_request_id column exists in notifications
        $has_req_col = $db->query("SHOW COLUMNS FROM notifications LIKE 'schedule_request_id'")->num_rows > 0;
        $check_column = $db->query("SHOW COLUMNS FROM notifications LIKE 'link'");

        if ($has_req_col) {
            if ($check_column->num_rows > 0) {
                $stmt = $db->prepare("INSERT INTO notifications (employee_id, type, message, link, target, is_read, schedule_request_id) VALUES (?, 'schedule_change', ?, ?, 'employee', 0, ?)");
                $stmt->bind_param("issi", $employeeId, $message, $link, $scheduleRequestId);
            } else {
                $stmt = $db->prepare("INSERT INTO notifications (employee_id, type, message, target, is_read, schedule_request_id) VALUES (?, 'schedule_change', ?, 'employee', 0, ?)");
                $stmt->bind_param("isi", $employeeId, $message, $scheduleRequestId);
            }
        } else {
            if ($check_column->num_rows > 0) {
                $stmt = $db->prepare("INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'schedule_change', ?, ?, 'employee', 0)");
                $stmt->bind_param("iss", $employeeId, $message, $link);
            } else {
                $stmt = $db->prepare("INSERT INTO notifications (employee_id, type, message, target, is_read) VALUES (?, 'schedule_change', ?, 'employee', 0)");
                $stmt->bind_param("is", $employeeId, $message);
            }
        }
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Notification Error: " . $e->getMessage());
    }
}
?>
