<?php
// Protect this page - require authentication
require_once '../auth_guard.php';
require_once '../navigation.php';

require '../db_connection.php';

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Get current user info
$currentUser = getCurrentUser();

class AttendanceReportViewer {
    private $db;
    private $attendanceRecords = [];
    private $errors = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function loadTodayAttendance($filters = []) {
        try {
            // Get date from filters or use current date
            $currentDate = !empty($filters['date']) ? $filters['date'] : date('Y-m-d');
            
            // Build query to fetch daily attendance with employee details
            $query = "SELECT 
                        da.id,
                        da.employee_id,
                        da.attendance_date,
                        da.time_in,
                        da.time_out,
                        da.scheduled_hours,
                        da.actual_hours,
                        da.late_minutes,
                        da.early_departure_minutes,
                        da.overtime_minutes,
                        da.break_time_minutes,
                        da.status,
                        da.notes,
                        e.employee_id as employee_id_string,
                        e.first_name,
                        e.middle_name,
                        e.last_name,
                        e.roles,
                        e.department,
                        e.profile_photo
                      FROM daily_attendance da
                      INNER JOIN employees e ON da.employee_id = e.id
                      WHERE da.attendance_date = ? AND da.status != 'visit'";
            
            $whereConditions = [];
            $params = [$currentDate];
            $types = 's';
            
            // Apply filters
            if (!empty($filters['role']) && $filters['role'] !== 'All Roles') {
                $whereConditions[] = "e.roles = ?";
                $params[] = $filters['role'];
                $types .= 's';
            }
            
            if (!empty($filters['department']) && $filters['department'] !== 'All Departments') {
                $whereConditions[] = "e.department = ?";
                $params[] = $filters['department'];
                $types .= 's';
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'sss';
            }
            
            
            // Add additional WHERE conditions
            if (!empty($whereConditions)) {
                $query .= " AND " . implode(" AND ", $whereConditions);
            }
            
            $query .= " ORDER BY da.time_in DESC, e.last_name, e.first_name";
            
            // Prepare and execute
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $this->db->error);
            }
            
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            $this->attendanceRecords = [];
            while ($row = $result->fetch_assoc()) {
                $this->attendanceRecords[] = $this->processAttendanceRecord($row);
            }
            
