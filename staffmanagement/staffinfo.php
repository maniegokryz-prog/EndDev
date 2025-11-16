<?php
// Protect this page - require authentication
require_once '../auth_guard.php';

require '../db_connection.php';

// Get current user info
$currentUser = getCurrentUser();

class EmployeeEditor {
    private $db;
    private $employee = null;
    private $errors = [];

    public function __construct($database) {
        $this->db = $database;
    }

    public function loadEmployee($employee_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM employees WHERE employee_id = ?");
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $this->db->error);
            }
            
            $stmt->bind_param('s', $employee_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $this->employee = $result->fetch_assoc();
            
            $stmt->close();
            
            if (!$this->employee) {
                $this->errors[] = "Employee not found with ID: " . htmlspecialchars($employee_id);
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
            return false;
        }
    }

    public function getEmployee() {
        return $this->employee;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }
}

// Get employee ID from URL parameter
$employee_id = $_GET['id'] ?? '';

if (empty($employee_id)) {
    header('Location: showRecord.php?error=no_id_to_edit');
    exit;
}

$editor = new EmployeeEditor($conn);
$loadSuccess = $editor->loadEmployee($employee_id);
$employee = $editor->getEmployee();

class EmployeeDetailViewer {
    private $db;
    private $employee = null;
    private $schedules = [];
    private $errors = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function loadEmployeeDetails($employee_id) {
        try {
            // Get employee basic information
            $stmt = $this->db->prepare("
                SELECT id, employee_id, first_name, middle_name, last_name, 
                       email, phone, roles, department, position, hire_date, 
                       status, created_at, updated_at, profile_photo
                FROM employees 
                WHERE employee_id = ?
            ");
            
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $this->db->error);
            }
            
            $stmt->bind_param('s', $employee_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute employee query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if (!$result) {
                throw new Exception('Failed to get result set');
            }
            
            $this->employee = $result->fetch_assoc();
            
            $stmt->close();
            
            if (!$this->employee) {
                $this->errors[] = "Employee not found with ID: " . htmlspecialchars($employee_id);
                return false;
            }
            
            // Sanitize employee data
            $this->employee = $this->sanitizeData($this->employee);
            
            // Get employee schedules and assignments
            $this->loadEmployeeSchedules($this->employee['id']);
            
            $this->logActivity("Employee details viewed", "Employee ID: " . $employee_id);
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
            $this->logError("Employee Details Load Failed", $e->getMessage());
            return false;
        }
    }
    
