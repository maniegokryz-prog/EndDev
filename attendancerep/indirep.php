 <?php
require '../db_connection.php';

class IndividualReportViewer {
    private $db;
    private $employee = null;
    private $attendanceRecords = [];
    private $errors = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function loadEmployee($employee_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, employee_id, first_name, middle_name, last_name, 
                       roles, department, profile_photo
                FROM employees 
                WHERE employee_id = ?
            ");
            
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $this->db->error);
            }
            
            $stmt->bind_param('s', $employee_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row) {
                $nameParts = [];
                if ($row['first_name']) $nameParts[] = $row['first_name'];
                if ($row['middle_name']) $nameParts[] = $row['middle_name'];
                if ($row['last_name']) $nameParts[] = $row['last_name'];
                
                $this->employee = [
                    'db_id' => $row['id'],
                    'employee_id' => $row['employee_id'],
                    'name' => implode(' ', $nameParts),
                    'role' => $row['roles'] ?? 'N/A',
                    'department' => $row['department'] ?? 'N/A',
                    'image' => $row['profile_photo'] ?? '../assets/profile_pic/user.png'
                ];
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->errors[] = "Error loading employee: " . $e->getMessage();
            return false;
        }
    }
    
    public function loadAttendanceRecords($filters = []) {
        if (!$this->employee) {
            return false;
        }
        
        try {
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
                        da.status,
                        da.notes
                      FROM daily_attendance da
                      WHERE da.employee_id = ?";
            
            $whereConditions = [];
            $params = [$this->employee['db_id']];
            $types = 'i';
            
            // Filter by month and year if provided
            if (!empty($filters['month']) && !empty($filters['year'])) {
                $whereConditions[] = "MONTH(da.attendance_date) = ? AND YEAR(da.attendance_date) = ?";
                $params[] = $filters['month'];
                $params[] = $filters['year'];
                $types .= 'ii';
            }
            
            if (!empty($whereConditions)) {
                $query .= " AND " . implode(" AND ", $whereConditions);
            }
            
            $query .= " ORDER BY da.attendance_date DESC";
            
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
            $this->errors[] = "Error loading attendance: " . $e->getMessage();
            return false;
        }
    }
    
    private function processAttendanceRecord($record) {
        // Convert minutes to hours (stored as minutes in database)
        $scheduledHours = $record['scheduled_hours'] ? round($record['scheduled_hours'] / 60, 2) : '---';
        $actualHours = $record['actual_hours'] ? round($record['actual_hours'] / 60, 2) : '---';
        
        // Determine status badge
        $statusInfo = $this->determineStatusBadge($record);
        
        return [
            'id' => $record['id'],
            'attendance_date' => $record['attendance_date'],
            'time_in' => $record['time_in'] ? date('g:i A', strtotime($record['time_in'])) : '---',
            'time_out' => $record['time_out'] ? date('g:i A', strtotime($record['time_out'])) : '---',
            'scheduled_hours' => $scheduledHours,
            'actual_hours' => $actualHours,
            'late_minutes' => $record['late_minutes'] ?? 0,
            'status' => $record['status'],
            'status_display' => $statusInfo['display'],
            'status_class' => $statusInfo['class'],
            'notes' => $record['notes']
        ];
    }
    
    private function determineStatusBadge($record) {
        $status = $record['status'];
        
        // Complete = Present (green)
        if ($status === 'complete') {
            return [
                'display' => 'Present',
                'class' => 'bg-success'
            ];
        }
        
        // Incomplete = Incomplete (orange/warning)
        if ($status === 'incomplete') {
            return [
                'display' => 'Incomplete',
                'class' => 'bg-warning text-dark'
            ];
        }
        
        // Absent = Absent (red)
        if ($status === 'absent') {
            return [
                'display' => 'Absent',
                'class' => 'bg-danger'
            ];
        }
        
        // Default fallback
        return [
            'display' => ucfirst($status),
            'class' => 'bg-secondary'
        ];
    }
    
    public function getEmployee() {
        return $this->employee;
    }
    
    public function getAttendanceRecords() {
        return $this->attendanceRecords;
    }
    
    public function getErrors() {
        return $this->errors;
    }
}

// Initialize the viewer
$viewer = new IndividualReportViewer($conn);

// Get employee ID from URL
$employee_id = $_GET['id'] ?? null;

// Load employee data
$employeeLoaded = false;
if ($employee_id) {
    $employeeLoaded = $viewer->loadEmployee($employee_id);
}

$employee = $viewer->getEmployee();

