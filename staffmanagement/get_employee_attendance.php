<?php
/**
 * Get Employee Attendance API
 * Fetches attendance records for a specific employee
 * 
 * Two Modes of Operation:
 * 
 * 1. LIMIT MODE (Recent Records):
 *    Parameters:
 *    - employee_id: Internal employee ID (required)
 *    - limit: Number of most recent records to fetch (required, max 100)
 *    
 *    Example: ?employee_id=123&limit=15
 *    Returns: First N records from present day going back
 * 
 * 2. DATE RANGE MODE:
 *    Parameters:
 *    - employee_id: Internal employee ID (required)
 *    - start_date: Start date in Y-m-d format (required)
 *    - end_date: End date in Y-m-d format (optional, defaults to start_date)
 *    
 *    Example: ?employee_id=123&start_date=2025-11-01&end_date=2025-11-15
 *    Returns: All records within the date range (max 16 days)
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "employee_id": 123,
 *   "mode": "limit" or "date_range",
 *   "count": 10,
 *   "data": [...]
 * }
 */

date_default_timezone_set('Asia/Manila');

// Disable all error output to prevent breaking JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/attendance_errors.log');

// Start output buffering to catch any unexpected output
ob_start();

require '../db_connection.php';

// Clear any output that may have occurred
ob_clean();

// Set JSON header
header('Content-Type: application/json');