    private function loadEmployeeSchedules($internal_employee_id) {
        try {
            $query = "
                SELECT 
                    s.schedule_name,
                    s.description as schedule_description,
                    sp.day_of_week,
                    sp.period_name,
                    sp.start_time,
                    sp.end_time,
                    ea.subject_code,
                    ea.designate_class,
                    ea.room_num,
                    es.is_active
                FROM employee_schedules es
                JOIN schedules s ON es.schedule_id = s.id
                JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                LEFT JOIN employee_assignments ea ON ea.employee_id = es.employee_id 
                    AND ea.schedule_period_id = sp.id
                WHERE es.employee_id = ? 
                AND es.is_active = 1
                AND sp.is_active = 1
                ORDER BY sp.day_of_week, sp.start_time
            ";
            
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                throw new Exception('Failed to prepare schedule query: ' . $this->db->error);
            }
            
            $stmt->bind_param('i', $internal_employee_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute schedule query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if (!$result) {
                throw new Exception('Failed to get result set');
            }
            
            $this->schedules = [];
            while ($row = $result->fetch_assoc()) {
                $this->schedules[] = $this->sanitizeData($row);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $this->errors[] = "Error loading schedules: " . $e->getMessage();
            $this->logError("Schedule Load Failed", $e->getMessage());
        }
    }
    
    private function sanitizeData($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                $sanitized[$key] = 'N/A';
            } else {
                $sanitized[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }
        return $sanitized;
    }
    
    public function getEmployee() {
        return $this->employee;
    }
    
    public function getSchedules() {
        return $this->schedules;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    public function getFullName() {
        if (!$this->employee) return 'Unknown';
        
        $nameParts = [];
        if ($this->employee['first_name'] && $this->employee['first_name'] !== 'N/A') {
            $nameParts[] = $this->employee['first_name'];
        }
        if ($this->employee['middle_name'] && $this->employee['middle_name'] !== 'N/A') {
            $nameParts[] = $this->employee['middle_name'];
        }
        if ($this->employee['last_name'] && $this->employee['last_name'] !== 'N/A') {
            $nameParts[] = $this->employee['last_name'];
        }
        
        return implode(' ', $nameParts);
    }
    
    public function getDayName($day_of_week) {
        // IMPORTANT: Use 0-6 format (Monday=0, Sunday=6) to match Python's datetime.weekday()
        $days = [
            0 => 'Monday',
            1 => 'Tuesday', 
            2 => 'Wednesday',
            3 => 'Thursday',
            4 => 'Friday',
            5 => 'Saturday',
            6 => 'Sunday'
        ];
        
        return $days[$day_of_week] ?? 'Unknown';
    }
    
    public function formatTime($time) {
        if (!$time || $time === 'N/A') return $time;
        
        try {
            $datetime = DateTime::createFromFormat('H:i:s', $time);
            if (!$datetime) {
                $datetime = DateTime::createFromFormat('H:i', $time);
            }
            
            if ($datetime) {
                return $datetime->format('g:i A');
            }
        } catch (Exception $e) {
            // Return original if formatting fails
        }
        
        return $time;
    }
    
    public function formatDateTime($datetimeStr) {
        if (!$datetimeStr || $datetimeStr === 'N/A') {
            return $datetimeStr;
        }
        try {
            $date = new DateTime($datetimeStr);
            return $date->format('M d, Y, g:i A');
        } catch (Exception $e) {
            // Return original string if formatting fails
            return $datetimeStr;
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

// Get employee ID from URL parameter
$employee_id = $_GET['id'] ?? '';

if (empty($employee_id)) {
    header('Location: showRecord.php?error=no_id');
    exit;
}

// Initialize the viewer
$viewer = new EmployeeDetailViewer($conn);
$loadSuccess = $viewer->loadEmployeeDetails($employee_id);
$employee = $viewer->getEmployee();
$schedules = $viewer->getSchedules();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Information - Attendance System [v<?php echo time(); ?>]</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Prevent caching -->
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">

  <!-- Bootstrap CSS (Local - Works Offline) -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons (Local - Works Offline) -->
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  
  <!-- FullCalendar CSS (included in JS bundle) -->
  <!-- Note: FullCalendar's index.global.min.js includes all CSS -->

  <!-- Chart.js for Performance Metrics (Local - Works Offline) -->
  <script src="../assets/vendor/chartjs/chart.umd.min.js"></script>

  <!-- Custom CSS with cache busting -->
  <link rel="stylesheet" href="staff.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .form-actions {
            margin-top: 30px;
            text-align: right;
        }
        .form-actions button {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-save {
            background-color: #2196F3;
            color: white;
            transition: background 0.3s;
        }
        .btn-save:hover {
            background-color: #1976D2;
        }
        .btn-cancel {
            background-color: #f44336;
            color: white;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
            padding: 12px 25px;
            border-radius: 5px;
        }
        .error-box {
            color: #d32f2f;
            border: 2px solid #d32f2f;
            background: #ffebee;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }

    .edit-schedule-btn {
        background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(255, 152, 0, 0.3);
        width: 100%;
    }

    .edit-schedule-btn:hover:not(:disabled) {
        background: linear-gradient(135deg, #F57C00 0%, #E65100 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(255, 152, 0, 0.4);
    }

    .add-schedule-btn:hover {
        background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
        transform: translateY(-2px);
    }

    /* Performance Metrics Styling */
    .metric-box {
        background: #f8f9fa;
        border-radius: 8px;
    }
        transition: all 0.3s ease;
        min-height: 200px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .metric-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .metric-box canvas {
        max-width: 120px;
        max-height: 120px;
    }

    .metrics-card .card-body {
        min-height: 250px;
    }
    
    /* Force DTR badge and icon colors - Override all other styles */
    .dtr-item span[style*="background-color"] {
        background-color: inherit !important;
        color: inherit !important;
    }
    
    .dtr-item div[style*="background-color"] {
        background-color: inherit !important;
    }
    
    .dtr-item i[style*="color"] {
        color: inherit !important;
    }
    </style>
</head>

<body>
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
      <img src="<?php echo !empty($currentUser['profile_photo']) ? '../' . htmlspecialchars($currentUser['profile_photo'], ENT_QUOTES, 'UTF-8') : '../assets/profile_pic/user.png'; ?>" 
           alt="Profile" 
           class="rounded-circle mb-2" 
           width="70" 
           height="70"
           onerror="this.src='../assets/profile_pic/user.png';">
      <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h5>
      <small class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></small>
    </div>
    <nav class="nav flex-column px-2">
      <a class="nav-link active" href="../dashboard/dashboard.php"><i class="bi bi-house-door me-2"></i> Dashboard</a>
      <a class="nav-link" href="../attendancerep/attendancerep.php"><i class="bi bi-file-earmark-bar-graph me-2"></i> Attendance Reports</a>
      <a class="nav-link" href="../staffmanagement/staff.php"><i class="bi bi-people me-2"></i> Staff Management</a>
      <a class="nav-link" href="../settings/settings.php"><i class="bi bi-gear me-2"></i> Settings</a>
      <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
    </nav>
  </div>

  <div class="content pt-3" id="content">
  <div class="container-fluid"></div>
<!----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<!-- PAGE CONTENT -->
<div class="container-fluid p-4">
  <div class="row g-3">

    <!-- LEFT: main card + metrics -->
    <div class="col-xl-8">

      <!-- Staff card -->
     <div class="card shadow-sm mb-4 staff-card">
      
    <div class="header-bar d-flex align-items-center px-3 py-2">
      <i class="bi bi-arrow-left-circle fs-4 me-2" id="backBtn"></i>
      <h5 class="mb-0 fw-bold">Staff Information</h5>
    </div>

    <script>
      document.getElementById('backBtn').addEventListener('click', function () {
        window.history.back(); // or use window.location.href = 'your-target-page.php';
      });
    </script>

 <div class="card-body d-flex flex-column flex-lg-row align-items-center justify-content-center gap-4">
        <div class="profile-wrapper">
          <?php 
            $profilePhoto = '../assets/profile_pic/user.png'; // Default
            if (!empty($employee['profile_photo']) && $employee['profile_photo'] !== 'N/A') {
                // Check if path already contains 'assets/'
                if (strpos($employee['profile_photo'], 'assets/') === 0) {
                    $profilePhoto = '../' . htmlspecialchars($employee['profile_photo']);
                } else {
                    $profilePhoto = '../assets/profile_pic/' . htmlspecialchars($employee['profile_photo']);
                }
            }
          ?>
          <img src="<?php echo '../' . $employee['profile_photo']; ?>" class="profile-img" alt="Profile Picture" onerror="this.src='../assets/profile_pic/user.png'">
        </div>

          <div class="text-center text-lg-start ms-lg-5">
          <h3 class="fw-bold mb-1"><?php echo $viewer->getFullName(); ?></h3>
          <p class="text-muted mb-1"><?php echo htmlspecialchars($employee['employee_id']); ?></p>
          <p class="mb-1"><?php echo htmlspecialchars($employee['roles']); ?> | <?php echo htmlspecialchars($employee['position']); ?></p>
          <p class="mb-1">Email: <?php echo htmlspecialchars($employee['email']); ?></p>
          <p class="mb-3">Contact: <?php echo htmlspecialchars($employee['phone']); ?></p>

          <div class="d-flex justify-content-center justify-content-lg-start gap-2">
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editInfoModal">Edit Info</button>
            <button class="btn btn-danger btn-sm" id="btnRemove" data-bs-toggle="modal" data-bs-target="#removeEmployeeModal">Remove Employee</button>
          </div>
        </div>
      </div>
    </div>
  </div>

    <!-- EDIT INFO MODAL -->
<div class="modal fade" id="editInfoModal" tabindex="-1" aria-labelledby="editInfoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content p-4">
      <div class="modal-header border-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
            <div class="container">
        <h1>Edit Employee Details</h1>

        <?php if ($editor->hasErrors()): ?>
            <div class="error-box">
                <strong>Errors:</strong>
                <ul>
                    <?php foreach ($editor->getErrors() as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($loadSuccess && $employee): ?>
            <form action="processes/update_employee.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                
                <div class="form-group">
                    <label>Profile Picture</label>
                    <img id="profile-preview" 
                         src="<?php echo $employee['profile_photo'] !== 'N/A' ? htmlspecialchars($employee['profile_photo']) : 'profile_pic/user.png'; ?>" 
                         alt="Profile Preview" 
                         style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; display: block; margin-bottom: 10px;"
                         onerror="this.src='profile_pic/user.png'">
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
                    <small>Select a new image to update the profile picture. Leave blank to keep the current one.</small>
                </div>

                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($employee['middle_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($employee['phone']); ?>">
                </div>

                <div class="form-group">
                    <label for="roles">Role</label>
                    <input type="text" id="roles" name="roles" value="<?php echo htmlspecialchars($employee['roles']); ?>">
                </div>

                <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>">
                </div>

                <div class="form-group">
                    <label for="position">Position</label>
                    <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($employee['position']); ?>">
                </div>

                <div class="form-group" hidden  >
                    <label for="hire_date">Hire Date</label>
                    <input type="date" id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($employee['hire_date']); ?>">
                </div>

                <div class="form-group" hidden>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Active" <?php echo ($employee['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($employee['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

             <div class="form-actions">
                    <a href="staffinfo.php?id=<?php echo htmlspecialchars($employee['employee_id']); ?>" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-save">Save Changes</button>
            </div>
            </form>
        <?php else: ?>
            <p>Could not load employee data. <a href="showRecord.php">Back to records</a>.</p>
        <?php endif; ?>
    </div>
      </div>
    </div>
  </div>
</div>

   <!-- Save Success Modal (shown after editing and saving employee info) -->
   <div class="modal fade" id="saveSuccessModal" tabindex="-1" aria-hidden="true">
     <div class="modal-dialog modal-dialog-centered">
       <div class="modal-content p-4 text-center">
         <h5 class="fw-bold mb-3 text-success">Save Successful</h5>
         <p>Your changes have been saved successfully.</p>
       </div>
     </div>
   </div>

   <!-- 🔹 Confirm Removal Modal -->
<div class="modal fade" id="removeEmployeeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4">
      <h5 class="fw-bold mb-3 text-danger text-center">Confirm Employee Removal</h5>
      <p class="text-center">This will move the employee to the archive. Enter your admin password to confirm.</p>
      <form id="removeEmployeeForm">
        <div class="mb-3">
          <label for="adminPasswordInput" class="form-label">Admin Password <span class="text-danger">*</span></label>
          <input type="password" class="form-control" id="adminPasswordInput" name="admin_password" required placeholder="Enter your password">
          <div id="passwordError" class="text-danger small mt-1" style="display: none;"></div>
        </div>
        <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" id="confirmRemoveBtn">
            <span id="removeBtnText">Remove Employee</span>
            <span id="removeBtnSpinner" class="spinner-border spinner-border-sm ms-2" style="display: none;" role="status" aria-hidden="true"></span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 🔹 Success Remove Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <i class="bi bi-check-circle-fill text-success fs-1 mb-3"></i>
      <h5 class="fw-bold mb-3 text-success">Employee Archived Successfully</h5>
      <p>The employee has been moved to the archive and is no longer active.</p>
      <p class="small text-muted">Redirecting to staff page...</p>
    </div>
  </div>
</div>

<!-- 🔹 Error Remove Modal -->
<div class="modal fade" id="errorRemoveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <i class="bi bi-exclamation-circle-fill text-danger fs-1 mb-3"></i>
      <h5 class="fw-bold mb-3 text-danger">Removal Failed</h5>
      <p id="errorRemoveMessage">An error occurred while archiving the employee.</p>
      <button type="button" class="btn btn-primary mt-3" data-bs-dismiss="modal">Close</button>
    </div>
  </div>
        </div>

        <!-- Attendance Success Modal -->
        <div class="modal fade" id="attendanceSuccessModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
              <h5 class="fw-bold mb-3 text-success">Attendance Saved</h5>
              <p id="attendanceSuccessMessage">Records saved successfully.</p>
            </div>
          </div>
        </div>

        <!-- Attendance Error Modal -->
        <div class="modal fade" id="attendanceErrorModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
              <h5 class="fw-bold mb-3 text-danger">Save Failed</h5>
              <p id="attendanceErrorMessage">An error occurred while saving records.</p>
            </div>
          </div>
        </div>



<script>
  document.addEventListener("DOMContentLoaded", () => {
    const removeBtn = document.getElementById("btnRemove");
    const removeEmployeeModal = new bootstrap.Modal(document.getElementById("removeEmployeeModal"));
    const successModal = new bootstrap.Modal(document.getElementById("successModal"));
    const errorRemoveModal = new bootstrap.Modal(document.getElementById("errorRemoveModal"));
    const removeForm = document.getElementById("removeEmployeeForm");
    const passwordInput = document.getElementById("adminPasswordInput");
    const passwordError = document.getElementById("passwordError");
    const removeBtnText = document.getElementById("removeBtnText");
    const removeBtnSpinner = document.getElementById("removeBtnSpinner");
    const confirmRemoveBtn = document.getElementById("confirmRemoveBtn");

    // Step 1: When Remove Employee button is clicked, show the remove employee modal
    removeBtn.addEventListener("click", (e) => {
      e.preventDefault();
      // Reset form
      removeForm.reset();
      passwordError.style.display = 'none';
      removeEmployeeModal.show();
    });

    // Step 2: Handle form submission with password verification
    removeForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      
      const adminPassword = passwordInput.value.trim();
      
      if (!adminPassword) {
        passwordError.textContent = 'Password is required';
        passwordError.style.display = 'block';
        return;
      }
      
      // Disable button and show spinner
      confirmRemoveBtn.disabled = true;
      removeBtnText.textContent = 'Processing...';
      removeBtnSpinner.style.display = 'inline-block';
      passwordError.style.display = 'none';
      
      try {
        const formData = new FormData();
        formData.append('employee_id', '<?= $employee_id ?>');
        formData.append('admin_password', adminPassword);
        formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?? "" ?>');
        
        const response = await fetch('processes/remove_employee.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          // Hide removal modal and show success modal
          removeEmployeeModal.hide();
          setTimeout(() => {
            successModal.show();
            
            // Auto-redirect after 3 seconds
            setTimeout(() => {
              window.location.href = 'staff.php';
            }, 3000);
          }, 400);
        } else {
          // Show error in the password field or modal
          if (result.message.toLowerCase().includes('password')) {
            passwordError.textContent = result.message;
            passwordError.style.display = 'block';
            passwordInput.classList.add('is-invalid');
          } else {
            // Show error modal for other errors
            removeEmployeeModal.hide();
            setTimeout(() => {
              document.getElementById('errorRemoveMessage').textContent = result.message;
              errorRemoveModal.show();
            }, 400);
          }
        }
      } catch (error) {
        console.error('Error removing employee:', error);
        removeEmployeeModal.hide();
        setTimeout(() => {
          document.getElementById('errorRemoveMessage').textContent = 'Failed to connect to server. Please try again.';
          errorRemoveModal.show();
        }, 400);
      } finally {
        // Re-enable button and hide spinner
        confirmRemoveBtn.disabled = false;
        removeBtnText.textContent = 'Remove Employee';
        removeBtnSpinner.style.display = 'none';
      }
    });
    
    // Clear error when user starts typing
    passwordInput.addEventListener('input', () => {
      passwordError.style.display = 'none';
      passwordInput.classList.remove('is-invalid');
    });
  });

 document.addEventListener("DOMContentLoaded", function() {
  const modal = document.getElementById("editInfoModal");
  const form = modal.querySelector("form");
  form.addEventListener("submit", function(e) {
    e.preventDefault(); // stop default reload

    const formData = new FormData(form);

    // Disable submit buttons to prevent double submit
    const submitButtons = form.querySelectorAll("button[type=submit], input[type=submit]");
    submitButtons.forEach(b => b.disabled = true);

    fetch(form.action, {
      method: "POST",
      body: formData
    })
    .then(response => {
      if (!response.ok) throw new Error("Server error");
      return response.text(); // or .json() if your PHP returns JSON
    })
    .then(data => {
      // Show centered save-success modal and auto-close after short delay
      const saveModalEl = document.getElementById('saveSuccessModal');
      const saveModal = new bootstrap.Modal(saveModalEl, { backdrop: true });

      const employeeId = form.querySelector("[name='employee_id']") ? form.querySelector("[name='employee_id']").value : '';

      saveModal.show();

      // Auto-close after 5 seconds, then redirect
      setTimeout(() => {
        try {
          saveModal.hide();
        } catch (e) {}
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        if (employeeId) {
          window.location.href = "staffinfo.php?id=" + encodeURIComponent(employeeId);
        } else {
          window.location.reload();
        }
      }, 5000);
    })
    .catch(error => {
      console.error("Save failed:", error);
      alert("Something went wrong while saving. Please try again.");
      // Re-enable submit buttons on failure
      submitButtons.forEach(b => b.disabled = false);
    });
  });
});
</script>

   <!-- RIGHT COLUMN (Calendar, Add Manual, Export DTR) -->
    <div class="col-xl-4">
      <!--add leave-->

         <div class="container py-4">
          <div class="row justify-content-end">
            <div class="col-xl-4 col-lg-5 col-md-6 px-2 scheduled-leave-card">
              <div class="card shadow-sm border-0">

                <div class="card-body py-3 px-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="card-title mb-0">Scheduled Leave</h6>
                    <button class="btn btn-success btn-sml" data-bs-toggle="modal" data-bs-target="#addLeaveModal">ADD</button>
                  </div>

                  <!-- Leave Entries -->
                  <div id="leaveList"  class="leave-list-container mt-3">
                    <!-- Entries will be added here -->
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- MODAL 1: Add Scheduled Leave -->
        <div class="modal fade" id="addLeaveModal" tabindex="-1" aria-labelledby="addLeaveLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Add Scheduled Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <label class="form-label">Type:</label>
                <select class="form-control mb-3" id="leaveType">
                  <option value="">Select leave type...</option>
                  <option value="Sick">Sick Leave</option>
                  <option value="Vacation">Vacation Leave</option>
                  <option value="Maternity">Maternity Leave</option>
                  <option value="Paternity">Paternity Leave</option>
                  <option value="Emergency">Emergency Leave</option>
                  <option value="Other">Other</option>
                </select>

                <label class="form-label">FROM:</label>
                <input type="date" class="form-control mb-3" id="leaveFrom">

                <label class="form-label">TO:</label>
                <input type="date" class="form-control mb-3" id="leaveTo">
                
                <label class="form-label">Reason:</label>
                <textarea class="form-control mb-3" id="leaveReason" rows="3" placeholder="Briefly explain your reason for leave"></textarea>
                
                <!-- Admin-only options -->
                <div class="form-check mb-2" id="adminOptionsDiv" style="display: none;">
                  <input class="form-check-input" type="checkbox" id="autoApprove">
                  <label class="form-check-label" for="autoApprove">
                    <strong>Auto-approve this request</strong> (Admin only)
                  </label>
                  <small class="d-block text-muted">If unchecked, request will be pending for approval</small>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" onclick="redirectToStaffInfo()">Cancel</button>
                <button class="btn btn-success" onclick="confirmLeave()">Submit Request</button>
              </div>
            </div>
          </div>
        </div>

          <!-- MODAL 1.5: Confirm Leave Details -->
          <div class="modal fade" id="leaveDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content text-center p-4">
                <div class="modal-body">
                  <h5 class="mb-3">Schedule a Leave for this Person?</h5>
                  <p id="leaveDetailsText" class="mb-4"></p>
                  <div class="d-flex justify-content-center gap-3">
                    <button class="btn btn-outline-dark" onclick="goBackToForm()">Change</button>
                    <button class="btn btn-success" onclick="finalizeLeave()">Confirm</button>
                  </div>
                </div>
              </div>
            </div>
          </div>


          <!-- MODAL 2: Confirmation -->
        <div class="modal fade" id="confirmModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
              <div class="modal-body">
                <div class="mb-3">
                  <div class="bg-success rounded-circle d-inline-block p-3">
                    <i class="bi bi-check-lg text-white fs-3"></i>
                  </div>
                </div>
                <h5 class="mb-2">Schedule Set!</h5>
              </div>
            </div>
          </div>
        </div>

        <!-- Leave Validation Error Modal -->
        <div class="modal fade" id="leaveValidationErrorModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
              <div class="mb-3">
                <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
              </div>
              <h5 class="fw-bold mb-3 text-danger">Validation Error</h5>
              <p id="leaveValidationErrorMsg"></p>
            </div>
          </div>
        </div>

        <!-- Leave Success Modal -->
        <div class="modal fade" id="leaveSuccessModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
              <div class="mb-3">
                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
              </div>
              <h5 class="fw-bold mb-3 text-success">Success</h5>
              <p id="leaveSuccessMsg"></p>
            </div>
          </div>
        </div>

        <!-- Leave Delete Confirmation Modal -->
        <div class="modal fade" id="leaveDeleteConfirmModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
              <h5 class="fw-bold mb-3 text-danger">Confirm Delete</h5>
              <p id="leaveDeleteConfirmMsg"></p>
              <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                <button type="button" class="btn btn-danger" id="leaveDeleteConfirmBtn">Yes, Delete</button>
              </div>
            </div>
          </div>
        </div>
        <script>
          // Leave Management System with API
          const employeeIdForLeave = <?php echo json_encode($employee['id']); ?>;
          const isAdmin = true; // Set based on actual session role
          
          // Show/hide admin options on page load
          document.addEventListener('DOMContentLoaded', function() {
            if (isAdmin) {
              document.getElementById('adminOptionsDiv').style.display = 'block';
            }
          });

          function confirmLeave() {
            const leaveType = document.getElementById("leaveType").value;
            const leaveFrom = document.getElementById("leaveFrom").value;
            const leaveTo = document.getElementById("leaveTo").value;
            const leaveReason = document.getElementById("leaveReason").value;

            if (!leaveType || !leaveFrom || !leaveTo) {
              document.getElementById("leaveValidationErrorMsg").textContent = "Please fill out all required fields.";
              
              // Get current backdrop count before showing error modal
              const existingBackdrops = document.querySelectorAll('.modal-backdrop').length;
              
              // Show error modal
              const errorModal = new bootstrap.Modal(document.getElementById("leaveValidationErrorModal"));
              errorModal.show();
              
              // Auto-close after 5 seconds
              setTimeout(() => {
                // Hide the error modal
                errorModal.hide();
                
                // Give Bootstrap time to remove backdrop, then manually clean up
                setTimeout(() => {
                  // Remove all modal backdrops
                  document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                  // Reset body styles
                  document.body.classList.remove('modal-open');
                  document.body.style.overflow = '';
                  
                  // Re-open the Add Leave modal so user can fix the form
                  const addModalEl = document.getElementById('addLeaveModal');
                  const addModal = new bootstrap.Modal(addModalEl, { backdrop: 'static', keyboard: false });
                  addModal.show();
                }, 150);
              }, 5000);
              return;
            }

            // Close Add Leave modal
            const addModalEl = document.getElementById('addLeaveModal');
            const addModal = bootstrap.Modal.getInstance(addModalEl);
            if (addModal) addModal.hide();

            // Remove any lingering backdrops
            setTimeout(() => {
              document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
              document.body.classList.remove('modal-open');
              document.body.style.overflow = '';
            }, 100);

            // Show confirmation modal with leave details
            const detailsText = `${leaveType} Leave, from ${formatDate(leaveFrom)} to ${formatDate(leaveTo)}`;
            document.getElementById("leaveDetailsText").innerText = detailsText;

            setTimeout(() => {
              const detailsModal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
              detailsModal.show();
            }, 150);
          }

          function finalizeLeave() {
            const leaveType = document.getElementById("leaveType").value;
            const leaveFrom = document.getElementById("leaveFrom").value;
            const leaveTo = document.getElementById("leaveTo").value;
            const leaveReason = document.getElementById("leaveReason").value;
            const autoApprove = isAdmin && document.getElementById("autoApprove").checked;

            // Hide the confirmation modal
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsModal'));
            if (detailsModal) detailsModal.hide();

            // Clean up backdrops before sending request
            setTimeout(() => {
              document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
              document.body.classList.remove('modal-open');
              document.body.style.overflow = '';
            }, 100);

            // Submit leave request to API
            const formData = new FormData();
            formData.append('action', 'submit_request');
            formData.append('employee_id', employeeIdForLeave);
            formData.append('leave_type', leaveType);
            formData.append('start_date', leaveFrom);
            formData.append('end_date', leaveTo);
            formData.append('reason', leaveReason);
            formData.append('is_admin', isAdmin ? '1' : '0');
            formData.append('auto_approve', autoApprove ? '1' : '0');

            setTimeout(() => {
              fetch('api/leave_request.php', {
                method: 'POST',
                body: formData
              })
              .then(res => res.json())
              .then(response => {
                if (response.success) {
                  // Show success modal
                  document.getElementById("leaveSuccessMsg").textContent = response.message || "Leave request submitted successfully!";
                  const successModal = new bootstrap.Modal(document.getElementById('leaveSuccessModal'));
                  successModal.show();

                  // Reload leave list
                  loadEmployeeLeaves();

                  // Auto-close after 5 seconds
                  setTimeout(() => {
                    successModal.hide();
                    
                    // Clean up after success modal closes
                    setTimeout(() => {
                      document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                      document.body.classList.remove('modal-open');
                      document.body.style.overflow = '';
                    }, 150);
                  }, 5000);
                } else {
                  document.getElementById("leaveValidationErrorMsg").textContent = 'Error: ' + response.error;
                  const errorModal = new bootstrap.Modal(document.getElementById("leaveValidationErrorModal"));
                  errorModal.show();
                  
                  // Auto-close after 5 seconds
                  setTimeout(() => {
                    errorModal.hide();
                    
                    // Clean up after error modal closes
                    setTimeout(() => {
                      document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                      document.body.classList.remove('modal-open');
                      document.body.style.overflow = '';
                    }, 150);
                  }, 5000);
                }
              })
              .catch(error => {
                console.error('Error submitting leave request:', error);
                document.getElementById("leaveValidationErrorMsg").textContent = 'Failed to submit leave request. Please try again.';
                const errorModal = new bootstrap.Modal(document.getElementById("leaveValidationErrorModal"));
                errorModal.show();
                
                // Auto-close after 5 seconds
                setTimeout(() => {
                  errorModal.hide();
                  
                  // Clean up after error modal closes
                  setTimeout(() => {
                    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                  }, 150);
                }, 5000);
              });
            }, 150);
          }

          function goBackToForm() {
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsModal'));
            if (detailsModal) detailsModal.hide();

            // Clean up backdrops
            setTimeout(() => {
              document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
              document.body.classList.remove('modal-open');
              document.body.style.overflow = '';
            }, 100);

            // Re-open add leave modal
            setTimeout(() => {
              const addModal = new bootstrap.Modal(document.getElementById('addLeaveModal'));
              addModal.show();
            }, 150);
          }

          function redirectToStaffInfo() {
            window.location.href = "staffinfo.php";
          }

          function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric'
            });
          }

          function loadEmployeeLeaves() {
            fetch(`api/leave_request.php?action=get_employee_requests&employee_id=${employeeIdForLeave}`)
              .then(res => res.json())
              .then(response => {
                if (response.success) {
                  const leaveList = document.getElementById("leaveList");
                  leaveList.innerHTML = '';

                  if (response.count === 0) {
                    leaveList.innerHTML = '<p class="text-muted small text-center">No scheduled leaves</p>';
                    return;
                  }

                  response.data.forEach(leave => {
                    const entry = document.createElement("div");
                    entry.className = "leave-entry d-flex justify-content-between align-items-start";
                    
                    let statusBadge = '';
                    let actionButton = '';
                    
                    if (leave.status === 'pending') {
                      statusBadge = '<span class="badge bg-warning text-dark ms-2">Pending</span>';
                      actionButton = `<button class="btn btn-sm btn-outline-danger" onclick="cancelLeave(${leave.id}, 'pending')" title="Cancel Request"><i class="bi bi-x-circle"></i></button>`;
                    } else if (leave.status === 'approved') {
                      statusBadge = '<span class="badge bg-success ms-2">Approved</span>';
                      actionButton = `<button class="btn btn-sm btn-outline-danger" onclick="cancelLeave(${leave.id}, 'approved')" title="Cancel Leave"><i class="bi bi-trash"></i></button>`;
                    } else if (leave.status === 'rejected') {
                      statusBadge = '<span class="badge bg-danger ms-2">Rejected</span>';
                      actionButton = `<button class="btn btn-sm btn-outline-secondary" onclick="cancelLeave(${leave.id}, 'rejected')" title="Delete"><i class="bi bi-trash"></i></button>`;
                    }

                    entry.innerHTML = `
                      <div>
                        <strong>${leave.leave_type}</strong> ${statusBadge}<br>
                        <small>${leave.formatted_dates}</small>
                      </div>
                      <div>${actionButton}</div>
                    `;

                    leaveList.appendChild(entry);
                  });
                }
              })
              .catch(error => {
                console.error('Error loading leaves:', error);
              });
          }

          let pendingLeaveDelete = { leaveId: null, status: null };

          async function cancelLeave(leaveId, status) {
            let confirmMessage = '';
            
            if (status === 'pending') {
              confirmMessage = 'Are you sure you want to cancel this pending leave request?';
            } else if (status === 'approved') {
              confirmMessage = 'This leave has been approved. Cancelling will remove it from the attendance records. Continue?';
            } else {
              confirmMessage = 'Are you sure you want to delete this rejected leave request?';
            }
            
            // Store the pending delete info
            pendingLeaveDelete = { leaveId, status };
            
            // Show confirmation modal
            document.getElementById("leaveDeleteConfirmMsg").textContent = confirmMessage;
            const confirmModal = new bootstrap.Modal(document.getElementById("leaveDeleteConfirmModal"));
            confirmModal.show();
          }

          // Handle the confirmed delete
          document.getElementById("leaveDeleteConfirmBtn").addEventListener("click", async function() {
            const { leaveId, status } = pendingLeaveDelete;
            
            try {
              const formData = new FormData();
              formData.append('leave_id', leaveId);
              formData.append('cancelled_by', 'admin'); // or 'employee' based on user role
              
              const response = await fetch('api/leave_request.php?action=cancel_request', {
                method: 'POST',
                body: formData
              });
              
              const result = await response.json();
              
              if (result.success) {
                // Hide the delete confirmation modal
                bootstrap.Modal.getInstance(document.getElementById("leaveDeleteConfirmModal")).hide();
                
                // Show success message
                document.getElementById("leaveSuccessMsg").textContent = result.message || "Leave deleted successfully!";
                const successModal = new bootstrap.Modal(document.getElementById('leaveSuccessModal'));
                successModal.show();
                
                // Auto-close success modal and reload list
                setTimeout(() => {
                  successModal.hide();
                  loadEmployeeLeaves();
                }, 5000);
              } else {
                bootstrap.Modal.getInstance(document.getElementById("leaveDeleteConfirmModal")).hide();
                document.getElementById("leaveValidationErrorMsg").textContent = 'Error: ' + result.error;
                const errorModal = new bootstrap.Modal(document.getElementById("leaveValidationErrorModal"));
                errorModal.show();
                // Auto-close after 5 seconds
                setTimeout(() => {
                  errorModal.hide();
                  // Clear all modal backdrops and reset body
                  document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                  document.body.classList.remove('modal-open');
                  document.body.style.overflow = '';
                }, 5000);
              }
            } catch (error) {
              console.error('Error cancelling leave:', error);
              bootstrap.Modal.getInstance(document.getElementById("leaveDeleteConfirmModal")).hide();
              document.getElementById("leaveValidationErrorMsg").textContent = 'Failed to delete leave. Please try again.';
              const errorModal = new bootstrap.Modal(document.getElementById("leaveValidationErrorModal"));
              errorModal.show();
              // Auto-close after 5 seconds
              setTimeout(() => {
                errorModal.hide();
                // Clear all modal backdrops and reset body
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
              }, 5000);
            }
          });

          // Load leaves on page load
          window.addEventListener("DOMContentLoaded", () => {
            loadEmployeeLeaves();
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('leaveFrom').setAttribute('min', today);
            document.getElementById('leaveTo').setAttribute('min', today);
          });

          function deleteLeave(button) {
            // Delete functionality can be implemented if needed
            alert('Leave cancellation requires admin approval');
          }
          </script>

      <!-- Calendar with Date Range Picker -->
      <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <button class="btn btn-sm btn-outline-secondary" id="prevMonth"><i class="bi bi-chevron-left"></i></button>
        <h5 class="mb-0" id="calendarTitle">Month Year</h5>
        <button class="btn btn-sm btn-outline-secondary" id="nextMonth"><i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="card-body">
        <div id="calendar" class="calendar-grid"></div>
      </div>
    </div>
    <script>
          // Date Range Picker Calendar for DTR Export
          document.addEventListener("DOMContentLoaded", function () {
          const calendar = document.getElementById("calendar");
          const calendarTitle = document.getElementById("calendarTitle");
          const prevBtn = document.getElementById("prevMonth");
          const nextBtn = document.getElementById("nextMonth");

          let currentDate = new Date();
          let selectedDates = [];
          let startDate = null;
          let endDate = null;
          const MAX_DAYS = 16;

          function formatDate(year, month, day) {
            return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
          }

          function parseDate(dateString) {
            const parts = dateString.split('-');
            return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
          }

          function getDaysBetween(date1, date2) {
            const oneDay = 24 * 60 * 60 * 1000;
            return Math.round(Math.abs((date1 - date2) / oneDay)) + 1;
          }

          function generateCalendar(year, month) {
            calendar.innerHTML = "";

            const monthStart = new Date(year, month, 1);
            const monthEnd = new Date(year, month + 1, 0);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const months = [
              "January", "February", "March", "April", "May", "June",
              "July", "August", "September", "October", "November", "December"
            ];

            calendarTitle.textContent = `${months[month]} ${year}`;

            const weekdays = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
            weekdays.forEach(day => {
              const weekdayDiv = document.createElement("div");
              weekdayDiv.textContent = day;
              weekdayDiv.classList.add("calendar-weekday");
              calendar.appendChild(weekdayDiv);
            });

            let startDay = (monthStart.getDay() + 6) % 7;
            for (let i = 0; i < startDay; i++) {
              const emptyCell = document.createElement("div");
              calendar.appendChild(emptyCell);
            }

            for (let day = 1; day <= monthEnd.getDate(); day++) {
              const dateDiv = document.createElement("div");
              dateDiv.textContent = day;
              dateDiv.classList.add("calendar-day");
              
              const currentDateStr = formatDate(year, month, day);
              const dateObj = new Date(year, month, day);
              dateObj.setHours(0, 0, 0, 0);

              // Mark today
              if (dateObj.getTime() === today.getTime()) {
                dateDiv.classList.add("today");
              }

              // Mark selected dates
              if (selectedDates.includes(currentDateStr)) {
                dateDiv.classList.add("selected-date");
                if (selectedDates.length > 1) {
                  if (currentDateStr === selectedDates[0]) {
                    dateDiv.classList.add("range-start");
                  } else if (currentDateStr === selectedDates[selectedDates.length - 1]) {
                    dateDiv.classList.add("range-end");
                  } else {
                    dateDiv.classList.add("in-range");
                  }
                }
              }

              // Disable future dates
              if (dateObj > today) {
                dateDiv.classList.add("disabled-date");
              } else {
                dateDiv.addEventListener("click", () => handleDateClick(year, month, day));
              }

              calendar.appendChild(dateDiv);
            }
          }

          function handleDateClick(year, month, day) {
            const clickedDate = formatDate(year, month, day);
            const clickedDateObj = parseDate(clickedDate);

            if (selectedDates.length === 0) {
              // First date selection
              selectedDates = [clickedDate];
              startDate = clickedDateObj;
              endDate = null;
            } else if (selectedDates.length === 1) {
              // Second date selection - create range
              const firstDate = parseDate(selectedDates[0]);
              
              if (clickedDateObj < firstDate) {
                startDate = clickedDateObj;
                endDate = firstDate;
              } else {
                startDate = firstDate;
                endDate = clickedDateObj;
              }

              const daysDiff = getDaysBetween(startDate, endDate);
              
              if (daysDiff > MAX_DAYS) {
                alert(`You can only select up to ${MAX_DAYS} days. Please select a shorter range.`);
                selectedDates = [];
                startDate = null;
                endDate = null;
              } else {
                // Fill in all dates between start and end
                selectedDates = [];
                let currentDate = new Date(startDate);
                while (currentDate <= endDate) {
                  const dateStr = formatDate(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());
                  selectedDates.push(dateStr);
                  currentDate.setDate(currentDate.getDate() + 1);
                }
                selectedDates.sort();
              }
            } else {
              // Reset and start new selection
              selectedDates = [clickedDate];
              startDate = clickedDateObj;
              endDate = null;
            }

            generateCalendar(year, month);
          }

          prevBtn.addEventListener("click", () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
          });

          nextBtn.addEventListener("click", () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
          });

          // Make selected dates available globally for export
          window.getSelectedDateRange = function() {
            return {
              dates: selectedDates,
              startDate: startDate,
              endDate: endDate,
              count: selectedDates.length
            };
          };

          // Initial load
          generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
        });

    </script> 
      <!-- Add Manual Attendance -->
      <div class="d-grid mb-4" style="margin-top: 10px !important;">
        <button class="btn btn-success btn-sm" id="openAttendance">Add Manual Attendance</button>
      </div>

         <!-- 🔹 Manual Attendance Modal -->
        <div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content p-3">
              <div class="modal-header">
                <h5 class="modal-title">Manual Attendance Record</h5>
              </div>
              <div class="modal-body">
                <div id="attendanceContainer">
                  <div class="attendance-row row mb-3 align-items-start">
                    <div class="col-md-3">
                      <label>Date:</label>
                      <input type="date" class="form-control">
                      <div class="schedule-error-container" style="min-height: 0;">
                        <small class="text-danger schedule-error d-block" style="display:none; font-size: 0.75rem; margin-top: 4px; line-height: 1.2;"></small>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <label>Time In:</label>
                      <input type="time" class="form-control">
                    </div>
                    <div class="col-md-3">
                      <label>Time Out:</label>
                      <input type="time" class="form-control">
                    </div>
                    <div class="col-md-3">
                      <button class="btn btn-danger removeRow" style="display:none; margin-top: 32px;">−</button>
                    </div>
                  </div>
                </div>
                <button id="addDayBtn" class="btn btn-warning mt-2">+ Add Another Day</button>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="saveBtn">Save Records</button>
              </div>
            </div>
          </div>
        </div>

<script>
  // Wait for DOM to be fully loaded
  document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap modal setup
    const attendanceModalEl = document.getElementById('attendanceModal');
    const openAttendanceBtn = document.getElementById('openAttendance');
    const addDayBtn = document.getElementById('addDayBtn');
    const attendanceContainer = document.getElementById('attendanceContainer');
    const saveBtn = document.getElementById('saveBtn');

    // Check if all elements exist
    if (!attendanceModalEl || !openAttendanceBtn || !addDayBtn || !attendanceContainer || !saveBtn) {
      console.error('Manual attendance elements not found!');
      return;
    }

    const attendanceModal = new bootstrap.Modal(attendanceModalEl);

    // 🔹 Function to fetch and populate schedule times for a date
    async function populateScheduleTimes(dateInput, timeInInput, timeOutInput) {
      const selectedDate = dateInput.value;
      
      // Find the error message element for this row
      const row = dateInput.closest('.attendance-row');
      const errorMsg = row ? row.querySelector('.schedule-error') : null;
      
      if (!selectedDate) {
        if (errorMsg) {
          errorMsg.style.display = 'none';
          errorMsg.textContent = '';
        }
        return;
      }

      try {
        // First, check if attendance record already exists for this date
        const existsResponse = await fetch(`api/check_attendance_exists.php?employee_id=<?php echo $employee['id']; ?>&date=${selectedDate}`);
        const existsResult = await existsResponse.json();
        
        if (existsResult.success && existsResult.exists) {
          // Clear time inputs
          timeInInput.value = '';
          timeOutInput.value = '';
          timeInInput.classList.remove('bg-light');
          timeOutInput.classList.remove('bg-light');
          
          // Show error message about existing record
          if (errorMsg) {
            const dateObj = new Date(selectedDate);
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
              weekday: 'long', 
              year: 'numeric', 
              month: 'long', 
              day: 'numeric' 
            });
            
            errorMsg.textContent = `Attendance record already exists for ${formattedDate}`;
            errorMsg.style.display = 'block';
          }
          return; // Stop here, don't fetch schedule
        }
        
        // If no existing record, proceed to fetch schedule
        const response = await fetch(`api/get_employee_schedule.php?employee_id=<?php echo $employee['id']; ?>&date=${selectedDate}`);
        const result = await response.json();

        if (result.success && result.has_schedule) {
          // Pre-populate the time inputs with the schedule times
          timeInInput.value = result.schedule.start_time;
          timeOutInput.value = result.schedule.end_time;
          
          // Add a visual indicator that times are from schedule
          timeInInput.classList.add('bg-light');
          timeOutInput.classList.add('bg-light');
          
          // Hide error message if schedule is found
          if (errorMsg) {
            errorMsg.style.display = 'none';
            errorMsg.textContent = '';
          }
        } else {
          // Clear times if no schedule found
          timeInInput.value = '';
          timeOutInput.value = '';
          timeInInput.classList.remove('bg-light');
          timeOutInput.classList.remove('bg-light');
          
          if (selectedDate && errorMsg) {
            // Show inline error message below date picker
            const dateObj = new Date(selectedDate);
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
              weekday: 'long', 
              year: 'numeric', 
              month: 'long', 
              day: 'numeric' 
            });
            
            errorMsg.textContent = `No schedule assigned for ${formattedDate}`;
            errorMsg.style.display = 'block';
          }
        }
      } catch (error) {
        console.error('Error fetching schedule:', error);
        if (errorMsg) {
          errorMsg.textContent = 'Error checking schedule';
          errorMsg.style.display = 'block';
        }
      }
    }



    // 🔹 Function to attach date change listener to a row
    function attachDateListener(row) {
      const dateInput = row.querySelector('input[type="date"]');
      const timeInputs = row.querySelectorAll('input[type="time"]');
      const timeInInput = timeInputs[0];
      const timeOutInput = timeInputs[1];
      
      if (dateInput && timeInInput && timeOutInput) {
        // Remove any existing listeners by cloning the element
        const newDateInput = dateInput.cloneNode(true);
        dateInput.parentNode.replaceChild(newDateInput, dateInput);
        
        // Add the change event listener
        newDateInput.addEventListener('change', () => {
          populateScheduleTimes(newDateInput, timeInInput, timeOutInput);
        });
        
        // Also add input event for better responsiveness
        newDateInput.addEventListener('input', () => {
          populateScheduleTimes(newDateInput, timeInInput, timeOutInput);
        });
      }
    }

    // 🔹 Step 1: Open Manual Attendance and setup listeners
    openAttendanceBtn.addEventListener('click', () => {
      attendanceModal.show();
      
      // Setup listener for initial row when modal opens
      const initialRow = attendanceContainer.querySelector('.attendance-row');
      if (initialRow) {
        attachDateListener(initialRow);
      }
    });

    // 🔹 Step 2: Add another day
    addDayBtn.addEventListener('click', () => {
      const newRow = document.createElement('div');
      newRow.classList.add('attendance-row', 'row', 'mb-3', 'align-items-start');
      newRow.innerHTML = `
        <div class="col-md-3">
          <label>Date:</label>
          <input type="date" class="form-control">
          <div class="schedule-error-container" style="min-height: 0;">
            <small class="text-danger schedule-error d-block" style="display:none; font-size: 0.75rem; margin-top: 4px; line-height: 1.2;"></small>
          </div>
        </div>
        <div class="col-md-3">
          <label>Time In:</label>
          <input type="time" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Time Out:</label>
          <input type="time" class="form-control">
        </div>
        <div class="col-md-3">
          <button class="btn btn-danger removeRow" style="margin-top: 32px;">−</button>
        </div>`;
      attendanceContainer.appendChild(newRow);
      
      // Attach date listener to the new row
      attachDateListener(newRow);
    });

    // 🔹 Step 3: Remove a day row
    attendanceContainer.addEventListener('click', (e) => {
      if (e.target.classList.contains('removeRow')) {
        e.target.closest('.attendance-row').remove();
      }
    });

    // 🔹 Step 4: Save attendance records
    saveBtn.addEventListener('click', async () => {
      const rows = attendanceContainer.querySelectorAll('.attendance-row');
      const records = [];
      let hasError = false;
      let validationMessage = '';

      // Collect all records
      rows.forEach((row, index) => {
        const inputs = row.querySelectorAll('input');
        const date = inputs[0].value;  // First input is date
        const timeIn = inputs[1].value;  // Second input is time in
        const timeOut = inputs[2].value;  // Third input is time out

        if (!date || !timeIn || !timeOut) {
          if (!hasError) validationMessage = `Please fill all fields in row ${index + 1}`;
          hasError = true;
          return;
        }

        records.push({
          date: date,
          time_in: timeIn,
          time_out: timeOut
        });
      });

      if (hasError || records.length === 0) {
        // Show validation modal (auto-close)
        const errEl = document.getElementById('attendanceErrorMessage');
        if (errEl) errEl.textContent = validationMessage || 'Please fill out all records before saving.';
        const errModalEl = document.getElementById('attendanceErrorModal');
        if (errModalEl) {
          document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
          document.body.classList.remove('modal-open');
          errModalEl.style.zIndex = 20000;
          setTimeout(() => {
            const em = new bootstrap.Modal(errModalEl);
            em.show();
            document.querySelectorAll('.modal-backdrop').forEach(el => el.style.zIndex = 19999);
          }, 40);
          // Auto-hide after 5s
          setTimeout(() => {
            try { const em = bootstrap.Modal.getInstance(errModalEl); if (em) em.hide(); } catch (e) {}
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Records';
          }, 5000);
        }
        return;
      }

      // Disable save button to prevent double submission
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';

      try {
        const response = await fetch('api/add_manual_attendance.php?action=add_manual', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            employee_id: <?php echo $employee['id']; ?>,
            records: records
          })
        });

        const result = await response.json();
        console.log('Server result:', result);

        if (result.success) {
          // Show success message with warnings if any
          let message = result.message || 'Records saved successfully.';
          if (result.warnings && result.warnings.length > 0) {
            message += ' Warnings: ' + result.warnings.join('; ');
          }
          const successEl = document.getElementById('attendanceSuccessMessage');
          if (successEl) successEl.textContent = message;

          // Hide the attendance modal and remove any lingering backdrops first
          try { attendanceModal.hide(); } catch (e) {}
          document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
          document.body.classList.remove('modal-open');
          document.body.style.overflow = '';

          // Show success modal slightly after to ensure it's on top
          const successModalEl = document.getElementById('attendanceSuccessModal');
          if (successModalEl) {
            successModalEl.style.zIndex = 20000;
            setTimeout(() => {
              const sm = new bootstrap.Modal(successModalEl);
              sm.show();
              // ensure backdrop z-index is behind modal
              document.querySelectorAll('.modal-backdrop').forEach(el => el.style.zIndex = 19999);
            }, 60);
          }

          // Auto-close after short delay and reload
          setTimeout(() => {
            try { const sm = bootstrap.Modal.getInstance(document.getElementById('attendanceSuccessModal')); if (sm) sm.hide(); } catch (e) {}
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            window.location.reload();
          }, 5000);

        } else {
          // Handle backend errors - properly display the error message from backend
          let errMsg = result.error || 'Unknown error occurred';
          
          // If there are warnings, include them in the error message
          if (result.warnings && result.warnings.length > 0) {
            errMsg = result.warnings.join('\n');
          }
          
          const errEl = document.getElementById('attendanceErrorMessage');
          if (errEl) errEl.textContent = errMsg;
          const errModalEl = document.getElementById('attendanceErrorModal');
          if (errModalEl) {
            // remove existing backdrops and ensure error modal appears on top
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            errModalEl.style.zIndex = 20000;
            setTimeout(() => {
              const em = new bootstrap.Modal(errModalEl);
              em.show();
              document.querySelectorAll('.modal-backdrop').forEach(el => el.style.zIndex = 19999);
              // Auto-hide after 8 seconds to give time to read the message
              setTimeout(() => {
                try { const inst = bootstrap.Modal.getInstance(errModalEl); if (inst) inst.hide(); } catch (e) {}
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
              }, 8000);
            }, 40);
          }
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save Records';
        }
      } catch (error) {
        console.error('Error saving attendance:', error);
        const errMsg = 'Failed to save attendance records. ' + (error.message || error);
        const errEl = document.getElementById('attendanceErrorMessage');
        if (errEl) errEl.textContent = errMsg;
        const errModalEl = document.getElementById('attendanceErrorModal');
        if (errModalEl) {
          document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
          document.body.classList.remove('modal-open');
          errModalEl.style.zIndex = 20000;
          setTimeout(() => {
            const em = new bootstrap.Modal(errModalEl);
            em.show();
            document.querySelectorAll('.modal-backdrop').forEach(el => el.style.zIndex = 19999);
            // Auto-hide after 8 seconds
            setTimeout(() => {
              try { const inst = bootstrap.Modal.getInstance(errModalEl); if (inst) inst.hide(); } catch (e) {}
              document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
              document.body.classList.remove('modal-open');
            }, 8000);
          }, 40);
        }
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Records';
      }
    });

  }); // End DOMContentLoaded
