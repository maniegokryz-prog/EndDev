<?php
/**
 * Clean Leave Request API - No dependencies
 */

// Completely disable all error output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/leave_errors.log');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Start output buffering to catch any stray output
ob_start();

// Database connection (inline to avoid any issues)
$servername = "localhost";
$username = "root";
$password = "Confirmp@ssword123";
$dbname = "database_records";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'submit_request':
            submitLeaveRequest($conn);
            break;
        case 'get_employee_requests':
            getEmployeeRequests($conn);
            break;
        case 'approve_request':
            approveLeaveRequest($conn);
            break;
        case 'reject_request':
            rejectLeaveRequest($conn);
            break;
        case 'cancel_request':
            cancelLeaveRequest($conn);
            break;
        case 'get_notifications':
            getAdminNotifications($conn);
            break;
        case 'mark_notification_read':
            markNotificationRead($conn);
            break;
        case 'get_pending_requests':
            getPendingRequests($conn);
            break;
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

$conn->close();

function submitLeaveRequest($conn) {
    ob_end_clean();
    
    try {
        $employee_id = $_POST['employee_id'] ?? 0;
        $leave_type = $_POST['leave_type'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $reason = $_POST['reason'] ?? '';
        $is_admin = ($_POST['is_admin'] ?? '0') === '1';
        $auto_approve = ($_POST['auto_approve'] ?? '0') === '1';
        
        if (!$employee_id || !$leave_type || !$start_date || !$end_date) {
            echo json_encode([
                'success' => false,
                'error' => 'Missing required fields'
            ]);
            return;
        }

        // If times are provided, append them to dates (assuming DATETIME columns or string storage)
        // If columns are DATE, this will just be truncated by MySQL which is acceptable fallback
        if ($start_time) {
            $start_date .= ' ' . $start_time;
        }
        if ($end_time) {
            $end_date .= ' ' . $end_time;
        }
    
    // Handle file upload
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
        $filename = $_FILES['attachment']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid file type. Allowed: JPG, PNG, PDF, DOC, DOCX'
            ]);
            return;
        }
        
        if ($_FILES['attachment']['size'] > 5242880) {
            echo json_encode([
                'success' => false,
                'error' => 'File too large. Maximum size: 5MB'
            ]);
            return;
        }
        
        $upload_dir = __DIR__ . '/../leave_attachments/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        
        $new_filename = 'leave_' . $employee_id . '_' . time() . '_' . uniqid() . '.' . $ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to upload file'
            ]);
            return;
        }
        
        $attachment_path = 'staffmanagement/leave_attachments/' . $new_filename;
    }
    
    // Get or create leave type
    $stmt = $conn->prepare("SELECT id FROM leave_types WHERE type_name = ?");
    $stmt->bind_param("s", $leave_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $leave_type_id = $row['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO leave_types (type_name, description) VALUES (?, ?)");
        $desc = $leave_type . " leave";
        $stmt->bind_param("ss", $leave_type, $desc);
        $stmt->execute();
        $leave_type_id = $conn->insert_id;
    }
    
    // Check if attachment column exists
    $check_column = $conn->query("SHOW COLUMNS FROM employee_leaves LIKE 'attachment'");
    $has_attachment_column = $check_column->num_rows > 0;
    
    // Determine initial status
    $initial_status = ($is_admin && $auto_approve) ? 'approved' : 'pending';
    
    // Insert leave request
    if ($has_attachment_column && $attachment_path) {
        $sql = "INSERT INTO employee_leaves (employee_id, leave_type_id, start_date, end_date, reason, status, attachment) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssss", $employee_id, $leave_type_id, $start_date, $end_date, $reason, $initial_status, $attachment_path);
    } else {
        $sql = "INSERT INTO employee_leaves (employee_id, leave_type_id, start_date, end_date, reason, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissss", $employee_id, $leave_type_id, $start_date, $end_date, $reason, $initial_status);
    }
    
    if ($stmt->execute()) {
        $leave_id = $conn->insert_id;
        
        // Create notification for admin (only if not auto-approved)
        if ($initial_status === 'pending') {
            // Get employee name and code
            $stmt_emp = $conn->prepare("SELECT first_name, last_name, employee_id FROM employees WHERE id = ?");
            $stmt_emp->bind_param("i", $employee_id);
            $stmt_emp->execute();
            $emp_result = $stmt_emp->get_result();
            $emp_name = "Employee";
            $emp_code = "";
            
            if ($emp_row = $emp_result->fetch_assoc()) {
                $emp_name = $emp_row['first_name'] . ' ' . $emp_row['last_name'];
                $emp_code = $emp_row['employee_id'];
            }
            
            // Check if notifications table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
            if ($check_table->num_rows > 0) {
                $message = $emp_name . " has submitted a leave request (Pending for approval)";
                $link = "/EndDev/staffmanagement/staff_profile.php?id=" . $emp_code;
                
                // Check if link column exists
                $check_column = $conn->query("SHOW COLUMNS FROM notifications LIKE 'link'");
                if ($check_column->num_rows > 0) {
                    $stmt_notif = $conn->prepare("INSERT INTO notifications (employee_id, leave_id, type, message, link, target, is_read) VALUES (?, ?, 'leave_request', ?, ?, 'admin', 0)");
                    $stmt_notif->bind_param("iiss", $employee_id, $leave_id, $message, $link);
                } else {
                    $stmt_notif = $conn->prepare("INSERT INTO notifications (employee_id, leave_id, type, message, target, is_read) VALUES (?, ?, 'leave_request', ?, 'admin', 0)");
                    $stmt_notif->bind_param("iis", $employee_id, $leave_id, $message);
                }
                $stmt_notif->execute();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'leave_id' => $leave_id,
            'status' => $initial_status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to submit leave request: ' . $stmt->error
        ]);
    }
    
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getEmployeeRequests($conn) {
    ob_end_clean();
    
    $employee_id = $_GET['employee_id'] ?? 0;
    
    $sql = "SELECT el.*, el.rejection_reason, 1 as force_renew, lt.type_name as leave_type
            FROM employee_leaves el
            INNER JOIN leave_types lt ON el.leave_type_id = lt.id
            WHERE el.employee_id = ?
            ORDER BY el.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
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

function formatDateRange($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    if ($start_date === $end_date) {
        return $start->format('M j, Y');
    }
    
    return $start->format('M j') . ' - ' . $end->format('M j, Y');
}

function approveLeaveRequest($conn) {
    ob_end_clean();
    
    try {
        $leave_id = $_POST['leave_id'] ?? 0;
        
        if (!$leave_id) {
            echo json_encode([
                'success' => false,
                'error' => 'Leave ID is required'
            ]);
            return;
        }
        
        // Get leave details and employee info
        $sql = "SELECT el.employee_id, e.first_name, e.last_name, e.employee_id as emp_code 
                FROM employee_leaves el
                JOIN employees e ON el.employee_id = e.id
                WHERE el.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $leave_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $leave = $result->fetch_assoc();
        
        if (!$leave) {
            echo json_encode([
                'success' => false,
                'error' => 'Leave request not found'
            ]);
            return;
        }
        
        // Update leave status
        $sql = "UPDATE employee_leaves SET status = 'approved' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $leave_id);
        
        if ($stmt->execute()) {
            // Create notification for employee
            $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
            if ($check_table->num_rows > 0) {
                $emp_name = $leave['first_name'] . ' ' . $leave['last_name'];
                $message = $emp_name . ", Your leave request has been Approved";
                $link = "/EndDev/staffmanagement/staff_profile.php?id=" . $leave['emp_code'];
                
                // Check if link column exists
                $check_column = $conn->query("SHOW COLUMNS FROM notifications LIKE 'link'");
                if ($check_column->num_rows > 0) {
                    $stmt_notif = $conn->prepare("INSERT INTO notifications (employee_id, leave_id, type, message, link, target, is_read) VALUES (?, ?, 'leave_approved', ?, ?, 'employee', 0)");
                    $stmt_notif->bind_param("iiss", $leave['employee_id'], $leave_id, $message, $link);
                } else {
                    $stmt_notif = $conn->prepare("INSERT INTO notifications (employee_id, leave_id, type, message, target, is_read) VALUES (?, ?, 'leave_approved', ?, 'employee', 0)");
                    $stmt_notif->bind_param("iis", $leave['employee_id'], $leave_id, $message);
                }
                $stmt_notif->execute();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Leave request approved successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to approve leave request'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function rejectLeaveRequest($conn) {
    ob_end_clean();
    
    try {
        $leave_id = $_POST['leave_id'] ?? 0;
        
        if (!$leave_id) {
            echo json_encode([
                'success' => false,
                'error' => 'Leave ID is required'
            ]);
            return;
        }
        
        // Get leave details and employee info
        $sql = "SELECT el.employee_id, e.first_name, e.last_name, e.employee_id as emp_code
                FROM employee_leaves el
                JOIN employees e ON el.employee_id = e.id
                WHERE el.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $leave_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $leave = $result->fetch_assoc();
        
        if (!$leave) {
            echo json_encode([
                'success' => false,
                'error' => 'Leave request not found'
            ]);
            return;
        }
        
        // Update leave status and save reason
        $rejection_reason = $_POST['rejection_reason'] ?? null;
        
        $sql = "UPDATE employee_leaves SET status = 'rejected', rejection_reason = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $rejection_reason, $leave_id);
        
        if ($stmt->execute()) {
            // Create notification for employee
            $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
            if ($check_table->num_rows > 0) {
                $emp_name = $leave['first_name'] . ' ' . $leave['last_name'];
                $message = $emp_name . ", Your leave request has been Rejected";
                $link = "/EndDev/staffmanagement/staff_profile.php?id=" . $leave['emp_code'];
                
                // Check if link column exists
                $check_column = $conn->query("SHOW COLUMNS FROM notifications LIKE 'link'");
                if ($check_column->num_rows > 0) {
                    $stmt_notif = $conn->prepare("INSERT INTO notifications (employee_id, leave_id, type, message, link, target, is_read) VALUES (?, ?, 'leave_rejected', ?, ?, 'employee', 0)");
                    $stmt_notif->bind_param("iiss", $leave['employee_id'], $leave_id, $message, $link);
                } else {
                    $stmt_notif = $conn->prepare("INSERT INTO notifications (employee_id, leave_id, type, message, target, is_read) VALUES (?, ?, 'leave_rejected', ?, 'employee', 0)");
                    $stmt_notif->bind_param("iis", $leave['employee_id'], $leave_id, $message);
                }
                $stmt_notif->execute();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Leave request rejected successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to reject leave request'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function cancelLeaveRequest($conn) {
    ob_end_clean();
    
    try {
        $leave_id = $_POST['leave_id'] ?? 0;
        $cancelled_by = $_POST['cancelled_by'] ?? 'user';
        
        if (!$leave_id) {
            echo json_encode([
                'success' => false,
                'error' => 'Leave ID is required'
            ]);
            return;
        }
        
        // Get leave details
        $sql = "SELECT employee_id, start_date, end_date, status FROM employee_leaves WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $leave_id);
        $stmt->execute();
        $leave = $stmt->get_result()->fetch_assoc();
        
        if (!$leave) {
            echo json_encode([
                'success' => false,
                'error' => 'Leave request not found'
            ]);
            return;
        }
        
        // Delete the leave request
        $sql = "DELETE FROM employee_leaves WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $leave_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Leave request cancelled successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to cancel leave request'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getAdminNotifications($conn) {
    ob_end_clean();
    
    try {
        // Get user info from session
        $user_id = $_SESSION['user_id'] ?? null;
        $user_role = $_SESSION['user_role'] ?? 'employee';
        
        if (!$user_id) {
            echo json_encode([
                'success' => true,
                'count' => 0,
                'unread_count' => 0,
                'data' => []
            ]);
            return;
        }
        
        // Check if notifications table exists
        $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($check_table->num_rows == 0) {
            echo json_encode([
                'success' => true,
                'count' => 0,
                'unread_count' => 0,
                'data' => []
            ]);
            return;
        }
        
        // Load notifications based on role
        if ($user_role === 'admin') {
            $sql = "SELECT n.*, e.first_name, e.last_name
                    FROM notifications n
                    LEFT JOIN employees e ON n.employee_id = e.id
                    WHERE n.target = 'admin' OR (n.target = 'employee' AND e.id = ?)
                    ORDER BY n.is_read ASC, n.created_at DESC
                    LIMIT 50";
        } else {
            $sql = "SELECT n.*, e.first_name, e.last_name
                    FROM notifications n
                    LEFT JOIN employees e ON n.employee_id = e.id
                    WHERE n.target = 'employee' AND e.id = ?
                    ORDER BY n.is_read ASC, n.created_at DESC
                    LIMIT 50";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'message' => $row['message'],
                'link' => $row['link'] ?? null,
                'is_read' => $row['is_read'],
                'created_at' => $row['created_at'],
                'leave_id' => $row['leave_id']
            ];
        }
        
        $unread_count = 0;
        foreach ($notifications as $n) {
            if ($n['is_read'] == 0) $unread_count++;
        }
        
        echo json_encode([
            'success' => true,
            'count' => count($notifications),
            'unread_count' => $unread_count,
            'data' => $notifications
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function markNotificationRead($conn) {
    ob_end_clean();
    
    try {
        $notification_id = $_POST['notification_id'] ?? 0;
        
        if (!$notification_id) {
            echo json_encode([
                'success' => false,
                'error' => 'Notification ID required'
            ]);
            return;
        }
        
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getPendingRequests($conn) {
    ob_end_clean();
    echo json_encode(['success' => true, 'count' => 0, 'data' => []]);
}
