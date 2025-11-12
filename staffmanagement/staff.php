<?php
require '../db_connection.php';

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">

  <!-- Custom CSS -->
<link rel="stylesheet" href="staff.css">
</head>

<body>
  <div class="top-navbar d-flex justify-content-between align-items-center p-2 shadow-sm">
    <div class="menu-toggle">
      <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn"></i>
    </div>
    <div class="notification">
      <i class="bi bi-bell-fill fs-4 text-warning icon-btn"></i>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column pt-5" id="sidebar">
    <div class="profile text-center p-3 mt-4">
      <img src="pic.png" alt="Placeholder" class="rounded-circle mb-2" width="70">
      <h5 class="mb-0">Kryztian Maniego</h5>
      <small class="role">Admin</small>
    </div>
    <nav class="nav flex-column px-2">
      <a class="nav-link active" href="../dashboard/dashboard.php"><i class="bi bi-house-door me-2"></i> Dashboard</a>
      <a class="nav-link" href="../attendancerep/attendancerep.php"><i class="bi bi-file-earmark-bar-graph me-2"></i> Attendance Reports</a>
      <a class="nav-link" href="../staffmanagement/staff.php"><i class="bi bi-people me-2"></i> Staff Management</a>
      <a class="nav-link" href="../settings/settings.php"><i class="bi bi-gear me-2"></i> Settings</a>
      <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </nav>
  </div>
<!----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
 <div class="content pt-3" id="content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold display-4 text-dark">Staff Management</h2>
     <div class="d-flex justify-content-end mb-3">
      <a href="newstaff.php" class="btn btn-warning">Add New Staff</a>
    </div>
  </div>

 <!-- Page Content -->
      <div class="container-fluid mt-3">
        <div class="row g-3 align-items-center">
          <div class="col-md-3">
            <select id="roleFilter" class="form-select">
              <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role); ?>" 
                                    <?php echo $filters['role'] === $role ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role); ?>
                            </option>
                        <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <select id="departmentFilter" class="form-select">
              <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" 
                                    <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <input type="text" id="searchInput" class="form-control" placeholder="Search" value="<?php echo htmlspecialchars($filters['search']); ?>">
          </div>
        </div>

        <div class="table-responsive mt-4">
          <table class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Department</th>
                <th>Position</th>
                <th>View Profile</th>
              </tr>
            </thead>
            <tbody id="staffTable">
              <!-- Staff rows inserted via JavaScript -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  
<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="staff.js"></script>
</body>
</html>


 