</script>

      <!-- Export DTR -->
      <div class="card" style="margin-top: 0 !important;">
        <div class="card-body">
          <button class="btn btn-success w-100 mb-3" id="exportDtrBtn" onclick="exportDTR()">Export DTR</button>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <div><small class="text-muted">Daily Time Record</small></div>
            <a href="../attendancerep/indirep.php?id=<?php echo htmlspecialchars($employee['employee_id']); ?>" class="small">See more...</a>
          </div>

          <div id="dtrLoading" class="text-center py-3" style="display: block;">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <div class="small text-muted mt-2">Loading attendance records...</div>
          </div>

          <div class="dtr-list" id="dtrList" style="display: none;">
          </div>

        </div>
      </div>

      <!-- Edit Time Out Modal -->
      <div class="modal fade" id="editTimeOutModal" tabindex="-1" aria-labelledby="editTimeOutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="editTimeOutModalLabel">Add Time Out</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label fw-bold">Date:</label>
                <p id="editDate" class="mb-0"></p>
              </div>
              <div class="mb-3">
                <label class="form-label fw-bold">Time In:</label>
                <p id="editTimeIn" class="mb-0"></p>
              </div>
              <div class="mb-3">
                <label for="editTimeOut" class="form-label fw-bold">Time Out: <span class="text-danger">*</span></label>
                <input type="time" class="form-control" id="editTimeOut" required>
                <small class="text-muted">Select the time the employee left</small>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary" id="confirmEditTimeOut">Confirm</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Confirm Edit Modal -->
      <div class="modal fade" id="confirmEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header border-0">
              <h5 class="modal-title text-warning">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Time Out Edit
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p>Are you sure you want to add this time out for the employee?</p>
              <div class="bg-light p-3 rounded">
                <p class="mb-1"><strong>Date:</strong> <span id="confirmDate"></span></p>
                <p class="mb-1"><strong>Time In:</strong> <span id="confirmTimeIn"></span></p>
                <p class="mb-0"><strong>Time Out:</strong> <span id="confirmTimeOut"></span></p>
              </div>
              <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>This action will update the attendance record.
              </p>
            </div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-warning" id="finalConfirmEdit">
                <i class="bi bi-check-circle me-1"></i>Yes, Update Time Out
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Success Edit Modal -->
      <div class="modal fade" id="successEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-body text-center py-4">
              <div class="mb-3">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
              </div>
              <h5 class="mb-2">Time Out Updated Successfully</h5>
              <p class="text-muted mb-0">The attendance record has been updated.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
              <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
            </div>
          </div>
        </div>
      </div>

      <script>
        const employeeInternalId = <?php echo json_encode($employee['id']); ?>;
        const employeeCode = <?php echo json_encode($employee['employee_id']); ?>;
        let lastSelectedDatesCount = 0;
        let isInitialLoad = true;

        // Load recent DTR data (first 15 records from present to past)
        function loadRecentDTR() {
          const dtrList = document.getElementById('dtrList');
          const dtrLoading = document.getElementById('dtrLoading');
          
          dtrList.style.display = 'none';
          dtrLoading.style.display = 'block';

          const cacheBuster = Date.now(); // Force fresh data
          
          // Use limit parameter to get first 15 records from present to past
          fetch(`get_employee_attendance.php?employee_id=${employeeInternalId}&limit=15&_=${cacheBuster}`)
            .then(res => res.json())
            .then(response => {
              dtrLoading.style.display = 'none';
              dtrList.style.display = 'block';

              if (!response.success) {
                throw new Error(response.error || 'Failed to load attendance');
              }

              if (response.count === 0) {
                dtrList.innerHTML = `
                  <div class="text-center text-muted py-3">
                    <i class="bi bi-calendar-range fs-4"></i>
                    <p class="small mt-2">No recent attendance records found</p>
                    <p class="small text-muted">Select dates from calendar to view specific records</p>
                  </div>
                `;
                return;
              }

              displayDTRRecords(response.data);
            })
            .catch(error => {
              console.error('Error loading recent DTR:', error);
              dtrLoading.style.display = 'none';
              dtrList.style.display = 'block';
              dtrList.innerHTML = `
                <div class="text-center text-danger py-3">
                  <i class="bi bi-exclamation-triangle fs-4"></i>
                  <p class="small mt-2">Error loading attendance records</p>
                  <p class="small text-muted">${error.message}</p>
                </div>
              `;
            });
        }

        // Display DTR records
        function displayDTRRecords(records) {
          const dtrList = document.getElementById('dtrList');
          
          // Define exact color values for each status
          const statusColors = {
            'success': { iconBg: '#4caf50', badgeBg: '#4caf50', badgeText: '#ffffff' },      // Green for Present
            'danger': { iconBg: '#f44336', badgeBg: '#f44336', badgeText: '#ffffff' },       // Red for Absent
            'warning text-dark': { iconBg: '#ffc107', badgeBg: '#ffc107', badgeText: '#333333' }, // Yellow for Incomplete
            'warning': { iconBg: '#ffc107', badgeBg: '#ffc107', badgeText: '#333333' },      // Yellow variant
            'manual': { iconBg: '#a8d5ba', badgeBg: '#a8d5ba', badgeText: '#2d5f3f' }       // Muted green for Manual
          };
          
          console.log('=== DTR RECORDS DEBUG v' + Date.now() + ' ===');
          console.log('Total records:', records.length);
          console.log('Status colors config:', statusColors);
          
          let html = '';
          records.forEach(record => {
            const statusInfo = record.status_info;
            const timeIn = record.time_in_formatted || 'N/A';
            const timeOut = record.time_out_formatted || 'N/A';
            const hoursWorked = record.hours_worked || 'N/A';
            
            // Get colors for this status
            const colors = statusColors[statusInfo.badge_class] || { 
              iconBg: '#6c757d', 
              badgeBg: '#6c757d', 
              badgeText: '#ffffff' 
            };

            // Check if record is incomplete with time_in but no time_out
            const showEditButton = record.status === 'incomplete' && 
                                   record.time_in && 
                                   !record.time_out;

            // Debug logging
            console.log(`\nDate: ${record.formatted_date}`);
            console.log(`  Status: ${record.status}`);
            console.log(`  Badge Class: "${statusInfo.badge_class}"`);
            console.log(`  Badge Text: "${statusInfo.badge_text}"`);
            console.log(`  Colors matched:`, colors);
            console.log(`  Icon BG: ${colors.iconBg}`);
            console.log(`  Badge BG: ${colors.badgeBg}`);
            console.log(`  Badge Text Color: ${colors.badgeText}`);
            console.log(`  Icon: ${statusInfo.icon}`);
            console.log(`  Full Icon Class: bi ${statusInfo.icon}`);
            console.log(`  Show Edit: ${showEditButton}`);

            html += `
              <div class="dtr-item d-flex align-items-start mb-3" style="background-color: #f8f9fa; padding: 10px; border-radius: 8px;">
                <div style="background: ${colors.iconBg}; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; flex-shrink: 0; margin-right: 1rem;">
                  <i class="bi ${statusInfo.icon}" style="color: #ffffff; font-size: 20px;"></i>
                </div>
                <div style="flex-grow: 1;">
                  <div style="font-weight: 600;">
                    ${record.formatted_date}
                    <span style="background: ${colors.badgeBg}; color: ${colors.badgeText}; padding: 0.35em 0.8em; border-radius: 0.3rem; font-weight: 600; display: inline-block; margin-left: 0.5rem; font-size: 0.9rem;">${statusInfo.badge_text}</span>
                  </div>
                  <div style="font-size: 0.875rem; color: #6c757d; margin-top: 0.25rem;">
                    Time In: ${timeIn} — Time Out: ${timeOut}
                  </div>
                  ${hoursWorked !== 'N/A' ? `<div style="font-size: 0.875rem; color: #6c757d;">Hours: ${hoursWorked}</div>` : ''}
                  ${showEditButton ? `
                    <button class="btn btn-sm btn-outline-primary mt-2 edit-timeout-btn" 
                            data-record-id="${record.id}" 
                            data-date="${record.attendance_date}"
                            data-formatted-date="${record.formatted_date}"
                            data-time-in="${record.time_in}"
                            data-time-in-formatted="${timeIn}"
                            style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                      <i class="bi bi-pencil-square"></i> Add Time Out
                    </button>
                  ` : ''}
                </div>
              </div>
            `;
          });

          dtrList.innerHTML = html;
          console.log('=== END DEBUG ===');
          
          // Attach event listeners to edit buttons
          document.querySelectorAll('.edit-timeout-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
              e.stopPropagation();
              const recordId = this.dataset.recordId;
              const date = this.dataset.date;
              const formattedDate = this.dataset.formattedDate;
              const timeIn = this.dataset.timeIn;
              const timeInFormatted = this.dataset.timeInFormatted;
              
              openEditTimeOutModal(recordId, date, formattedDate, timeIn, timeInFormatted);
            });
          });
        }

        // Load DTR data when date range is selected
        function loadDTRForSelectedRange() {
          const rangeData = window.getSelectedDateRange();
          
          // Only reload if selection changed
          if (!rangeData || rangeData.count === lastSelectedDatesCount) {
            return;
          }
          
          lastSelectedDatesCount = rangeData.count;
          
          if (rangeData.count === 0) {
            // When selection is cleared, reload recent DTR
            if (!isInitialLoad) {
              loadRecentDTR();
            }
            return;
          }

          isInitialLoad = false;

          const dtrList = document.getElementById('dtrList');
          const dtrLoading = document.getElementById('dtrLoading');
          
          dtrList.style.display = 'none';
          dtrLoading.style.display = 'block';

          // Use new API endpoint with cache busting
          const startDate = rangeData.dates[0];
          const endDate = rangeData.dates[rangeData.dates.length - 1];
          const cacheBuster = Date.now(); // Force fresh data
          
          fetch(`get_employee_attendance.php?employee_id=${employeeInternalId}&start_date=${startDate}&end_date=${endDate}&_=${cacheBuster}`)
            .then(res => res.json())
            .then(response => {
              dtrLoading.style.display = 'none';
              dtrList.style.display = 'block';

              if (!response.success) {
                throw new Error(response.error || 'Failed to load attendance');
              }

              if (response.count === 0) {
                dtrList.innerHTML = `
                  <div class="text-center text-muted py-3">
                    <i class="bi bi-exclamation-circle fs-4"></i>
                    <p class="small mt-2">No attendance records found for selected dates</p>
                    <p class="small text-muted">${response.start_date} to ${response.end_date}</p>
                  </div>
                `;
                return;
              }

              displayDTRRecords(response.data);
            })
            .catch(error => {
              console.error('Error loading DTR:', error);
              dtrLoading.style.display = 'none';
              dtrList.style.display = 'block';
              dtrList.innerHTML = `
                <div class="text-center text-danger py-3">
                  <i class="bi bi-exclamation-triangle fs-4"></i>
                  <p class="small mt-2">Error loading attendance records</p>
                  <p class="small text-muted">${error.message}</p>
                </div>
              `;
            });
        }

        // Ensure Export DTR error modal exists (create on DOMContentLoaded)
        document.addEventListener('DOMContentLoaded', function() {
          if (!document.getElementById('exportDtrErrorModal')) {
            const modalHtml = `
              <div class="modal fade" id="exportDtrErrorModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content p-4 text-center">
                    <h5 class="fw-bold mb-3 text-danger">Select Date Range</h5>
                    <p id="exportDtrErrorMessage">Please select a date range from the calendar first (maximum 16 days).</p>
                  </div>
                </div>
              </div>`;
            const temp = document.createElement('div');
            temp.innerHTML = modalHtml.trim();
            document.body.appendChild(temp.firstElementChild);
          }
        });

        // Export DTR function
        function exportDTR() {
          const rangeData = window.getSelectedDateRange();
          
          if (!rangeData || rangeData.count === 0) {
            // Show Bootstrap modal (auto-close) instead of alert
            const msgEl = document.getElementById('exportDtrErrorMessage');
            if (msgEl) msgEl.textContent = 'Please select a date range from the calendar first (maximum 16 days)';
            const modalEl = document.getElementById('exportDtrErrorModal');
            if (modalEl) {
              document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
              document.body.classList.remove('modal-open');
              modalEl.style.zIndex = 20000;
              setTimeout(() => {
                const m = new bootstrap.Modal(modalEl);
                m.show();
                document.querySelectorAll('.modal-backdrop').forEach(el => el.style.zIndex = 19999);
              }, 40);
              setTimeout(() => {
                try { const inst = bootstrap.Modal.getInstance(modalEl); if (inst) inst.hide(); } catch (e) {}
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
              }, 5000);
            }
            return;
          }

          const startDateStr = rangeData.startDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
          const endDateStr = rangeData.endDate ? rangeData.endDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : startDateStr;
          
          // Redirect to export page with date range parameters
          const startParam = rangeData.dates[0];
          const endParam = rangeData.dates[rangeData.dates.length - 1];
          
          window.location.href = `../attendancerep/export_individual.php?id=${employeeCode}&start_date=${startParam}&end_date=${endParam}`;
        }

        // Listen for calendar updates using event delegation
        document.addEventListener('DOMContentLoaded', function() {
          // Load recent DTR on page load
          loadRecentDTR();
          
          // Check for calendar updates every 500ms
          setInterval(loadDTRForSelectedRange, 500);
        });

        // Edit Time Out Modal Functions
        let currentEditRecord = null;

        function openEditTimeOutModal(recordId, date, formattedDate, timeIn, timeInFormatted) {
          currentEditRecord = {
            id: recordId,
            date: date,
            formattedDate: formattedDate,
            timeIn: timeIn,
            timeInFormatted: timeInFormatted
          };

          // Set the modal content
          document.getElementById('editDate').textContent = formattedDate;
          document.getElementById('editTimeIn').textContent = timeInFormatted;
          document.getElementById('editTimeOut').value = '';

          // Show the modal
          const editModal = new bootstrap.Modal(document.getElementById('editTimeOutModal'));
          editModal.show();
        }

        // Handle confirm button in edit modal
        document.getElementById('confirmEditTimeOut').addEventListener('click', function() {
          const timeOut = document.getElementById('editTimeOut').value;
          
          if (!timeOut) {
            alert('Please select a time out.');
            return;
          }

          // Format time out for display
          const timeOutFormatted = formatTime12Hour(timeOut);

          // Set confirmation modal content
          document.getElementById('confirmDate').textContent = currentEditRecord.formattedDate;
          document.getElementById('confirmTimeIn').textContent = currentEditRecord.timeInFormatted;
          document.getElementById('confirmTimeOut').textContent = timeOutFormatted;

          // Store time out in current record
          currentEditRecord.timeOut = timeOut;
          currentEditRecord.timeOutFormatted = timeOutFormatted;

          // Hide edit modal and show confirmation modal
          const editModal = bootstrap.Modal.getInstance(document.getElementById('editTimeOutModal'));
          editModal.hide();

          const confirmModal = new bootstrap.Modal(document.getElementById('confirmEditModal'));
          confirmModal.show();
        });

        // Handle final confirmation
        document.getElementById('finalConfirmEdit').addEventListener('click', async function() {
          const btn = this;
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';

          try {
            const response = await fetch('api/add_manual_attendance.php?action=update_timeout', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                record_id: currentEditRecord.id,
                employee_id: employeeInternalId,
                date: currentEditRecord.date,
                time_out: currentEditRecord.timeOut
              })
            });

            const result = await response.json();

            if (result.success) {
              // Hide confirmation modal
              const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmEditModal'));
              confirmModal.hide();

              // Show success modal
              const successModal = new bootstrap.Modal(document.getElementById('successEditModal'));
              successModal.show();

              // Reload DTR records after a short delay
              setTimeout(() => {
                successModal.hide();
                // Reload the appropriate DTR view
                const rangeData = window.getSelectedDateRange();
                if (rangeData && rangeData.count > 0) {
                  loadDTRForSelectedRange();
                } else {
                  loadRecentDTR();
                }
              }, 5000);

            } else {
              throw new Error(result.error || 'Failed to update time out');
            }

          } catch (error) {
            console.error('Error updating time out:', error);
            alert('Failed to update time out: ' + error.message);
          } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Yes, Update Time Out';
          }
        });

        // Helper function to format time to 12-hour format
        function formatTime12Hour(time24) {
          const [hours, minutes] = time24.split(':');
          const hour = parseInt(hours);
          const ampm = hour >= 12 ? 'PM' : 'AM';
          const hour12 = hour % 12 || 12;
          return `${hour12}:${minutes} ${ampm}`;
        }
      </script>

    </div>
  </div>
