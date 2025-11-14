<?php
require '../db_connection.php';
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
  <title>Staff Information - Attendance System</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS (Single version - 5.3.3) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- FullCalendar CSS -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="staff.css">
  <link rel="stylesheet" href="../assets/css/styles.css">
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
          <img src="<?php echo $employee['profile_photo']; ?>" class="profile-img" alt="Profile Picture" onerror="this.src='../assets/profile_pic/user.png'">
        </div>

          <div class="text-center text-lg-start ms-lg-5">
          <h3 class="fw-bold mb-1"><?php echo $viewer->getFullName(); ?></h3>
          <p class="text-muted mb-1"><?php echo htmlspecialchars($employee['employee_id']); ?></p>
          <p class="mb-1"><?php echo htmlspecialchars($employee['roles']); ?> | <?php echo htmlspecialchars($employee['position']); ?></p>
          <p class="mb-1">Email: <?php echo htmlspecialchars($employee['email']); ?></p>
          <p class="mb-3">Contact: <?php echo htmlspecialchars($employee['phone']); ?></p>

          <div class="d-flex justify-content-center justify-content-lg-start gap-2">
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editInfoModal">Edit Info</button>
            <button class="btn btn-danger btn-sm" id="btnRemove">Remove Employee</button>
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
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <img id="profile-preview" 
                             src="<?php echo !empty($employee['profile_photo']) ? '../assets/profile_pic/' . htmlspecialchars($employee['profile_photo']) : '../assets/profile_pic/user.png'; ?>" 
                             alt="Profile Preview" 
                             style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"
                             onerror="this.src='../assets/profile_pic/user.png'">
                        <div>
                            <button type="button" class="btn btn-primary btn-sm mb-2" onclick="document.getElementById('profile_photo').click()">
                                <i class="bi bi-upload"></i> Choose Photo
                            </button>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/jpg,image/png" style="display: none;">
                            <div>
                                <small class="text-muted d-block">JPEG or PNG only, max 5MB</small>
                                <small class="text-muted d-block">Image will be automatically resized</small>
                            </div>
                        </div>
                    </div>
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
                    <a href="employee_detail.php?id=<?php echo htmlspecialchars($employee['employee_id']); ?>" class="btn-cancel">Cancel</a>
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

 <!-- 🔹 Confirm Removal Modal -->
<div class="modal fade" id="removeEmployeeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-danger">Confirm Employee Removal</h5>
      <p>Are you sure you want to remove this employee?</p>
      <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
        <button class="btn btn-secondary" data-bs-dismiss="modal">No</button>
        <button class="btn btn-danger" id="confirmRemoveBtn">Yes</button>
      </div>
    </div>
  </div>
</div>