// Process filters
$filters = [
    'month' => $_GET['month'] ?? null,
    'year' => $_GET['year'] ?? null
];

// Load attendance records if employee exists
if ($employeeLoaded) {
    $viewer->loadAttendanceRecords($filters);
}

$attendanceRecords = $viewer->getAttendanceRecords();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

 <!-- ✅ BOOTSTRAP 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
 <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <!-- ✅ FONT AWESOME (official CDN – works on localhost) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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

  <div class="content" id="content">
    <div class="container-fluid">
      <div class="mb-10">
    <a href="attendancerep.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa-solid fa-arrow-left me-1"></i> Back
    </a>
  </div>
    <h2 class="fw-bold mt-3 mb-4 display-4 text-dark">Individual Report</h2>

<?php if ($employee): ?>
  <div class="card p-4 shadow-sm mb-4">
    <div class="d-flex align-items-center">
      <img src="<?= $employee['image'] ?>" class="rounded-circle me-3" width="70" height="70" alt="Profile">
      <div>
        <h4 class="mb-1"><?= $employee['name'] ?></h4>
        <small class="text-muted"><?= $employee['employee_id'] ?> | <?= $employee['role'] ?></small>
        
        <!-- ✅ SHOW PROFILE BUTTON -->
        <div class="mt-2">
          <a href="../staffmanagement/staffinfo.php?id=<?= $employee['employee_id'] ?>" class="btn btn-outline-primary btn-sm">
            Show Profile
          </a>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>


    <div class="d-flex flex-wrap gap-2 mb-3">
    <!-- Month -->
    <div class="dropdown">
      <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        Select Month
      </button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item month-option" href="#">January</a></li>
        <li><a class="dropdown-item month-option" href="#">February</a></li>
        <li><a class="dropdown-item month-option" href="#">March</a></li>
        <li><a class="dropdown-item month-option" href="#">April</a></li>
        <li><a class="dropdown-item month-option" href="#">May</a></li>
        <li><a class="dropdown-item month-option" href="#">June</a></li>
        <li><a class="dropdown-item month-option" href="#">July</a></li>
        <li><a class="dropdown-item month-option" href="#">August</a></li>
        <li><a class="dropdown-item month-option" href="#">September</a></li>
        <li><a class="dropdown-item month-option" href="#">October</a></li>
        <li><a class="dropdown-item month-option" href="#">November</a></li>
        <li><a class="dropdown-item month-option" href="#">December</a></li>
      </ul>
    </div>

    <!-- Year -->
    <div class="dropdown">
      <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        Select Year
      </button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item year-option" href="#">2024</a></li>
        <li><a class="dropdown-item year-option" href="#">2025</a></li>
        <li><a class="dropdown-item year-option" href="#">2026</a></li>
      </ul>
    </div>

    <!-- Export -->
    <div class="dropdown">
      <button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
        Export
      </button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item export-option" data-type="pdf" href="#">PDF</a></li>
        <li><a class="dropdown-item export-option" data-type="excel" href="#">Excel</a></li>
        <li><a class="dropdown-item export-option" data-type="csv" href="#">CSV</a></li>
      </ul>
    </div>
  </div>

  <!-- Table -->
  <div class="card p-3 shadow-sm">
    <div class="table-responsive">
      <table class="table align-middle table-striped">
        <thead class="table-light">
          <tr>
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
            <td colspan="6" class="text-center py-4 text-muted">
              <i class="bi bi-inbox fs-1 d-block mb-2"></i>
              <?php if (!$employee): ?>
                No employee found with ID: <?php echo htmlspecialchars($employee_id ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
              <?php else: ?>
                No attendance records found for this employee
              <?php endif; ?>
            </td>
          </tr>
          <?php else: ?>
            <?php foreach ($attendanceRecords as $record): ?>
          <tr>
            <td><?php echo date('F d, Y', strtotime($record['attendance_date'])); ?></td>
            <td><?php echo $record['time_in']; ?></td>
            <td><?php echo $record['time_out']; ?></td>
            <td><?php echo $record['scheduled_hours'] === '---' ? '---' : number_format($record['scheduled_hours'], 2) . ' hrs'; ?></td>
            <td><?php echo $record['actual_hours'] === '---' ? '---' : number_format($record['actual_hours'], 2) . ' hrs'; ?></td>
            <td><span class="badge <?php echo $record['status_class']; ?>"><?php echo $record['status_display']; ?></span></td>
          </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>











<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="attendancerep.js"></script>
</body>
</html>
