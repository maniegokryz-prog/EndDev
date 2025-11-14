<?php
/**
 * Leave Management API
 * Handles employee leave requests with approval workflow
 * 
 * Actions:
 * - POST ?action=submit_leave - Submit a new leave request
 * - GET ?action=get_pending - Get all pending leave requests (admin only)
 * - GET ?action=get_employee_leaves&employee_id=X - Get leaves for specific employee
 * - POST ?action=approve_leave - Approve a leave request (admin only)
 * - POST ?action=reject_leave - Reject a leave request (admin only)
 * - POST ?action=cancel_leave - Cancel own leave request (employee)
 * - GET ?action=get_dashboard_leaves - Get leaves for dashboard display (admin only)
 */

// Start output buffering FIRST
ob_start();

// Use API-specific database connection (no headers)
require '../../db_connection_api.php';

// Clear any output
ob_clean();

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

/**
 * Authenticate user session
 */
function authenticateUser() {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Please log in'
        ]);
        exit;
    }
    
    return [
        'employee_id' => $_SESSION['employee_id'],
        'username' => $_SESSION['username'],
        'user_type' => $_SESSION['user_type']
    ];
}

/**
 * Check if user is admin
 */
function requireAdmin($user) {
    if ($user['user_type'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden: Admin access required'
        ]);
        exit;
    }
}

/**
 * Get employee database ID from employee_id string
 */
function getEmployeeDbId($conn, $employee_id) {
    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, email FROM employees WHERE employee_id = ? AND status = 'active'");
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $employee = $result->fetch_assoc();
    $stmt->close();
    
    return [
        'id' => $employee['id'],
        'full_name' => trim($employee['first_name'] . ' ' . ($employee['middle_name'] ? $employee['middle_name'] . ' ' : '') . $employee['last_name']),
        'email' => $employee['email']
    ];
}

/**
 * Get or create leave type
 */
function getOrCreateLeaveType($conn, $type_name) {
    // Check if leave type exists
    $stmt = $conn->prepare("SELECT id FROM leave_types WHERE type_name = ?");
    $stmt->bind_param("s", $type_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id'];
    }
    $stmt->close();
    
    // Create new leave type
    $stmt = $conn->prepare("INSERT INTO leave_types (type_name) VALUES (?)");
    $stmt->bind_param("s", $type_name);
    $stmt->execute();
    $leave_type_id = $stmt->insert_id;
    $stmt->close();
    
    return $leave_type_id;
}

/**
 * Submit a new leave request
 */
function submitLeave($conn, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['employee_id']) || !isset($input['leave_type']) || 
        !isset($input['start_date']) || !isset($input['end_date'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: employee_id, leave_type, start_date, end_date'
        ]);
        return;
    }
    
    $employee_id = $input['employee_id'];
    $leave_type = $input['leave_type'];
    $start_date = $input['start_date'];
    $end_date = $input['end_date'];
    $reason = isset($input['reason']) ? $input['reason'] : null;
    
    // Validate employee
    $employee = getEmployeeDbId($conn, $employee_id);
    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found'
        ]);
        return;
    }
    
    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Start date must be before or equal to end date'
        ]);
        return;
    }
    
    // Check for overlapping leave requests
    $stmt = $conn->prepare("
        SELECT id, start_date, end_date, status 
        FROM employee_leaves 
        WHERE employee_id = ? 
          AND status IN ('pending', 'approved')
          AND (
            (start_date <= ? AND end_date >= ?) OR
            (start_date <= ? AND end_date >= ?) OR
            (start_date >= ? AND end_date <= ?)
          )
    ");
    $stmt->bind_param("issssss", 
        $employee['id'], 
        $end_date, $start_date,
        $end_date, $end_date,
        $start_date, $end_date
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $overlapping = $result->fetch_assoc();
        $stmt->close();
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Overlapping leave request exists (' . $overlapping['status'] . ' from ' . $overlapping['start_date'] . ' to ' . $overlapping['end_date'] . ')'
        ]);
        return;
    }
    $stmt->close();
    
    // Get or create leave type
    $leave_type_id = getOrCreateLeaveType($conn, $leave_type);
    
    // Insert leave request
    $stmt = $conn->prepare("
        INSERT INTO employee_leaves 
        (employee_id, leave_type_id, start_date, end_date, reason, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param("iisss", $employee['id'], $leave_type_id, $start_date, $end_date, $reason);
    
    if ($stmt->execute()) {
        $leave_id = $stmt->insert_id;
        $stmt->close();
        
        // Log to file
        $log_file = __DIR__ . '/../../logs/leave_requests.log';
        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_message = date('Y-m-d H:i:s') . " - SUBMITTED | Employee: {$employee['full_name']} ($employee_id) | Type: $leave_type | From: $start_date To: $end_date | ID: $leave_id\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
        
        echo json_encode([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'data' => [
                'leave_id' => $leave_id,
                'employee_name' => $employee['full_name'],
                'status' => 'pending'
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $stmt->error
        ]);
    }
}

/**
 * Get all pending leave requests (admin only)
 */
function getPendingLeaves($conn, $user) {
    requireAdmin($user);
    
    $stmt = $conn->prepare("
        SELECT 
            el.id,
            el.employee_id,
            e.employee_id as employee_code,
            CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name, ' '), ''), e.last_name) as employee_name,
            e.profile_photo,
            e.department,
            e.position,
            lt.type_name as leave_type,
            el.start_date,
            el.end_date,
            el.reason,
            el.status,
            el.created_at,
            DATEDIFF(el.end_date, el.start_date) + 1 as duration_days
        FROM employee_leaves el
        JOIN employees e ON el.employee_id = e.id
        JOIN leave_types lt ON el.leave_type_id = lt.id
        WHERE el.status = 'pending'
        ORDER BY el.created_at ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaves = [];
    while ($row = $result->fetch_assoc()) {
        $leaves[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total' => count($leaves),
            'leaves' => $leaves
        ]
    ]);
}

