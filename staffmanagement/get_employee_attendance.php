<?php
/**
 * Get Employee Attendance API
 * Fetches attendance records for a specific employee within a date range
 * 
 * Parameters:
 * - employee_id: Internal employee ID (required)
 * - start_date: Start date in Y-m-d format (required)
 * - end_date: End date in Y-m-d format (optional, defaults to start_date)
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "employee_id": 123,
 *   "start_date": "2025-11-01",
 *   "end_date": "2025-11-15",
 *   "count": 10,
 *   "data": [...]
 * }
 */

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require '../db_connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, we'll catch them

try {
    // Get parameters
    $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $start_date;
    
    // Validate parameters
    if ($employee_id <= 0) {
        throw new Exception('Invalid employee ID');
    }
    
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
    
    // Fetch attendance records
    $sql = "SELECT 
                da.*
            FROM daily_attendance da
            WHERE da.employee_id = ?
            AND da.attendance_date BETWEEN ? AND ?
            ORDER BY da.attendance_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    
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
        $hours_worked = null;
        if (!empty($row['actual_hours'])) {
            $hours = floor($row['actual_hours']);
            $minutes = round(($row['actual_hours'] - $hours) * 60);
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
        'total_hours_worked' => 0
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
        
        if (!empty($record['actual_hours'])) {
            $summary['total_hours_worked'] += $record['actual_hours'];
        }
    }
    
    // Format total hours worked
    $total_hours = floor($summary['total_hours_worked']);
    $total_minutes = round(($summary['total_hours_worked'] - $total_hours) * 60);
    $summary['total_hours_worked_formatted'] = "{$total_hours}h {$total_minutes}m";
    
    // Build response
    $response = [
        'success' => true,
        'employee' => $employee_info,
        'employee_id' => $employee_id,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'days_in_range' => $days_diff,
        'count' => count($attendance_records),
        'summary' => $summary,
        'data' => $attendance_records
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
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
}

$conn->close();
?>
