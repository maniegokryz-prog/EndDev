<?php
/**
 * Centralized Attendance Records API
 * Fetches various attendance-related data based on the current date or given date
 * 
 * Parameters:
 * - date: Optional date in Y-m-d format (defaults to today)
 * - type: Type of data to fetch (default: 'all')
 *   - 'all': Returns all data types
 *   - 'feed': Recent attendance logs
 *   - 'late': Employees who were late
 *   - 'on_leave': Employees on leave
 *   - 'summary': Summary statistics (present, absent, on-time, late percentages)
 *   - 'daily': Daily attendance records
 * - limit: Maximum number of records for feed (default: 50, max: 100)
 * 
 * Usage Examples:
 * 1. Get all data for today:
 *    get_attendance_records.php
 *    get_attendance_records.php?type=all
 * 
 * 2. Get only late employees for today:
 *    get_attendance_records.php?type=late
 * 
 * 3. Get attendance feed for a specific date:
 *    get_attendance_records.php?type=feed&date=2025-11-10
 * 
 * 4. Get summary statistics for yesterday:
 *    get_attendance_records.php?type=summary&date=2025-11-11
 * 
 * 5. Get daily attendance records with custom date:
 *    get_attendance_records.php?type=daily&date=2025-11-01
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "date": "2025-11-12",
 *   "server_time": "2025-11-12 17:30:00",
 *   "server_timezone": "Asia/Manila",
 *   "feed": { "count": 10, "data": [...] },      // if type=feed or all
 *   "late": { "count": 5, "data": [...] },       // if type=late or all
 *   "on_leave": { "count": 2, "data": [...] },   // if type=on_leave or all
 *   "summary": { "total_employees": 50, ... },   // if type=summary or all
 *   "daily": { "count": 45, "data": [...] }      // if type=daily or all
 * }
 */

// Set timezone to match your location (Philippine Time)
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
require '../db_connection.php';
require_once '../attendancerep/dtr_utils.php';

