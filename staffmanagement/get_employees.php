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
    $filter = isset($_GET['filter']) ? $_GET['filter'] : '';

    if ($filter === 'pending_face') {
        // Use NOT EXISTS for clearer logic
        $sql = "SELECT 
                    id, employee_id, first_name, middle_name, last_name, 
                    roles, department, position, status, profile_photo
                FROM employees e
                WHERE e.status = 'active' 
                AND NOT EXISTS (SELECT 1 FROM face_embeddings fe WHERE fe.employee_id = e.id)";
    } elseif ($filter === 'pending_schedule') {
        $sql = "SELECT 
                    id, employee_id, first_name, middle_name, last_name, 
                    roles, department, position, status, profile_photo
                FROM employees e
                WHERE e.status = 'active' 
                AND NOT EXISTS (SELECT 1 FROM employee_schedules es WHERE es.employee_id = e.id AND es.is_active = 1)";
    } else {
        $sql = "SELECT 
                    id, employee_id, first_name, middle_name, last_name, 
                    roles, department, position, status, profile_photo
                FROM employees e
                WHERE e.status = 'active'";
    }

    $params = [];
    $types = '';
    
    // For standard filters, we use 'e.' prefix since we aliased table as 'e' in all queries above
    // (Note: Removed specific prefix logic since I updated all queries to use alias 'e')
    
    // Add role filter
    if (!empty($role)) {
        $sql .= " AND e.roles LIKE ?";
        $params[] = "%$role%";
        $types .= 's';
    }

    // Add department filter
    if (!empty($department)) {
        $sql .= " AND e.department = ?";
        $params[] = $department;
        $types .= 's';
    }

    // Add search filter
    if (!empty($search)) {
        $sql .= " AND (CONCAT(e.first_name, ' ', IFNULL(e.middle_name, ''), ' ', e.last_name) LIKE ? 
                  OR e.employee_id LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }

    // Sort
    if ($filter === 'pending_face' || $filter === 'pending_schedule') {
        $sql .= " ORDER BY e.id DESC";
    } else {
        $sql .= " ORDER BY e.last_name, e.first_name";
    }

    // Pagination
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Validate limit/offset
    if ($limit <= 0) $limit = 15;
    if ($offset < 0) $offset = 0;

    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    // Execute
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
             throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
        if (!$result) {
             throw new Exception("Query failed: " . $conn->error);
        }
    }

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $fullName = trim($row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . $row['last_name']);
        
        $employees[] = [
            'id' => $row['id'],
            'employee_id' => $row['employee_id'],
            'name' => $fullName,
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
        'error' => 'Database error: ' . $e->getMessage() . " (Filter: $filter)"
    ]);
}

$conn->close();
?>