/**
 * Get leaves for specific employee
 */
function getEmployeeLeaves($conn, $user) {
    if (!isset($_GET['employee_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'employee_id parameter required'
        ]);
        return;
    }
    
    $employee_id = $_GET['employee_id'];
    
    // Get employee database ID
    $employee = getEmployeeDbId($conn, $employee_id);
    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found'
        ]);
        return;
    }
    
    // Get status filter (default: all)
    $status_filter = isset($_GET['status']) ? $_GET['status'] : null;
    
    $query = "
        SELECT 
            el.id,
            lt.type_name as leave_type,
            el.start_date,
            el.end_date,
            el.reason,
            el.status,
            el.created_at,
            el.updated_at,
            DATEDIFF(el.end_date, el.start_date) + 1 as duration_days
        FROM employee_leaves el
        JOIN leave_types lt ON el.leave_type_id = lt.id
        WHERE el.employee_id = ?
    ";
    
    if ($status_filter) {
        $query .= " AND el.status = ?";
    }
    
    $query .= " ORDER BY el.created_at DESC";
    
    if ($status_filter) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $employee['id'], $status_filter);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $employee['id']);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaves = [];
    while ($row = $result->fetch_assoc()) {
        $leaves[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'employee_id' => $employee_id,
            'employee_name' => $employee['full_name'],
            'total' => count($leaves),
            'leaves' => $leaves
        ]
    ]);
}

/**
 * Approve a leave request (admin only)
 */
function approveLeave($conn, $user) {
    requireAdmin($user);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['leave_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'leave_id required'
        ]);
        return;
    }
    
    $leave_id = $input['leave_id'];
    
    // Get leave details
    $stmt = $conn->prepare("
        SELECT el.*, e.employee_id as employee_code, 
               CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name, ' '), ''), e.last_name) as employee_name,
               lt.type_name
        FROM employee_leaves el
        JOIN employees e ON el.employee_id = e.id
        JOIN leave_types lt ON el.leave_type_id = lt.id
        WHERE el.id = ?
    ");
    $stmt->bind_param("i", $leave_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Leave request not found'
        ]);
        return;
    }
    
    $leave = $result->fetch_assoc();
    $stmt->close();
    
    if ($leave['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Leave request is already ' . $leave['status']
        ]);
        return;
    }
    
    // Update status to approved
    $stmt = $conn->prepare("UPDATE employee_leaves SET status = 'approved', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $leave_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log to file
        $log_file = __DIR__ . '/../../logs/leave_requests.log';
        $log_message = date('Y-m-d H:i:s') . " - APPROVED | Admin: {$user['username']} | Employee: {$leave['employee_name']} ({$leave['employee_code']}) | Type: {$leave['type_name']} | From: {$leave['start_date']} To: {$leave['end_date']} | ID: $leave_id\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
        
        echo json_encode([
            'success' => true,
            'message' => 'Leave request approved successfully',
            'data' => [
                'leave_id' => $leave_id,
                'employee_name' => $leave['employee_name'],
                'status' => 'approved'
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $stmt->error
        ]);
    }
}

