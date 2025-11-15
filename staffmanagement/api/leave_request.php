<?php
/**
 * Leave Request Management API
 * Handles employee leave requests, approvals, and notifications
 * 
 * Actions:
 * - submit_request: Submit a new leave request
 * - get_pending_requests: Get all pending requests (admin)
 * - approve_request: Approve a leave request (admin)
 * - reject_request: Reject a leave request (admin)
 * - get_employee_requests: Get requests for specific employee
 * - get_notifications: Get admin notifications
 */

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require '../../db_connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'submit_request':
            submitLeaveRequest($conn);
            break;
            
        case 'get_pending_requests':
            getPendingRequests($conn);
            break;
            
        case 'approve_request':
            approveLeaveRequest($conn);
            break;
            
        case 'reject_request':
            rejectLeaveRequest($conn);
            break;
            
        case 'get_employee_requests':
            getEmployeeRequests($conn);
            break;
            
        case 'get_notifications':
            getAdminNotifications($conn);
            break;
            
        case 'mark_notification_read':
            markNotificationRead($conn);
            break;
            
        case 'cancel_request':
            cancelLeaveRequest($conn);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();

/**
 * Submit a new leave request
 */
function submitLeaveRequest($conn) {
    $employee_id = $_POST['employee_id'] ?? 0;
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $is_admin = ($_POST['is_admin'] ?? '0') === '1';
    $auto_approve = ($_POST['auto_approve'] ?? '0') === '1';
    
    if (!$employee_id || !$leave_type || !$start_date || !$end_date) {
        throw new Exception('Missing required fields');
    }
    
    // Validate dates
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    if ($end < $start) {
        throw new Exception('End date cannot be before start date');
    }
    
    // Check for overlapping leave requests
    $sql = "SELECT id FROM employee_leaves 
            WHERE employee_id = ? 
            AND status IN ('pending', 'approved')
            AND (
                (start_date <= ? AND end_date >= ?)
                OR (start_date <= ? AND end_date >= ?)
                OR (start_date >= ? AND end_date <= ?)
            )";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssss", $employee_id, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('There is already a leave request for this date range');
    }
    
    // Get or create leave type
    $leave_type_id = getOrCreateLeaveType($conn, $leave_type);
    
    // Determine initial status
    $initial_status = ($is_admin && $auto_approve) ? 'approved' : 'pending';
    
    // Insert leave request
    $sql = "INSERT INTO employee_leaves 
            (employee_id, leave_type_id, start_date, end_date, reason, status) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissss", $employee_id, $leave_type_id, $start_date, $end_date, $reason, $initial_status);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit leave request');
    }
    
    $leave_id = $conn->insert_id;
    
    // If admin auto-approved, mark dates as leave
    if ($initial_status === 'approved') {
        markDatesAsLeave($conn, $employee_id, $start_date, $end_date);
        logActivity($conn, 'Leave auto-approved by admin', "Employee ID: $employee_id, Leave ID: $leave_id");
        
        $message = $is_admin ? 
            'Leave request submitted and automatically approved' : 
            'Leave request approved successfully';
    } else {
        // Create notification for admin (only if not auto-approved)
        if (!$is_admin) {
            createAdminNotification($conn, $employee_id, $leave_id, 'new_request');
        } else {
            createAdminNotification($conn, $employee_id, $leave_id, 'admin_request');
        }
        
        logActivity($conn, 'Leave request submitted', "Employee ID: $employee_id, Leave ID: $leave_id, Requested by: " . ($is_admin ? 'Admin' : 'Employee'));
        
        $message = $is_admin ? 
            'Leave request submitted for approval' : 
            'Leave request submitted successfully and pending approval';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'leave_id' => $leave_id,
        'status' => $initial_status
    ]);
}

/**
 * Get all pending leave requests for admin
 */
