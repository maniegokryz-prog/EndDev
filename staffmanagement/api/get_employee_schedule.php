<?php
/**
 * Get Employee Schedule API
 * Returns the schedule times for an employee on a specific date
 */

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require '../../db_connection.php';

try {
    $employee_id = $_GET['employee_id'] ?? 0;
    $date = $_GET['date'] ?? '';
    
    if (!$employee_id) {
        throw new Exception('Employee ID is required');
    }
    
    if (!$date) {
        throw new Exception('Date is required');
    }
    
    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj) {
        throw new Exception('Invalid date format');
    }
    
    // Get day of week (0=Monday, 6=Sunday for database)
    $dayOfWeek = $dateObj->format('w'); // 0 (Sunday) to 6 (Saturday)
    $dayOfWeekDb = ($dayOfWeek == 0) ? 6 : ($dayOfWeek - 1);
    
    // Get all schedule periods for this employee on this day
    $sql = "SELECT sp.start_time, sp.end_time
            FROM employee_schedules es
            JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
            WHERE es.employee_id = ? 
              AND es.is_active = 1
              AND sp.day_of_week = ?
              AND sp.is_active = 1
              AND (es.end_date IS NULL OR es.end_date >= ?)
            ORDER BY sp.start_time ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $employee_id, $dayOfWeekDb, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule_periods = $result->fetch_all(MYSQLI_ASSOC);
    
    if (empty($schedule_periods)) {
        echo json_encode([
            'success' => false,
            'message' => 'No schedule found for this day',
            'has_schedule' => false
        ]);
        exit;
    }
    
    // Get first start time and last end time
    $first_period_start = $schedule_periods[0]['start_time'];
    $last_period_end = $schedule_periods[count($schedule_periods) - 1]['end_time'];
    
    // Format times to HH:MM (remove seconds if present)
    $start_time_formatted = substr($first_period_start, 0, 5);
    $end_time_formatted = substr($last_period_end, 0, 5);
    
    echo json_encode([
        'success' => true,
        'has_schedule' => true,
        'schedule' => [
            'start_time' => $start_time_formatted,
            'end_time' => $end_time_formatted,
            'periods' => $schedule_periods
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
