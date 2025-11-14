<?php
/**
 * Performance Metrics API
 * 
 * Fetches attendance performance metrics for an employee
 * Returns: Present %, Absent %, On Time %, Late %
 * 
 * CALCULATION METHODOLOGY:
 * 
 * 1. PRESENT (Complete Status):
 *    - Count records where status = 'complete'
 *    - Formula: (complete_count / total_scheduled_days) * 100
 *    - Represents days where employee clocked in AND out properly
 * 
 * 2. ABSENT:
 *    - Count records where status = 'absent'
 *    - Formula: (absent_count / total_scheduled_days) * 100
 *    - Represents days where employee did not show up at all
 * 
 * 3. ON TIME:
 *    - Count 'complete' records where late_minutes = 0 OR late_minutes IS NULL
 *    - Formula: (on_time_count / total_scheduled_days) * 100
 *    - Represents days where employee arrived on or before scheduled time
 * 
 * 4. LATE:
 *    - Count 'complete' records where late_minutes > 0
 *    - Formula: (late_count / total_scheduled_days) * 100
 *    - Represents days where employee arrived after scheduled time
 * 
 * NOTE: Incomplete status is not included in these metrics as it represents
 * partial attendance (only time in or time out recorded, not both)
 */

require_once '../db_connection.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $employeeId = $_GET['employee_id'] ?? null;
    $month = $_GET['month'] ?? null;
    $year = $_GET['year'] ?? date('Y');
    
    if (!$employeeId) {
        throw new Exception('Employee ID is required');
    }
    
    // Get employee's internal ID
    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name FROM employees WHERE employee_id = ?");
    $stmt->bind_param("s", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Employee not found');
    }
    
    $employee = $result->fetch_assoc();
    $employeeInternalId = $employee['id'];
    $stmt->close();
    
    // Build query for attendance metrics
    $query = "SELECT 
                status,
                late_minutes,
                attendance_date
              FROM daily_attendance 
              WHERE employee_id = ?";
    
    $params = [$employeeInternalId];
    $types = "i";
    
    // Apply month and year filters
    if ($month && $year) {
        $query .= " AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?";
        $params[] = $month;
        $params[] = $year;
        $types .= "ii";
    } elseif ($year) {
        $query .= " AND YEAR(attendance_date) = ?";
        $params[] = $year;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Initialize counters
    $totalScheduledDays = 0;
    $completeCount = 0;      // Present days (status = complete)
    $absentCount = 0;        // Absent days (status = absent)
    $onTimeCount = 0;        // Days arrived on time (late_minutes = 0 or NULL)
    $lateCount = 0;          // Days arrived late (late_minutes > 0)
    
    // Process attendance records
    while ($row = $result->fetch_assoc()) {
        $status = strtolower(trim($row['status']));
        $lateMinutes = $row['late_minutes'];
        
        $totalScheduledDays++;
        
        // Count by status
        if ($status === 'complete') {
            $completeCount++;
            
            // Check if on time or late
            if ($lateMinutes === null || $lateMinutes == 0) {
                $onTimeCount++;
            } else if ($lateMinutes > 0) {
                $lateCount++;
            }
        } elseif ($status === 'absent') {
            $absentCount++;
        }
        // Note: 'incomplete' status is not counted in any metric
    }
    
    $stmt->close();
    
    // Calculate percentages
    $presentPercentage = $totalScheduledDays > 0 ? round(($completeCount / $totalScheduledDays) * 100, 1) : 0;
    $absentPercentage = $totalScheduledDays > 0 ? round(($absentCount / $totalScheduledDays) * 100, 1) : 0;
    $onTimePercentage = $totalScheduledDays > 0 ? round(($onTimeCount / $totalScheduledDays) * 100, 1) : 0;
    $latePercentage = $totalScheduledDays > 0 ? round(($lateCount / $totalScheduledDays) * 100, 1) : 0;
    
    // Prepare response
    $response = [
        'success' => true,
        'employee' => [
            'id' => $employeeId,
            'name' => trim($employee['first_name'] . ' ' . ($employee['middle_name'] ?? '') . ' ' . $employee['last_name'])
        ],
        'period' => [
            'month' => $month,
            'year' => $year
        ],
        'metrics' => [
            'present' => [
                'count' => $completeCount,
                'percentage' => $presentPercentage,
                'description' => 'Days with complete time in and time out'
            ],
            'absent' => [
                'count' => $absentCount,
                'percentage' => $absentPercentage,
                'description' => 'Days marked as absent'
            ],
            'onTime' => [
                'count' => $onTimeCount,
                'percentage' => $onTimePercentage,
                'description' => 'Days arrived on or before scheduled time'
            ],
            'late' => [
                'count' => $lateCount,
                'percentage' => $latePercentage,
                'description' => 'Days arrived after scheduled time'
            ]
        ],
        'summary' => [
            'total_scheduled_days' => $totalScheduledDays,
            'total_complete' => $completeCount,
            'total_absent' => $absentCount,
            'total_on_time' => $onTimeCount,
            'total_late' => $lateCount
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