<!-- 🔹 Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-success">Removed Successfully</h5>
      <p>The employee has been moved to the archive.</p>
      <div class="mt-3">
        <button class="btn btn-success" id="successOkBtn">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const removeBtn = document.getElementById("btnRemove");
    const removeEmployeeModal = new bootstrap.Modal(document.getElementById("removeEmployeeModal"));
    const successModal = new bootstrap.Modal(document.getElementById("successModal"));
    const employeeId = '<?php echo htmlspecialchars($employee['employee_id']); ?>';

    // Step 1: Show confirm modal when Remove Employee is clicked
    removeBtn.addEventListener("click", (e) => {
      e.preventDefault();
      removeEmployeeModal.show();
    });

    // Step 2: When "Yes" clicked → archive employee via API
    document.getElementById("confirmRemoveBtn").addEventListener("click", async () => {
      try {
        // Show loading state
        document.getElementById("confirmRemoveBtn").disabled = true;
        document.getElementById("confirmRemoveBtn").innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Removing...';
        
        // Call archive API
        const formData = new FormData();
        formData.append('action', 'archive');
        formData.append('employee_ids[]', employeeId);
        formData.append('reason', 'Removed by administrator');
        
        const response = await fetch('api/archive_employee.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (result.success) {
          // Hide confirm modal and show success
          removeEmployeeModal.hide();
          setTimeout(() => successModal.show(), 400);
        } else {
          throw new Error(result.message || 'Failed to archive employee');
        }
      } catch (error) {
        console.error('Error archiving employee:', error);
        alert('Error: ' + error.message);
        // Re-enable button
        document.getElementById("confirmRemoveBtn").disabled = false;
        document.getElementById("confirmRemoveBtn").innerHTML = 'Yes';
      }
    });

    // Step 3: When "OK" clicked → redirect to staff.php
    document.getElementById("successOkBtn").addEventListener("click", () => {
      successModal.hide();
      document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
      document.body.classList.remove('modal-open');
      document.body.style.overflow = '';
      setTimeout(() => window.location.href = "staff.php", 400);
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
                <input type="text" class="form-control mb-3" placeholder="Maternity, Sick, Vacation etc." id="leaveType">

                <label class="form-label">FROM:</label>
                <input type="date" class="form-control mb-3" id="leaveFrom">

                <label class="form-label">TO:</label>
                <input type="date" class="form-control mb-3" id="leaveTo">
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" onclick="redirectToStaffInfo()">Cancel</button>
                <button class="btn btn-success" onclick="confirmLeave()">Confirm</button>
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
        <script>
          //add button
          let leaveType = "";
          let leaveFrom = "";
          let leaveTo = "";

          function confirmLeave() {
            leaveType = document.getElementById("leaveType").value;
            leaveFrom = document.getElementById("leaveFrom").value;
            leaveTo = document.getElementById("leaveTo").value;

            if (!leaveType || !leaveFrom || !leaveTo) {
              alert("Please fill out all fields.");
              return;
            }

            // Close Add Leave modal
            const addModalEl = document.getElementById('addLeaveModal');
            const addModal = bootstrap.Modal.getInstance(addModalEl);
            if (addModal) addModal.hide();

            // Show confirmation modal with leave details
            const detailsText = `${leaveType} Leave, from ${formatDate(leaveFrom)} - ${formatDate(leaveTo)}`;
            document.getElementById("leaveDetailsText").innerText = detailsText;

            const detailsModal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
            detailsModal.show();
          }

          function finalizeLeave() {
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsModal'));
            if (detailsModal) detailsModal.hide();

            // Get employee ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const employeeId = urlParams.get('id');
            
            if (!employeeId) {
              alert('Error: Employee ID not found');
              return;
            }

            // Submit leave request to API
            fetch('api/leave_management.php?action=submit_leave', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                employee_id: employeeId,
                leave_type: leaveType,
                start_date: leaveFrom,
                end_date: leaveTo,
                reason: `${leaveType} Leave`
              })
            })
            .then(response => response.json())
            .then(result => {
              if (result.success) {
                // Add to display
                const leaveList = document.getElementById("leaveList");
                const entry = document.createElement("div");
                entry.className = "leave-entry";
                entry.setAttribute('data-leave-id', result.data.leave_id);
                entry.setAttribute('data-status', 'pending');

                entry.innerHTML = `
                  <div>
                    <strong>${leaveType}</strong>
                    <span class="badge bg-warning text-dark ms-2">Pending</span><br>
                    <small>${formatDate(leaveFrom)} to ${formatDate(leaveTo)}</small>
                  </div>
                  <button class="btn btn-outline-danger btn-sm btn-delete" onclick="deleteLeave(this, ${result.data.leave_id})">
                    <i class="bi bi-trash"></i>
                  </button>
                `;

                leaveList.appendChild(entry);

                // Show confirmation modal
                const confirmModalEl = document.getElementById('confirmModal');
                const confirmModal = new bootstrap.Modal(confirmModalEl, { backdrop: false });
                confirmModal.show();

                document.body.classList.remove("modal-open");
                document.querySelector(".modal-backdrop")?.remove();

                setTimeout(() => {
                  location.reload();
                }, 1500);
              } else {
                alert('❌ Error: ' + result.message);
                // Reopen the details modal
                detailsModal.show();
              }
            })
            .catch(error => {
              console.error('Error:', error);
              alert('❌ Network error: ' + error.message);
              detailsModal.show();
            });
          }


          function goBackToForm() {
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsModal'));
            if (detailsModal) detailsModal.hide();

            const addModal = new bootstrap.Modal(document.getElementById('addLeaveModal'));
            addModal.show();
          }

          function redirectToStaffInfo() {
            window.location.href = "staffinfo.php";
          }

          function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-PH', {
              month: 'long',
              day: 'numeric',
              year: 'numeric'
            });
          }

          window.addEventListener("DOMContentLoaded", () => {
            // Get employee ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            const employeeId = urlParams.get('id');
            
            if (!employeeId) {
              console.error('No employee ID in URL');
              return;
            }

            // Load leaves from database via API
            fetch(`api/leave_management.php?action=get_employee_leaves&employee_id=${employeeId}`)
              .then(response => response.json())
              .then(result => {
                if (result.success && result.data.leaves.length > 0) {
                  const leaveList = document.getElementById("leaveList");
                  
                  result.data.leaves.forEach(leave => {
                    const entry = document.createElement("div");
                    entry.className = "leave-entry";
                    entry.setAttribute('data-leave-id', leave.id);
                    entry.setAttribute('data-status', leave.status);
                    
                    // Badge color based on status
                    let badgeClass = 'bg-warning text-dark';
                    if (leave.status === 'approved') badgeClass = 'bg-success';
                    if (leave.status === 'rejected') badgeClass = 'bg-danger';
                    
                    entry.innerHTML = `
                      <div>
                        <strong>${leave.leave_type}</strong>
                        <span class="badge ${badgeClass} ms-2">${leave.status.charAt(0).toUpperCase() + leave.status.slice(1)}</span><br>
                        <small>${formatDate(leave.start_date)} to ${formatDate(leave.end_date)}</small>
                      </div>
                      ${leave.status === 'pending' ? `
                        <button class="btn btn-outline-danger btn-sm btn-delete" onclick="deleteLeave(this, ${leave.id})">
                          <i class="bi bi-trash"></i>
                        </button>
                      ` : ''}
                    `;
                    leaveList.appendChild(entry);
                  });
                }
              })
              .catch(error => {
                console.error('Error loading leaves:', error);
              });
          });

          function deleteLeave(button, leaveId) {
            if (!confirm('Are you sure you want to cancel this leave request?')) {
              return;
            }

            const entry = button.closest(".leave-entry");
            if (!entry) return;

            // For now, just remove from display
            // TODO: Implement API call to cancel leave request
            entry.remove();

            const leaveList = document.getElementById("leaveList");
            const entries = leaveList.querySelectorAll(".leave-entry");

            if (entries.length === 0) {
              leaveList.innerHTML = '<small class="text-muted">No scheduled leaves</small>';
            }
          }
          </script>

      <!-- Calendar -->
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
          document.addEventListener("DOMContentLoaded", function () {
          const calendar = document.getElementById("calendar");
          const calendarTitle = document.getElementById("calendarTitle");
          const prevBtn = document.getElementById("prevMonth");
          const nextBtn = document.getElementById("nextMonth");

          let currentDate = new Date();
          let selectedStartDate = null;
          let selectedEndDate = null;

          const months = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
          ];

          function generateCalendar(year, month) {
            calendar.innerHTML = "";

            const monthStart = new Date(year, month, 1);
            const monthEnd = new Date(year, month + 1, 0);
            const today = new Date();

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

              const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
              dateDiv.dataset.date = dateStr;

              if (day <= 15) dateDiv.classList.add("first-half");
              else dateDiv.classList.add("second-half");

              if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                dateDiv.classList.add("today");
              }

              // Apply range selection styling
              if (selectedStartDate && selectedEndDate) {
                if (dateStr === selectedStartDate) {
                  dateDiv.classList.add("range-start");
                } else if (dateStr === selectedEndDate) {
                  dateDiv.classList.add("range-end");
                } else if (dateStr > selectedStartDate && dateStr < selectedEndDate) {
                  dateDiv.classList.add("range-between");
                }
              } else if (selectedStartDate && dateStr === selectedStartDate) {
                dateDiv.classList.add("range-start");
              }

              dateDiv.addEventListener("click", () => handleDateSelection(dateStr, year, month, day));

              calendar.appendChild(dateDiv);
            }
          }

          function handleDateSelection(dateStr, year, month, day) {
            if (!selectedStartDate || (selectedStartDate && selectedEndDate)) {
              // Start new selection
              selectedStartDate = dateStr;
              selectedEndDate = null;
              updateRangeDisplay();
              generateCalendar(year, month);
            } else {
              // Complete the selection
              if (dateStr < selectedStartDate) {
                selectedEndDate = selectedStartDate;
                selectedStartDate = dateStr;
              } else {
                selectedEndDate = dateStr;
              }

              // Validate 16-day limit
              const start = new Date(selectedStartDate);
              const end = new Date(selectedEndDate);
              const diffTime = end - start;
              const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
              const totalDays = diffDays + 1;

              if (totalDays > 16) {
                alert(`You selected ${totalDays} days.\n\nMaximum allowed is 16 days.\nPlease select a shorter date range.`);
                selectedStartDate = null;
                selectedEndDate = null;
                updateRangeDisplay();
                generateCalendar(year, month);
                return;
              }

              updateRangeDisplay();
              generateCalendar(year, month);
            }
          }

          function updateRangeDisplay() {
            // Reload DTR records when range changes
            if (typeof loadDTRRecords === 'function') {
              loadDTRRecords();
            }
          }

          // Global function for Export DTR to access selected range
          window.getSelectedDateRange = function() {
            if (selectedStartDate && selectedEndDate) {
              return {
                start_date: selectedStartDate,
                end_date: selectedEndDate
              };
            }
            return null;
          };

          prevBtn.addEventListener("click", () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
          });

          nextBtn.addEventListener("click", () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
          });

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
                  <div class="attendance-row row mb-2">
                    <div class="col-md-3">
                      <label>Date:</label>
                      <input type="date" class="form-control">
                    </div>
                    <div class="col-md-3">
                      <label>Time In:</label>
                      <input type="time" class="form-control">
                    </div>
                    <div class="col-md-3">
                      <label>Time Out:</label>
                      <input type="time" class="form-control">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                      <button class="btn btn-danger removeRow" style="display:none;">−</button>
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
  const attendanceModal = new bootstrap.Modal(document.getElementById('attendanceModal'));

  const openAttendanceBtn = document.getElementById('openAttendance');
  const addDayBtn = document.getElementById('addDayBtn');
  const attendanceContainer = document.getElementById('attendanceContainer');
  const saveBtn = document.getElementById('saveBtn');

  // Check if elements exist
  if (!openAttendanceBtn || !addDayBtn || !attendanceContainer || !saveBtn) {
    console.error('Manual attendance elements not found!');
    return;
  }

  // 🔹 Step 1: Open Manual Attendance directly (no password)
  openAttendanceBtn.addEventListener('click', () => {
    console.log('Opening attendance modal...');
    attendanceModal.show();
  });

  // 🔹 Step 2: Add another day
  addDayBtn.addEventListener('click', () => {
    const newRow = document.createElement('div');
    newRow.classList.add('attendance-row', 'row', 'mb-2');
    newRow.innerHTML = `
      <div class="col-md-3">
        <label>Date:</label>
        <input type="date" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Time In:</label>
        <input type="time" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Time Out:</label>
        <input type="time" class="form-control">
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-danger removeRow">−</button>
      </div>`;
    attendanceContainer.appendChild(newRow);
  });

  // 🔹 Step 3: Remove a day row
  attendanceContainer.addEventListener('click', (e) => {
    if (e.target.classList.contains('removeRow')) {
      e.target.closest('.attendance-row').remove();
    }
  });

  // 🔹 Step 4: Save attendance records via API
  saveBtn.addEventListener('click', async () => {
    // Get employee ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const employeeId = urlParams.get('id');
    
    if (!employeeId) {
      alert('Error: Employee ID not found');
      return;
    }
    
    // Collect all attendance records from the form
    const rows = document.querySelectorAll('.attendance-row');
    const records = [];
    
    for (const row of rows) {
      const dateInput = row.querySelector('input[type="date"]');
      const timeInInput = row.querySelectorAll('input[type="time"]')[0];
      const timeOutInput = row.querySelectorAll('input[type="time"]')[1];
      
      const date = dateInput.value;
      const timeIn = timeInInput.value;
      const timeOut = timeOutInput.value;
      
      // Validate: at least date and one time must be provided
      if (!date) {
        alert('Please fill in all dates');
        return;
      }
      
      if (!timeIn && !timeOut) {
        alert('Please provide at least Time In or Time Out for each record');
        return;
      }
      
      records.push({
        date: date,
        time_in: timeIn || null,
        time_out: timeOut || null
      });
    }
    
    if (records.length === 0) {
      alert('Please add at least one attendance record');
      return;
    }
    
    // Show loading spinner
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    
    try {
      // Send to API
      const response = await fetch('api/add_manual_attendance.php?action=add_manual', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          employee_id: employeeId,
          records: records
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        alert(`✅ Success!\n\nAdded: ${result.data.added} records\nUpdated: ${result.data.updated} records\nTotal: ${result.data.total} records\n\nFor: ${result.data.employee_name}`);
        attendanceModal.hide();
        location.reload(); // Reload page to show updated attendance
      } else {
        alert('❌ Error: ' + result.message);
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save Records';
      }
    } catch (error) {
      console.error('Error:', error);
      alert('❌ Network error: ' + error.message);
      saveBtn.disabled = false;
      saveBtn.innerHTML = 'Save Records';
    }
  });
}); // End DOMContentLoaded
</script>

      <!-- Daily Time Record (DTR) -->
      <div class="card" style="margin-top: 0 !important;">
        <div class="card-body">
          <button class="btn btn-success w-100 mb-3" id="exportDTRBtn">
            <i class="bi bi-download me-2"></i>Export DTR
          </button>

          <div class="mb-3">
            <small class="text-muted">Daily Time Record</small>
          </div>

          <div id="dtrLoadingSpinner" class="text-center py-4" style="display: none;">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <div class="small text-muted mt-2">Loading attendance records...</div>
          </div>

          <div id="dtrList" class="dtr-list">
            <!-- DTR records will be loaded here -->
          </div>

          <div id="dtrEmptyState" class="text-center py-4" style="display: none;">
            <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
            <small class="text-muted">No attendance records for this month</small>
          </div>

          <!-- See More Button -->
          <div id="seeMoreContainer" class="text-center mt-3 mb-3" style="display: none;">
            <button class="btn btn-outline-primary btn-sm w-100" id="seeMoreBtn">
              <i class="bi bi-chevron-down me-2"></i>See More...
            </button>
          </div>

          <div class="mt-3" id="dtrStats" style="display: none;">
            <hr>
            <div class="row g-2 small">
              <div class="col-6">
                <div class="text-muted">Total Days</div>
                <div class="fw-semibold" id="statTotalDays">0</div>
              </div>
              <div class="col-6">
                <div class="text-muted">On Time</div>
                <div class="fw-semibold text-success" id="statOnTime">0</div>
              </div>
              <div class="col-6">
                <div class="text-muted">Late Days</div>
                <div class="fw-semibold text-danger" id="statLateDays">0</div>
              </div>
              <div class="col-6">
                <div class="text-muted">Completion</div>
                <div class="fw-semibold text-primary" id="statCompletion">0%</div>
              </div>
            </div>
          </div>

        </div>
      </div>

