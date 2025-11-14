<?php
/**
 * Manual Attendance API
 * Allows admins to manually add attendance records for employees
 * 
 * Actions:
 * - POST ?action=add_manual - Add manual attendance records
 * - GET ?action=get_attendance&employee_id=X - Get attendance history for an employee
 * 
 * Security: Admin-only access
 */

// Start output buffering
ob_start();

// Use API-specific database connection
require '../../db_connection_api.php';

// Clear output
ob_clean();

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');

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
    
    // Only admins can add manual attendance
    if ($_SESSION['user_type'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden: Admin access required'
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
 * Get employee database ID from employee_id string
 */
function getEmployeeDbId($conn, $employee_id) {
    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name FROM employees WHERE employee_id = ? AND status = 'active'");
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
        'full_name' => trim($employee['first_name'] . ' ' . ($employee['middle_name'] ? $employee['middle_name'] . ' ' : '') . $employee['last_name'])
    ];
}

/**
 * Calculate attendance metrics
 */
function calculateAttendanceMetrics($conn, $employee_db_id, $attendance_date, $time_in, $time_out) {
    $metrics = [
        'scheduled_hours' => 0,
        'actual_hours' => 0,
        'late_minutes' => 0,
        'early_departure_minutes' => 0,
        'overtime_minutes' => 0,
        'status' => 'incomplete'
    ];
    
    // If only time_in or only time_out, status is incomplete
    if (!$time_in || !$time_out) {
        return $metrics;
    }
    
    // Get employee schedule for the given date
    $day_of_week = date('w', strtotime($attendance_date)); // 0 (Sunday) to 6 (Saturday)
    
    $stmt = $conn->prepare("
        SELECT sp.start_time, sp.end_time, sp.period_name
        FROM employee_schedules es
        JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
        WHERE es.employee_id = ?
          AND es.is_active = 1
          AND sp.day_of_week = ?
          AND sp.is_active = 1
          AND (es.end_date IS NULL OR es.end_date >= ?)
        ORDER BY sp.start_time ASC
    ");
    $stmt->bind_param("iis", $employee_db_id, $day_of_week, $attendance_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedule_periods = [];
    while ($row = $result->fetch_assoc()) {
        $schedule_periods[] = $row;
    }
    $stmt->close();
    
    if (count($schedule_periods) === 0) {
        // No schedule found - calculate actual hours only
        $time_in_obj = new DateTime($attendance_date . ' ' . $time_in);
        $time_out_obj = new DateTime($attendance_date . ' ' . $time_out);
        
        // Handle overnight shifts
        if ($time_out_obj < $time_in_obj) {
            $time_out_obj->modify('+1 day');
        }
        
        $diff_minutes = ($time_out_obj->getTimestamp() - $time_in_obj->getTimestamp()) / 60;
        $metrics['actual_hours'] = round($diff_minutes / 60, 2);
        $metrics['status'] = 'complete';
        return $metrics;
    }
    
    // Calculate scheduled hours (total minutes from all periods)
    $total_scheduled_minutes = 0;
    $first_period_start = null;
    $last_period_end = null;
    
    foreach ($schedule_periods as $period) {
        $start = new DateTime($attendance_date . ' ' . $period['start_time']);
        $end = new DateTime($attendance_date . ' ' . $period['end_time']);
        
        if ($end < $start) {
            $end->modify('+1 day');
        }
        
        $period_minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
        $total_scheduled_minutes += $period_minutes;
        
        if ($first_period_start === null) {
            $first_period_start = $start;
        }
        $last_period_end = $end;
    }
    
    $metrics['scheduled_hours'] = round($total_scheduled_minutes / 60, 2);
    
    // Calculate late minutes (compared to first period start time)
    if ($first_period_start) {
        $actual_time_in = new DateTime($attendance_date . ' ' . $time_in);
        $diff = $actual_time_in->getTimestamp() - $first_period_start->getTimestamp();
        
        if ($diff > 0) {
            $metrics['late_minutes'] = floor($diff / 60);
        }
    }
    
    // Calculate early departure and overtime (compared to last period end time)
    if ($last_period_end) {
        $actual_time_out = new DateTime($attendance_date . ' ' . $time_out);
        
        // Handle overnight
        if ($actual_time_out < $actual_time_in) {
            $actual_time_out->modify('+1 day');
        }
        
        $diff = $actual_time_out->getTimestamp() - $last_period_end->getTimestamp();
        
        if ($diff < 0) {
            // Left early
            $metrics['early_departure_minutes'] = abs(floor($diff / 60));
        } else if ($diff > 0) {
            // Overtime
            $metrics['overtime_minutes'] = floor($diff / 60);
        }
    }
    
    // Calculate actual hours worked
    $actual_time_in = new DateTime($attendance_date . ' ' . $time_in);
    $actual_time_out = new DateTime($attendance_date . ' ' . $time_out);
    
    if ($actual_time_out < $actual_time_in) {
        $actual_time_out->modify('+1 day');
    }
    
    $actual_minutes = ($actual_time_out->getTimestamp() - $actual_time_in->getTimestamp()) / 60;
    
    // Subtract late and early departure from actual hours
    $actual_minutes = max(0, $actual_minutes - $metrics['late_minutes'] - $metrics['early_departure_minutes']);
    
    $metrics['actual_hours'] = round($actual_minutes / 60, 2);
    $metrics['status'] = 'complete';
    
    return $metrics;
}

/**
 * Add manual attendance records
 */
function addManualAttendance($conn, $user) {
    global $conn;
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['employee_id']) || !isset($input['records']) || !is_array($input['records'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request: employee_id and records array required'
        ]);
        return;
    }
    
    $employee_id = $input['employee_id'];
    $records = $input['records'];
    
    // Validate employee exists
    $employee = getEmployeeDbId($conn, $employee_id);
    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found'
        ]);
        return;
    }
    
    $employee_db_id = $employee['id'];
    $employee_name = $employee['full_name'];
    
    // Validate records
    $validated_records = [];
    foreach ($records as $index => $record) {
        if (!isset($record['date']) || empty($record['date'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Record #" . ($index + 1) . ": Date is required"
            ]);
            return;
        }
        
        $attendance_date = $record['date'];
        $time_in = isset($record['time_in']) && !empty($record['time_in']) ? $record['time_in'] : null;
        $time_out = isset($record['time_out']) && !empty($record['time_out']) ? $record['time_out'] : null;
        
        // At least one of time_in or time_out must be provided
        if (!$time_in && !$time_out) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Record #" . ($index + 1) . ": At least time in or time out is required"
            ]);
            return;
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Record #" . ($index + 1) . ": Invalid date format (use YYYY-MM-DD)"
            ]);
            return;
        }
        
        // Validate time formats
        if ($time_in && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time_in)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Record #" . ($index + 1) . ": Invalid time in format (use HH:MM)"
            ]);
            return;
        }
        
        if ($time_out && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time_out)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Record #" . ($index + 1) . ": Invalid time out format (use HH:MM)"
            ]);
            return;
        }
        
        // Add seconds if not provided
        if ($time_in && strlen($time_in) === 5) {
            $time_in .= ':00';
        }
        if ($time_out && strlen($time_out) === 5) {
            $time_out .= ':00';
        }
        
        $validated_records[] = [
            'date' => $attendance_date,
            'time_in' => $time_in,
            'time_out' => $time_out
        ];
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $added_count = 0;
        $updated_count = 0;
        $skipped_count = 0;
        $details = [];
        
        foreach ($validated_records as $record) {
            $attendance_date = $record['date'];
            $time_in = $record['time_in'];
            $time_out = $record['time_out'];
            
            // Check if record already exists
            $stmt = $conn->prepare("
                SELECT id, time_in, time_out 
                FROM daily_attendance 
                WHERE employee_id = ? AND attendance_date = ?
            ");
            $stmt->bind_param("is", $employee_db_id, $attendance_date);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();
            
            // Calculate metrics
            $metrics = calculateAttendanceMetrics($conn, $employee_db_id, $attendance_date, $time_in, $time_out);
            
            if ($existing) {
                // Update existing record
                $stmt = $conn->prepare("
                    UPDATE daily_attendance
                    SET time_in = COALESCE(?, time_in),
                        time_out = COALESCE(?, time_out),
                        scheduled_hours = ?,
                        actual_hours = ?,
                        late_minutes = ?,
                        early_departure_minutes = ?,
                        overtime_minutes = ?,
                        status = ?,
                        notes = CONCAT(COALESCE(notes, ''), '\nManually updated by ', ?),
                        calculated_at = NOW()
                    WHERE id = ?
                ");
                $admin_name = $user['username'];
                $stmt->bind_param(
                    "ssddiiissi",
                    $time_in,
                    $time_out,
                    $metrics['scheduled_hours'],
                    $metrics['actual_hours'],
                    $metrics['late_minutes'],
                    $metrics['early_departure_minutes'],
                    $metrics['overtime_minutes'],
                    $metrics['status'],
                    $admin_name,
                    $existing['id']
                );
                $stmt->execute();
                $stmt->close();
                
                $updated_count++;
                $details[] = [
                    'date' => $attendance_date,
                    'action' => 'updated',
                    'time_in' => $time_in,
                    'time_out' => $time_out
                ];
            } else {
                // Insert new record
                $stmt = $conn->prepare("
                    INSERT INTO daily_attendance
                    (employee_id, attendance_date, time_in, time_out, 
                     scheduled_hours, actual_hours, late_minutes, 
                     early_departure_minutes, overtime_minutes, status, notes, calculated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $notes = "Manually added by " . $user['username'];
                $stmt->bind_param(
                    "isssddiiiis",
                    $employee_db_id,
                    $attendance_date,
                    $time_in,
                    $time_out,
                    $metrics['scheduled_hours'],
                    $metrics['actual_hours'],
                    $metrics['late_minutes'],
                    $metrics['early_departure_minutes'],
                    $metrics['overtime_minutes'],
                    $metrics['status'],
                    $notes
                );
                $stmt->execute();
                $stmt->close();
                
                $added_count++;
                $details[] = [
                    'date' => $attendance_date,
                    'action' => 'added',
                    'time_in' => $time_in,
                    'time_out' => $time_out
                ];
            }
            
            // Also log to attendance_logs table for audit trail
            $log_type_in = $time_in ? 'time_in' : null;
            $log_type_out = $time_out ? 'time_out' : null;
            
            if ($log_type_in) {
                $stmt = $conn->prepare("
                    INSERT INTO attendance_logs
                    (employee_id, log_date, log_type, log_time, source, notes)
                    VALUES (?, ?, 'time_in', ?, 'manual', ?)
                ");
                $log_datetime_in = $attendance_date . ' ' . $time_in;
                $log_notes = "Manually added by " . $user['username'];
                $stmt->bind_param("isss", $employee_db_id, $attendance_date, $log_datetime_in, $log_notes);
                $stmt->execute();
                $stmt->close();
            }
            
            if ($log_type_out) {
                $stmt = $conn->prepare("
                    INSERT INTO attendance_logs
                    (employee_id, log_date, log_type, log_time, source, notes)
                    VALUES (?, ?, 'time_out', ?, 'manual', ?)
                ");
                $log_datetime_out = $attendance_date . ' ' . $time_out;
                $log_notes = "Manually added by " . $user['username'];
                $stmt->bind_param("isss", $employee_db_id, $attendance_date, $log_datetime_out, $log_notes);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Log to file
        $log_file = __DIR__ . '/../../logs/manual_attendance.log';
        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_message = date('Y-m-d H:i:s') . " - Admin: {$user['username']} | Employee: $employee_name ($employee_id) | Added: $added_count | Updated: $updated_count\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
        
        // Return success
        echo json_encode([
            'success' => true,
            'message' => "Manual attendance records processed successfully",
            'data' => [
                'employee_id' => $employee_id,
                'employee_name' => $employee_name,
                'added' => $added_count,
                'updated' => $updated_count,
                'skipped' => $skipped_count,
                'total' => count($validated_records),
                'details' => $details
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get attendance history for an employee
 */
function getAttendanceHistory($conn) {
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
    
    $employee_db_id = $employee['id'];
    
    // Get date range (default: last 30 days)
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    $from_date = date('Y-m-d', strtotime("-$days days"));
    $to_date = date('Y-m-d');
    
    if (isset($_GET['from_date'])) {
        $from_date = $_GET['from_date'];
    }
    if (isset($_GET['to_date'])) {
        $to_date = $_GET['to_date'];
    }
    
    // Fetch attendance records
    $stmt = $conn->prepare("
        SELECT 
            attendance_date,
            time_in,
            time_out,
            scheduled_hours,
            actual_hours,
            late_minutes,
            early_departure_minutes,
            overtime_minutes,
            status,
            notes,
            calculated_at
        FROM daily_attendance
        WHERE employee_id = ?
          AND attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date DESC
    ");
    $stmt->bind_param("iss", $employee_db_id, $from_date, $to_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'employee_id' => $employee_id,
            'employee_name' => $employee['full_name'],
            'from_date' => $from_date,
            'to_date' => $to_date,
            'total_records' => count($records),
            'records' => $records
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
    case 'add_manual':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        addManualAttendance($conn, $user);
        break;
        
    case 'get_attendance':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        getAttendanceHistory($conn);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action. Available actions: add_manual, get_attendance'
        ]);
}
?>
