<?php
/**
 * Get Attendance Data from SQLite (Kiosk Database)
 * 
 * This API reads attendance data directly from the kiosk's local SQLite database
 * where the face recognition system stores time in/out records.
 * 
 * Parameters:
 * - id: Employee ID (required)
 * - month: Month filter in YYYY-MM format (optional)
 * - year: Year filter in YYYY format (optional)
 */

header('Content-Type: application/json');

// Get parameters
$employee_id = $_GET['id'] ?? null;
$month_filter = $_GET['month'] ?? null;
$year_filter = $_GET['year'] ?? null;

// Validate employee ID
if (!$employee_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Employee ID is required'
    ]);
    exit;
}

// Path to SQLite database
$sqlite_db_path = __DIR__ . '/../../faceid/database/kiosk_local.db';

// Check if SQLite database exists
if (!file_exists($sqlite_db_path)) {
    echo json_encode([
        'success' => false,
        'message' => 'Kiosk database not found at: ' . $sqlite_db_path,
        'data' => []
    ]);
    exit;
}

try {
    // Connect to SQLite database
    $db = new PDO('sqlite:' . $sqlite_db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // First, get the employee's database ID from their employee_id code
    $stmt = $db->prepare("SELECT id, employee_id, first_name, last_name, department, position FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found in kiosk database',
            'data' => []
        ]);
        exit;
    }
    
    // Build query for daily_attendance
    $query = "SELECT 
                da.id,
                da.attendance_date,
                da.time_in,
                da.time_out,
                da.scheduled_hours,
                da.actual_hours,
                da.late_minutes,
                da.early_departure_minutes,
                da.overtime_minutes,
                da.status,
                da.notes
              FROM daily_attendance da
              WHERE da.employee_id = ?";
    
    $params = [$employee['id']];
    
    // Add month/year filters
    if ($month_filter && preg_match('/^\d{4}-\d{2}$/', $month_filter)) {
        // Month format: YYYY-MM
        $query .= " AND strftime('%Y-%m', da.attendance_date) = ?";
        $params[] = $month_filter;
    } elseif ($year_filter && preg_match('/^\d{4}$/', $year_filter)) {
        // Year format: YYYY
        $query .= " AND strftime('%Y', da.attendance_date) = ?";
        $params[] = $year_filter;
    }
    
    $query .= " ORDER BY da.attendance_date DESC";
    
    // Execute query
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $total_days = count($records);
    $on_time = 0;
    $late = 0;
    $total_late_minutes = 0;
    $total_overtime_minutes = 0;
    
    foreach ($records as &$record) {
        // Count on-time vs late
        if ($record['late_minutes'] == 0 && $record['time_in']) {
            $on_time++;
        } elseif ($record['late_minutes'] > 0) {
            $late++;
            $total_late_minutes += $record['late_minutes'];
        }
        
        if ($record['overtime_minutes'] > 0) {
            $total_overtime_minutes += $record['overtime_minutes'];
        }
        
        // Format times for display
        if ($record['time_in']) {
            $record['time_in_formatted'] = date('h:i A', strtotime($record['time_in']));
        } else {
            $record['time_in_formatted'] = '--';
        }
        
        if ($record['time_out']) {
            $record['time_out_formatted'] = date('h:i A', strtotime($record['time_out']));
        } else {
            $record['time_out_formatted'] = '--';
        }
        
        // Format date
        $record['date_formatted'] = date('M d, Y', strtotime($record['attendance_date']));
        $record['day_of_week'] = date('l', strtotime($record['attendance_date']));
        
        // Format hours (stored as minutes in SQLite)
        if ($record['scheduled_hours']) {
            $record['scheduled_hours_display'] = number_format($record['scheduled_hours'] / 60, 2) . ' hrs';
        } else {
            $record['scheduled_hours_display'] = '--';
        }
        
        if ($record['actual_hours']) {
            $record['actual_hours_display'] = number_format($record['actual_hours'] / 60, 2) . ' hrs';
        } else {
            $record['actual_hours_display'] = '--';
        }
        
        // Status badge
        $record['status_badge'] = $record['status'] ?? 'incomplete';
    }
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'employee' => [
            'id' => $employee['id'],
            'employee_id' => $employee['employee_id'],
            'name' => $employee['first_name'] . ' ' . $employee['last_name'],
            'department' => $employee['department'] ?? 'N/A',
            'position' => $employee['position'] ?? 'N/A'
        ],
        'statistics' => [
            'total_days' => $total_days,
            'on_time' => $on_time,
            'late' => $late,
            'total_late_minutes' => $total_late_minutes,
            'total_overtime_minutes' => $total_overtime_minutes
        ],
        'data' => $records,
        'month_filter' => $month_filter,
        'year_filter' => $year_filter
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'data' => []
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => []
    ]);
}
?>