</div> 

<!-- PERFORMANCE METRICS -->
<div class="card mb-3 metrics-card mt-3">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
    <strong>Performance Metrics</strong>
    <div class="d-flex gap-2 mt-2 mt-sm-0">
      <select class="form-select form-select-sm w-auto" id="selectMonth">
        <option value="">All Months</option>
        <option value="1">January</option>
        <option value="2">February</option>
        <option value="3">March</option>
        <option value="4">April</option>
        <option value="5">May</option>
        <option value="6">June</option>
        <option value="7">July</option>
        <option value="8">August</option>
        <option value="9">September</option>
        <option value="10">October</option>
        <option value="11" <?= date('n') == 11 ? 'selected' : '' ?>>November</option>
        <option value="12">December</option>
      </select>
      <select class="form-select form-select-sm w-auto" id="selectYear">
        <?php
          $currentYear = date('Y');
          $hireYear = !empty($employee['hire_date']) ? date('Y', strtotime($employee['hire_date'])) : $currentYear;
          for ($y = $hireYear; $y <= $currentYear; $y++) {
            $selected = $y == $currentYear ? 'selected' : '';
            echo "<option value='$y' $selected>$y</option>";
          }
        ?>
      </select>
    </div>
  </div>

  <div class="card-body">
    <div id="metricsLoading" class="text-center py-4">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-2 text-muted">Loading performance metrics...</p>
    </div>
    
    <div id="metricsContent" class="row text-center gx-3 gy-3" style="display: none;">
      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartPresent" width="150" height="150"></canvas>
          <div class="mt-2 fw-semibold">Present</div>
          <div class="text-muted small" id="presentValue">0%</div>
          <div class="text-muted" style="font-size: 0.7rem;" id="presentCount">0 days</div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartAbsent" width="150" height="150"></canvas>
          <div class="mt-2 fw-semibold">Absent</div>
          <div class="text-muted small" id="absentValue">0%</div>
          <div class="text-muted" style="font-size: 0.7rem;" id="absentCount">0 days</div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartOntime" width="150" height="150"></canvas>
          <div class="mt-2 fw-semibold">On Time</div>
          <div class="text-muted small" id="ontimeValue">0%</div>
          <div class="text-muted" style="font-size: 0.7rem;" id="ontimeCount">0 days</div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartLate" width="150" height="150"></canvas>
          <div class="mt-2 fw-semibold">Late</div>
          <div class="text-muted small" id="lateValue">0%</div>
          <div class="text-muted" style="font-size: 0.7rem;" id="lateCount">0 days</div>
        </div>
      </div>
    </div>
    
    <div id="metricsError" class="text-center py-4 text-danger" style="display: none;">
      <i class="bi bi-exclamation-triangle fs-1"></i>
      <p class="mt-2">Error loading metrics. Please try again.</p>
    </div>
  </div>
