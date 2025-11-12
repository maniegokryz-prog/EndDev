<?php
/**
 * Late Today API
 * Fetches employees who were late today
 */

// Set timezone to match your location (Philippine Time)
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
require '../db_connection.php';

try {
    // Get today's date range
    $today = date('Y-m-d');
    $start_of_day = $today . ' 00:00:00';
    $end_of_day = $today . ' 23:59:59';
    
    // Fetch employees who were late today
    // Join attendance_logs with daily_attendance to get late_minutes
    $sql = "SELECT DISTINCT
                e.id,
                e.employee_id as employee_code,
                e.first_name,
                e.middle_name,
                e.last_name,
                e.profile_photo,
                e.position,
                e.department,
                da.late_minutes,
                da.time_in,
                da.status
            FROM employees e
            INNER JOIN daily_attendance da ON e.id = da.employee_id
            WHERE da.attendance_date = ?
            AND da.late_minutes > 0
            ORDER BY da.late_minutes DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $late_employees = [];
    
    while ($row = $result->fetch_assoc()) {
        // Build full name
        $full_name = trim($row['first_name'] . ' ' . $row['last_name']);
        
        // Determine profile photo path
        $profile_photo_path = $row['profile_photo'];
        
        if (!empty($profile_photo_path)) {
            if (strpos($profile_photo_path, 'assets/') === 0) {
                $profile_photo = '../' . $profile_photo_path;
            } elseif (strpos($profile_photo_path, '../') === 0) {
                $profile_photo = $profile_photo_path;
            } else {
                $profile_photo = '../' . $profile_photo_path;
            }
        } else {
            $profile_photo = '../assets/profile_pic/user.png';
        }
        
        // Format time_in
        $time_in = '';
        if (!empty($row['time_in'])) {
            $time_obj = new DateTime($row['time_in']);
            $time_in = $time_obj->format('g:i A'); // e.g., "8:55 AM"
        }
        
        // Convert late_minutes to hours and minutes
        $late_minutes = intval($row['late_minutes']);
        $late_hours = floor($late_minutes / 60);
        $late_mins = $late_minutes % 60;
        
        // Format late time display
        $late_display = '';
        if ($late_hours > 0 && $late_mins > 0) {
            $late_display = "Late {$late_hours}h {$late_mins}m";
        } elseif ($late_hours > 0) {
            $late_display = "Late {$late_hours}h";
        } else {
            $late_display = "Late {$late_mins}m";
        }
        
        $late_employees[] = [
            'id' => $row['id'],
            'employee_code' => $row['employee_code'],
            'full_name' => $full_name,
            'profile_photo' => $profile_photo,
            'position' => $row['position'],
            'department' => $row['department'],
            'time_in' => $time_in,
            'late_minutes' => $late_minutes,
            'late_display' => $late_display,
            'status' => $row['status']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($late_employees),
        'data' => $late_employees,
        'date' => $today
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch late employees',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
