<?php
/**
 * Daily Time Record (DTR) API
 * Displays employee time in/out records from daily_attendance table
 * 
 * Actions:
 * - GET ?action=get_employee_dtr&employee_id=X&month=YYYY-MM - Get employee DTR for specific month
 * - GET ?action=get_employee_dtr&employee_id=X&limit=10 - Get recent DTR records
 * - GET ?action=get_stats&employee_id=X&month=YYYY-MM - Get attendance statistics
 * 
 * Security: Employee can view own records, admin can view all
 */

// Start output buffering
ob_start();

// Use API-specific database connection
require '../../db_connection_api.php';

// Clear output
ob_clean();

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $response['message'] = 'Unauthorized: Please log in';
    echo json_encode($response);
    exit;
}

// Get action
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_employee_dtr':
            $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;
            
            if (!$employee_id) {
                throw new Exception('Employee ID is required');
            }
            
            // Security: Employees can only view their own records, admins can view all
            if ($_SESSION['user_type'] !== 'admin' && intval($_SESSION['employee_id']) !== $employee_id) {
                throw new Exception('Unauthorized: You can only view your own records');
            }
            
            // Get filter parameters
            $month = $_GET['month'] ?? null; // Format: YYYY-MM
            $start_date = $_GET['start_date'] ?? null; // Format: YYYY-MM-DD
            $end_date = $_GET['end_date'] ?? null; // Format: YYYY-MM-DD
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
            
            // Build query
            $query = "SELECT 
                        da.id,
                        da.employee_id,
                        da.attendance_date,
                        da.time_in,
                        da.time_out,
                        da.scheduled_hours,
                        da.actual_hours,
                        da.late_minutes,
                        da.early_departure_minutes,
                        da.overtime_minutes,
                        da.break_time_minutes,
                        da.status,
                        da.notes,
                        da.calculated_at,
                        e.employee_id as employee_code,
                        e.first_name,
                        e.middle_name,
                        e.last_name
                      FROM daily_attendance da
                      INNER JOIN employees e ON da.employee_id = e.id
                      WHERE da.employee_id = ?";
            
            $params = [$employee_id];
            $paramTypes = 'i';
            
            // Add date range filter if provided (takes precedence over month)
            if ($start_date && $end_date) {
                $query .= " AND da.attendance_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
                $paramTypes .= 'ss';
            }
            // Otherwise, add month filter if provided
            elseif ($month) {
                $query .= " AND DATE_FORMAT(da.attendance_date, '%Y-%m') = ?";
                $params[] = $month;
                $paramTypes .= 's';
            }
            
            $query .= " ORDER BY da.attendance_date DESC";
            
            // Add limit if provided
            if ($limit) {
                $query .= " LIMIT ?";
                $params[] = $limit;
                $paramTypes .= 'i';
            }
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            // Bind parameters dynamically
            $stmt->bind_param($paramTypes, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $records = [];
            while ($row = $result->fetch_assoc()) {
                // Format time for display
                $timeIn = $row['time_in'] ? date('g:i A', strtotime($row['time_in'])) : 'N/A';
                $timeOut = $row['time_out'] ? date('g:i A', strtotime($row['time_out'])) : 'N/A';
                
                // Format date
                $dateFormatted = date('l, F j, Y', strtotime($row['attendance_date']));
                $dateShort = date('M d, Y', strtotime($row['attendance_date']));
                $dayOfWeek = date('l', strtotime($row['attendance_date']));
                
                // Determine status display
                $statusInfo = determineStatusDisplay($row);
                
                // Calculate hours worked
                $hoursWorked = $row['actual_hours'] ? round($row['actual_hours'] / 60, 1) : 0;
                $scheduledHrs = $row['scheduled_hours'] ? round($row['scheduled_hours'] / 60, 1) : 0;
                
                $records[] = [
                    'id' => $row['id'],
                    'employee_code' => $row['employee_code'],
                    'employee_name' => trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']),
                    'attendance_date' => $row['attendance_date'],
                    'date_formatted' => $dateFormatted,
                    'date_short' => $dateShort,
                    'day_of_week' => $dayOfWeek,
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                    'time_in_raw' => $row['time_in'],
                    'time_out_raw' => $row['time_out'],
                    'scheduled_hours' => $scheduledHrs,
                    'actual_hours' => $hoursWorked,
                    'late_minutes' => intval($row['late_minutes']),
                    'early_departure_minutes' => intval($row['early_departure_minutes']),
                    'overtime_minutes' => intval($row['overtime_minutes']),
                    'break_time_minutes' => intval($row['break_time_minutes']),
                    'status' => $row['status'],
                    'status_display' => $statusInfo['display'],
                    'status_class' => $statusInfo['class'],
                    'status_badge' => $statusInfo['badge'],
                    'icon' => $statusInfo['icon'],
                    'icon_bg' => $statusInfo['icon_bg'],
                    'notes' => $row['notes'] ?? '',
                    'calculated_at' => $row['calculated_at']
                ];
            }
            
            $stmt->close();
            
            $response['success'] = true;
            $response['message'] = 'DTR records retrieved successfully';
            $response['data'] = [
                'records' => $records,
                'total_records' => count($records),
                'month' => $month,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'employee_id' => $employee_id
            ];
            break;
            
        case 'get_stats':
            $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;
            $month = $_GET['month'] ?? null; // Format: YYYY-MM
            $start_date = $_GET['start_date'] ?? null; // Format: YYYY-MM-DD
            $end_date = $_GET['end_date'] ?? null; // Format: YYYY-MM-DD
            
            if (!$employee_id) {
                throw new Exception('Employee ID is required');
            }
            
            // Security check
            if ($_SESSION['user_type'] !== 'admin' && intval($_SESSION['employee_id']) !== $employee_id) {
                throw new Exception('Unauthorized: You can only view your own records');
            }
            
            // Build WHERE clause for date filtering
            $dateFilter = '';
            $params = [$employee_id];
            $paramTypes = 'i';
            
            if ($start_date && $end_date) {
                $dateFilter = "AND attendance_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
                $paramTypes .= 'ss';
                $period = "$start_date to $end_date";
            } elseif ($month) {
                $dateFilter = "AND DATE_FORMAT(attendance_date, '%Y-%m') = ?";
                $params[] = $month;
                $paramTypes .= 's';
                $period = $month;
            } else {
                // Default to current month if no filter provided
                $month = date('Y-m');
                $dateFilter = "AND DATE_FORMAT(attendance_date, '%Y-%m') = ?";
                $params[] = $month;
                $paramTypes .= 's';
                $period = $month;
            }
            
            // Get statistics for the specified period
            $query = "SELECT 
                        COUNT(*) as total_days,
                        SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as complete_days,
                        SUM(CASE WHEN status = 'incomplete' THEN 1 ELSE 0 END) as incomplete_days,
                        SUM(CASE WHEN late_minutes > 0 THEN 1 ELSE 0 END) as late_days,
                        SUM(CASE WHEN early_departure_minutes > 0 THEN 1 ELSE 0 END) as early_departure_days,
                        SUM(late_minutes) as total_late_minutes,
                        SUM(early_departure_minutes) as total_early_minutes,
                        SUM(overtime_minutes) as total_overtime_minutes,
                        SUM(actual_hours) as total_actual_hours,
                        SUM(scheduled_hours) as total_scheduled_hours,
                        AVG(actual_hours) as avg_hours_per_day
                      FROM daily_attendance
                      WHERE employee_id = ?
                      $dateFilter";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param($paramTypes, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();
            
            // Calculate percentages and format data
            $totalDays = intval($stats['total_days']);
            $completeDays = intval($stats['complete_days']);
            $lateDays = intval($stats['late_days']);
            
            $onTimePercentage = $totalDays > 0 ? round((($completeDays - $lateDays) / $totalDays) * 100, 1) : 0;
            $completionRate = $totalDays > 0 ? round(($completeDays / $totalDays) * 100, 1) : 0;
            
            $response['success'] = true;
            $response['message'] = 'Statistics retrieved successfully';
            $response['data'] = [
                'period' => $period,
                'month' => $month ?? null,
                'start_date' => $start_date ?? null,
                'end_date' => $end_date ?? null,
                'total_days' => $totalDays,
                'complete_days' => $completeDays,
                'incomplete_days' => intval($stats['incomplete_days']),
                'late_days' => $lateDays,
                'early_departure_days' => intval($stats['early_departure_days']),
                'on_time_days' => $completeDays - $lateDays,
                'total_late_minutes' => intval($stats['total_late_minutes']),
                'total_early_minutes' => intval($stats['total_early_minutes']),
                'total_overtime_minutes' => intval($stats['total_overtime_minutes']),
                'total_actual_hours' => round(floatval($stats['total_actual_hours']) / 60, 1),
                'total_scheduled_hours' => round(floatval($stats['total_scheduled_hours']) / 60, 1),
                'avg_hours_per_day' => round(floatval($stats['avg_hours_per_day']) / 60, 1),
                'on_time_percentage' => $onTimePercentage,
                'completion_rate' => $completionRate
            ];
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

/**
 * Determine status display information
 */
function determineStatusDisplay($record) {
    $status = $record['status'];
    $lateMinutes = intval($record['late_minutes']);
    $earlyDeparture = intval($record['early_departure_minutes']);
    
    // Complete and on-time
    if ($status === 'complete' && $lateMinutes === 0 && $earlyDeparture === 0) {
        return [
            'display' => 'On Time',
            'class' => 'status-ontime',
            'badge' => 'bg-success',
            'icon' => 'bi-check-lg',
            'icon_bg' => 'bg-success'
        ];
    }
    
    // Complete but late
    if ($status === 'complete' && $lateMinutes > 0) {
        return [
            'display' => 'Late',
            'class' => 'status-late',
            'badge' => 'bg-danger',
            'icon' => 'bi-clock-history',
            'icon_bg' => 'bg-danger'
        ];
    }
    
    // Complete but early departure
    if ($status === 'complete' && $earlyDeparture > 0 && $lateMinutes === 0) {
        return [
            'display' => 'Early Out',
            'class' => 'status-early',
            'badge' => 'bg-warning',
            'icon' => 'bi-arrow-left-circle',
            'icon_bg' => 'bg-warning'
        ];
    }
    
    // Incomplete (no time out)
    if ($status === 'incomplete') {
        if ($lateMinutes > 0) {
            return [
                'display' => 'Incomplete (Late)',
                'class' => 'status-incomplete',
                'badge' => 'bg-warning',
                'icon' => 'bi-exclamation-triangle',
                'icon_bg' => 'bg-warning'
            ];
        } else {
            return [
                'display' => 'Incomplete',
                'class' => 'status-incomplete',
                'badge' => 'bg-secondary',
                'icon' => 'bi-dash-circle',
                'icon_bg' => 'bg-secondary'
            ];
        }
    }
    
    // Default
    return [
        'display' => ucfirst($status),
        'class' => 'status-ontime',
        'badge' => 'bg-info',
        'icon' => 'bi-info-circle',
        'icon_bg' => 'bg-info'
    ];
}
?>
