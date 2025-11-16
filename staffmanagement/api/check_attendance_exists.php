<?php
/**
 * Check if attendance record exists for employee on a specific date
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
    
    // Check if attendance record exists for this date
    $sql = "SELECT id, time_in, time_out, status 
            FROM daily_attendance 
            WHERE employee_id = ? AND attendance_date = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $employee_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    
    if ($record) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'record' => [
                'id' => $record['id'],
                'time_in' => $record['time_in'],
                'time_out' => $record['time_out'],
                'status' => $record['status']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false
        ]);
    }
    
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
