<?php
/**
 * Manual Attendance Management API
 * Handles adding manual attendance records for employees
 */

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require '../../db_connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../logs/manual_attendance_errors.log');

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_manual':
            addManualAttendance($conn);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    error_log("Manual Attendance Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

if (isset($conn)) {
    $conn->close();
}

/**
 * Add manual attendance records
 */
function addManualAttendance($conn) {
    // Get JSON data from request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    $employee_id = $data['employee_id'] ?? 0;
    $records = $data['records'] ?? [];
    
    if (!$employee_id) {
        throw new Exception('Employee ID is required');
    }
    
    if (empty($records)) {
        throw new Exception('No attendance records provided');
    }
    
    // Validate employee exists
    $sql = "SELECT id, first_name, last_name FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    
    if (!$employee) {
        throw new Exception('Employee not found');
    }
    
    $conn->begin_transaction();
    
    try {
        $success_count = 0;
        $errors = [];
        
        foreach ($records as $index => $record) {
            $date = $record['date'] ?? '';
            $time_in = $record['time_in'] ?? '';
            $time_out = $record['time_out'] ?? '';
            
            // Validate required fields
            if (empty($date) || empty($time_in) || empty($time_out)) {
                $errors[] = "Record " . ($index + 1) . ": Missing date, time in, or time out";
                continue;
            }
            
            // Validate date format
            $dateObj = DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj) {
                $errors[] = "Record " . ($index + 1) . ": Invalid date format";
                continue;
            }
            
            // Validate time formats
            if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time_in)) {
                $errors[] = "Record " . ($index + 1) . ": Invalid time in format";
                continue;
            }
            
            if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time_out)) {
                $errors[] = "Record " . ($index + 1) . ": Invalid time out format";
                continue;
            }
            
            // Check if time_out is after time_in
            $timeInObj = new DateTime($date . ' ' . $time_in);
            $timeOutObj = new DateTime($date . ' ' . $time_out);
            
            if ($timeOutObj <= $timeInObj) {
                $errors[] = "Record " . ($index + 1) . ": Time out must be after time in";
                continue;
            }
            
            // Check if employee has a schedule for this date and get all schedule periods
            $dayOfWeek = $dateObj->format('w'); // 0 (Sunday) to 6 (Saturday)
            // Convert PHP's day format (0=Sunday) to database format (0=Monday, 6=Sunday)
            $dayOfWeekDb = ($dayOfWeek == 0) ? 6 : ($dayOfWeek - 1);
            
            $sql = "SELECT sp.start_time, sp.end_time
                    FROM employee_schedules es
                    JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                    WHERE es.employee_id = ? 
                      AND es.is_active = 1
                      AND sp.day_of_week = ?
                      AND sp.is_active = 1
                      AND (es.end_date IS NULL OR es.end_date >= ?)
                    ORDER BY sp.start_time ASC";
            $schedule_stmt = $conn->prepare($sql);
            $schedule_stmt->bind_param("iis", $employee_id, $dayOfWeekDb, $date);
            $schedule_stmt->execute();
            $schedule_result = $schedule_stmt->get_result();
            $schedule_periods = $schedule_result->fetch_all(MYSQLI_ASSOC);
            
            if (empty($schedule_periods)) {
                $errors[] = "Record " . ($index + 1) . " (" . $dateObj->format('M d, Y') . "): No schedule found for this day";
                continue;
            }
            
            // Calculate scheduled_hours (sum of all periods in minutes, stored as decimal)
            $scheduled_minutes = 0;
            $first_period_start = null;
            $last_period_end = null;
            
            foreach ($schedule_periods as $period) {
                $start_parts = explode(':', $period['start_time']);
                $end_parts = explode(':', $period['end_time']);
                
                $start_minutes = ($start_parts[0] * 60) + $start_parts[1];
                $end_minutes = ($end_parts[0] * 60) + $end_parts[1];
                
                $scheduled_minutes += ($end_minutes - $start_minutes);
                
                // Track first and last periods for late/overtime calculation
                if ($first_period_start === null) {
                    $first_period_start = $period['start_time'];
                }
                $last_period_end = $period['end_time'];
            }
            
            // Convert scheduled_minutes to decimal (for storage in scheduled_hours field)
            $scheduled_hours = round($scheduled_minutes, 2);
            
            // Calculate actual hours worked (in minutes, stored as decimal)
            $interval = $timeInObj->diff($timeOutObj);
            $actual_minutes = ($interval->h * 60) + $interval->i;
            $actual_hours = round($actual_minutes, 2);
            
            // Calculate late minutes (based on first period start time)
            $late_minutes = 0;
            if ($first_period_start) {
                $start_parts = explode(':', $first_period_start);
                $scheduled_start = new DateTime($date . ' ' . $first_period_start);
                
                if ($timeInObj > $scheduled_start) {
                    $late_interval = $scheduled_start->diff($timeInObj);
                    $late_minutes = ($late_interval->h * 60) + $late_interval->i;
                }
            }
            
            // Calculate early departure or overtime (based on last period end time)
            $early_departure_minutes = 0;
            $overtime_minutes = 0;
            
            if ($last_period_end) {
                $scheduled_end = new DateTime($date . ' ' . $last_period_end);
                
                if ($timeOutObj < $scheduled_end) {
                    // Left early (undertime)
                    $early_interval = $timeOutObj->diff($scheduled_end);
                    $early_departure_minutes = ($early_interval->h * 60) + $early_interval->i;
                } else if ($timeOutObj > $scheduled_end) {
                    // Overtime
                    $overtime_interval = $scheduled_end->diff($timeOutObj);
                    $overtime_minutes = ($overtime_interval->h * 60) + $overtime_interval->i;
                }
            }
            
            // Check if record already exists for this date
            $sql = "SELECT id FROM daily_attendance WHERE employee_id = ? AND attendance_date = ?";
            $check_stmt = $conn->prepare($sql);
            $check_stmt->bind_param("is", $employee_id, $date);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();
            
            if ($existing) {
                // Update existing record
                $sql = "UPDATE daily_attendance 
                        SET time_in = ?, 
                            time_out = ?, 
                            scheduled_hours = ?,
                            actual_hours = ?,
                            late_minutes = ?,
                            early_departure_minutes = ?,
                            overtime_minutes = ?,
                            status = 'manual',
                            calculated_at = NOW()
                        WHERE employee_id = ? AND attendance_date = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    $errors[] = "Record " . ($index + 1) . ": Failed to prepare UPDATE statement - " . $conn->error;
                    continue;
                }
                $stmt->bind_param("ssddiiis", $time_in, $time_out, $scheduled_hours, $actual_hours, 
                                 $late_minutes, $early_departure_minutes, $overtime_minutes, 
                                 $employee_id, $date);
            } else {
                // Insert new record
                $sql = "INSERT INTO daily_attendance 
                        (employee_id, attendance_date, time_in, time_out, scheduled_hours, actual_hours, 
                         late_minutes, early_departure_minutes, overtime_minutes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual')";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    $errors[] = "Record " . ($index + 1) . ": Failed to prepare INSERT statement - " . $conn->error;
                    continue;
                }
                $stmt->bind_param("isssddiii", $employee_id, $date, $time_in, $time_out, 
                                 $scheduled_hours, $actual_hours, $late_minutes, 
                                 $early_departure_minutes, $overtime_minutes);
            }
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $errors[] = "Record " . ($index + 1) . ": Database error - " . $stmt->error;
                error_log("Manual Attendance SQL Error: " . $stmt->error . " | SQL: " . $sql);
            }
        }
        
        if ($success_count > 0) {
            $conn->commit();
            
            $response = [
                'success' => true,
                'message' => "$success_count attendance record(s) added successfully",
                'records_processed' => count($records),
                'records_added' => $success_count
            ];
            
            if (!empty($errors)) {
                $response['warnings'] = $errors;
            }
            
            echo json_encode($response);
        } else {
            throw new Exception('No records were added. Errors: ' . implode('; ', $errors));
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}
?>