<script>
let allDTRRecords = [];
let showingAllRecords = false;

// Load DTR records on page load
document.addEventListener('DOMContentLoaded', function() {
  loadDTRRecords();
  
  // See More button click event
  document.getElementById('seeMoreBtn').addEventListener('click', function() {
    if (!showingAllRecords) {
      displayAllRecords();
      this.innerHTML = '<i class="bi bi-chevron-up me-2"></i>Show Less';
      showingAllRecords = true;
    } else {
      displayLimitedRecords();
      this.innerHTML = '<i class="bi bi-chevron-down me-2"></i>See More...';
      showingAllRecords = false;
    }
  });
  
  // Export DTR button
  document.getElementById('exportDTRBtn').addEventListener('click', function() {
    const employeeId = <?php echo $employee['id']; ?>;
    
    // Check if date range is selected from calendar
    const dateRange = window.getSelectedDateRange ? window.getSelectedDateRange() : null;
    
    if (dateRange && dateRange.start_date && dateRange.end_date) {
      // Export with selected date range
      window.location.href = `../attendancerep/indirep.php?id=${employeeId}&start_date=${dateRange.start_date}&end_date=${dateRange.end_date}`;
    } else {
      // Export current month
      const currentMonth = new Date().toISOString().slice(0, 7);
      window.location.href = `../attendancerep/indirep.php?id=${employeeId}&month=${currentMonth}`;
    }
  });
});