</div>

<script>
// Performance Metrics Chart Implementation
const employeeId = '<?= $employee_id ?>';
let metricsCharts = {
  present: null,
  absent: null,
  ontime: null,
  late: null
};

// Create donut chart
function createDonutChart(canvasId, percentage, color) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return null;
  
  // Destroy existing chart if it exists
  if (metricsCharts[canvasId.replace('chart', '').toLowerCase()]) {
    metricsCharts[canvasId.replace('chart', '').toLowerCase()].destroy();
  }
  
  const chart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      datasets: [{
        data: [percentage, 100 - percentage],
        backgroundColor: [color, '#e9ecef'],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      cutout: '75%',
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          enabled: false
        }
      }
    }
  });
  
  return chart;
}

// Fetch and display metrics
async function loadPerformanceMetrics() {
  const month = document.getElementById('selectMonth').value;
  const year = document.getElementById('selectYear').value;
  
  // Show loading
  document.getElementById('metricsLoading').style.display = 'block';
  document.getElementById('metricsContent').style.display = 'none';
  document.getElementById('metricsError').style.display = 'none';
  
  try {
    const params = new URLSearchParams({
      employee_id: employeeId,
      year: year
    });
    
    if (month) {
      params.append('month', month);
    }
    
    const response = await fetch(`get_performance_metrics.php?${params.toString()}`);
    const data = await response.json();
    
    if (!data.success) {
      throw new Error(data.error || 'Failed to load metrics');
    }
    
    // Update charts and values
    const metrics = data.metrics;
    
    // Present (Green)
    metricsCharts.present = createDonutChart('chartPresent', metrics.present.percentage, '#28a745');
    document.getElementById('presentValue').textContent = metrics.present.percentage + '%';
    document.getElementById('presentCount').textContent = metrics.present.count + ' days';
    
    // Absent (Red)
    metricsCharts.absent = createDonutChart('chartAbsent', metrics.absent.percentage, '#dc3545');
    document.getElementById('absentValue').textContent = metrics.absent.percentage + '%';
    document.getElementById('absentCount').textContent = metrics.absent.count + ' days';
    
    // On Time (Blue)
    metricsCharts.ontime = createDonutChart('chartOntime', metrics.onTime.percentage, '#0d6efd');
    document.getElementById('ontimeValue').textContent = metrics.onTime.percentage + '%';
    document.getElementById('ontimeCount').textContent = metrics.onTime.count + ' days';
    
    // Late (Orange)
    metricsCharts.late = createDonutChart('chartLate', metrics.late.percentage, '#fd7e14');
    document.getElementById('lateValue').textContent = metrics.late.percentage + '%';
    document.getElementById('lateCount').textContent = metrics.late.count + ' days';
    
    // Show content
    document.getElementById('metricsLoading').style.display = 'none';
    document.getElementById('metricsContent').style.display = 'flex';
    
  } catch (error) {
    console.error('Error loading metrics:', error);
    document.getElementById('metricsLoading').style.display = 'none';
    document.getElementById('metricsError').style.display = 'block';
  }
}

