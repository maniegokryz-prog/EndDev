<?php
// Protect this page - require authentication
require_once '../auth_guard.php';
require_once '../navigation.php';

require '../db_connection.php';

// TOGGLE: Set to true to hide the Add New Staff button entirely
$hide_add_staff_button = false;

// Get current user info
$currentUser = getCurrentUser();

class EmployeeRecordViewer
{
  private $db;
  private $employees = [];
  private $errors = [];

  public function __construct($database)
  {
    $this->db = $database;
  }

  public function loadEmployeeRecords($filters = [])
  {
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

  private function sanitizeEmployeeData($employee)
  {
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
    if ($employee['first_name'])
      $nameParts[] = $employee['first_name'];
    if ($employee['middle_name'])
      $nameParts[] = $employee['middle_name'];
    if ($employee['last_name'])
      $nameParts[] = $employee['last_name'];

    $sanitized['full_name'] = htmlspecialchars(implode(' ', $nameParts), ENT_QUOTES, 'UTF-8');

    return $sanitized;
  }

  public function getEmployees()
  {
    return $this->employees;
  }

  public function getErrors()
  {
    return $this->errors;
  }

  public function hasErrors()
  {
    return !empty($this->errors);
  }

  public function getDistinctDepartments()
  {
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

  public function getDistinctRoles()
  {
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

  private function logActivity($activity, $reference = '')
  {
    $log_entry = "[" . date('Y-m-d H:i:s') . "] [ACTIVITY] " . $activity;
    if ($reference)
      $log_entry .= " - " . $reference;
    $log_entry .= PHP_EOL;

    $log_dir = __DIR__ . '/logs/';
    if (!file_exists($log_dir)) {
      mkdir($log_dir, 0755, true);
    }

    file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
  }

  private function logError($context, $message)
  {
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
  <title>Attendance</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <!-- Bootstrap CSS -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="staff.css">
</head>

<body>
  <div class="top-navbar d-flex justify-content-between align-items-center p-2 shadow-sm">
    <div class="d-flex align-items-center">
      <div class="menu-toggle me-3">
        <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn"></i>
      </div>
    </div>
    <?php include '../includes/notification_bell.php'; ?>
  </div>

  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column pt-5" id="sidebar">
    <div class="profile text-center p-3 mt-4">
      <img
        src="<?php echo (!empty($currentUser['profile_photo']) && $currentUser['profile_photo'] !== 'N/A') ? '../' . htmlspecialchars($currentUser['profile_photo'], ENT_QUOTES, 'UTF-8') . '?v=' . time() : '../assets/profile_pic/user.png?v=' . time(); ?>"
        alt="Profile" class="rounded-circle mb-2" style="width: 70px; height: 70px; object-fit: cover;"
        onerror="this.src='../assets/profile_pic/user.png';">
      <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h5>
      <small
        class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></small>
    </div>
    <nav class="nav flex-column px-2">
      <?php renderNavigation('Staff'); ?>
    </nav>
  </div>
  <!----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
  <div class="content pt-3" id="content">
    <div class="container-fluid">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2 gap-md-3">
        <h2 class="display-4 text-dark mb-0">Staff Management</h2>
        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2 gap-sm-3">
          <?php if (!$hide_add_staff_button): ?>
          <?php if (canAddNewStaff()): ?>
          <a href="newstaff.php" class="btn btn-add-staff fw-bold text-nowrap">Add New Staff</a>
          <?php else: ?>
          <button class="btn btn-secondary" disabled title="Adding new staff is disabled on this server">
            <i class="bi bi-lock"></i> Add New Staff (Disabled)
          </button>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Page Content -->
      <div class="row g-3 align-items-center mb-4">
          <div class="col-md-3">
            <select id="roleFilter" class="form-select">
              <option value="">All Roles</option>
              <?php foreach ($roles as $role): ?>
                <?php $trimRole = trim($role); ?>
                <?php $displayRole = (preg_match('/faculty[\s_\-]*member/i', $trimRole) || strcasecmp($trimRole, 'faculty') === 0) ? 'Faculty' : $role; ?>
                <?php $isSelected = (isset($filters['role']) && (strcasecmp($filters['role'], $role) === 0 || strcasecmp($filters['role'], $displayRole) === 0)); ?>
                <option value="<?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <select id="departmentFilter" class="form-select">
              <option value="">All Departments</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $filters['department'] === $dept ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($dept); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <input type="text" id="searchInput" class="form-control" placeholder="search by name or Id number"
              value="<?php echo htmlspecialchars($filters['search']); ?>">
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
        
        <!-- Load More Container -->
        <div id="loadMoreContainer" class="text-center mt-3 d-none">
          <button id="loadMoreBtn" class="btn btn-outline-primary px-4">
            See More <i class="bi bi-chevron-down ms-1"></i>
          </button>
        </div>
      </div>
      </div>
  </div>

  <!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->

  <!-- Logout Modal -->
  <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 shadow border-0 overflow-hidden">
        <div class="modal-body text-center py-4">
          <h5 class="fw-bold mb-3">Confirm Logout</h5>
          <p class="mb-0 fs-6">Are you sure you want to log out?</p>
        </div>
        <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
          <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4" data-bs-dismiss="modal">No</button>
          <form id="logoutForm" method="POST" action="logout.php" style="display:inline;">
            <input type="hidden" name="confirm_logout" value="1">
            <button type="submit" class="btn-modern btn-outline text-danger border-danger px-4">Yes, Log out</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../dashboard/dashboard.js"></script>
  <script src="staff.js"></script>
  <script>
    function showLogoutModal() {
      var modal = new bootstrap.Modal(document.getElementById('logoutModal'));
      modal.show();
    }
  </script>
</body>

</html>