async function loadDTRRecords() {
  const dtrList = document.getElementById('dtrList');
  const loadingSpinner = document.getElementById('dtrLoadingSpinner');
  const emptyState = document.getElementById('dtrEmptyState');
  const statsDiv = document.getElementById('dtrStats');
  const seeMoreContainer = document.getElementById('seeMoreContainer');
  const employeeId = <?php echo $employee['id']; ?>;
  
  // Reset state
  showingAllRecords = false;
  document.getElementById('seeMoreBtn').innerHTML = '<i class="bi bi-chevron-down me-2"></i>See More...';
  
  // Show loading
  loadingSpinner.style.display = 'block';
  dtrList.style.display = 'none';
  emptyState.style.display = 'none';
  statsDiv.style.display = 'none';
  seeMoreContainer.style.display = 'none';
  
  try {
    // Check if date range is selected from calendar
    let fetchUrl;
    const dateRange = window.getSelectedDateRange ? window.getSelectedDateRange() : null;
    
    if (dateRange && dateRange.start_date && dateRange.end_date) {
      // Use date range from calendar selection (load all records)
      fetchUrl = `api/dtr.php?action=get_employee_dtr&employee_id=${employeeId}&start_date=${dateRange.start_date}&end_date=${dateRange.end_date}`;
    } else {
      // Load current month records
      const currentMonth = new Date().toISOString().slice(0, 7);
      fetchUrl = `api/dtr.php?action=get_employee_dtr&employee_id=${employeeId}&month=${currentMonth}`;
    }
    
    // Fetch DTR records
    const response = await fetch(fetchUrl);
    const result = await response.json();
    
    loadingSpinner.style.display = 'none';
    
    if (result.success && result.data.records.length > 0) {
      // Store all records
      allDTRRecords = result.data.records;
      
      // Display limited records initially (5 records)
      displayLimitedRecords();
      
      // Show "See More" button if there are more than 5 records
      if (allDTRRecords.length > 5) {
        seeMoreContainer.style.display = 'block';
      }
      
      dtrList.style.display = 'block';
      
      // Load and display statistics
      const dateRange = window.getSelectedDateRange ? window.getSelectedDateRange() : null;
      if (dateRange && dateRange.start_date && dateRange.end_date) {
        loadDTRStatsRange(employeeId, dateRange.start_date, dateRange.end_date);
      } else {
        const currentMonth = new Date().toISOString().slice(0, 7);
        loadDTRStats(employeeId, currentMonth);
      }
      
    } else {
      emptyState.style.display = 'block';
    }
    
  } catch (error) {
    loadingSpinner.style.display = 'none';
    console.error('Error loading DTR:', error);
    dtrList.innerHTML = `
      <div class="alert alert-danger small">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Error loading attendance records: ${error.message}
      </div>
    `;
    dtrList.style.display = 'block';
  }
}

