<?php
/**
 * Get Attendance Records Count
 * Returns the total count of records in daily_attendance table
 */

session_start();

// Check authentication - match clear_all_records.php pattern
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Admin access required'
    ]);
    exit;
}

// Database connection
require_once '../../db_connection.php';

try {
    // Get count of all attendance records
    $query = "SELECT COUNT(*) as total FROM daily_attendance";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'success' => true,
            'count' => (int)$row['total']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to retrieve record count: ' . mysqli_error($conn)
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get records count error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve record count'
    ]);
}
