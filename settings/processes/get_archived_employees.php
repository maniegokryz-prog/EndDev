<?php
/**
 * Get Archived Employees
 * Retrieves all employees with status = 'inactive'
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require '../../db_connection.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT 
                id,
                employee_id,
                first_name,
                middle_name,
                last_name,
                email,
                phone,
                roles,
                department,
                position,
                profile_photo,
                updated_at
            FROM employees
            WHERE status = 'inactive'
            ORDER BY updated_at DESC";

    $result = $conn->query($sql);

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        // Construct full name
        $fullName = trim($row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . $row['last_name']);
        
        $employees[] = [
            'id' => $row['id'],
            'employee_id' => $row['employee_id'],
            'name' => $fullName,
            'first_name' => $row['first_name'],
            'middle_name' => $row['middle_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'] ?? 'N/A',
            'phone' => $row['phone'] ?? 'N/A',
            'role' => $row['roles'] ?? 'N/A',
            'department' => $row['department'] ?? 'N/A',
            'position' => $row['position'] ?? 'N/A',
            'profile_photo' => $row['profile_photo'] ?? '../assets/profile_pic/user.png',
            'date_removed' => $row['updated_at'] ? date('m/d/Y', strtotime($row['updated_at'])) : 'N/A'
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $employees,
        'count' => count($employees)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