function displayLimitedRecords() {
  const dtrList = document.getElementById('dtrList');
  dtrList.innerHTML = '';
  
  // Show only first 5 records
  const recordsToShow = allDTRRecords.slice(0, 5);
  recordsToShow.forEach(record => {
    dtrList.appendChild(createDTRItem(record));
  });
}

function displayAllRecords() {
  const dtrList = document.getElementById('dtrList');
  dtrList.innerHTML = '';
  
  // Show all records
  allDTRRecords.forEach(record => {
    dtrList.appendChild(createDTRItem(record));
  });
}

function createDTRItem(record) {
  const dtrItem = document.createElement('div');
  dtrItem.className = 'dtr-item d-flex align-items-start mb-3';
  dtrItem.innerHTML = `
    <div class="dtr-icon ${record.icon_bg} text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px;">
      <i class="bi ${record.icon}"></i>
    </div>
    <div class="flex-grow-1">
      <div class="fw-semibold">
        ${record.date_formatted}
        <span class="badge ${record.status_badge} ms-2">${record.status_display}</span>
      </div>
      <div class="small text-muted">
        Time In: ${record.time_in} — Time Out: ${record.time_out}
      </div>
      ${record.late_minutes > 0 ? `<div class="small text-danger">Late: ${record.late_minutes} min</div>` : ''}
      ${record.early_departure_minutes > 0 ? `<div class="small text-warning">Early Out: ${record.early_departure_minutes} min</div>` : ''}
      ${record.overtime_minutes > 0 ? `<div class="small text-info">Overtime: ${record.overtime_minutes} min</div>` : ''}
    </div>
  `;
  return dtrItem;
}