// Event listeners for month/year change
document.getElementById('selectMonth').addEventListener('change', loadPerformanceMetrics);
document.getElementById('selectYear').addEventListener('change', loadPerformanceMetrics);

// Load metrics on page load
document.addEventListener('DOMContentLoaded', loadPerformanceMetrics);
</script>

<!-- SCHEDULE SECTION -->
<div class="card mb-3 schedule-card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <strong>Schedule</strong>
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editScheduleModal">
      Edit Schedule
    </button>
  </div>
  <!-- Visual Schedule section -->
       <div class="info-section">
        <?php if (!empty($schedules)): ?>
                <div class="calendar-wrapper">
                    <div class="schedule-calendar" id="employee-schedule-calendar">
                        <!-- Time slots header -->
                        <div class="time-header"></div>
                        
                        <!-- Day headers -->
                        <div class="day-header" data-day="Monday">Mon</div>
                        <div class="day-header" data-day="Tuesday">Tue</div>
                        <div class="day-header" data-day="Wednesday">Wed</div>
                        <div class="day-header" data-day="Thursday">Thu</div>
                        <div class="day-header" data-day="Friday">Fri</div>
                        <div class="day-header" data-day="Saturday">Sat</div>
                        <div class="day-header" data-day="Sunday">Sun</div>
                        
                        <!-- Calendar grid will be populated by JavaScript -->
                    </div>
                </div>
            <script>
                // Schedule data from PHP - convert to format matching index.php
                const employeeSchedulesRaw = <?php echo json_encode($schedules); ?>;
                
                console.log('Raw employee schedules from database:', employeeSchedulesRaw);
                
                // Predefined color palette matching index.php
                const scheduleColors = [
                    '#4a7c59', '#8b4a6b', '#b85450', '#5b9bd5', '#ffc000',
                    '#c55a11', '#7030a0', '#0070c0', '#00b050', '#ff6b6b'
                ];
                
                // Convert database schedules to the format used in index.php
                function convertSchedulesToDisplayFormat() {
                    // IMPORTANT: day_of_week is 0-6 (Monday=0, Sunday=6) to match Python's datetime.weekday()
                    const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    const scheduleGroups = {};
                    
                    employeeSchedulesRaw.forEach(schedule => {
                        // Create a unique key for grouping schedules with same time/subject/class
                        const key = `${schedule.start_time}-${schedule.end_time}-${schedule.subject_code}-${schedule.designate_class}`;
                        
                        if (!scheduleGroups[key]) {
                            scheduleGroups[key] = {
                                startTime: schedule.start_time.substring(0, 5), // Convert HH:MM:SS to HH:MM
                                endTime: schedule.end_time.substring(0, 5),
                                subject: schedule.subject_code,
                                class: schedule.designate_class,
                                room_num: schedule.room_num,
                                days: [],
                                color: scheduleColors[Object.keys(scheduleGroups).length % scheduleColors.length]
                            };
                        }
                        
                        // day_of_week is already 0-6, so use directly as array index
                        const dayName = dayNames[parseInt(schedule.day_of_week)];
                        if (!scheduleGroups[key].days.includes(dayName)) {
                            scheduleGroups[key].days.push(dayName);
                        }
                    });
                    
                    return Object.values(scheduleGroups);
                }
                
                const addedSchedules = convertSchedulesToDisplayFormat();
                console.log('Converted schedules for display:', addedSchedules);
                
                // Copy exact functions from add_employee.js
                function parseTime(timeString) {
                    const [hours, minutes] = timeString.split(':').map(Number);
                    return hours * 60 + minutes;
                }
                
                function formatTimeSlot(minutes) {
                    const hours = Math.floor(minutes / 60);
                    const mins = minutes % 60;
                    return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
                }
                
                function formatTime(timeSlot) {
                    const [hours, minutes] = timeSlot.split(':').map(Number);
                    const period = hours >= 12 ? 'PM' : 'AM';
                    const displayHours = hours > 12 ? hours - 12 : (hours === 0 ? 12 : hours);
                    return `${displayHours}:${minutes.toString().padStart(2, '0')}${period}`;
                }
                
                function generateTimeSlots(startTime, endTime, intervalMinutes) {
                    const slots = [];
                    const start = parseTime(startTime);
                    const end = parseTime(endTime);
                    
                    let current = start;
                    while (current < end) {
                        slots.push(formatTimeSlot(current));
                        current += intervalMinutes;
                    }
                    
                    return slots;
                }
                
                function getRandomScheduleColor() {
                    return scheduleColors[Math.floor(Math.random() * scheduleColors.length)];
                }
                
                function initializeDisplayCalendar() {
                    const calendar = document.getElementById('employee-schedule-calendar');
                    if (!calendar) {
                        console.error('Display calendar element not found!');
                        return;
                    }
                    
                    console.log('Display calendar element found:', calendar);
                    const timeSlots = generateTimeSlots('07:00', '24:00', 30);
                    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    
                    // Clear existing grid content (keep headers)
                    const existingCells = calendar.querySelectorAll('.time-slot, .calendar-cell');
                    existingCells.forEach(cell => cell.remove());
                    
                    // Set up grid rows (header + time slots)
                    calendar.style.gridTemplateRows = `40px repeat(${timeSlots.length}, 40px)`;
                    
                    // Create time slots and calendar cells
                    timeSlots.forEach((timeSlot, timeIndex) => {
                        // Time slot label
                        const timeLabel = document.createElement('div');
                        timeLabel.className = 'time-slot';
                        timeLabel.textContent = formatTime(timeSlot);
                        timeLabel.style.gridColumn = '1';
                        timeLabel.style.gridRow = `${timeIndex + 2}`;
                        calendar.appendChild(timeLabel);
                        
                        // Calendar cells for each day
                        days.forEach((day, dayIndex) => {
                            const cell = document.createElement('div');
                            cell.className = 'calendar-cell';
                            cell.dataset.day = day;
                            cell.dataset.timeSlot = timeSlot;
                            cell.dataset.timeIndex = timeIndex;
                            cell.style.gridColumn = `${dayIndex + 2}`;
                            cell.style.gridRow = `${timeIndex + 2}`;
                            calendar.appendChild(cell);
                        });
                    });
                    
                    console.log('Calendar grid created with', timeSlots.length, 'time slots');
                    
                    // Render schedules
                    renderDisplaySchedules();
                }
                
                function renderDisplaySchedules() {
                    // Get the display calendar
                    const displayCalendar = document.getElementById('employee-schedule-calendar');
                    if (!displayCalendar) {
                        console.error('Display calendar not found!');
                        return;
                    }
                    
                    // Clear existing schedule blocks only from display calendar
                    displayCalendar.querySelectorAll('.schedule-block').forEach(block => block.remove());
                    
                    console.log('Rendering', addedSchedules.length, 'schedule(s) for display calendar');
                    
                    // Re-render all schedules
                    addedSchedules.forEach((schedule, index) => {
                        renderDisplayScheduleBlock(schedule, index);
                    });
                }
                
                function renderDisplayScheduleBlock(schedule, scheduleIndex) {
                    const startTimeMinutes = parseTime(schedule.startTime);
                    const endTimeMinutes = parseTime(schedule.endTime);
                    const baseTimeMinutes = 420; // 7:00 AM in minutes
                    const slotDuration = 30; // 30-minute slots
                    const slotHeight = 40; // 40px per slot
                    
                    // Calculate slot positions
                    const startSlotIndex = Math.floor((startTimeMinutes - baseTimeMinutes) / slotDuration);
                    const endSlotIndex = Math.ceil((endTimeMinutes - baseTimeMinutes) / slotDuration);
                    const slotsSpanned = endSlotIndex - startSlotIndex;
                    
                    console.log(`Schedule ${scheduleIndex}: ${schedule.startTime}-${schedule.endTime}, slots ${startSlotIndex}-${endSlotIndex}, span ${slotsSpanned}`);
                    
                    // Get the visual schedule calendar specifically
                    const displayCalendar = document.getElementById('employee-schedule-calendar');
                    if (!displayCalendar) {
                        console.error('Display calendar not found!');
                        return;
                    }
                    
                    schedule.days.forEach(day => {
                        const dayIndex = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].indexOf(day);
                        
                        if (startSlotIndex >= 0 && endSlotIndex <= 34) { // Within 7AM-12AM range
                            // Find the target cell within the display calendar only
                            const targetCell = displayCalendar.querySelector(`[data-day="${day}"][data-time-index="${startSlotIndex}"]`);
                            
                            if (targetCell) {
                                const scheduleBlock = document.createElement('div');
                                const isFacultySchedule = schedule.class !== 'N/A' && schedule.subject !== 'GENERAL' && schedule.room_num !== 'TBD';
                                scheduleBlock.className = isFacultySchedule ? 'schedule-block faculty-schedule' : 'schedule-block non-faculty-schedule';
                                
                                // Add unique identifier
                                scheduleBlock.dataset.scheduleId = scheduleIndex;
                                scheduleBlock.dataset.day = day;
                                scheduleBlock.dataset.startTime = schedule.startTime;
                                scheduleBlock.dataset.endTime = schedule.endTime;
                                
                                // Apply the schedule's assigned color
                                scheduleBlock.style.background = schedule.color || getRandomScheduleColor();
                                
                                // Calculate exact height
                                const exactHeight = slotsSpanned * slotHeight;
                                scheduleBlock.style.height = `${exactHeight}px`;
                                
                                // Generate content based on available information
                                let scheduleContent = '';
                                
                                if (isFacultySchedule) {
                                    // Faculty schedule with full information
                                    scheduleContent = `
                                        <div class="class-subject">${schedule.class}<br>${schedule.subject}</div>
                                        <div class="room-info">Room: ${schedule.room_num}</div>
                                        <div class="time-range">${formatTime(schedule.startTime)} - ${formatTime(schedule.endTime)}</div>
                                    `;
                                } else {
                                    // Non-faculty or minimal schedule - only show time
                                    scheduleContent = `
                                        <div class="time-range-only">${formatTime(schedule.startTime)} - ${formatTime(schedule.endTime)}</div>
                                        <div class="schedule-type">Work Schedule</div>
                                    `;
                                }
                                
                                scheduleBlock.innerHTML = `
                                    <div class="schedule-info">
                                        ${scheduleContent}
                                    </div>
                                `;
                                
                                targetCell.appendChild(scheduleBlock);
                                console.log(`Added schedule block to ${day} at slot ${startSlotIndex}`);
                            } else {
                                console.warn(`Could not find target cell for ${day} at slot ${startSlotIndex}`);
                            }
                        } else {
                            console.warn(`Schedule time out of range: ${schedule.startTime}-${schedule.endTime}`);
                        }
                    });
                }
                
                // Initialize calendar when page loads
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('DOM loaded, initializing display calendar...');
                    initializeDisplayCalendar();
                });
            </script>
        <?php else: ?>
            <p>No schedule assigned to this employee.</p>
        <?php endif; ?>
        </div>


