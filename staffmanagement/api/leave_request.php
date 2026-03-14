<?php
/**
 * Leave Request Management API
 * Handles employee leave requests, approvals, and notifications
 */

// Disable all output buffering and error display
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unwanted output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

date_default_timezone_set('Asia/Manila');

// Mark error settings as configured before including db_connection
$GLOBALS['error_reporting_configured'] = true;

// Include database connection
require_once '../../db_connection.php';

// Clear any output that might have been generated
ob_end_clean();

// Start fresh output buffer
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

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

        case 'delete_notification':
            deleteNotification($conn);
            break;

        case 'delete_all_notifications':
            deleteAllNotifications($conn);
            break;

        case 'cancel_request':
            cancelLeaveRequest($conn);
            break;

        case 'get_rejected_schedule_detail':
            getRejectedScheduleDetail($conn);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    // Clear any error output
    if (ob_get_length())
        ob_end_clean();

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
}

$conn->close();

/**
 * Submit a new leave request
 */
function submitLeaveRequest($conn)
{
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

    // Handle file attachment
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $attachment_path = handleFileUpload($_FILES['attachment'], $employee_id);
    }

    // Check employee role - Faculty members cannot request leave
    $stmt_role = $conn->prepare("SELECT roles FROM employees WHERE id = ?");
    $stmt_role->bind_param("i", $employee_id);
    $stmt_role->execute();
    $role_result = $stmt_role->get_result()->fetch_assoc();

    if ($role_result) {
        $roles = strtolower($role_result['roles']);
        if (stripos($roles, 'faculty') !== false) {
            throw new Exception('Faculty members are not allowed to request leave through this system. Please contact HR directly.');
        }
    }

    // Validate dates
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);

    if ($end < $start) {
        throw new Exception('End date cannot be before start date');
    }

    // RULE 1: Check if employee has any PENDING requests (must wait for approval)
    $sql_pending = "SELECT id FROM employee_leaves 
                    WHERE employee_id = ? 
                    AND status = 'pending'";
    $stmt_pending = $conn->prepare($sql_pending);
    $stmt_pending->bind_param("i", $employee_id);
    $stmt_pending->execute();
    $pending_result = $stmt_pending->get_result();

    if ($pending_result->num_rows > 0) {
        throw new Exception('You already have a pending leave request. Please wait for admin approval before submitting another request.');
    }

    // RULE 2: Check monthly APPROVED leave request limit (2 approved per month)
    $request_month = $start->format('Y-m');
    $sql_count = "SELECT COUNT(*) as request_count 
                  FROM employee_leaves 
                  WHERE employee_id = ? 
                  AND status = 'approved'
                  AND (DATE_FORMAT(start_date, '%Y-%m') = ? OR DATE_FORMAT(end_date, '%Y-%m') = ?)";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("iss", $employee_id, $request_month, $request_month);
    $stmt_count->execute();
    $count_result = $stmt_count->get_result()->fetch_assoc();

    if ($count_result['request_count'] >= 2) {
        throw new Exception('Monthly leave limit reached. You have already used 2 approved leave requests this month.');
    }

    // RULE 3: Check for overlapping/duplicate leave dates
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
        throw new Exception('You cannot request leave for the same or overlapping dates. Please choose different dates.');
    }

    // Get or create leave type
    $leave_type_id = getOrCreateLeaveType($conn, $leave_type);

    // Determine initial status
    $initial_status = ($is_admin && $auto_approve) ? 'approved' : 'pending';

    // Check if attachment column exists
    $check_column = $conn->query("SHOW COLUMNS FROM employee_leaves LIKE 'attachment'");
    $has_attachment_column = $check_column->num_rows > 0;

    // Insert leave request (with or without attachment column)
    if ($has_attachment_column && $attachment_path) {
        $sql = "INSERT INTO employee_leaves 
                (employee_id, leave_type_id, start_date, end_date, reason, status, attachment) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssss", $employee_id, $leave_type_id, $start_date, $end_date, $reason, $initial_status, $attachment_path);
    } else {
        $sql = "INSERT INTO employee_leaves 
                (employee_id, leave_type_id, start_date, end_date, reason, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissss", $employee_id, $leave_type_id, $start_date, $end_date, $reason, $initial_status);
    }

    if (!$stmt->execute()) {
        throw new Exception('Failed to submit leave request: ' . $stmt->error);
    }

    $leave_id = $conn->insert_id;

    // Sync employee leave to cloud
    require_once __DIR__ . '/../../db_cloud_sync.php';
    syncToCloud('employee_leaves', [
        'id' => $leave_id,
        'employee_id' => $employee_id,
        'leave_type_id' => $leave_type_id,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'reason' => $reason,
        'status' => $initial_status
    ], 'insert');

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
            // Also notify the admin requestor as an employee
            createEmployeeNotification($conn, $employee_id, $leave_id, 'pending');
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
function getPendingRequests($conn)
{
    $sql = "SELECT 
                el.id,
                el.employee_id,
                el.start_date,
                el.end_date,
                el.reason,
                el.status,
                el.attachment,
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
            'attachment' => $row['attachment'],
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
function approveLeaveRequest($conn)
{
    $leave_id = $_POST['leave_id'] ?? 0;
    $approved_by = $_POST['approved_by'] ?? 'admin';

    if (!$leave_id) {
        throw new Exception('Leave ID is required');
    }

    // Get leave details and cloud_id
    $sql = "SELECT employee_id, start_date, end_date, cloud_id FROM employee_leaves WHERE id = ?";
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

    // Flag local record for sync
    require_once __DIR__ . '/../../db_cloud_sync.php';
    syncToCloud('employee_leaves', ['status' => 'approved'], 'update', "id = $leave_id");

    // Set actioned_by on the admin notification so it persists only for this admin
    $admin_id = $_SESSION['user_id'] ?? 0;
    if ($admin_id > 0) {
        $check_col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'actioned_by'");
        if ($check_col && $check_col->num_rows > 0) {
            $updStmt = $conn->prepare("UPDATE notifications SET actioned_by = ?, is_read = 1 WHERE target = 'admin' AND leave_id = ? AND type = 'new_request'");
            $updStmt->bind_param("ii", $admin_id, $leave_id);
            $updStmt->execute();
        }
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
function rejectLeaveRequest($conn)
{
    $leave_id = $_POST['leave_id'] ?? 0;
    $rejected_by = $_POST['rejected_by'] ?? 'admin';
    $rejection_reason = $_POST['rejection_reason'] ?? '';

    if (!$leave_id) {
        throw new Exception('Leave ID is required');
    }

    // Get leave details
    $sql = "SELECT employee_id, cloud_id FROM employee_leaves WHERE id = ?";
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

    // Flag local record for sync
    require_once __DIR__ . '/../../db_cloud_sync.php';
    syncToCloud('employee_leaves', ['status' => 'rejected'], 'update', "id = $leave_id");

    // Set actioned_by on the admin notification so it persists only for this admin
    $admin_id = $_SESSION['user_id'] ?? 0;
    if ($admin_id > 0) {
        $check_col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'actioned_by'");
        if ($check_col && $check_col->num_rows > 0) {
            $updStmt = $conn->prepare("UPDATE notifications SET actioned_by = ?, is_read = 1 WHERE target = 'admin' AND leave_id = ? AND type = 'new_request'");
            $updStmt->bind_param("ii", $admin_id, $leave_id);
            $updStmt->execute();
        }
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
function cancelLeaveRequest($conn)
{
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
function removeLeaveMarkings($conn, $employee_id, $start_date, $end_date)
{
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
function getEmployeeRequests($conn)
{
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

    // Check if attachment column exists
    $check_column = $conn->query("SHOW COLUMNS FROM employee_leaves LIKE 'attachment'");
    $has_attachment_column = $check_column->num_rows > 0;

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = [
            'id' => $row['id'],
            'leave_type' => $row['leave_type'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'reason' => $row['reason'],
            'status' => $row['status'],
            'attachment' => $has_attachment_column ? ($row['attachment'] ?? null) : null,
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
function getAdminNotifications($conn)
{
    // Session already started at top of file
    $user_id = $_SESSION['user_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? 'employee';

    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'error' => 'User not logged in',
            'data' => []
        ]);
        return;
    }

    // Ensure notifications table exists
    ensureNotificationsTable($conn);

    // Auto-add schedule_request_id column if it doesn't exist yet
    $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'schedule_request_id'");
    if ($col_check && $col_check->num_rows === 0) {
        $conn->query("ALTER TABLE notifications ADD COLUMN schedule_request_id INT NULL DEFAULT NULL");
    }

    // Admin sees all admin-targeted notifications (leave requests from others)
    // AND their own employee-targeted notifications (their leave request status)
    // Employees see only their own employee-targeted notifications
    if ($user_role === 'admin') {
        // Check if this is a System Admin (from admin_users table)
        // System Admins have IDs that might collide with Employee IDs, so we MUST NOT show personal notifications based on ID
        $is_system_admin = $_SESSION['is_system_admin'] ?? false;

        if ($is_system_admin) {
            // System Admin: Only see admin notifications, STRICTLY filtering out personal types
            // We do NOT check employee_id here because the system admin's ID does not correspond to an employee
            $sql = "SELECT 
                        n.id,
                        n.type,
                        n.message,
                        n.is_read,
                        n.created_at,
                        n.leave_id,
                        n.schedule_request_id,
                        el.start_date,
                        el.end_date,
                        e.first_name,
                        e.last_name,
                        e.employee_id as employee_code,
                        n.link
                    FROM notifications n
                    LEFT JOIN employee_leaves el ON n.leave_id = el.id
                    LEFT JOIN employees e ON n.employee_id = e.id
                    WHERE n.target = 'admin' AND n.type NOT IN ('schedule_change', 'leave_approved', 'leave_rejected')
                    AND (n.deleted_by IS NULL OR n.deleted_by NOT LIKE CONCAT('%[', ?, ']%'))
                    AND (n.actioned_by IS NULL OR n.actioned_by = ?)
                    ORDER BY n.is_read ASC, n.created_at DESC
                    LIMIT 50";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            // Employee Admin: Can see admin notifications AND their own personal notifications
            $sql = "SELECT 
                        n.id,
                        n.type,
                        n.message,
                        n.is_read,
                        n.created_at,
                        n.leave_id,
                        n.schedule_request_id,
                        el.start_date,
                        el.end_date,
                        e.first_name,
                        e.last_name,
                        e.employee_id as employee_code,
                        n.link
                    FROM notifications n
                    LEFT JOIN employee_leaves el ON n.leave_id = el.id
                    LEFT JOIN employees e ON n.employee_id = e.id
                    WHERE (n.target = 'admin' AND n.type NOT IN ('schedule_change', 'leave_approved', 'leave_rejected') 
                           AND (n.deleted_by IS NULL OR n.deleted_by NOT LIKE CONCAT('%[', ?, ']%'))
                           AND (n.actioned_by IS NULL OR n.actioned_by = ?)) 
                       OR (n.target = 'employee' AND n.employee_id = ?)
                    ORDER BY n.is_read ASC, n.created_at DESC
                    LIMIT 50";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
        }
    } else {
        $sql = "SELECT 
                    n.id,
                    n.type,
                    n.message,
                    n.is_read,
                    n.created_at,
                    n.leave_id,
                    n.schedule_request_id,
                    el.start_date,
                    el.end_date,
                    e.first_name,
                    e.last_name,
                    e.employee_id as employee_code,
                    n.link
                FROM notifications n
                LEFT JOIN employee_leaves el ON n.leave_id = el.id
                LEFT JOIN employees e ON n.employee_id = e.id
                WHERE n.target = 'employee' AND e.id = ?
                ORDER BY n.is_read ASC, n.created_at DESC
                LIMIT 50";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
    }

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
            'leave_id' => $row['leave_id'],
            'link' => $row['link'],
            'schedule_request_id' => $row['schedule_request_id'] ?? null
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($notifications),
        'unread_count' => array_reduce($notifications, function ($count, $n) {
            return $count + ($n['is_read'] ? 0 : 1);
        }, 0),
        'data' => $notifications
    ]);
}

/**
 * Mark notification as read
 */
function markNotificationRead($conn)
{
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
 * Delete a single notification
 */
function deleteNotification($conn)
{
    $notification_id = $_POST['notification_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? 'employee';

    if (!$notification_id) {
        throw new Exception('Notification ID is required');
    }

    if (!$user_id) {
        throw new Exception('User not logged in');
    }

    // Check if notification is admin-targeted and user is admin
    $check = $conn->prepare("SELECT target FROM notifications WHERE id = ?");
    $check->bind_param("i", $notification_id);
    $check->execute();
    $res = $check->get_result()->fetch_assoc();

    if ($res && $res['target'] === 'admin' && $user_role === 'admin') {
        // Soft delete for this admin by appending to deleted_by
        $sql = "UPDATE notifications SET deleted_by = CONCAT(IFNULL(deleted_by, ''), '[', ?, ']') WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $notification_id);
    } else {
        // Only allow hard deletion of own employee notifications
        $sql = "DELETE FROM notifications WHERE id = ? AND employee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notification_id, $user_id);
    }

    if (!$stmt->execute()) {
        throw new Exception('Failed to delete notification');
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('Notification not found or access denied');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Notification deleted successfully'
    ]);
}

/**
 * Delete all notifications for current user
 */
function deleteAllNotifications($conn)
{
    $user_id = $_SESSION['user_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? 'employee';

    if (!$user_id) {
        throw new Exception('User not logged in');
    }

    // Delete based on user role and notification target
    if ($user_role === 'admin') {
        // Soft-delete admin notifications
        $sql1 = "UPDATE notifications SET deleted_by = CONCAT(IFNULL(deleted_by, ''), '[', ?, ']') WHERE target = 'admin' AND (deleted_by IS NULL OR deleted_by NOT LIKE CONCAT('%[', ?, ']%'))";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("ii", $user_id, $user_id);
        $stmt1->execute();

        // Hard-delete admin's own employee notifications
        $sql2 = "DELETE FROM notifications WHERE target = 'employee' AND employee_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        
        $deleted_count = $stmt1->affected_rows + $stmt2->affected_rows;
    } else {
        // Delete only employee's own notifications
        $sql = "DELETE FROM notifications WHERE target = 'employee' AND employee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete notifications');
        }
        $deleted_count = $stmt->affected_rows;
    }

    echo json_encode([
        'success' => true,
        'message' => "Deleted {$deleted_count} notification(s)",
        'deleted_count' => $deleted_count
    ]);
}

/**
 * Get rejected schedule request detail for display in notification modal
 */
function getRejectedScheduleDetail($conn)
{
    $notification_id = $_GET['notification_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$notification_id || !$user_id) {
        throw new Exception('Missing parameters');
    }

    // Auto-add schedule_request_id column if it does not exist
    $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'schedule_request_id'");
    if ($col_check->num_rows === 0) {
        $conn->query("ALTER TABLE notifications ADD COLUMN schedule_request_id INT NULL DEFAULT NULL");
    }

    // Fetch the notification and verify it belongs to this user
    $stmt = $conn->prepare(
        "SELECT n.message, n.schedule_request_id, n.employee_id
         FROM notifications n
         LEFT JOIN employees e ON n.employee_id = e.id
         WHERE n.id = ? AND n.employee_id = ?"
    );
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $notif = $stmt->get_result()->fetch_assoc();

    if (!$notif) {
        throw new Exception('Notification not found or access denied');
    }

    $scheduleData = null;
    $remarks = '';

    // Parse remarks from the message (format: "...rejected... Reason: <remarks>")
    $message = $notif['message'];
    if (strpos($message, 'Reason: ') !== false) {
        $remarks = trim(substr($message, strpos($message, 'Reason: ') + 8));
    }

    // Fetch schedule data from schedule_requests using stored request ID
    if (!empty($notif['schedule_request_id'])) {
        $stmt2 = $conn->prepare("SELECT schedule_data FROM schedule_requests WHERE id = ?");
        $stmt2->bind_param("i", $notif['schedule_request_id']);
        $stmt2->execute();
        $req = $stmt2->get_result()->fetch_assoc();
        if ($req) {
            $scheduleData = json_decode($req['schedule_data'], true);
        }
    }

    // If no specific request found, try to get the most recent rejected request for this employee
    if ($scheduleData === null) {
        $stmt3 = $conn->prepare(
            "SELECT schedule_data FROM schedule_requests
             WHERE employee_id = ? AND status = 'rejected'
             ORDER BY updated_at DESC LIMIT 1"
        );
        $stmt3->bind_param("i", $notif['employee_id']);
        $stmt3->execute();
        $req3 = $stmt3->get_result()->fetch_assoc();
        if ($req3) {
            $scheduleData = json_decode($req3['schedule_data'], true);
        }
    }

    echo json_encode([
        'success' => true,
        'schedule_data' => $scheduleData ?? [],
        'remarks' => $remarks,
        'message' => $message
    ]);
}

/**
 * Helper function: Get or create leave type
 */
function getOrCreateLeaveType($conn, $leave_type_name)
{
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
function createAdminNotification($conn, $employee_id, $leave_id, $type)
{
    // Get employee name and code
    $sql = "SELECT first_name, last_name, employee_id as code FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();

    $employee_name = trim($emp['first_name'] . ' ' . $emp['last_name']);

    // Create link to employee profile
    $link = "../staffmanagement/staffinfo.php?id=" . $emp['code'];

    if ($type === 'admin_request') {
        $message = "$employee_name has submitted a leave request (Pending for approval)";
    } else {
        $message = "$employee_name has submitted a leave request (Pending for approval)";
    }

    // Create notification table if not exists
    ensureNotificationsTable($conn);

    $sql = "INSERT INTO notifications (employee_id, leave_id, type, message, target, is_read, link) 
            VALUES (?, ?, ?, ?, 'admin', 0, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissss", $employee_id, $leave_id, $type, $message, $link);
    $stmt->execute();
    $notif_id = $conn->insert_id;

    // Sync notification to cloud
    // Lookup employee_id string for accurate mapping on cloud
    $stmtCode = $conn->prepare("SELECT employee_id FROM employees WHERE id = ?");
    $stmtCode->bind_param("i", $employee_id);
    $stmtCode->execute();
    $empCode = $stmtCode->get_result()->fetch_assoc()['employee_id'] ?? '';

    require_once __DIR__ . '/../../db_cloud_sync.php';
    // Use lookup sync for notifications because ID might differ
    syncToCloudWithLookup('notifications', [
        'employee_id_string' => $empCode,
        'leave_id' => $leave_id,
        'leave_cloud_id' => $leave['cloud_id'] ?? null, // Pass cloud_id of leave if available
        'type' => $type,
        'message' => $message,
        'target' => 'admin',
        'is_read' => 0,
        'link' => $link
    ]);
}

/**
 * Helper function: Create employee notification
 */
function createEmployeeNotification($conn, $employee_id, $leave_id, $status)
{
    // Get employee name
    $sql = "SELECT first_name, last_name FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();

    $employee_name = trim($emp['first_name'] . ' ' . $emp['last_name']);

    if ($status === 'approved') {
        $message = "$employee_name, Your leave request has been Approved";
    } else if ($status === 'rejected') {
        $message = "$employee_name, Your leave request has been Rejected";
    } else if ($status === 'pending') {
        $message = "$employee_name, Your leave request has been submitted (Pending for approval)";
    } else {
        $message = "$employee_name, Your leave request has been " . ucfirst($status);
    }

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
function markDatesAsLeave($conn, $employee_id, $start_date, $end_date)
{
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

        // Sync daily attendance to cloud
        require_once __DIR__ . '/../../db_cloud_sync.php';
        if ($result->num_rows > 0) {
            syncToCloud('daily_attendance', [
                'status' => 'on_leave'
            ], 'update', "employee_id = $employee_id AND attendance_date = '$date'");
        } else {
            syncToCloud('daily_attendance', [
                'employee_id' => $employee_id,
                'attendance_date' => $date,
                'status' => 'on_leave'
            ], 'insert');
        }

        $start->modify('+1 day');
    }
}

/**
 * Helper function: Ensure notifications table exists
 */
function ensureNotificationsTable($conn)
{
    try {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT,
            leave_id INT,
            type VARCHAR(50),
            message TEXT,
            target ENUM('admin', 'employee') DEFAULT 'admin',
            is_read BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_by TEXT NULL DEFAULT NULL,
            actioned_by INT NULL DEFAULT NULL,
            INDEX idx_target (target),
            INDEX idx_employee (employee_id),
            INDEX idx_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        @$conn->query($sql);

        // Auto-add deleted_by column if it doesn't exist yet
        $col_check_deleted = $conn->query("SHOW COLUMNS FROM notifications LIKE 'deleted_by'");
        if ($col_check_deleted && $col_check_deleted->num_rows === 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN deleted_by TEXT NULL DEFAULT NULL");
        }
        
        // Auto-add actioned_by column if it doesn't exist yet
        $col_check_actioned = $conn->query("SHOW COLUMNS FROM notifications LIKE 'actioned_by'");
        if ($col_check_actioned && $col_check_actioned->num_rows === 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN actioned_by INT NULL DEFAULT NULL");
        }

        // Add foreign keys separately if they don't exist
        @$conn->query("ALTER TABLE notifications ADD CONSTRAINT fk_notif_employee 
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE");
        @$conn->query("ALTER TABLE notifications ADD CONSTRAINT fk_notif_leave 
            FOREIGN KEY (leave_id) REFERENCES employee_leaves(id) ON DELETE CASCADE");
    } catch (Exception $e) {
        // Silently fail - table might already exist
    }
}

/**
 * Helper function: Format date range
 */
function formatDateRange($start_date, $end_date)
{
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);

    if ($start_date === $end_date) {
        return $start->format('M j, Y');
    }

    return $start->format('M j') . ' - ' . $end->format('M j, Y');
}

/**
 * Helper function: Handle file upload
 */
function handleFileUpload($file, $employee_id)
{
    // Validate file
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX');
    }

    if ($file['size'] > $max_size) {
        throw new Exception('File size exceeds 5MB limit');
    }

    // Create upload directory
    $upload_dir = __DIR__ . '/../leave_attachments/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'leave_' . $employee_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to upload file');
    }

    // Return relative path
    return 'staffmanagement/leave_attachments/' . $filename;
}

/**
 * Helper function: Log activity
 */
function logActivity($conn, $activity, $details = '')
{
    $log_entry = "[" . date('Y-m-d H:i:s') . "] [LEAVE] " . $activity;
    if ($details)
        $log_entry .= " - " . $details;
    $log_entry .= PHP_EOL;

    $log_dir = __DIR__ . '/../logs/';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    file_put_contents($log_dir . 'leave_system.log', $log_entry, FILE_APPEND | LOCK_EX);
}
?>