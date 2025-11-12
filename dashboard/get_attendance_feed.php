<?php
/**
 * Attendance Feed API
 * Fetches recent attendance logs for the dashboard feed
 */

// Set timezone to match your location (Philippine Time)
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
require '../db_connection.php';

try {
    // Get the limit parameter (default to 50 recent logs)
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $limit = min($limit, 100); // Max 100 records
    
    // Fetch recent attendance logs with employee details
    $sql = "SELECT 
                al.id,
                al.employee_id,
                al.log_type,
                al.log_time,
                al.notes,
                e.employee_id as employee_code,
                e.first_name,
                e.middle_name,
                e.last_name,
                e.profile_photo,
                e.position,
                e.department
            FROM attendance_logs al
            INNER JOIN employees e ON al.employee_id = e.id
            ORDER BY al.log_time DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendance_logs = [];
    
    while ($row = $result->fetch_assoc()) {
        // Calculate time ago
        $log_time = new DateTime($row['log_time']);
        $now = new DateTime();
        $interval = $now->diff($log_time);
        
        // Calculate total minutes difference
        $total_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        
        // Format time ago string (simplified)
        if ($interval->days > 0) {
            $time_ago = $interval->days . 'd ago';
        } elseif ($interval->h > 0) {
            $time_ago = $interval->h . 'h ago';
        } elseif ($interval->i > 0) {
            $time_ago = $interval->i . 'm ago';
        } else {
            $time_ago = 'Just now';
        }
        
        // Format detailed time ago for tooltip
        $detailed_time_ago = '';
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
        
        // Format log time for display
        $formatted_time = $log_time->format('g:i A'); // e.g., "7:00 AM"
        $formatted_date = $log_time->format('M j, Y'); // e.g., "Nov 12, 2025"
        
        // Build full name
        $full_name = trim($row['first_name'] . ' ' . $row['last_name']);
        
        // Determine profile photo path
        // Profile photos are stored relative to the project root
        // We need to remove the leading '../' if it exists in the path
        $profile_photo_path = $row['profile_photo'];
        
        if (!empty($profile_photo_path)) {
            // If the path already starts with 'assets/', use it as is
            if (strpos($profile_photo_path, 'assets/') === 0) {
                $profile_photo = '../' . $profile_photo_path;
            }
            // If path starts with '../', remove one level since we're already in dashboard folder
            elseif (strpos($profile_photo_path, '../') === 0) {
                $profile_photo = $profile_photo_path;
            }
            // Otherwise, assume it's a relative path from root
            else {
                $profile_photo = '../' . $profile_photo_path;
            }
        } else {
            $profile_photo = '../assets/profile_pic/user.png';
        }
        
        // Format log type for display
        $log_type_display = ($row['log_type'] === 'time_in') ? 'Time In' : 'Time Out';
        
        // Extract status from notes (if available)
        $status = '';
        if (!empty($row['notes'])) {
            // Extract the status message (e.g., "On-time", "Late by 30 minutes")
            if (strpos($row['notes'], 'Time In:') !== false) {
                $status = str_replace('Time In: ', '', $row['notes']);
            } elseif (strpos($row['notes'], 'Time Out:') !== false) {
                $status = str_replace('Time Out: ', '', $row['notes']);
            }
        }
        
        $attendance_logs[] = [
            'id' => $row['id'],
            'employee_code' => $row['employee_code'],
            'full_name' => $full_name,
            'profile_photo' => $profile_photo,
            'position' => $row['position'],
            'department' => $row['department'],
            'log_type' => $row['log_type'],
            'log_type_display' => $log_type_display,
            'log_time' => $row['log_time'],
            'formatted_time' => $formatted_time,
            'formatted_date' => $formatted_date,
            'time_ago' => $time_ago,
            'detailed_time_ago' => $detailed_time_ago,
            'total_minutes' => $total_minutes,
            'status' => $status,
            'notes' => $row['notes']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($attendance_logs),
        'data' => $attendance_logs,
        'server_time' => (new DateTime())->format('Y-m-d H:i:s'),
        'server_timezone' => date_default_timezone_get()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch attendance logs',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
