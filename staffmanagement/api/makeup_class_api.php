<?php
/**
 * Makeup Class Requests API
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

date_default_timezone_set('Asia/Manila');

require_once '../../db_connection.php';
require_once '../../db_cloud_sync.php';

ob_end_clean();
ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'submit_request':
            submitRequest($conn);
            break;
        case 'get_employee_requests':
            getEmployeeRequests($conn);
            break;
        case 'admin_get_requests':
            adminGetRequests($conn);
            break;
        case 'admin_update_status':
            adminUpdateStatus($conn);
            break;
        case 'cancel_request':
            cancelRequest($conn);
            break;
        case 'get_single_request':
            getSingleRequest($conn);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if (ob_get_length())
        ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

$conn->close();

function checkOverlap($conn, $employee_id, $requested_date, $start_time, $end_time)
{
    $day_of_week = date('N', strtotime($requested_date)) - 1; // 0=Mon, 6=Sun

    // 1. Check regular schedules
    $sql1 = "
        SELECT sp.id 
        FROM employee_schedules es
        JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
        WHERE es.employee_id = ? AND es.is_active = 1 AND sp.is_active = 1
        AND sp.day_of_week = ?
        AND (sp.start_time < ? AND sp.end_time > ?)
    ";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("iiss", $employee_id, $day_of_week, $end_time, $start_time);
    $stmt1->execute();
    if ($stmt1->get_result()->num_rows > 0) {
        return "The requested time overlaps with your regular schedule.";
    }

    // 2. Check existing makeup classes
    $sql2 = "
        SELECT id 
        FROM makeup_class_requests 
        WHERE employee_id = ? AND requested_date = ? 
        AND status IN ('pending', 'approved')
        AND (start_time < ? AND end_time > ?)
    ";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("isss", $employee_id, $requested_date, $end_time, $start_time);
    $stmt2->execute();
    if ($stmt2->get_result()->num_rows > 0) {
        return "The requested time overlaps with another pending or approved makeup class.";
    }

    return null; // No overlap
}

function submitRequest($conn)
{
    $employee_id = $_POST['employee_id'] ?? 0;
    $requested_date = $_POST['requested_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $designate_class = $_POST['designate_class'] ?? '';
    $subject_code = $_POST['subject_code'] ?? '';
    $room_num = $_POST['room_num'] ?? '';
    $reason = $_POST['reason'] ?? '';

    if (!$employee_id || !$requested_date || !$start_time || !$end_time || !$reason) {
        throw new Exception('Missing required fields, including Reason.');
    }

    if (strtotime($start_time) >= strtotime($end_time)) {
        throw new Exception('Start time must be before end time.');
    }

    // Verify it's a vacant time
    $overlapError = checkOverlap($conn, $employee_id, $requested_date, $start_time, $end_time);
    if ($overlapError) {
        throw new Exception($overlapError);
    }

    // Handle optional file attachment
    $attachment_path = null;
    if (!empty($_FILES['attachment']['name'])) {
        $file = $_FILES['attachment'];

        // Check upload error FIRST before inspecting the file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload_max_filesize limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form MAX_FILE_SIZE limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            ];
            $err_msg = $upload_errors[$file['error']] ?? 'Unknown upload error (code ' . $file['error'] . ').'; 
            throw new Exception('File upload error: ' . $err_msg);
        }

        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg',
                          'application/msword',
                          'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $allowed_ext  = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        $mime = '';
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($file['tmp_name']);
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        } else {
            // Fallback to the browser-provided MIME type if server extensions are disabled
            $mime = $file['type'];
        }

        if (!in_array($file_ext, $allowed_ext)) {
            throw new Exception('Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX.');
        }
        if ($mime && !in_array($mime, $allowed_types)) {
            throw new Exception('Invalid file MIME type. Allowed: PDF, JPG, PNG, DOC, DOCX.');
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('Attachment must be 5MB or smaller.');
        }

        // Use dirname(__FILE__) chain so the path is always correct
        // regardless of the server's document root (localhost vs Hostinger).
        // __FILE__ = .../EndDev/staffmanagement/api/makeup_class_api.php
        // dirname x3 = .../EndDev/
        $enddev_root = dirname(dirname(dirname(__FILE__)));
        $upload_dir = $enddev_root . '/uploads/makeup_attachments/';
        if (!is_dir($upload_dir)) {
            if (!@mkdir($upload_dir, 0755, true)) {
                throw new Exception('Server configuration error: Cannot create upload directory. Please contact the administrator.');
            }
        }
        if (!is_writable($upload_dir)) {
            throw new Exception('Server configuration error: Upload directory is not writable. Please contact the administrator.');
        }
        $filename = 'makeup_' . $employee_id . '_' . time() . '.' . $file_ext;
        $dest     = $upload_dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('Failed to save attachment. Please try again.');
        }
        // Store a serve_file.php URL so the link works on both localhost and
        // Hostinger regardless of how the document root is configured.
        // serve_file.php resolves the file via __DIR__ (always correct).
        $attachment_path = 'EndDev/serve_file.php?file=uploads/makeup_attachments/' . $filename;
    }

    // Auto-approve if requested by an admin
    $is_admin = isset($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin';
    $status = $is_admin ? 'approved' : 'pending';

    $sql = "INSERT INTO makeup_class_requests (employee_id, requested_date, start_time, end_time, designate_class, subject_code, room_num, reason, status, attachment_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssssss", $employee_id, $requested_date, $start_time, $end_time, $designate_class, $subject_code, $room_num, $reason, $status, $attachment_path);

    if (!$stmt->execute()) {
        throw new Exception('Failed to submit request: ' . $stmt->error);
    }

    $req_id = $conn->insert_id;

    // Get employee details
    $empSql = "SELECT employee_id, first_name, last_name FROM employees WHERE id = ?";
    $empStmt = $conn->prepare($empSql);
    $empStmt->bind_param("i", $employee_id);
    $empStmt->execute();
    $empData = $empStmt->get_result()->fetch_assoc();
    $emp_pub_id = $empData['employee_id'] ?? '';

    $emp_name = trim(($empData['first_name'] ?? '') . ' ' . ($empData['last_name'] ?? ''));
    if (!$emp_name)
        $emp_name = "A Faculty Member";

    // Notify admin only if the request is NOT from an admin
    if (!$is_admin) {
        $msg = "{$emp_name} requested a Makeup Class on " . date('M d, Y', strtotime($requested_date));
        $link = "/EndDev/staffmanagement/staff_profile.php?id=" . urlencode($emp_pub_id) . "&tab=makeup&req_id=" . $req_id;
        $notif_sql = "INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'makeup_request', ?, ?, 'admin', 0)";
        $notif_stmt = $conn->prepare($notif_sql);
        $notif_stmt->bind_param("iss", $employee_id, $msg, $link);
        $notif_stmt->execute();
    }

    $success_msg = $is_admin ? 'Makeup class added and auto-approved successfully' : 'Makeup class requested successfully';
    echo json_encode(['success' => true, 'message' => $success_msg, 'request_id' => $req_id]);
}

function getEmployeeRequests($conn)
{
    $employee_id = $_GET['employee_id'] ?? 0;
    if (!$employee_id)
        throw new Exception('Employee ID required');

    $sql = "SELECT m.* 
            FROM makeup_class_requests m
            WHERE m.employee_id = ?
            ORDER BY m.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
}

function adminGetRequests($conn)
{
    $employee_id = $_GET['employee_id'] ?? 0;

    $sql = "SELECT m.*, e.first_name, e.last_name, e.employee_id as emp_code
            FROM makeup_class_requests m
            JOIN employees e ON m.employee_id = e.id";

    if ($employee_id) {
        $sql .= " WHERE m.employee_id = ?";
        $stmt = $conn->prepare($sql . " ORDER BY m.created_at DESC");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        // Fallback for global fetch if needed: show pending and approved so they remain for review
        $sql .= " WHERE m.status IN ('pending', 'approved') ORDER BY m.created_at DESC";
        $res = $conn->query($sql);
    }

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
}

function getSingleRequest($conn)
{
    $req_id = $_GET['req_id'] ?? 0;
    if (!$req_id)
        throw new Exception('Request ID required');

    $sql = "SELECT m.*, e.first_name, e.last_name, e.employee_id as emp_code
            FROM makeup_class_requests m
            JOIN employees e ON m.employee_id = e.id
            WHERE m.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $req_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        echo json_encode(['success' => true, 'data' => $res]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not found']);
    }
}

function adminUpdateStatus($conn)
{
    $request_id = $_POST['request_id'] ?? 0;
    $status = $_POST['status'] ?? '';

    if (!$request_id || !in_array($status, ['approved', 'rejected'])) {
        throw new Exception('Invalid parameters');
    }

    $stmt = $conn->prepare("UPDATE makeup_class_requests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $request_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update status');
    }

    // Get employee_id and date for notification
    $stmt2 = $conn->prepare("SELECT m.employee_id, m.requested_date, e.employee_id as pub_id FROM makeup_class_requests m JOIN employees e ON m.employee_id=e.id WHERE m.id = ?");
    $stmt2->bind_param("i", $request_id);
    $stmt2->execute();
    $req = $stmt2->get_result()->fetch_assoc();

    if ($req) {
        $msg = "Your makeup class request for " . date('M d, Y', strtotime($req['requested_date'])) . " has been " . $status;
        $link = "/EndDev/staffmanagement/staff_profile.php?id=" . urlencode($req['pub_id']) . "&tab=makeup";
        $notif_sql = "INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'makeup_status', ?, ?, 'employee', 0)";
        $notif_stmt = $conn->prepare($notif_sql);
        $notif_stmt->bind_param("iss", $req['employee_id'], $msg, $link);
        $notif_stmt->execute();
    }

    if (function_exists('syncToCloud')) {
        syncToCloud('makeup_class_requests', [], 'update', "id = $request_id");
    }

    echo json_encode(['success' => true, 'message' => "Request $status successfully"]);
}

function cancelRequest($conn)
{
    $request_id = $_POST['request_id'] ?? 0;
    $cancel_reason = trim($_POST['cancel_reason'] ?? '');
    if (!$request_id)
        throw new Exception('Request ID required');
    if (!$cancel_reason)
        throw new Exception('Please provide a reason for cancellation.');

    // Allow cancelling both pending and approved requests
    $stmt = $conn->prepare("UPDATE makeup_class_requests SET status = 'cancelled', cancel_reason = ? WHERE id = ? AND status IN ('pending', 'approved')");
    $stmt->bind_param("si", $cancel_reason, $request_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to cancel request');
    }
    if ($stmt->affected_rows === 0) {
        throw new Exception('Request not found or cannot be cancelled.');
    }

    // Notify admin about the cancellation
    $stmt2 = $conn->prepare("SELECT m.employee_id, m.requested_date, e.first_name, e.last_name, e.employee_id as pub_id FROM makeup_class_requests m JOIN employees e ON m.employee_id = e.id WHERE m.id = ?");
    $stmt2->bind_param("i", $request_id);
    $stmt2->execute();
    $req = $stmt2->get_result()->fetch_assoc();
    if ($req) {
        $emp_name = trim($req['first_name'] . ' ' . $req['last_name']);
        $msg = "{$emp_name} cancelled their Makeup Class for " . date('M d, Y', strtotime($req['requested_date'])) . ". Reason: {$cancel_reason}";
        $link = "/EndDev/staffmanagement/staff_profile.php?id=" . urlencode($req['pub_id']) . "&tab=makeup";
        $notif_sql = "INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'makeup_cancel', ?, ?, 'admin', 0)";
        $notif_stmt = $conn->prepare($notif_sql);
        $notif_stmt->bind_param("iss", $req['employee_id'], $msg, $link);
        $notif_stmt->execute();
    }

    if (function_exists('syncToCloud')) {
        syncToCloud('makeup_class_requests', [], 'update', "id = $request_id");
    }

    echo json_encode(['success' => true, 'message' => 'Request cancelled successfully']);
}
?>