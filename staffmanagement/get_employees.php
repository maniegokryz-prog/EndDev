<?php
require_once '../db_connection.php';

header('Content-Type: application/json');

// Verify CSRF token if needed (optional for GET requests)
// if (!isset($_SESSION['csrf_token'])) {
//     http_response_code(403);
//     echo json_encode(['error' => 'Invalid session']);
//     exit;
// }

try {
    // Get filter parameters
    $role = isset($_GET['role']) && $_GET['role'] !== 'All Roles' ? $_GET['role'] : '';
    $department = isset($_GET['department']) && $_GET['department'] !== 'All Departments' ? $_GET['department'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // Build the query
    $sql = "SELECT 
                id,
                employee_id,
                first_name,
                middle_name,
                last_name,
                roles,
                department,
                position,
                status,
                profile_photo
            FROM employees
            WHERE status = 'active'";

    $params = [];
    $types = '';

    // Add role filter
    if (!empty($role)) {
        $sql .= " AND roles LIKE ?";
        $params[] = "%$role%";
        $types .= 's';
    }

    // Add department filter
    if (!empty($department)) {
        $sql .= " AND department = ?";
        $params[] = $department;
        $types .= 's';
    }

    // Add search filter (searches in name and employee_id)
    if (!empty($search)) {
        $sql .= " AND (CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ? 
                  OR employee_id LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }

    $sql .= " ORDER BY last_name, first_name";

    // Prepare and execute the statement
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

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
            'role' => $row['roles'] ?? 'N/A',
            'department' => $row['department'] ?? 'N/A',
            'position' => $row['position'] ?? 'N/A',
            'profile_photo' => $row['profile_photo'] ?? '../assets/profile_pic/user.png'
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
?>