function getPendingRequests($conn) {
    $sql = "SELECT 
                el.id,
                el.employee_id,
                el.start_date,
                el.end_date,
                el.reason,
                el.status,
                el.created_at,
                e.employee_id as employee_code,
                e.first_name,
                e.last_name,
                e.position,
                e.department,
                e.profile_photo,
                lt.type_name as leave_type
            FROM employee_leaves el
            INNER JOIN employees e ON el.employee_id = e.id
            INNER JOIN leave_types lt ON el.leave_type_id = lt.id
            WHERE el.status = 'pending'
            ORDER BY el.created_at DESC";
    
    $result = $conn->query($sql);
    $requests = [];
    
    while ($row = $result->fetch_assoc()) {
        $requests[] = [
            'id' => $row['id'],
            'employee_id' => $row['employee_id'],
            'employee_code' => $row['employee_code'],
            'employee_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'position' => $row['position'],
            'department' => $row['department'],
            'profile_photo' => $row['profile_photo'],
            'leave_type' => $row['leave_type'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'reason' => $row['reason'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'formatted_dates' => formatDateRange($row['start_date'], $row['end_date'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($requests),
        'data' => $requests
    ]);
}

/**
 * Approve a leave request
 */
function approveLeaveRequest($conn) {
    $leave_id = $_POST['leave_id'] ?? 0;
    $approved_by = $_POST['approved_by'] ?? 'admin';
    
    if (!$leave_id) {
        throw new Exception('Leave ID is required');
    }
    
    // Get leave details
    $sql = "SELECT employee_id, start_date, end_date FROM employee_leaves WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leave_id);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();
    
    if (!$leave) {
        throw new Exception('Leave request not found');
    }
    
    // Update leave status
    $sql = "UPDATE employee_leaves SET status = 'approved' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leave_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to approve leave request');
    }
    
    // Mark attendance dates as "on_leave"
    markDatesAsLeave($conn, $leave['employee_id'], $leave['start_date'], $leave['end_date']);
    
    // Create notification for employee
    createEmployeeNotification($conn, $leave['employee_id'], $leave_id, 'approved');
    
    // Log activity
    logActivity($conn, 'Leave request approved', "Leave ID: $leave_id, Approved by: $approved_by");
    
    echo json_encode([
        'success' => true,
        'message' => 'Leave request approved successfully'
    ]);
}

/**
 * Reject a leave request
 */
function rejectLeaveRequest($conn) {
    $leave_id = $_POST['leave_id'] ?? 0;
    $rejected_by = $_POST['rejected_by'] ?? 'admin';
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    if (!$leave_id) {
        throw new Exception('Leave ID is required');
    }
    
    // Get leave details
    $sql = "SELECT employee_id FROM employee_leaves WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leave_id);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();
    
    if (!$leave) {
        throw new Exception('Leave request not found');
    }
    
    // Update leave status
    $sql = "UPDATE employee_leaves SET status = 'rejected', reason = CONCAT(reason, '\nRejection Reason: ', ?) WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $rejection_reason, $leave_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to reject leave request');
    }
    
    // Create notification for employee
    createEmployeeNotification($conn, $leave['employee_id'], $leave_id, 'rejected');
    
    // Log activity
    logActivity($conn, 'Leave request rejected', "Leave ID: $leave_id, Rejected by: $rejected_by");
    
    echo json_encode([
        'success' => true,
        'message' => 'Leave request rejected'
    ]);
}

/**
 * Cancel/Delete a leave request
 */
function cancelLeaveRequest($conn) {
    $leave_id = $_POST['leave_id'] ?? 0;
    $cancelled_by = $_POST['cancelled_by'] ?? 'user';
    
    if (!$leave_id) {
        throw new Exception('Leave ID is required');
    }
    
    // Get leave details
    $sql = "SELECT employee_id, start_date, end_date, status FROM employee_leaves WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leave_id);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();
    
    if (!$leave) {
        throw new Exception('Leave request not found');
    }
    
    // If leave was approved, remove the leave markings from attendance
    if ($leave['status'] === 'approved') {
        removeLeaveMarkings($conn, $leave['employee_id'], $leave['start_date'], $leave['end_date']);
    }
    
    // Delete the leave request
    $sql = "DELETE FROM employee_leaves WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leave_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to cancel leave request');
    }
    
    // Delete related notifications
    $sql = "DELETE FROM notifications WHERE leave_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leave_id);
    $stmt->execute();
    
    // Log activity
    logActivity($conn, 'Leave request cancelled', "Leave ID: $leave_id, Cancelled by: $cancelled_by");
    
    echo json_encode([
        'success' => true,
        'message' => 'Leave request cancelled successfully'
    ]);
}

/**
 * Helper function: Remove leave markings from attendance
 */
function removeLeaveMarkings($conn, $employee_id, $start_date, $end_date) {
    // Update status from 'on_leave' back to 'absent' for dates that have no other attendance
    $sql = "UPDATE daily_attendance 
            SET status = 'absent', time_in = NULL, time_out = NULL, hours_worked = 0
            WHERE employee_id = ? 
            AND date BETWEEN ? AND ?
            AND status = 'on_leave'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    $stmt->execute();
}

/**
 * Get requests for a specific employee
 */
function getEmployeeRequests($conn) {
    $employee_id = $_GET['employee_id'] ?? 0;
    
    if (!$employee_id) {
        throw new Exception('Employee ID is required');
    }
    
    $sql = "SELECT 
                el.*,
                lt.type_name as leave_type
            FROM employee_leaves el
            INNER JOIN leave_types lt ON el.leave_type_id = lt.id
            WHERE el.employee_id = ?
            ORDER BY el.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = [
            'id' => $row['id'],
            'leave_type' => $row['leave_type'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'reason' => $row['reason'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'formatted_dates' => formatDateRange($row['start_date'], $row['end_date'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($requests),
        'data' => $requests
    ]);
}

/**
 * Get admin notifications
 */
function getAdminNotifications($conn) {
    $sql = "SELECT 
                n.id,
                n.type,
                n.message,
                n.is_read,
                n.created_at,
                n.leave_id,
                el.start_date,
                el.end_date,
                e.first_name,
                e.last_name,
                e.employee_id as employee_code
            FROM notifications n
            LEFT JOIN employee_leaves el ON n.leave_id = el.id
            LEFT JOIN employees e ON n.employee_id = e.id
            WHERE n.target = 'admin'
            ORDER BY n.is_read ASC, n.created_at DESC
            LIMIT 50";
    
    $result = $conn->query($sql);
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'message' => $row['message'],
            'is_read' => $row['is_read'],
            'created_at' => $row['created_at'],
            'employee_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'employee_code' => $row['employee_code'],
            'leave_id' => $row['leave_id']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($notifications),
        'unread_count' => array_reduce($notifications, function($count, $n) {
            return $count + ($n['is_read'] ? 0 : 1);
        }, 0),
        'data' => $notifications
    ]);
}

/**
 * Mark notification as read
 */
function markNotificationRead($conn) {
    $notification_id = $_POST['notification_id'] ?? 0;
    
    if (!$notification_id) {
        throw new Exception('Notification ID is required');
    }
    
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notification_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to mark notification as read');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);
}

/**
 * Helper function: Get or create leave type
 */
function getOrCreateLeaveType($conn, $leave_type_name) {
    // Check if exists
    $sql = "SELECT id FROM leave_types WHERE type_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $leave_type_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    // Create new leave type
    $sql = "INSERT INTO leave_types (type_name, description) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $desc = $leave_type_name . " leave";
    $stmt->bind_param("ss", $leave_type_name, $desc);
    $stmt->execute();
    
    return $conn->insert_id;
}

/**
 * Helper function: Create admin notification
 */
function createAdminNotification($conn, $employee_id, $leave_id, $type) {
    // Get employee name
    $sql = "SELECT first_name, last_name FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();
    
    $employee_name = trim($emp['first_name'] . ' ' . $emp['last_name']);
    
    if ($type === 'admin_request') {
        $message = "Admin has submitted a leave request for $employee_name (pending approval)";
    } else {
        $message = "$employee_name has submitted a new leave request";
    }
    
    // Create notification table if not exists
    ensureNotificationsTable($conn);
    
    $sql = "INSERT INTO notifications (employee_id, leave_id, type, message, target, is_read) 
            VALUES (?, ?, ?, ?, 'admin', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $employee_id, $leave_id, $type, $message);
    $stmt->execute();
}

/**
 * Helper function: Create employee notification
 */
function createEmployeeNotification($conn, $employee_id, $leave_id, $status) {
    $message = "Your leave request has been " . $status;
    
    ensureNotificationsTable($conn);
    
    $sql = "INSERT INTO notifications (employee_id, leave_id, type, message, target, is_read) 
            VALUES (?, ?, ?, ?, 'employee', 0)";
    $stmt = $conn->prepare($sql);
    $type = 'leave_' . $status;
    $stmt->bind_param("iiss", $employee_id, $leave_id, $type, $message);
    $stmt->execute();
}

/**
 * Helper function: Mark dates as leave in daily_attendance
 */
function markDatesAsLeave($conn, $employee_id, $start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    while ($start <= $end) {
        $date = $start->format('Y-m-d');
        
        // Check if record exists
        $sql = "SELECT id FROM daily_attendance WHERE employee_id = ? AND attendance_date = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $employee_id, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing record
            $sql = "UPDATE daily_attendance SET status = 'on_leave' WHERE employee_id = ? AND attendance_date = ?";
        } else {
            // Insert new record
            $sql = "INSERT INTO daily_attendance (employee_id, attendance_date, status) VALUES (?, ?, 'on_leave')";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $employee_id, $date);
        $stmt->execute();
        
        $start->modify('+1 day');
    }
}

/**
 * Helper function: Ensure notifications table exists
 */
function ensureNotificationsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT,
        leave_id INT,
        type VARCHAR(50),
        message TEXT,
        target ENUM('admin', 'employee') DEFAULT 'admin',
        is_read BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (leave_id) REFERENCES employee_leaves(id) ON DELETE CASCADE
    )";
    $conn->query($sql);
}

/**
 * Helper function: Format date range
 */
function formatDateRange($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    if ($start_date === $end_date) {
        return $start->format('M j, Y');
    }
    
    return $start->format('M j') . ' - ' . $end->format('M j, Y');
}

/**
 * Helper function: Log activity
 */
function logActivity($conn, $activity, $details = '') {
    $log_entry = "[" . date('Y-m-d H:i:s') . "] [LEAVE] " . $activity;
    if ($details) $log_entry .= " - " . $details;
    $log_entry .= PHP_EOL;
    
    $log_dir = __DIR__ . '/../logs/';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_dir . 'leave_system.log', $log_entry, FILE_APPEND | LOCK_EX);
}
?>