/**
 * Reject a leave request (admin only)
 */
function rejectLeave($conn, $user) {
    requireAdmin($user);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['leave_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'leave_id required'
        ]);
        return;
    }
    
    $leave_id = $input['leave_id'];
    $rejection_reason = isset($input['reason']) ? $input['reason'] : null;
    
    // Get leave details
    $stmt = $conn->prepare("
        SELECT el.*, e.employee_id as employee_code, 
               CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name, ' '), ''), e.last_name) as employee_name,
               lt.type_name
        FROM employee_leaves el
        JOIN employees e ON el.employee_id = e.id
        JOIN leave_types lt ON el.leave_type_id = lt.id
        WHERE el.id = ?
    ");
    $stmt->bind_param("i", $leave_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Leave request not found'
        ]);
        return;
    }
    
    $leave = $result->fetch_assoc();
    $stmt->close();
    
    if ($leave['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Leave request is already ' . $leave['status']
        ]);
        return;
    }
    
    // Update status to rejected
    $stmt = $conn->prepare("UPDATE employee_leaves SET status = 'rejected', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $leave_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log to file
        $log_file = __DIR__ . '/../../logs/leave_requests.log';
        $log_message = date('Y-m-d H:i:s') . " - REJECTED | Admin: {$user['username']} | Employee: {$leave['employee_name']} ({$leave['employee_code']}) | Type: {$leave['type_name']} | From: {$leave['start_date']} To: {$leave['end_date']} | ID: $leave_id";
        if ($rejection_reason) {
            $log_message .= " | Reason: $rejection_reason";
        }
        $log_message .= "\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
        
        echo json_encode([
            'success' => true,
            'message' => 'Leave request rejected',
            'data' => [
                'leave_id' => $leave_id,
                'employee_name' => $leave['employee_name'],
                'status' => 'rejected'
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $stmt->error
        ]);
    }
}

/**
 * Get leaves for dashboard display (admin only)
 */
function getDashboardLeaves($conn, $user) {
    requireAdmin($user);
    
    // Get today's date
    $today = date('Y-m-d');
    
    // Get employees on leave today (approved leaves)
    $stmt = $conn->prepare("
        SELECT 
            e.employee_id,
            CONCAT(e.first_name, ' ', IFNULL(CONCAT(e.middle_name, ' '), ''), e.last_name) as employee_name,
            e.profile_photo,
            e.department,
            e.position,
            lt.type_name as leave_type,
            el.start_date,
            el.end_date,
            DATEDIFF(el.end_date, ?) + 1 as days_remaining
        FROM employee_leaves el
        JOIN employees e ON el.employee_id = e.id
        JOIN leave_types lt ON el.leave_type_id = lt.id
        WHERE el.status = 'approved'
          AND el.start_date <= ?
          AND el.end_date >= ?
        ORDER BY el.end_date ASC
    ");
    $stmt->bind_param("sss", $today, $today, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $on_leave = [];
    while ($row = $result->fetch_assoc()) {
        $on_leave[] = $row;
    }
    $stmt->close();
    
    // Get pending leave requests count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employee_leaves WHERE status = 'pending'");
    $stmt->execute();
    $result = $stmt->get_result();
    $pending_count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'on_leave_today' => $on_leave,
            'pending_requests' => $pending_count
        ]
    ]);
}

// ========================================
// Main Request Handler
// ========================================

// Authenticate user
$user = authenticateUser();

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'submit_leave':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        submitLeave($conn, $user);
        break;
        
    case 'get_pending':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        getPendingLeaves($conn, $user);
        break;
        
    case 'get_employee_leaves':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        getEmployeeLeaves($conn, $user);
        break;
        
    case 'approve_leave':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        approveLeave($conn, $user);
        break;
        
    case 'reject_leave':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        rejectLeave($conn, $user);
        break;
        
    case 'get_dashboard_leaves':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        getDashboardLeaves($conn, $user);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action. Available actions: submit_leave, get_pending, get_employee_leaves, approve_leave, reject_leave, get_dashboard_leaves'
        ]);
}
?>