            $stmt->close();
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
            return false;
        }
    }
    
    private function processAttendanceRecord($record) {
        // Build full name
        $nameParts = [];
        if ($record['first_name']) $nameParts[] = $record['first_name'];
        if ($record['middle_name']) $nameParts[] = $record['middle_name'];
        if ($record['last_name']) $nameParts[] = $record['last_name'];
        $fullName = implode(' ', $nameParts);
        
        // Determine status display
        $statusInfo = $this->determineStatus($record);
        
        // Calculate vacant hours (break time converted to hours)
        $vacantHours = $record['break_time_minutes'] ? round($record['break_time_minutes'] / 60, 1) : 0;
        
        // Convert scheduled_hours and actual_hours from MINUTES to HOURS
        // NOTE: Database stores these values in minutes despite the field names
        $scheduledHours = $record['scheduled_hours'] ? round($record['scheduled_hours'] / 60, 1) : 0;
        $actualHours = $record['actual_hours'] ? round($record['actual_hours'] / 60, 1) : 0;
        
        return [
            'id' => $record['id'],
            'employee_id' => htmlspecialchars($record['employee_id_string'], ENT_QUOTES, 'UTF-8'),
            'full_name' => htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
            'role' => htmlspecialchars($record['roles'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            'department' => htmlspecialchars($record['department'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            'profile_photo' => $record['profile_photo'] ?? '../assets/profile_pic/user.png',
            'attendance_date' => $record['attendance_date'],
            'time_in' => $record['time_in'] ? date('g:i A', strtotime($record['time_in'])) : 'N/A',
            'time_out' => $record['time_out'] ? date('g:i A', strtotime($record['time_out'])) : 'N/A',
            'vacant_hours' => $vacantHours,
            'actual_hours' => $actualHours,
            'scheduled_hours' => $scheduledHours,
            'late_minutes' => $record['late_minutes'] ?? 0,
            'status' => $record['status'],
            'status_display' => $statusInfo['display'],
            'status_class' => $statusInfo['class'],
            'notes' => $record['notes']
        ];
    }
    
    private function determineStatus($record) {
        $status = $record['status'];
        
        // Complete status - has both time_in and time_out
        if ($status === 'complete') {
            return [
                'display' => 'Complete',
                'class' => 'status-complete-dot'
            ];
        }
        
        // Absent status
        if ($status === 'absent') {
            return [
                'display' => 'Absent',
                'class' => 'status-absent-dot'
            ];
        }
        
        // Manual status
        if ($status === 'manual') {
            return [
                'display' => 'Manual',
                'class' => 'status-manual-dot'
            ];
        }
        
        // Incomplete status - check different scenarios
        if ($status === 'incomplete') {
            // No time_in yet - Not Arrived
            if (empty($record['time_in'])) {
                return [
                    'display' => 'Not Arrived',
                    'class' => 'status-not-arrived-dot'
                ];
            }
            
            // Has time_in but no time_out - check if late or on-time
            if (!empty($record['time_in']) && empty($record['time_out'])) {
                if ($record['late_minutes'] > 0) {
                    return [
                        'display' => 'Late',
                        'class' => 'status-late-dot'
                    ];
                } else {
                    return [
                        'display' => 'On-Time',
                        'class' => 'status-ontime-dot'
                    ];
                }
            }
        }
        
        // Default fallback for incomplete
        return [
            'display' => 'Incomplete',
            'class' => 'status-incomplete-dot'
        ];
    }
    
    public function getAttendanceRecords() {
        return $this->attendanceRecords;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function hasErrors() {
        return !empty($this->errors);
    }
}

// Initialize the viewer
$viewer = new AttendanceReportViewer($conn);

// Fetch all unique departments from employees table
$departmentQuery = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department ASC";
$departmentResult = $conn->query($departmentQuery);
$departments = [];
if ($departmentResult) {
    while ($row = $departmentResult->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// Fetch all unique roles from employees table
$roleQuery = "SELECT DISTINCT roles FROM employees WHERE roles IS NOT NULL AND roles != '' ORDER BY roles ASC";
$roleResult = $conn->query($roleQuery);
$roles = [];
if ($roleResult) {
    while ($row = $roleResult->fetch_assoc()) {
        $roles[] = $row['roles'];
    }
}

// Process filter parameters
$filters = [
    'role' => $_GET['role'] ?? '',
    'department' => $_GET['department'] ?? '',
    'search' => $_GET['search'] ?? '',
    'date' => $_GET['date'] ?? date('Y-m-d') // Default to today in Manila timezone
];

// Load attendance records for selected date
$loadSuccess = $viewer->loadTodayAttendance($filters);
$attendanceRecords = $viewer->getAttendanceRecords();
$selectedDate = $filters['date'];
$currentDate = date('F d, Y', strtotime($selectedDate)); // Format: November 11, 2025

// Handle AJAX Request
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (empty($attendanceRecords)) {
        echo '<tr>
          <td colspan="7" class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            No attendance records found for ' . $currentDate . '
          </td>
        </tr>';
    } else {
        foreach ($attendanceRecords as $record) {
            echo '<tr data-id="' . $record['employee_id'] . '">
              <td>
                <div class="d-flex align-items-center">
                  <img src="../' . $record['profile_photo'] . '" 
                       onerror="this.src=\'../assets/profile_pic/user.png\';" 
                       class="employee-img rounded-circle me-2" 
                       width="40" 
                       height="40"
                       alt="Profile">
                  <div>
                    <span class="fw-semibold">' . $record['full_name'] . '</span><br>
                    <small class="text-muted">' . $record['role'] . '</small>
                  </div>
                </div>
              </td>
              <td>' . date('Y-m-d', strtotime($record['attendance_date'])) . '</td>
              <td>' . $record['time_in'] . '</td>
              <td>' . $record['time_out'] . '</td>
              <td>' . number_format($record['scheduled_hours'], 1) . ' hr</td>
              <td>' . number_format($record['actual_hours'], 1) . ' hr</td>
              <td><span class="status-dot ' . $record['status_class'] . '"></span> ' . $record['status_display'] . '</td>
            </tr>';
        }
    }
    exit; // Stop further execution for AJAX requests
}?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Reports</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Prevent caching -->
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  
  <!-- Version: 2.0 - Date Picker Added -->

  <!-- Bootstrap CSS -->
  <!-- Bootstrap CSS -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Custom CSS -->
<link rel="stylesheet" href="attendancerep.css?v=<?php echo time(); ?>">
</head>

<body>
 <body>
  <div class="top-navbar d-flex justify-content-between align-items-center p-2 shadow-sm">
  <div class="menu-toggle">
    <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn"></i>
  </div>
  <?php include '../includes/notification_bell.php'; ?>
</div>

  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column pt-5" id="sidebar">
    <div class="profile text-center p-3 mt-4">
      <img src="<?php echo !empty($currentUser['profile_photo']) ? '../' . htmlspecialchars($currentUser['profile_photo'], ENT_QUOTES, 'UTF-8') . '?v=' . time() : '../assets/profile_pic/user.png?v=' . time(); ?>" 
           alt="Profile" 
           class="rounded-circle mb-2" 
           width="70" 
           height="70"
           onerror="this.src='../assets/profile_pic/user.png';">
      <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h5>
      <small class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></small>
    </div>
    <nav class="nav flex-column px-2">
      <?php renderNavigation('Attendance'); ?>
    </nav>
  </div>
<!----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
  <div class="content pt-3" id="content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="display-4 text-dark">Attendance Reports</h2>
      <div class="d-flex align-items-center gap-3">
        <span class="text-dark">Selected Date: <strong><?php echo $currentDate; ?></strong></span>
        <?php if (isAdmin()): ?>
        <a href="exporep.php" class="btn btn-warning fw-bold">Batch Export DTR</a>
        <?php endif; ?>
      </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-2">
      <label for="dateFilter" class="form-label small text-muted">Select Date</label>
      <input type="date" class="form-control" id="dateFilter" 
             value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>"
             max="<?php echo date('Y-m-d'); ?>"
             title="Select date to view attendance">
    </div>
    <div class="col-md-3">
      <label for="roleFilter" class="form-label small text-muted">Role</label>
        <select class="form-select" id="roleFilter">
          <option value="">All</option>
          <?php foreach ($roles as $role): ?>
            <?php $trimRole = trim($role); ?>
            <?php $displayRole = (preg_match('/faculty[\s_\-]*member/i', $trimRole) || strcasecmp($trimRole, 'faculty') === 0) ? 'Faculty' : $role; ?>
            <?php $isSelected = (isset($filters['role']) && (strcasecmp($filters['role'], $role) === 0 || strcasecmp($filters['role'], $displayRole) === 0)); ?>
            <option value="<?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?>" 
                    <?php echo $isSelected ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
      <label for="deptFilter" class="form-label small text-muted">Department</label>
      <select class="form-select" id="deptFilter">
        <option value="">All</option>
        <?php foreach ($departments as $department): ?>
          <option value="<?php echo htmlspecialchars($department, ENT_QUOTES, 'UTF-8'); ?>" 
                  <?php echo (isset($filters['department']) && $filters['department'] === $department) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($department, ENT_QUOTES, 'UTF-8'); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-auto d-flex align-items-end">
      <button id="filterToggleBtn" class="btn btn-sm btn-outline-secondary" title="Toggle advanced filters">
        <i class="bi bi-funnel"></i>
      </button>
    </div>
    <div class="col-md-3">
      <label for="searchBox" class="form-label small text-muted">Search</label>
      <input type="text" id="searchBox" class="form-control" placeholder="Search by name" 
             value="<?php echo htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>
  </div>

  <div class="card p-3 shadow-sm">
  <div class="table-responsive">
    <table class="table align-middle" id="attendanceTable">
      <thead class="table-light">
        <tr>
          <th>Employee</th>
          <th>Date</th>
          <th>Time In</th>
          <th>Time Out</th>
          <th>Scheduled Hours</th>
          <th>Actual Hours</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($attendanceRecords)): ?>
        <tr>
          <td colspan="7" class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            No attendance records found for <?php echo $currentDate; ?>
          </td>
        </tr>
        <?php else: ?>
          <?php foreach ($attendanceRecords as $record): ?>
        <tr data-id="<?php echo $record['employee_id']; ?>">
          <td>
            <div class="d-flex align-items-center">
              <img src="<?php echo '../' . $record['profile_photo']; ?>" 
                   onerror="this.src='../assets/profile_pic/user.png';" 
                   class="employee-img rounded-circle me-2" 
                   width="40" 
                   height="40"
                   alt="Profile">
              <div>
                <span class="fw-semibold"><?php echo $record['full_name']; ?></span><br>
                <small class="text-muted"><?php echo $record['role']; ?></small>
              </div>
            </div>
          </td>
          <td><?php echo date('Y-m-d', strtotime($record['attendance_date'])); ?></td>
          <td><?php echo $record['time_in']; ?></td>
          <td><?php echo $record['time_out']; ?></td>
          <td><?php echo number_format($record['scheduled_hours'], 1); ?> hr</td>
          <td><?php echo number_format($record['actual_hours'], 1); ?> hr</td>
          <td><span class="status-dot <?php echo $record['status_class']; ?>"></span> <?php echo $record['status_display']; ?></td>
        </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
  </div>
  </div>

<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body text-center">
        <h5 class="mb-3">Confirm Logout</h5>
        <p class="mb-0">Are you sure you want to log out?</p>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
        <form id="logoutForm" method="POST" action="logout.php" style="display:inline;">
          <input type="hidden" name="confirm_logout" value="1">
          <button type="submit" class="btn btn-danger">Yes, Log out</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="attendancerep.js?v=<?php echo time(); ?>"></script>
 <script>
   function showLogoutModal() {
     var modal = new bootstrap.Modal(document.getElementById('logoutModal'));
     modal.show();
   }
 </script>
</body>
</html>
