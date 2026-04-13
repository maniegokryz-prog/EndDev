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

    // DEBUG LOGGING
    $logMsg = "Request: ID=$employee_id, Date=$date\n";
    file_put_contents('debug_schedule.log', $logMsg, FILE_APPEND);

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

    // Check for approved offset schedule first
    $stmt_offset = $conn->prepare("SELECT original_schedule_id, original_day_of_week, start_time, end_time FROM offset_schedule_requests WHERE employee_id = ? AND requested_date = ? AND status IN ('approved', 'completed') LIMIT 1");
    $stmt_offset->bind_param("is", $employee_id, $date);
    $stmt_offset->execute();
    $res_offset = $stmt_offset->get_result();
    $offsetRow = $res_offset->fetch_assoc();
    $stmt_offset->close();

    $handled_by_offset = false;
    $schedule_periods = [];

    if ($offsetRow) {
        if (!empty($offsetRow['original_schedule_id']) && $offsetRow['original_day_of_week'] !== null) {
            // If an offset mirroring a schedule exists, use its schedule periods
            $sql = "SELECT start_time, end_time
                    FROM schedule_periods 
                    WHERE schedule_id = ? 
                      AND day_of_week = ? 
                      AND is_active = 1
                    ORDER BY start_time ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $offsetRow['original_schedule_id'], $offsetRow['original_day_of_week']);
            $stmt->execute();
            $result = $stmt->get_result();
            $schedule_periods = $result->fetch_all(MYSQLI_ASSOC);
            $handled_by_offset = true;
        } else if (!empty($offsetRow['start_time']) && !empty($offsetRow['end_time'])) {
            // If an offset using a custom time exists, use its manual input times directly
            $schedule_periods = [
                [
                    'start_time' => $offsetRow['start_time'],
                    'end_time' => $offsetRow['end_time']
                ]
            ];
            $handled_by_offset = true;
        }
    }

    if (!$handled_by_offset) {
        // Fallback to normal employee schedule lookup
        $sql = "SELECT sp.start_time, sp.end_time
                FROM employee_schedules es
                JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                WHERE es.employee_id = ? 
                  AND es.is_active = 1
                  AND sp.is_active = 1
                  AND sp.day_of_week = ?
                  AND (es.end_date IS NULL OR es.end_date >= ?)
                ORDER BY sp.start_time ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $employee_id, $dayOfWeekDb, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedule_periods = $result->fetch_all(MYSQLI_ASSOC);
        
        // --- INJECT MAKEUP CLASSES ---
        $sqlMakeup = "SELECT start_time, end_time FROM makeup_class_requests WHERE employee_id = ? AND requested_date = ? AND status = 'approved'";
        $stmtMakeup = $conn->prepare($sqlMakeup);
        $stmtMakeup->bind_param("is", $employee_id, $date);
        $stmtMakeup->execute();
        $makeup_res = $stmtMakeup->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!empty($makeup_res)) {
            $schedule_periods = array_merge($schedule_periods, $makeup_res);
        }
        
        // Sort by start time to make calculations accurate
        usort($schedule_periods, function($a, $b) {
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });
    }

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