try {
    // Get parameters
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $limit = min($limit, 100); // Max 100 records

    // Validate date format
    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
        throw new Exception('Invalid date format. Use Y-m-d format (e.g., 2025-11-12)');
    }

    $response = [
        'success' => true,
        'date' => $date,
        'server_time' => (new DateTime())->format('Y-m-d H:i:s'),
        'server_timezone' => date_default_timezone_get()
    ];

    // Helper function to format profile photo path
    function getProfilePhotoPath($profile_photo_path)
    {
        if (empty($profile_photo_path)) {
            return '../assets/profile_pic/user.png';
        }

        if (strpos($profile_photo_path, 'assets/') === 0) {
            return '../' . $profile_photo_path;
        } elseif (strpos($profile_photo_path, '../') === 0) {
            return $profile_photo_path;
        } else {
            return '../' . $profile_photo_path;
        }
    }

    // Helper function to calculate time ago
    function getTimeAgo($datetime_string)
    {
        $log_time = new DateTime($datetime_string);
        $now = new DateTime();
        $interval = $now->diff($log_time);

        // Calculate total minutes difference
        $total_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

        // Format time ago string (simplified)
        if ($interval->days > 0) {
            if ($interval->days == 1) {
                $time_ago = '1 day ago';
            } else {
                $time_ago = $interval->days . ' days ago';
            }
        } elseif ($interval->h > 0) {
            $time_ago = $interval->h . 'h ago';
        } elseif ($interval->i > 0) {
            $time_ago = $interval->i . 'm ago';
        } else {
            $time_ago = 'Just now';
        }

        // Format detailed time ago for tooltip
        $parts = [];
        if ($interval->days > 0) {
            $parts[] = $interval->days . ' day' . ($interval->days > 1 ? 's' : '');
        }
        if ($interval->h > 0) {
            $parts[] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
        }
        if ($interval->i > 0) {
            $parts[] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
        }
        if ($interval->s > 0 && empty($parts)) {
            $parts[] = $interval->s . ' second' . ($interval->s > 1 ? 's' : '');
        }

        $detailed_time_ago = !empty($parts) ? implode(', ', $parts) . ' ago' : 'Just now';

        return [
            'time_ago' => $time_ago,
            'detailed_time_ago' => $detailed_time_ago,
            'total_minutes' => $total_minutes
        ];
    }

    // Fetch attendance feed (recent logs from attendance_logs table to include visits)
    if ($type === 'all' || $type === 'feed') {
        // Query attendance_logs table which has ALL scans (including visits)
        // We LEFT JOIN with daily_attendance to get late status/stats if applicable
        $sql = "SELECT 
                    al.id,
                    al.log_type,
                    al.log_time,
                    al.notes,
                    e.employee_id as employee_code,
                    e.first_name,
                    e.middle_name,
                    e.last_name,
                    e.profile_photo,
                    e.position,
                    e.department,
                    da.late_minutes,
                    da.status as daily_status
                FROM attendance_logs al
                INNER JOIN employees e ON al.employee_id = e.id
                LEFT JOIN daily_attendance da ON (al.employee_id = da.employee_id AND al.log_date = da.attendance_date)
                WHERE al.log_date = ?
                ORDER BY al.log_time DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("SQL prepare failed: " . $conn->error);
        }

        $stmt->bind_param("si", $date, $limit);

        if (!$stmt->execute()) {
            throw new Exception("SQL execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();

        $attendance_logs = [];

        // Debug: Count total rows
        $total_rows = $result->num_rows;

        while ($row = $result->fetch_assoc()) {
            // Build full name
            $full_name = trim($row['first_name'] . ' ' . $row['last_name']);

            // Get profile photo path
            $profile_photo = getProfilePhotoPath($row['profile_photo']);

            // Format time
            $log_time_obj = new DateTime($row['log_time']);
            $formatted_time = $log_time_obj->format('g:i A');
            $formatted_date = $log_time_obj->format('M j, Y');
            $time_ago_data = getTimeAgo($row['log_time']);

            // Determine display text and status
            $log_type = $row['log_type'];
            $log_type_display = ucfirst(str_replace('_', ' ', $log_type));

            // Status logic
            $status = '';

            if ($log_type === 'visit') {
                $status = 'Visitor';
            } elseif ($log_type === 'time_in') {
                // For time_in, check late minutes from linked daily_attendance
                if (!empty($row['late_minutes']) && $row['late_minutes'] > 0) {
                    $late_hours = floor($row['late_minutes'] / 60);
                    $late_mins = $row['late_minutes'] % 60;
                    if ($late_hours > 0) {
                        $status = "Late ({$late_hours}h {$late_mins}m)";
                    } else {
                        $status = "Late ({$late_mins}m)";
                    }
                } else {
                    $status = "On-time";
                }
            } elseif ($log_type === 'time_out') {
                // For time_out, we can show status from daily record or just generic
                $status = 'Logged out';
            }

            $attendance_logs[] = [
                'id' => $row['id'],
                'employee_code' => $row['employee_code'],
                'full_name' => $full_name,
                'profile_photo' => $profile_photo,
                'position' => $row['position'],
                'department' => $row['department'],
                'log_type' => $log_type,
                'log_type_display' => $log_type_display,
                'log_time' => $log_time_obj->format('H:i:s'), // 24hr format for sorting/reference
                'formatted_time' => $formatted_time,
                'formatted_date' => $formatted_date,
                'time_ago' => $time_ago_data['time_ago'],
                'detailed_time_ago' => $time_ago_data['detailed_time_ago'],
                'total_minutes' => $time_ago_data['total_minutes'],
                'status' => $status,
                'notes' => $row['notes']
            ];
        }

        $response['feed'] = [
            'count' => count($attendance_logs),
            'data' => $attendance_logs,
            'debug' => [
                'date_queried' => $date,
                'limit' => $limit,
                'rows_found' => $total_rows,
                'source' => 'attendance_logs table'
            ]
        ];
    }

    // Fetch late employees
    if ($type === 'all' || $type === 'late') {
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
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        $late_employees = [];

        while ($row = $result->fetch_assoc()) {
            $full_name = trim($row['first_name'] . ' ' . $row['last_name']);
            $profile_photo = getProfilePhotoPath($row['profile_photo']);

            // Format time_in
            $time_in = '';
            if (!empty($row['time_in'])) {
                $time_obj = new DateTime($row['time_in']);
                $time_in = $time_obj->format('g:i A');
            }

            // Convert late_minutes to hours and minutes
            $late_minutes = intval($row['late_minutes']);
            $late_hours = floor($late_minutes / 60);
            $late_mins = $late_minutes % 60;

            // Format late time display
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

        $response['late'] = [
            'count' => count($late_employees),
            'data' => $late_employees
        ];
    }

    // Fetch employees on leave
    if ($type === 'all' || $type === 'on_leave') {
        $sql = "SELECT 
                    e.id,
                    e.employee_id as employee_code,
                    e.first_name,
                    e.middle_name,
                    e.last_name,
                    e.profile_photo,
                    e.position,
                    e.department,
                    el.start_date,
                    el.end_date,
                    el.reason,
                    el.status,
                    el.leave_type_id
                FROM employees e
                INNER JOIN employee_leaves el ON e.id = el.employee_id
                WHERE el.status = 'approved'
                AND ? BETWEEN el.start_date AND el.end_date
                ORDER BY el.start_date ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        $on_leave_employees = [];

        while ($row = $result->fetch_assoc()) {
            $full_name = trim($row['first_name'] . ' ' . $row['last_name']);
            $profile_photo = getProfilePhotoPath($row['profile_photo']);

            // Format dates
            $start_date_obj = new DateTime($row['start_date']);
            $end_date_obj = new DateTime($row['end_date']);

            // Format date range for display
            $start_formatted = $start_date_obj->format('M j');
            $end_formatted = $end_date_obj->format('M j, Y');
            $date_range = $start_formatted . ' to ' . $end_formatted;

            // If same year, simplify format
            if ($start_date_obj->format('Y') === $end_date_obj->format('Y')) {
                if ($start_date_obj->format('m') === $end_date_obj->format('m')) {
                    // Same month and year
                    $date_range = $start_date_obj->format('M j') . ' to ' . $end_date_obj->format('j, Y');
                } else {
                    // Different months, same year
                    $date_range = $start_date_obj->format('M j') . ' to ' . $end_date_obj->format('M j, Y');
                }
            }

            $on_leave_employees[] = [
                'id' => $row['id'],
                'employee_code' => $row['employee_code'],
                'full_name' => $full_name,
                'profile_photo' => $profile_photo,
                'position' => $row['position'],
                'department' => $row['department'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'date_range' => $date_range,
                'reason' => $row['reason'],
                'leave_type_id' => $row['leave_type_id'],
                'status' => $row['status']
            ];
        }

        $response['on_leave'] = [
            'count' => count($on_leave_employees),
            'data' => $on_leave_employees
        ];
    }

    // Fetch summary statistics
    if ($type === 'all' || $type === 'summary') {
        // Get total daily_attendance records for this day (base for all calculations)
        $sql = "SELECT COUNT(*) as total 
                FROM daily_attendance 
                WHERE attendance_date = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $total_records = $stmt->get_result()->fetch_assoc()['total'];

        // Get present count (employees with time_in)
        // Present = Total users who timed in (regardless of late or on-time)
        $sql = "SELECT COUNT(*) as present 
                FROM daily_attendance 
                WHERE attendance_date = ? AND time_in IS NOT NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $present_count = $stmt->get_result()->fetch_assoc()['present'];

        // Get absent count (employees with NO time_in)
        $sql = "SELECT COUNT(*) as absent 
                FROM daily_attendance 
                WHERE attendance_date = ? AND time_in IS NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $absent_count = $stmt->get_result()->fetch_assoc()['absent'];

        // Get on-time count (has time_in AND late_minutes = 0)
        $sql = "SELECT COUNT(*) as on_time 
                FROM daily_attendance 
                WHERE attendance_date = ? AND time_in IS NOT NULL AND late_minutes = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $on_time_count = $stmt->get_result()->fetch_assoc()['on_time'];

        // Get late count (has time_in AND late_minutes > 0)
        $sql = "SELECT COUNT(*) as late 
                FROM daily_attendance 
                WHERE attendance_date = ? AND time_in IS NOT NULL AND late_minutes > 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $late_count = $stmt->get_result()->fetch_assoc()['late'];

        // Calculate percentages based on total daily_attendance records
        $present_percentage = $total_records > 0 ? round(($present_count / $total_records) * 100, 1) : 0;
        $absent_percentage = $total_records > 0 ? round(($absent_count / $total_records) * 100, 1) : 0;
        $on_time_percentage = $total_records > 0 ? round(($on_time_count / $total_records) * 100, 1) : 0;
        $late_percentage = $total_records > 0 ? round(($late_count / $total_records) * 100, 1) : 0;

        $response['summary'] = [
            'total_records' => $total_records,
            'present' => [
                'count' => $present_count,
                'percentage' => $present_percentage
            ],
            'absent' => [
                'count' => $absent_count,
                'percentage' => $absent_percentage
            ],
            'on_time' => [
                'count' => $on_time_count,
                'percentage' => $on_time_percentage
            ],
            'late' => [
                'count' => $late_count,
                'percentage' => $late_percentage
            ]
        ];
    }

    // Fetch daily attendance records
    if ($type === 'all' || $type === 'daily') {
        $sql = "SELECT 
                    da.*,
                    e.employee_id as employee_code,
                    e.first_name,
                    e.middle_name,
                    e.last_name,
                    e.profile_photo,
                    e.position,
                    e.department,
                    e.roles
                FROM daily_attendance da
                INNER JOIN employees e ON da.employee_id = e.id
                WHERE da.attendance_date = ?
                ORDER BY da.time_in DESC, e.last_name, e.first_name";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        $daily_records = [];

        while ($row = $result->fetch_assoc()) {
            $full_name = trim($row['first_name'] . ' ' . $row['last_name']);
            $profile_photo = getProfilePhotoPath($row['profile_photo']);

            // Format times
            $time_in_formatted = !empty($row['time_in']) ? (new DateTime($row['time_in']))->format('g:i A') : null;
            $time_out_formatted = !empty($row['time_out']) ? (new DateTime($row['time_out']))->format('g:i A') : null;

            // Format hours worked
            // Dynamic recalculation to bypass DB log penalty for older records
            if (!empty($row['time_in']) && !empty($row['time_out'])) {
                $schedule = getEmployeeSchedule($conn, $row['employee_id']);
                $row['actual_hours'] = calculateActualHoursWithClamping($row['time_in'], $row['time_out'], $schedule, $row['attendance_date'], $row['roles']);
            }

            $hours_worked = null;
            if (!empty($row['actual_hours'])) {
                $total_minutes = $row['actual_hours'];
                $hours = floor($total_minutes / 60);
                $minutes = round($total_minutes % 60);
                if ($hours > 0) {
                    $hours_worked = "{$hours}h {$minutes}m";
                } else {
                    $hours_worked = "{$minutes}m";
                }
            }

            $daily_records[] = [
                'id' => $row['id'],
                'employee_id' => $row['employee_id'],
                'employee_code' => $row['employee_code'],
                'full_name' => $full_name,
                'profile_photo' => $profile_photo,
                'position' => $row['position'],
                'department' => $row['department'],
                'roles' => $row['roles'],
                'attendance_date' => $row['attendance_date'],
                'time_in' => $row['time_in'],
                'time_in_formatted' => $time_in_formatted,
                'time_out' => $row['time_out'],
                'time_out_formatted' => $time_out_formatted,
                'late_minutes' => $row['late_minutes'] ?? 0,
                'overtime_minutes' => $row['overtime_minutes'] ?? 0,
                'undertime_minutes' => $row['undertime_minutes'] ?? 0,
                'actual_hours' => $row['actual_hours'] ?? null,
                'hours_worked' => $hours_worked,
                'status' => $row['status'],
                'schedule_id' => $row['schedule_id'] ?? null
            ];
        }

        $response['daily'] = [
            'count' => count($daily_records),
            'data' => $daily_records
        ];
    }

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'date' => isset($date) ? $date : null,
        'type' => isset($type) ? $type : null
    ], JSON_PRETTY_PRINT);
}

$conn->close();
?>