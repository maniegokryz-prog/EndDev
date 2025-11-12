<?php
require '../db_connection.php';

class AttendanceReportViewer {
    private $db;
    private $attendanceRecords = [];
    private $errors = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function loadTodayAttendance($filters = []) {
        try {
            // Get current date
            $currentDate = date('Y-m-d');
            
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
                      WHERE da.attendance_date = ?";
            
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
            'actual_hours' => $record['actual_hours'] ?? 0,
            'scheduled_hours' => $record['scheduled_hours'] ?? 0,
            'late_minutes' => $record['late_minutes'] ?? 0,
            'status' => $record['status'],
            'status_display' => $statusInfo['display'],
            'status_class' => $statusInfo['class'],
            'notes' => $record['notes']
        ];
    }
    
    private function determineStatus($record) {
        $status = $record['status'];
        
        // If status is complete
        if ($status === 'complete') {
            return [
                'display' => 'Complete',
                'class' => 'status-ontime' // green dot
            ];
        }
        
        // If incomplete, check if late
        if ($status === 'incomplete') {
            if ($record['late_minutes'] > 0) {
                return [
                    'display' => 'Late',
                    'class' => 'status-late' // red dot
                ];
            } else {
                return [
                    'display' => 'On-Time',
                    'class' => 'status-ontime' // green dot
                ];
            }
        }
        
        // Default fallback
        return [
            'display' => ucfirst($status),
            'class' => 'status-ontime'
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

// Process filter parameters
$filters = [
    'role' => $_GET['role'] ?? '',
    'department' => $_GET['department'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Load today's attendance records
$loadSuccess = $viewer->loadTodayAttendance($filters);
$attendanceRecords = $viewer->getAttendanceRecords();
$currentDate = date('F d, Y'); // Format: November 11, 2025
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Reports</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Custom CSS -->
<link rel="stylesheet" href="attendancerep.css">
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
<!----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
  <div class="content pt-3" id="content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold display-4 text-dark">Attendance Reports</h2>
      <div class="d-flex align-items-center gap-3">
        <span class="text-muted">Today: <strong><?php echo $currentDate; ?></strong></span>
        <a href="exporep.php" class="btn btn-warning">Export</a>
      </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <select class="form-select" id="roleFilter">
        <option value="">All Roles</option>
        <option value="Admin">Admin</option>
        <option value="Faculty_Member">Faculty Member</option>
        <option value="Non-Teaching_Personnel">Non-Teaching Personnel</option>
      </select>
    </div>
    <div class="col-md-5">
      <select class="form-select" id="deptFilter">
        <option value="">All Departments</option>
        <option value="Information Systems">Information Systems</option>
        <option value="Office Management">Office Management</option>
        <option value="Accounting Information Systems">Accounting Information Systems</option>
        <option value="Hotel and Restaurant Services">Hotel and Restaurant Services</option>
        <option value="Registrar's Office">Registrar's Office</option>
        <option value="Guidance and Counseling Office">Guidance and Counseling Office</option>
        <option value="Bsom">Bsom</option>
      </select>
    </div>
    <div class="col-md-4">
      <input type="text" id="searchBox" class="form-control" placeholder="Search by name or ID">
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
            No attendance records found for today
          </td>
        </tr>
        <?php else: ?>
          <?php foreach ($attendanceRecords as $record): ?>
        <tr data-id="<?php echo $record['employee_id']; ?>">
          <td>
            <div class="d-flex align-items-center">
              <img src="<?php echo $record['profile_photo']; ?>" 
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
          <td><?php echo number_format($record['vacant_hours'], 1); ?> hr</td>
          <td><?php echo number_format($record['actual_hours'], 1); ?> hrs</td>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="attendancerep.js"></script>
</body>
</html>
