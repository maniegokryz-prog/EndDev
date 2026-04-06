<?php
/**
 * Offset Schedule Management API
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
        case 'get_time_bank':
            getTimeBankBalance($conn);
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
        case 'submit_cto':
            submitCtoRequest($conn);
            break;
        case 'get_employee_cto_requests':
            getEmployeeCtoRequests($conn);
            break;
        case 'admin_get_cto_requests':
            adminGetCtoRequests($conn);
            break;
        case 'admin_update_cto_status':
            adminUpdateCtoStatus($conn);
            break;
        case 'cancel_cto_request':
            cancelCtoRequest($conn);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

$conn->close();

function submitRequest($conn) {
    $employee_id = $_POST['employee_id'] ?? 0;
    $schedule_id = $_POST['original_schedule_id'] ?? 0;
    $day_of_week = $_POST['original_day_of_week'] ?? null;
    $requested_date = $_POST['requested_date'] ?? '';

    if (!$employee_id || !$schedule_id || !$requested_date || $day_of_week === null) {
        throw new Exception('Missing required fields');
    }

    // Check for existing request on the same date
    $stmt = $conn->prepare("SELECT id FROM offset_schedule_requests WHERE employee_id = ? AND requested_date = ? AND status IN ('pending', 'approved')");
    $stmt->bind_param("is", $employee_id, $requested_date);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('You already have a pending or approved offset schedule for this date.');
    }

    $sql = "INSERT INTO offset_schedule_requests (employee_id, original_schedule_id, original_day_of_week, requested_date, status) VALUES (?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $employee_id, $schedule_id, $day_of_week, $requested_date);
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit request: ' . $stmt->error);
    }


    $req_id = $conn->insert_id;

    // Notify admin
    $msg = "Employee requested an offset schedule for " . date('M d, Y', strtotime($requested_date));
    $link = "/EndDev/staffmanagement/staff_profile.php?id=" . urlencode($_POST['employee_id'] ?? '');
    $notif_sql = "INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'offset_request', ?, ?, 'admin', 0)";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->bind_param("iss", $employee_id, $msg, $link);
    $notif_stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Offset schedule requested successfully', 'request_id' => $req_id]);
}

function getEmployeeRequests($conn) {
    $employee_id = $_GET['employee_id'] ?? 0;
    if (!$employee_id) throw new Exception('Employee ID required');

    $sql = "SELECT r.*, s.schedule_name 
            FROM offset_schedule_requests r
            JOIN schedules s ON r.original_schedule_id = s.id
            WHERE r.employee_id = ?
            ORDER BY r.created_at DESC";
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

function getTimeBankBalance($conn) {
    $employee_id = $_GET['employee_id'] ?? 0;
    if (!$employee_id) throw new Exception('Employee ID required');

    $sql = "SELECT SUM(CASE WHEN transaction_type = 'earned' THEN hours ELSE -hours END) as balance 
            FROM time_bank_ledger WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    $balance = $res['balance'] ?? 0;
    
    echo json_encode(['success' => true, 'balance' => round($balance, 2)]);
}

function adminGetRequests($conn) {
    $sql = "SELECT r.*, e.first_name, e.last_name, e.employee_id as emp_code, s.schedule_name
            FROM offset_schedule_requests r
            JOIN employees e ON r.employee_id = e.id
            JOIN schedules s ON r.original_schedule_id = s.id
            WHERE r.status = 'pending'
            ORDER BY r.created_at DESC";
    $res = $conn->query($sql);
    
    $data = [];
    $daysArray = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    while ($row = $res->fetch_assoc()) {
        $dayName = $daysArray[$row['original_day_of_week']] ?? 'Unknown';
        $row['schedule_name'] = $row['schedule_name'] . ' (' . $dayName . ')';
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
}

function adminUpdateStatus($conn) {
    $request_id = $_POST['request_id'] ?? 0;
    $status = $_POST['status'] ?? '';

    if (!$request_id || !in_array($status, ['approved', 'rejected'])) {
        throw new Exception('Invalid parameters');
    }

    $stmt = $conn->prepare("UPDATE offset_schedule_requests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $request_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update status');
    }

    // Get employee_id and date for notification
    $stmt2 = $conn->prepare("SELECT employee_id, requested_date FROM offset_schedule_requests WHERE id = ?");
    $stmt2->bind_param("i", $request_id);
    $stmt2->execute();
    $req = $stmt2->get_result()->fetch_assoc();

    if ($req) {
        $msg = "Your offset schedule request for " . date('M d, Y', strtotime($req['requested_date'])) . " has been " . $status;
        $link = "/EndDev/staffmanagement/staff_profile.php?id=" . urlencode($req['employee_id']);
        $notif_sql = "INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'offset_status', ?, ?, 'employee', 0)";
        $notif_stmt = $conn->prepare($notif_sql);
        $notif_stmt->bind_param("iss", $req['employee_id'], $msg, $link);
        $notif_stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => "Request $status successfully"]);
}

function cancelRequest($conn) {
    $request_id = $_POST['request_id'] ?? 0;
    if (!$request_id) throw new Exception('Request ID required');

    $stmt = $conn->prepare("UPDATE offset_schedule_requests SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $request_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to cancel request');
    }

    echo json_encode(['success' => true, 'message' => 'Request cancelled successfully']);
}
function submitCtoRequest($conn) {
    $employee_id = $_POST['employee_id'] ?? 0;
    $requested_date = $_POST['requested_date'] ?? '';
    $hours_used = floatval($_POST['hours_used'] ?? 0);

    if (!$employee_id || !$requested_date || $hours_used <= 0) {
        throw new Exception('Missing required fields');
    }

    // Check increments of 0.5
    if (fmod($hours_used, 0.5) != 0) {
        throw new Exception('Hours used must be in half-hour increments (e.g. 1.0, 1.5, 2.0).');
    }

    // Check balance
    $sqlBalance = "SELECT SUM(CASE WHEN transaction_type = 'earned' THEN hours ELSE -hours END) as balance FROM time_bank_ledger WHERE employee_id = ?";
    $stmtBalance = $conn->prepare($sqlBalance);
    $stmtBalance->bind_param("i", $employee_id);
    $stmtBalance->execute();
    $resBalance = $stmtBalance->get_result()->fetch_assoc();
    $balance = $resBalance['balance'] ?? 0;

    if ($hours_used > $balance) {
        throw new Exception("Insufficient Time Bank balance. You have {$balance} hours, but requested {$hours_used} hours.");
    }

    // Check for existing pending/approved for this date
    $stmt = $conn->prepare("SELECT id FROM cto_requests WHERE employee_id = ? AND requested_date = ? AND status IN ('pending', 'approved')");
    $stmt->bind_param("is", $employee_id, $requested_date);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('You already have a pending or approved CTO request for this date.');
    }

    $sql = "INSERT INTO cto_requests (employee_id, requested_date, hours_used, status) VALUES (?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isd", $employee_id, $requested_date, $hours_used);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit CTO request: ' . $stmt->error);
    }
    
    // Notify admin
    $msg = "Employee requested {$hours_used} hr(s) of CTO on " . date('M d, Y', strtotime($requested_date));
    $link = "/EndDev/staffmanagement/staff_profile.php?id=" . urlencode($_POST['employee_id'] ?? '');
    $notif_sql = "INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'offset_request', ?, ?, 'admin', 0)";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->bind_param("iss", $employee_id, $msg, $link);
    $notif_stmt->execute();

    echo json_encode(['success' => true, 'message' => 'CTO requested successfully']);
}

function getEmployeeCtoRequests($conn) {
    $employee_id = $_GET['employee_id'] ?? 0;
    if (!$employee_id) throw new Exception('Employee ID required');

    $sql = "SELECT * FROM cto_requests WHERE employee_id = ? ORDER BY requested_date DESC";
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

function adminGetCtoRequests($conn) {
    $sql = "SELECT c.*, e.first_name, e.last_name, e.employee_id as emp_code
            FROM cto_requests c
            JOIN employees e ON c.employee_id = e.id
            ORDER BY c.status = 'pending' DESC, c.requested_date DESC";
    
    $res = $conn->query($sql);
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
}

function adminUpdateCtoStatus($conn) {
    $request_id = $_POST['request_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    if (!$request_id || !in_array($status, ['approved', 'rejected'])) {
        throw new Exception('Invalid parameters');
    }

    // Get current offset details
    $stmt_req = $conn->prepare("SELECT employee_id, requested_date, hours_used, status FROM cto_requests WHERE id = ?");
    $stmt_req->bind_param("i", $request_id);
    $stmt_req->execute();
    $req = $stmt_req->get_result()->fetch_assoc();
    
    if (!$req) throw new Exception('Request not found');
    $current_status = $req['status'];
    
    if ($current_status === $status) throw new Exception('Status is already '.$status);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE cto_requests SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $request_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update status');
        }

        // Ledger mechanics
        if ($status === 'approved' && $current_status !== 'approved') {
            // Deduct from ledger
            $ledgerStmt = $conn->prepare("INSERT INTO time_bank_ledger (employee_id, transaction_type, hours, source_id, description, reference_date) VALUES (?, 'used', ?, ?, 'Approved CTO Request', ?)");
            $ledgerStmt->bind_param("idis", $req['employee_id'], $req['hours_used'], $request_id, $req['requested_date']);
            if(!$ledgerStmt->execute()) throw new Exception('Failed to update time bank ledger');
            
            // Insert or Update daily_attendance so DTR engine calculates the CTO
            $daStmt = $conn->prepare("SELECT id FROM daily_attendance WHERE employee_id = ? AND attendance_date = ?");
            $daStmt->bind_param("is", $req['employee_id'], $req['requested_date']);
            $daStmt->execute();
            $daRes = $daStmt->get_result()->fetch_assoc();
            
            $note = "CTO Applied: " . floatval($req['hours_used']) . " hrs";
            if ($daRes) {
                $uStmt = $conn->prepare("UPDATE daily_attendance SET notes = CONCAT(IFNULL(notes,''), ' (', ?, ')') WHERE id = ?");
                $uStmt->bind_param("si", $note, $daRes['id']);
                $uStmt->execute();
            } else {
                $statusType = 'cto'; // acts as an override
                $nStmt = $conn->prepare("INSERT INTO daily_attendance (employee_id, attendance_date, status, notes) VALUES (?, ?, ?, ?)");
                $nStmt->bind_param("isss", $req['employee_id'], $req['requested_date'], $statusType, $note);
                $nStmt->execute();
            }
        } else if ($status === 'rejected' && $current_status === 'approved') {
            // Revert deduction
            $ledgerStmt = $conn->prepare("DELETE FROM time_bank_ledger WHERE source_id = ? AND transaction_type = 'used' AND description = 'Approved CTO Request'");
            $ledgerStmt->bind_param("i", $request_id);
            if(!$ledgerStmt->execute()) throw new Exception('Failed to revert time bank ledger');
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    // Notify employee
    $msg = "Your CTO request for " . date('M d, Y', strtotime($req['requested_date'])) . " has been " . $status;
    $link = "/EndDev/staffmanagement/staff_profile.php?id=" . urlencode($req['employee_id']); // Ideally uses public 'employee_id'
    $notif_sql = "INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'offset_status', ?, ?, 'employee', 0)";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->bind_param("iss", $req['employee_id'], $msg, $link);
    $notif_stmt->execute();

    echo json_encode(['success' => true, 'message' => "CTO Request $status successfully"]);
}

function cancelCtoRequest($conn) {
    $request_id = $_POST['request_id'] ?? 0;
    if (!$request_id) throw new Exception('Request ID required');

    $stmt = $conn->prepare("UPDATE cto_requests SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $request_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to cancel CTO request');
    }

    echo json_encode(['success' => true, 'message' => 'CTO Request cancelled successfully']);
}
?>