</div>

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered edit-schedule-modal-dialog">
    <div class="modal-content border-0 shadow-sm">
      <div class="modal-header">
        <h4 class="modal-title fw-semibold" id="editScheduleModalLabel">Edit Schedule</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <form id="editScheduleForm" action="processes/update_employee_schedule.php" method="POST">
            <!-- Hidden fields for employee identification -->
            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
            <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>">
            <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>">
            <div class="schedule-section">
                <div class="form-group">
                    <label>Select Working Days:</label>
                    <p class="helper-text">Selected days appear dimmed</p>
                    <div class="day-buttons">
                        <button type="button" class="day-btn" data-day="Monday" onclick="toggleDay(this)">Mon</button>
                        <button type="button" class="day-btn" data-day="Tuesday" onclick="toggleDay(this)">Tue</button>
                        <button type="button" class="day-btn" data-day="Wednesday" onclick="toggleDay(this)">Wed</button>
                        <button type="button" class="day-btn" data-day="Thursday" onclick="toggleDay(this)">Thu</button>
                        <button type="button" class="day-btn" data-day="Friday" onclick="toggleDay(this)">Fri</button>
                        <button type="button" class="day-btn" data-day="Saturday" onclick="toggleDay(this)">Sat</button>
                        <button type="button" class="day-btn" data-day="Sunday" onclick="toggleDay(this)">Sun</button>
                    </div>
                    <input type="hidden" name="work_days" id="work_days" value="">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="shift_start">Shift Start Time:</label>
                        <input type="time" id="shift_start" name="shift_start">
                    </div>
                    <div class="form-group">
                        <label for="shift_end">Shift End Time:</label>
                        <input type="time" id="shift_end" name="shift_end">
                    </div>
                </div>
                <div class="form-row" id="faculty-fields">
                    <div class="form-group">
                        <label for="designate_class">Designate Class <span style="color: #999;">(Faculty Only)</span></label>
                        <input type="text" id="designate_class" name="designate_class" 
                               placeholder="Available for Faculty Members only" 
                               autocomplete="off" style="text-transform: uppercase;" disabled>
                        <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing to see existing classes</small>
                    </div>
                    <div class="form-group">
                        <label for="designate_subject">Subject <span style="color: #999;">(Faculty Only)</span></label>
                        <input type="text" id="designate_subject" name="designate_subject" 
                               placeholder="Available for Faculty Members only" 
                               autocomplete="off" style="text-transform: uppercase;" disabled>
                        <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing to see existing subjects</small>
                    </div>
                    <div class="form-group">
                        <label for="room-number">Room Number <span style="color: #999;">(Faculty Only)</span></label>
                        <input type="text" id="room-number" name="room-number" 
                               placeholder="Available for Faculty Members only" 
                               autocomplete="off" style="text-transform: uppercase;" disabled>
                        <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing to see existing rooms</small>
                     </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="display: flex; gap: 10px;">
                        <button type="button" class="add-schedule-btn" onclick="addSchedule()">Add Schedule</button>
                        <button type="button" id="edit-schedule-btn" class="edit-schedule-btn" onclick="editSchedule()" disabled>Update Selected Schedule</button>
                        <button type="button" class="btn-cancel" onclick="clearScheduleForm()">Cancel</button>
                    </div>
                </div>

                <!-- Weekly Schedule Calendar -->
                <div class="schedule-calendar-section">
                    <div class="schedule-header">
                        <h3>Schedule</h3>
                        <button type="button" class="clear-schedules-btn" onclick="clearAllSchedules()">
                            Clear All Schedules
                        </button>
                    </div>
                    <div class="calendar-wrapper">
                        <div class="schedule-calendar" id="edit-schedule-calendar">
                            <!-- Time slots header -->
                            <div class="time-header"></div>
                            
                            <!-- Day headers -->
                            <div class="day-header" data-day="Monday">Mon</div>
                            <div class="day-header" data-day="Tuesday">Tue</div>
                            <div class="day-header" data-day="Wednesday">Wed</div>
                            <div class="day-header" data-day="Thursday">Thu</div>
                            <div class="day-header" data-day="Friday">Fri</div>
                            <div class="day-header" data-day="Saturday">Sat</div>
                            <div class="day-header" data-day="Sunday">Sun</div>
                            
                            <!-- Time slots and schedule cells -->
                            <div id="calendar-grid"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden input for schedule data -->
                <input type="hidden" name="schedule_data" id="schedule_data">
                
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>

    <?php
    // --- PHP: Query employee schedules and output as JSON (with assignments) ---
    $emp_id = $employee['id'] ?? null;
    $existingSchedules = [];
    if ($emp_id) {
        $sql = "SELECT es.*, sp.day_of_week, sp.start_time, sp.end_time, sp.period_name,
                       ea.designate_class, ea.subject_code, ea.room_num
                FROM employee_schedules es
                JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                LEFT JOIN employee_assignments ea ON es.employee_id = ea.employee_id AND sp.id = ea.schedule_period_id
                WHERE es.employee_id = ? AND es.is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $employee['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $scheduleMap = [];
        while ($row = $result->fetch_assoc()) {
            $key = $row['schedule_id'] . '_' . $row['start_time'] . '_' . $row['end_time'] . '_' . ($row['designate_class'] ?? '') . '_' . ($row['subject_code'] ?? '') . '_' . ($row['room_num'] ?? '');
            if (!isset($scheduleMap[$key])) {
                $scheduleMap[$key] = [
                    'days' => [],
                    'startTime' => $row['start_time'],
                    'endTime' => $row['end_time'],
                    'class' => $row['designate_class'] ?? $row['period_name'] ?? '',
                    'subject' => $row['subject_code'] ?? '',
                    'room_num' => $row['room_num'] ?? '',
                ];
            }
            // IMPORTANT: Use 0-6 format (Monday=0, Sunday=6) to match Python's datetime.weekday()
            $daysOfWeek = [0=>'Monday',1=>'Tuesday',2=>'Wednesday',3=>'Thursday',4=>'Friday',5=>'Saturday',6=>'Sunday'];
            $dayStr = $daysOfWeek[$row['day_of_week']] ?? '';
            if ($dayStr && !in_array($dayStr, $scheduleMap[$key]['days'])) {
                $scheduleMap[$key]['days'][] = $dayStr;
            }
        }
        $existingSchedules = array_values($scheduleMap);
        $stmt->close();
    }
    ?>
      </div>
      
    </div>
  </div>
</div>

<!-- Edit Schedule Notification Modals -->
<!-- 1. No Working Day Selected Modal -->
<div class="modal fade" id="scheduleNoWorkDayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-warning">No Working Day Selected</h5>
      <p id="scheduleNoWorkDayMsg">Please select at least one working day first!</p>
    </div>
  </div>
</div>

<!-- 2. Schedule Missing Time Modal -->
<div class="modal fade" id="scheduleMissingTimeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-warning">Missing Information</h5>
      <p id="scheduleMissingTimeMsg">Please select both start and end times!</p>
    </div>
  </div>
</div>

<!-- 3. Invalid Time Order Modal -->
<div class="modal fade" id="scheduleInvalidTimeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-danger">Invalid Time Range</h5>
      <p id="scheduleInvalidTimeMsg">Start time must be before end time!</p>
    </div>
  </div>
</div>

<!-- 4. Faculty Missing Fields Modal -->
<div class="modal fade" id="scheduleFacultyMissingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-warning">Required Fields</h5>
      <p id="scheduleFacultyMissingMsg">Faculty members must enter class, subject, and room number for schedules!</p>
    </div>
  </div>
</div>

<!-- 5. Schedule Added Success Modal -->
<div class="modal fade" id="scheduleAddedSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-success">Schedule Added Successfully</h5>
      <p id="scheduleAddedSuccessMsg">Your schedule has been added.</p>
    </div>
  </div>
</div>

<!-- 6. Schedule Updated Success Modal -->
<div class="modal fade" id="scheduleUpdatedSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-success">Schedule Updated Successfully</h5>
      <p id="scheduleUpdatedSuccessMsg">Your schedule has been updated.</p>
    </div>
  </div>
</div>

<!-- 7. Clear All Schedules Confirm Modal -->
<div class="modal fade" id="scheduleClearConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-warning">Confirm Clear All</h5>
      <p id="scheduleClearConfirmMsg">Are you sure you want to clear all schedules?</p>
      <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
        <button type="button" class="btn btn-danger" id="scheduleClearConfirmBtn">Yes, Clear All</button>
      </div>
    </div>
  </div>
</div>

<!-- 8. Clear All Schedules Success Modal (No OK button, auto-close) -->
<div class="modal fade" id="scheduleClearedSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-success">Schedules Cleared</h5>
      <p id="scheduleClearedSuccessMsg">All schedules have been cleared!</p>
    </div>
  </div>
</div>

<!-- 9. Schedule Saved Success Modal -->
<div class="modal fade" id="scheduleSavedSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-success">Schedules Saved</h5>
      <p id="scheduleSavedSuccessMsg">Schedule updated successfully!</p>
    </div>
  </div>
</div>

<!-- 10. Schedule No Data Modal -->
<div class="modal fade" id="scheduleNoDataModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-info">No Schedules</h5>
      <p id="scheduleNoDataMsg">No schedules to clear!</p>
    </div>
  </div>
</div>

<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<!-- Bootstrap JS (Local - Works Offline) -->
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Custom Scripts -->
<script src="staff.js"></script>
<script>
    // --- JS: Populate calendar with existing schedules for the edit modal ---
    window.existingSchedules = <?php echo json_encode($existingSchedules); ?>;
</script>
<script src="../assets/js/edit_employee.js"></script>
<script>
    // --- JS: Live preview for profile picture ---
    document.addEventListener('DOMContentLoaded', function() {
        const photoInput = document.getElementById('profile_photo');
        const previewImg = document.getElementById('profile-preview');
        if (photoInput && previewImg) {
            photoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    previewImg.src = URL.createObjectURL(file);
                }
            });
        }
        
        // Initialize the edit schedule modal calendar when modal is shown
        const editScheduleModal = document.getElementById('editScheduleModal');
        if (editScheduleModal) {
            editScheduleModal.addEventListener('shown.bs.modal', function () {

                console.log('Edit schedule modal opened, initializing calendar...');
                // The initializeCalendar function from edit_employee.js should be available
                if (typeof initializeCalendar === 'function') {
                    initializeCalendar();
                }
                // Re-render schedules after calendar initialization
                if (typeof renderSchedules === 'function') {
                    console.log('Re-rendering schedules. Total schedules:', window.editAddedSchedules?.length || 0);
                    renderSchedules();
                }
            });
        }
    });
</script>
</body>
</html>




<!-- TODO FIX THE SCHEDULE CALENDAR GRID -->