try {
    // Get parameters
    $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $start_date;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
    
    // Validate parameters
    if ($employee_id <= 0) {
        throw new Exception('Invalid employee ID');
    }
    
    // Two modes: limit mode (recent records) or date range mode
    $use_limit_mode = ($limit > 0 && empty($start_date));
    
    if (!$use_limit_mode) {
        // Date range mode validation
        if (empty($start_date)) {
            throw new Exception('Start date is required');
        }
        
        // Validate date formats
        $start_date_obj = DateTime::createFromFormat('Y-m-d', $start_date);
        if (!$start_date_obj || $start_date_obj->format('Y-m-d') !== $start_date) {
            throw new Exception('Invalid start date format. Use Y-m-d format (e.g., 2025-11-12)');
        }
        
        $end_date_obj = DateTime::createFromFormat('Y-m-d', $end_date);
        if (!$end_date_obj || $end_date_obj->format('Y-m-d') !== $end_date) {
            throw new Exception('Invalid end date format. Use Y-m-d format (e.g., 2025-11-12)');
        }
        
        // Validate date range (max 16 days)
        $interval = $start_date_obj->diff($end_date_obj);
        $days_diff = $interval->days + 1;
        
        if ($days_diff > 16) {
            throw new Exception('Date range cannot exceed 16 days');
        }
        
        if ($end_date_obj < $start_date_obj) {
            throw new Exception('End date cannot be before start date');
        }
    } else {
        // Limit mode - validate limit
        if ($limit > 100) {
            throw new Exception('Limit cannot exceed 100 records');
        }
        $days_diff = null; // Not applicable in limit mode
    }
    
    // Fetch employee details
    $sql = "SELECT employee_id as employee_code, first_name, last_name, position, department 
            FROM employees 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $employee_result = $stmt->get_result();
    
    if ($employee_result->num_rows === 0) {
        throw new Exception('Employee not found');
    }
    
    $employee_info = $employee_result->fetch_assoc();
    $stmt->close();
    
    // Fetch attendance records - use different query based on mode
    if ($use_limit_mode) {
        // Limit mode: Get most recent N records from present to past
        $sql = "SELECT 
                    da.*
                FROM daily_attendance da
                WHERE da.employee_id = ?
                ORDER BY da.attendance_date DESC
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $employee_id, $limit);
    } else {
        // Date range mode: Get records within date range
        $sql = "SELECT 
                    da.*
                FROM daily_attendance da
                WHERE da.employee_id = ?
                AND da.attendance_date BETWEEN ? AND ?
                ORDER BY da.attendance_date DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception('Failed to get result set: ' . $conn->error);
    }
    
    $attendance_records = [];
    
    while ($row = $result->fetch_assoc()) {
        // Format times
        $time_in_formatted = null;
        if (!empty($row['time_in'])) {
            $time_obj = new DateTime($row['time_in']);
            $time_in_formatted = $time_obj->format('g:i A');
        }
        
        $time_out_formatted = null;
        if (!empty($row['time_out'])) {
            $time_obj = new DateTime($row['time_out']);
            $time_out_formatted = $time_obj->format('g:i A');
        }
        
        // Format hours worked
        // NOTE: actual_hours in database is stored in MINUTES, not hours
        $hours_worked = null;
        if (!empty($row['actual_hours'])) {
            $total_minutes = $row['actual_hours'];
            $hours = floor($total_minutes / 60);
            $minutes = $total_minutes % 60;
            $hours_worked = "{$hours}h {$minutes}m";
        }
        
        // Format date
        $date_obj = new DateTime($row['attendance_date']);
        $formatted_date = $date_obj->format('l, F j, Y');
        $day_of_week = $date_obj->format('l');
        
        // Determine status badge
        $status_info = [
            'status' => $row['status'],
            'badge_class' => 'secondary',
            'badge_text' => ucfirst($row['status']),
            'icon_class' => 'bg-secondary',
            'icon' => 'bi-dash'
        ];
        
        // Trim and lowercase for comparison
        $status_lower = strtolower(trim($row['status']));
        
        if ($status_lower === 'complete' || $status_lower === 'present') {
            $status_info['badge_class'] = 'success';
            $status_info['badge_text'] = 'Present';
            $status_info['icon_class'] = 'bg-success';
            $status_info['icon'] = 'bi-check-lg';
        } elseif ($status_lower === 'incomplete') {
            $status_info['badge_class'] = 'warning text-dark';
            $status_info['badge_text'] = 'Incomplete';
            $status_info['icon_class'] = 'bg-warning';
            $status_info['icon'] = 'bi-exclamation-circle-fill';
        } elseif ($status_lower === 'absent') {
            $status_info['badge_class'] = 'danger';
            $status_info['badge_text'] = 'Absent';
            $status_info['icon_class'] = 'bg-danger';
            $status_info['icon'] = 'bi-x-circle-fill';
        } elseif ($status_lower === 'manual') {
            $status_info['badge_class'] = 'manual';
            $status_info['badge_text'] = 'Manual';
            $status_info['icon_class'] = 'bg-manual';
            $status_info['icon'] = 'bi-pencil-square';
        }
        
        $attendance_records[] = [
            'id' => $row['id'],
            'attendance_date' => $row['attendance_date'],
            'formatted_date' => $formatted_date,
            'day_of_week' => $day_of_week,
            'time_in' => $row['time_in'],
            'time_in_formatted' => $time_in_formatted,
            'time_out' => $row['time_out'],
            'time_out_formatted' => $time_out_formatted,
            'late_minutes' => $row['late_minutes'] ?? 0,
            'overtime_minutes' => $row['overtime_minutes'] ?? 0,
            'actual_hours' => $row['actual_hours'] ?? null,
            'hours_worked' => $hours_worked,
            'status' => $row['status'],
            'status_info' => $status_info,
            'notes' => $row['notes'] ?? ''
        ];
    }
    
    $stmt->close();
    
    // Calculate summary statistics
    $summary = [
        'total_days' => count($attendance_records),
        'present_days' => 0,
        'absent_days' => 0,
        'incomplete_days' => 0,
        'manual_days' => 0,
        'total_late_minutes' => 0,
        'total_hours_worked' => 0 // This will be in minutes
    ];
    
    foreach ($attendance_records as $record) {
        if ($record['status'] === 'complete') {
            $summary['present_days']++;
            if ($record['late_minutes'] > 0) {
                $summary['total_late_minutes'] += $record['late_minutes'];
            }
        } elseif ($record['status'] === 'absent') {
            $summary['absent_days']++;
        } elseif ($record['status'] === 'incomplete') {
            $summary['incomplete_days']++;
        } elseif ($record['status'] === 'manual') {
            $summary['manual_days']++;
            if ($record['late_minutes'] > 0) {
                $summary['total_late_minutes'] += $record['late_minutes'];
            }
        }
        
        // actual_hours is stored in MINUTES in database
        if (!empty($record['actual_hours'])) {
            $summary['total_hours_worked'] += $record['actual_hours'];
        }
    }
    
    // Format total hours worked (convert minutes to hours and minutes)
    $total_hours = floor($summary['total_hours_worked'] / 60);
    $total_minutes = $summary['total_hours_worked'] % 60;
    $summary['total_hours_worked_formatted'] = "{$total_hours}h {$total_minutes}m";
    
    // Build response based on mode
    if ($use_limit_mode) {
        $response = [
            'success' => true,
            'employee' => $employee_info,
            'employee_id' => $employee_id,
            'mode' => 'limit',
            'limit' => $limit,
            'count' => count($attendance_records),
            'summary' => $summary,
            'data' => $attendance_records
        ];
    } else {
        $response = [
            'success' => true,
            'employee' => $employee_info,
            'employee_id' => $employee_id,
            'mode' => 'date_range',
            'start_date' => $start_date,
            'end_date' => $end_date,
            'days_in_range' => $days_diff,
            'count' => count($attendance_records),
            'summary' => $summary,
            'data' => $attendance_records
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
    // End output buffering and flush
    ob_end_flush();
    
} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();
    
    // Log the error
    error_log("Get Employee Attendance Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'employee_id' => isset($employee_id) ? $employee_id : null,
        'start_date' => isset($start_date) ? $start_date : null,
        'end_date' => isset($end_date) ? $end_date : null
    ], JSON_PRETTY_PRINT);
    
    ob_end_flush();
}

if (isset($conn)) {
    $conn->close();
}
?>
