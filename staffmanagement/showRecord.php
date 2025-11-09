<?php
// filepath: d:\xampp_root\htdocs\All Projects\Web UI\showRecord.php
require 'db_connection.php';

class EmployeeRecordViewer {
    private $db;
    private $employees = [];
    private $errors = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function loadEmployeeRecords($filters = []) {
        try {
            // Build dynamic query with filters
            $query = "SELECT 
                        employee_id, first_name, middle_name, last_name, 
                        email, phone, roles, department, position, hire_date, status
                      FROM employees";
            
            $whereConditions = [];
            $params = [];
            $types = '';
            
            // Apply filters with prepared statements
            if (!empty($filters['status'])) {
                $whereConditions[] = "status = ?";
                $params[] = $filters['status'];
                $types .= 's';
            } else {
                // Default: only show active employees
                $whereConditions[] = "status = ?";
                $params[] = 'active';
                $types .= 's';
            }
            
            if (!empty($filters['department'])) {
                $whereConditions[] = "department = ?";
                $params[] = $filters['department'];
                $types .= 's';
            }
            
            if (!empty($filters['role'])) {
                $whereConditions[] = "roles = ?";
                $params[] = $filters['role'];
                $types .= 's';
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ? OR employee_id LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'sss';
            }
            
            // Add WHERE clause if conditions exist
            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            // Add ordering
            $query .= " ORDER BY created_at DESC";
            
            // Execute query with prepared statement for security
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $this->db->error);
            }
            
            // Bind parameters safely using mysqli
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute employee query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if (!$result) {
                throw new Exception('Failed to get result set');
            }
            
            // Fetch and sanitize all records
            $this->employees = [];
            while ($row = $result->fetch_assoc()) {
                $this->employees[] = $this->sanitizeEmployeeData($row);
            }
            
            $stmt->close();
            
            // Log successful operation
            $this->logActivity("Employee records retrieved", count($this->employees) . " records");
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
            $this->logError("Employee Records Load Failed", $e->getMessage());
            return false;
        }
    }
    
    private function sanitizeEmployeeData($employee) {
        // Sanitize output data to prevent XSS
        $sanitized = [];
        
        foreach ($employee as $key => $value) {
            if ($value === null || $value === '') {
                $sanitized[$key] = 'N/A';
            } else {
                $sanitized[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }
        
        // Build full name properly
        $nameParts = [];
        if ($employee['first_name']) $nameParts[] = $employee['first_name'];
        if ($employee['middle_name']) $nameParts[] = $employee['middle_name'];
        if ($employee['last_name']) $nameParts[] = $employee['last_name'];
        
        $sanitized['full_name'] = htmlspecialchars(implode(' ', $nameParts), ENT_QUOTES, 'UTF-8');
        
        return $sanitized;
    }
    
    public function getEmployees() {
        return $this->employees;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    public function getDistinctDepartments() {
        try {
            $result = $this->db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department");
            $departments = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $departments[] = $row['department'];
                }
            }
            
            return $departments;
        } catch (Exception $e) {
            $this->logError("Get Departments Failed", $e->getMessage());
            return [];
        }
    }
    
    public function getDistinctRoles() {
        try {
            $result = $this->db->query("SELECT DISTINCT roles FROM employees WHERE roles IS NOT NULL AND roles != '' ORDER BY roles");
            $roles = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $roles[] = $row['roles'];
                }
            }
            
            return $roles;
        } catch (Exception $e) {
            $this->logError("Get Roles Failed", $e->getMessage());
            return [];
        }
    }
    
    private function logActivity($activity, $reference = '') {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [ACTIVITY] " . $activity;
        if ($reference) $log_entry .= " - " . $reference;
        $log_entry .= PHP_EOL;
        
        $log_dir = __DIR__ . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function logError($context, $message) {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [ERROR] Context: " . $context . " - Message: " . $message . PHP_EOL;
        
        $log_dir = __DIR__ . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Initialize the viewer
$viewer = new EmployeeRecordViewer($conn);

// Process filter parameters from GET request
$filters = [
    'status' => $_GET['status'] ?? 'active',
    'department' => $_GET['department'] ?? '',
    'role' => $_GET['role'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Load employee records with filters
$loadSuccess = $viewer->loadEmployeeRecords($filters);
$employees = $viewer->getEmployees();
$departments = $viewer->getDistinctDepartments();
$roles = $viewer->getDistinctRoles();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Records</title>
</head>
<body>
    <h1>Employee Records</h1>

    <!-- Error Display -->
    <?php if ($viewer->hasErrors()): ?>
        <div style="color: red; border: 1px solid red; padding: 10px; margin: 10px 0;">
            <strong>Errors:</strong>
            <ul>
                <?php foreach ($viewer->getErrors() as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" action="">
        <table border="1" style="margin-bottom: 20px;">
            <tr>
                <td>
                    <label for="search">Search (Name/ID):</label><br>
                    <input type="text" id="search" name="search" 
                           value="<?php echo htmlspecialchars($filters['search']); ?>" 
                           placeholder="Enter name or employee ID">
                </td>
                
                <td>
                    <label for="department">Department:</label><br>
                    <select id="department" name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" 
                                    <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                
                <td>
                    <label for="role">Role:</label><br>
                    <select id="role" name="role">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role); ?>" 
                                    <?php echo $filters['role'] === $role ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                
                <td>
                    <label for="status">Status:</label><br>
                    <select id="status" name="status">
                        <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="" <?php echo $filters['status'] === '' ? 'selected' : ''; ?>>All</option>
                    </select>
                </td>
                
                <td>
                    <input type="submit" value="Filter">
                    <a href="showRecord.php">Clear</a>
                </td>
            </tr>
        </table>
    </form>

    <!-- Employee Records Table -->
    <?php if ($loadSuccess && !empty($employees)): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Hire Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td>
                            <a href="employee_detail.php?id=<?php echo urlencode($employee['employee_id']); ?>">
                                <?php echo $employee['full_name']; ?>
                            </a><br>
                            <p style="margin: 0; color: grey;"><?php echo $employee['employee_id']; ?></p>
                        </td>
                        <td><?php echo $employee['email']; ?></td>
                        <td><?php echo $employee['phone']; ?></td>
                        <td><?php echo $employee['roles']; ?></td>
                        <td><?php echo $employee['department']; ?></td>
                        <td><?php echo $employee['position']; ?></td>
                        <td><?php echo $employee['hire_date']; ?></td>
                        <td><?php echo $employee['status']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p><strong>Records found:</strong> <?php echo count($employees); ?></p>
        
    <?php else: ?>
        <p>No employee records found.</p>
    <?php endif; ?>

    <p>
        <a href="index.php">Add New Employee</a>
    </p>
</body>
</html>