async function loadDTRStats(employeeId, month) {
  try {
    const response = await fetch(`api/dtr.php?action=get_stats&employee_id=${employeeId}&month=${month}`);
    const result = await response.json();
    
    if (result.success) {
      const stats = result.data;
      
      document.getElementById('statTotalDays').textContent = stats.total_days;
      document.getElementById('statOnTime').textContent = stats.on_time_days;
      document.getElementById('statLateDays').textContent = stats.late_days;
      document.getElementById('statCompletion').textContent = stats.completion_rate + '%';
      
      document.getElementById('dtrStats').style.display = 'block';
    }
  } catch (error) {
    console.error('Error loading stats:', error);
  }
}

async function loadDTRStatsRange(employeeId, startDate, endDate) {
  try {
    const response = await fetch(`api/dtr.php?action=get_stats&employee_id=${employeeId}&start_date=${startDate}&end_date=${endDate}`);
    const result = await response.json();
    
    if (result.success) {
      const stats = result.data;
      
      document.getElementById('statTotalDays').textContent = stats.total_days;
      document.getElementById('statOnTime').textContent = stats.on_time_days;
      document.getElementById('statLateDays').textContent = stats.late_days;
      document.getElementById('statCompletion').textContent = stats.completion_rate + '%';
      
      document.getElementById('dtrStats').style.display = 'block';
    }
  } catch (error) {
    console.error('Error loading stats:', error);
  }
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
        <option>September</option>
        <option>October</option>
      </select>
      <select class="form-select form-select-sm w-auto" id="selectYear">
        <option>2024</option>
        <option selected>2025</option>
      </select>
    </div>
  </div>

  <div class="card-body">
    <div class="row text-center gx-3 gy-3">
      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartPresent"></canvas>
          <div class="mt-2 fw-semibold">Present</div>
          <div class="text-muted small" id="presentValue"></div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartAbsent"></canvas>
          <div class="mt-2 fw-semibold">Absent</div>
          <div class="text-muted small" id="absentValue"></div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartOntime"></canvas>
          <div class="mt-2 fw-semibold">On Time</div>
          <div class="text-muted small" id="ontimeValue"></div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartLate"></canvas>
          <div class="mt-2 fw-semibold">Late</div>
          <div class="text-muted small" id="lateValue"></div>
        </div>
      </div>
    </div>
  </div>
</div>

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

<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<!-- Bootstrap JS (Single Load) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Scripts -->
<script src="staff.js"></script>
<script>
    // --- JS: Populate calendar with existing schedules for the edit modal ---
    window.existingSchedules = <?php echo json_encode($existingSchedules); ?>;
</script>
<script src="../assets/js/edit_employee.js"></script>
<script>
    // --- JS: Profile Picture Upload Integration with Secure API ---
    document.addEventListener('DOMContentLoaded', function() {
        const photoInput = document.getElementById('profile_photo');
        const previewImg = document.getElementById('profile-preview');
        const editForm = document.querySelector('#editInfoModal form');
        const saveButton = editForm?.querySelector('button[type="submit"]');
        
        // Live preview for profile picture
        if (photoInput && previewImg) {
            photoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file before preview
                    const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    
                    if (!validTypes.includes(file.type.toLowerCase())) {
                        alert('Please select a valid image file (JPEG or PNG only)');
                        photoInput.value = '';
                        return;
                    }
                    
                    if (file.size > maxSize) {
                        alert('File size must be less than 5MB');
                        photoInput.value = '';
                        return;
                    }
                    
                    previewImg.src = URL.createObjectURL(file);
                }
            });
        }
        
        // Handle form submission with profile picture upload via API
        if (editForm) {
            editForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(editForm);
                const profilePhotoFile = photoInput?.files[0];
                const employeeId = formData.get('employee_id');
                
                // Disable save button and show loading state
                if (saveButton) {
                    saveButton.disabled = true;
                    saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
                }
                
                try {
                    // Step 1: Upload profile picture via secure API if a new file is selected
                    if (profilePhotoFile) {
                        console.log('Uploading profile picture via API...');
                        
                        const uploadFormData = new FormData();
                        uploadFormData.append('profile_picture', profilePhotoFile);
                        uploadFormData.append('employee_id', employeeId);
                        
                        // Get CSRF token from session
                        const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
                        if (csrfToken) {
                            uploadFormData.append('csrf_token', csrfToken);
                        }
                        
                        const uploadResponse = await fetch('api/upload_profile_picture.php', {
                            method: 'POST',
                            body: uploadFormData,
                            credentials: 'same-origin'
                        });
                        
                        const uploadResult = await uploadResponse.json();
                        
                        if (!uploadResult.success) {
                            throw new Error(uploadResult.message || 'Profile picture upload failed');
                        }
                        
                        console.log('Profile picture uploaded successfully:', uploadResult.image_url);
                        
                        // Remove the file input from form data since we've already uploaded it
                        formData.delete('profile_photo');
                    }
                    
                    // Step 2: Submit the rest of the form data
                    console.log('Submitting employee details...');
                    
                    const response = await fetch('processes/update_employee.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    // Check if response is redirect or JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const result = await response.json();
                        if (!result.success) {
                            throw new Error(result.message || 'Update failed');
                        }
                    }
                    
                    // Success - redirect to staffinfo page
                    window.location.href = 'staffinfo.php?id=' + encodeURIComponent(employeeId) + '&status=updated';
                    
                } catch (error) {
                    console.error('Error during update:', error);
                    alert('Error: ' + error.message);
                    
                    // Re-enable save button
                    if (saveButton) {
                        saveButton.disabled = false;
                        saveButton.innerHTML = 'Save Changes';
                    }
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