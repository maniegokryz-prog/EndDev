<<<<<<< HEAD
<?php
require '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login/login.php');
    exit;
}

// Get employee ID from URL (can be employee_id string or database id)
$employee_identifier = $_GET['id'] ?? null;

if (!$employee_identifier) {
    header('Location: attendancerep.php?error=no_employee');
    exit;
}

// Check for date range parameters (from calendar date range picker)
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$use_date_range = false;

// Validate date range if provided
if ($start_date && $end_date) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $diff = $start->diff($end);
        $days_diff = $diff->days;
        
        // Validate 16-day limit
        if ($days_diff <= 15 && $start <= $end) {
            $use_date_range = true;
        }
    }
}

// Get month and year filters (default to current month) - used when no date range
$selected_month = $_GET['month'] ?? date('Y-m');

// Validate month format (should be YYYY-MM)
if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $selected_month = date('Y-m');
}

$month_year_parts = explode('-', $selected_month);
$selected_year = isset($month_year_parts[0]) && is_numeric($month_year_parts[0]) ? $month_year_parts[0] : date('Y');
$selected_month_num = isset($month_year_parts[1]) && is_numeric($month_year_parts[1]) ? $month_year_parts[1] : date('m');

// Ensure month is zero-padded (01-12)
$selected_month_num = str_pad($selected_month_num, 2, '0', STR_PAD_LEFT);

// Load employee data
$employee = null;
$employee_db_id = null;

// Check if identifier is a database ID (numeric) or employee_id (string like MA20230001)
if (is_numeric($employee_identifier)) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->bind_param('i', $employee_identifier);
} else {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
    $stmt->bind_param('s', $employee_identifier);
}

$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    header('Location: attendancerep.php?error=employee_not_found');
    exit;
}

$employee_db_id = $employee['id'];
$employee_code = $employee['employee_id'];

// Load attendance records for the selected month from SQLite (kiosk database)
$attendance_records = [];

// Path to SQLite database where kiosk stores attendance
$sqlite_db_path = __DIR__ . '/../faceid/database/kiosk_local.db';

if (!file_exists($sqlite_db_path)) {
    die("<div style='padding:20px;'><h2 style='color:red;'>Error: Kiosk database not found!</h2>
         <p>The attendance database is not available at: " . htmlspecialchars($sqlite_db_path) . "</p>
         <p>Please ensure the face recognition kiosk system has been initialized.</p></div>");
}

try {
    // Connect to SQLite database
    $sqlite_db = new PDO('sqlite:' . $sqlite_db_path);
    $sqlite_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query attendance records from SQLite - with date range support
    if ($use_date_range) {
        // Use date range filter
        $query = "SELECT 
                    id,
                    attendance_date,
                    time_in,
                    time_out,
                    scheduled_hours,
                    actual_hours,
                    late_minutes,
                    early_departure_minutes,
                    overtime_minutes,
                    break_time_minutes,
                    status,
                    notes
                  FROM daily_attendance
                  WHERE employee_id = :employee_id
                  AND attendance_date BETWEEN :start_date AND :end_date
                  ORDER BY attendance_date DESC";
        
        $stmt = $sqlite_db->prepare($query);
        $stmt->execute([
            ':employee_id' => $employee_db_id,
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ]);
    } else {
        // Use month filter (default)
        $query = "SELECT 
                    id,
                    attendance_date,
                    time_in,
                    time_out,
                    scheduled_hours,
                    actual_hours,
                    late_minutes,
                    early_departure_minutes,
                    overtime_minutes,
                    break_time_minutes,
                    status,
                    notes
                  FROM daily_attendance
                  WHERE employee_id = :employee_id
                  AND strftime('%Y-%m', attendance_date) = :selected_month
                  ORDER BY attendance_date DESC";
        
        $stmt = $sqlite_db->prepare($query);
        $stmt->execute([
            ':employee_id' => $employee_db_id,
            ':selected_month' => $selected_month
        ]);
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div style='padding:20px;'><h2 style='color:red;'>Database Error!</h2>
         <p>Error reading from kiosk database: " . htmlspecialchars($e->getMessage()) . "</p></div>");
}

foreach ($rows as $row) {
    // Format data for display
    $timeIn = $row['time_in'] ? date('g:i A', strtotime($row['time_in'])) : 'N/A';
    $timeOut = $row['time_out'] ? date('g:i A', strtotime($row['time_out'])) : 'N/A';
    
    // Calculate hours
    $scheduledHours = $row['scheduled_hours'] ? round($row['scheduled_hours'] / 60, 1) : 0;
    $actualHours = $row['actual_hours'] ? round($row['actual_hours'] / 60, 1) : 0;
    $breakHours = $row['break_time_minutes'] ? round($row['break_time_minutes'] / 60, 1) : 0;
    
    // Determine status
    $statusBadge = 'bg-secondary';
    $statusText = 'N/A';
    
    if ($row['status'] === 'complete') {
        if ($row['late_minutes'] > 0) {
            $statusBadge = 'bg-danger';
            $statusText = 'Late';
        } elseif ($row['early_departure_minutes'] > 0) {
            $statusBadge = 'bg-warning text-dark';
            $statusText = 'Early Out';
        } else {
            $statusBadge = 'bg-success';
            $statusText = 'On Time';
        }
    } else {
        $statusBadge = 'bg-secondary';
        $statusText = 'Incomplete';
    }
    
    $attendance_records[] = [
        'date' => date('F j, Y', strtotime($row['attendance_date'])),
        'date_short' => date('M d, Y', strtotime($row['attendance_date'])),
        'day' => date('l', strtotime($row['attendance_date'])),
        'time_in' => $timeIn,
        'time_out' => $timeOut,
        'break_hours' => $breakHours,
        'actual_hours' => $actualHours,
        'scheduled_hours' => $scheduledHours,
        'late_minutes' => $row['late_minutes'],
        'early_minutes' => $row['early_departure_minutes'],
        'overtime_minutes' => $row['overtime_minutes'],
        'status_badge' => $statusBadge,
        'status_text' => $statusText,
        'notes' => $row['notes']
    ];
}

// Close SQLite connection
$sqlite_db = null;

// Calculate summary statistics
$total_days = count($attendance_records);
$total_hours = array_sum(array_column($attendance_records, 'actual_hours'));
$total_late_days = count(array_filter($attendance_records, function($r) { return $r['late_minutes'] > 0; }));
$on_time_days = count(array_filter($attendance_records, function($r) { return $r['status_text'] === 'On Time'; }));

// Generate month options for dropdown
$month_options = [
    '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
    '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
    '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];

// Validate selected_month_num exists in month_options
if (!isset($month_options[$selected_month_num])) {
    $selected_month_num = date('m');
}

// Get display name for selected month
$selected_month_name = $month_options[$selected_month_num];

// Generate year options (last 3 years to next year)
$current_year = date('Y');
$year_options = range($current_year - 2, $current_year + 1);
=======
 <?php
require_once '../db_connection.php';

$id = $_GET['id'] ?? null;
$employee = null;
$hireYear = date('Y'); // Default to current year

if ($id) {
    // Fetch employee data from database
    $stmt = $conn->prepare("SELECT employee_id, first_name, middle_name, last_name, roles, hire_date, profile_photo FROM employees WHERE employee_id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $fullName = trim($row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . $row['last_name']);
        
        $employee = [
            'name' => $fullName,
            'role' => $row['roles'] ?? 'N/A',
            'image' => $row['profile_photo'] ?? '../assets/profile_pic/user.png',
            'hire_date' => $row['hire_date']
        ];
        
        // Get hire year for dynamic year dropdown
        if (!empty($row['hire_date'])) {
            $hireYear = date('Y', strtotime($row['hire_date']));
        }
    }
    $stmt->close();
}

// Generate year options from hire year to current year
$currentYear = date('Y');
$yearOptions = [];
for ($year = $hireYear; $year <= $currentYear; $year++) {
    $yearOptions[] = $year;
}

// Fetch attendance records
$attendanceRecords = [];
if ($id) {
    // Get employee's internal ID
    $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $empRow = $result->fetch_assoc();
        $employeeInternalId = $empRow['id'];
        
        // Build attendance query with filters
        $query = "SELECT 
                    attendance_date, 
                    time_in, 
                    time_out, 
                    scheduled_hours, 
                    actual_hours, 
                    late_minutes,
                    early_departure_minutes,
                    overtime_minutes,
                    status 
                  FROM daily_attendance 
                  WHERE employee_id = ?";
        
        $params = [$employeeInternalId];
        $types = "i";
        
        // Apply filters from GET parameters
        $filterMonth = $_GET['month'] ?? null;
        $filterYear = $_GET['year'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        
        if ($startDate && $endDate) {
            $query .= " AND attendance_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= "ss";
        } elseif ($filterMonth && $filterYear) {
            $query .= " AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?";
            $params[] = $filterMonth;
            $params[] = $filterYear;
            $types .= "ii";
        } elseif ($filterYear) {
            $query .= " AND YEAR(attendance_date) = ?";
            $params[] = $filterYear;
            $types .= "i";
        }
        
        $query .= " ORDER BY attendance_date DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $attendanceRecords[] = $row;
        }
        $stmt->close();
    }
}
>>>>>>> de54bce0e298425ce30c77eb7e2cb27b74dc8ef5
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
<<<<<<< HEAD
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="attendancerep.css">
  
  <style>
    /* Ensure dropdowns are clickable and visible */
    .dropdown-menu {
      z-index: 9999 !important;
      max-height: 400px;
      overflow-y: auto;
    }
    .dropdown-toggle::after {
      vertical-align: middle;
    }
    
    /* Print styles for PDF export */
    @media print {
      /* Hide navigation and non-essential elements */
      .sidebar,
      .top-navbar,
      .btn,
      button,
      .dropdown,
      .no-print {
        display: none !important;
      }
      
      /* Show only the content */
      .content {
        margin: 0 !important;
        padding: 20px !important;
        width: 100% !important;
      }
      
      /* Make sure cards and tables print nicely */
      .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        page-break-inside: avoid;
      }
      
      /* Table styling for print */
      table {
        width: 100% !important;
        font-size: 10pt !important;
      }
      
      thead {
        display: table-header-group;
      }
      
      tfoot {
        display: table-footer-group;
      }
      
      tr {
        page-break-inside: avoid;
      }
      
      /* Badge styling for print */
      .badge {
        border: 1px solid #000 !important;
        background-color: #fff !important;
        color: #000 !important;
      }
      
      /* Add print header */
      .content::before {
        content: "Daily Time Record (DTR) Report";
        display: block;
        font-size: 18pt;
        font-weight: bold;
        text-align: center;
        margin-bottom: 20px;
      }
    }
  </style>
</head>

<body>
=======
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  
  <!-- Daterangepicker CSS -->
  <link rel="stylesheet" type="text/css" href="../assets/vendor/daterangepicker/daterangepicker.css" />

  <!-- Custom CSS -->
  <link rel="stylesheet" href="attendancerep.css">
</head>

<body>
 <body>
>>>>>>> de54bce0e298425ce30c77eb7e2cb27b74dc8ef5
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
<<<<<<< HEAD
      <div class="mb-3">
        <a href="attendancerep.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i> Back
        </a>
      </div>
      
      <h2 class="fw-bold mt-3 mb-4 display-4 text-dark">Individual DTR Report</h2>

      <!-- Employee Info Card -->
      <div class="card p-4 shadow-sm mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
          <div class="d-flex align-items-center">
            <img src="<?php echo $employee['profile_photo'] ?? '../assets/profile_pic/user.png'; ?>" 
                 onerror="this.src='../assets/profile_pic/user.png';"
                 class="rounded-circle me-3" 
                 width="70" 
                 height="70" 
                 alt="Profile">
            <div>
              <h4 class="mb-1"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name']); ?></h4>
              <small class="text-muted">
                <?php echo htmlspecialchars($employee_code); ?> | 
                <?php echo htmlspecialchars($employee['roles'] ?? 'N/A'); ?> | 
                <?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?>
              </small>
              <div class="mt-2">
                <a href="../staffmanagement/staffinfo.php?id=<?php echo $employee_code; ?>" class="btn btn-outline-primary btn-sm">
                  <i class="bi bi-person-circle me-1"></i> View Profile
                </a>
              </div>
            </div>
          </div>
          
          <!-- Summary Stats -->
          <div class="text-end">
            <div class="small text-muted">
              Period: 
              <?php 
                if ($use_date_range) {
                  echo date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date));
                } else {
                  echo date('F Y', strtotime($selected_month . '-01'));
                }
              ?>
            </div>
            <div class="mt-2">
              <span class="badge bg-primary">Total Days: <?php echo $total_days; ?></span>
              <span class="badge bg-success">On Time: <?php echo $on_time_days; ?></span>
              <span class="badge bg-danger">Late: <?php echo $total_late_days; ?></span>
            </div>
            <div class="mt-1 fw-bold text-primary">Total Hours: <?php echo number_format($total_hours, 1); ?> hrs</div>
          </div>
        </div>
      </div>

      <!-- Filters and Export -->
      <div class="card p-3 shadow-sm mb-3">
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
          <div class="d-flex flex-wrap gap-2">
            <!-- Month Dropdown -->
            <div class="dropdown">
              <button class="btn btn-outline-primary dropdown-toggle" 
                      type="button" 
                      id="monthDropdown" 
                      data-bs-toggle="dropdown" 
                      data-bs-auto-close="true"
                      aria-expanded="false">
                <?php echo htmlspecialchars($selected_month_name); ?>
              </button>
              <ul class="dropdown-menu" aria-labelledby="monthDropdown" style="max-height: 300px; overflow-y: auto;">
                <?php foreach ($month_options as $num => $name): ?>
                  <li>
                    <a class="dropdown-item month-option <?php echo ($num === $selected_month_num) ? 'active' : ''; ?>" 
                       href="indirep.php?id=<?php echo $employee_db_id; ?>&month=<?php echo $selected_year . '-' . $num; ?>">
                      <?php echo htmlspecialchars($name); ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>

            <!-- Year Dropdown -->
            <div class="dropdown">
              <button class="btn btn-outline-primary dropdown-toggle" 
                      type="button" 
                      id="yearDropdown" 
                      data-bs-toggle="dropdown"
                      data-bs-auto-close="true" 
                      aria-expanded="false">
                <?php echo $selected_year; ?>
              </button>
              <ul class="dropdown-menu" aria-labelledby="yearDropdown">
                <?php foreach ($year_options as $year): ?>
                  <li>
                    <a class="dropdown-item year-option <?php echo ($year == $selected_year) ? 'active' : ''; ?>" 
                       href="indirep.php?id=<?php echo $employee_db_id; ?>&month=<?php echo $year . '-' . $selected_month_num; ?>">
                      <?php echo $year; ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
            
            <button class="btn btn-info" onclick="loadReport()">
              <i class="bi bi-arrow-clockwise me-1"></i> Load Report
            </button>
          </div>

          <!-- Export Buttons -->
          <div class="dropdown">
            <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="bi bi-download me-1"></i> Export
            </button>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item export-option" href="#" data-type="pdf"><i class="bi bi-file-pdf me-2"></i> PDF</a></li>
              <li><a class="dropdown-item export-option" href="#" data-type="excel"><i class="bi bi-file-excel me-2"></i> Excel</a></li>
              <li><a class="dropdown-item export-option" href="#" data-type="csv"><i class="bi bi-file-earmark-text me-2"></i> CSV</a></li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Attendance Table -->
      <div class="card p-3 shadow-sm">
        <?php if (empty($attendance_records)): ?>
          <div class="text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
            <h5 class="text-muted">No attendance records found</h5>
            <p class="text-muted">No attendance data for <?php echo date('F Y', strtotime($selected_month . '-01')); ?></p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle table-hover" id="attendanceTable">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th>Day</th>
                  <th>Time In</th>
                  <th>Time Out</th>
                  <th>Break</th>
                  <th>Total Hours</th>
                  <th>Status</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($attendance_records as $record): ?>
                  <tr>
                    <td><?php echo $record['date_short']; ?></td>
                    <td><span class="badge bg-light text-dark"><?php echo $record['day']; ?></span></td>
                    <td>
                      <i class="bi bi-box-arrow-in-right text-success"></i>
                      <?php echo $record['time_in']; ?>
                    </td>
                    <td>
                      <i class="bi bi-box-arrow-left text-danger"></i>
                      <?php echo $record['time_out']; ?>
                    </td>
                    <td><?php echo $record['break_hours']; ?> hr</td>
                    <td>
                      <strong><?php echo $record['actual_hours']; ?> hrs</strong>
                      <?php if ($record['overtime_minutes'] > 0): ?>
                        <br><small class="text-info">+<?php echo $record['overtime_minutes']; ?> min OT</small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge <?php echo $record['status_badge']; ?>">
                        <?php echo $record['status_text']; ?>
                      </span>
                      <?php if ($record['late_minutes'] > 0): ?>
                        <br><small class="text-danger">Late: <?php echo $record['late_minutes']; ?> min</small>
                      <?php endif; ?>
                      <?php if ($record['early_minutes'] > 0): ?>
                        <br><small class="text-warning">Early: <?php echo $record['early_minutes']; ?> min</small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($record['notes']): ?>
                        <small class="text-muted"><?php echo htmlspecialchars($record['notes']); ?></small>
                      <?php else: ?>
                        <small class="text-muted">-</small>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="table-light">
                <tr>
                  <th colspan="5" class="text-end">Total:</th>
                  <th><strong><?php echo number_format($total_hours, 1); ?> hrs</strong></th>
                  <th colspan="2"></th>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

<script>
// Store current employee ID for Load Report button
const currentEmployeeId = '<?php echo $employee_db_id; ?>';
let selectedMonth = '<?php echo $selected_month_num; ?>';
let selectedYear = '<?php echo $selected_year; ?>';

console.log('Initial values:', { currentEmployeeId, selectedMonth, selectedYear });

// Note: Month and Year dropdowns now use direct href links - no JavaScript needed!
// The dropdown items will navigate directly when clicked.

// Load report button function (optional - since dropdowns now work with direct links)
function loadReport() {
  const monthYear = selectedYear + '-' + selectedMonth;
  console.log('Loading report for:', monthYear, 'Employee ID:', currentEmployeeId);
  window.location.href = `indirep.php?id=${currentEmployeeId}&month=${monthYear}`;
}

  // Export functionality
  document.querySelectorAll('.export-option').forEach(option => {
    option.addEventListener('click', function(e) {
      e.preventDefault();
      const exportType = this.dataset.type;
      exportReport(exportType);
    });
  });

  function exportReport(type) {
    const employeeName = '<?php echo addslashes($employee['first_name'] . ' ' . $employee['last_name']); ?>';
    const employeeId = '<?php echo addslashes($employee['employee_id']); ?>';
    const department = '<?php echo addslashes($employee['department'] ?? 'N/A'); ?>';
    
    // Get period text from PHP
    <?php if ($use_date_range): ?>
    const periodText = '<?php echo date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)); ?>';
    <?php else: ?>
    const periodText = '<?php echo date('F Y', strtotime($selected_month . '-01')); ?>';
    <?php endif; ?>
    
    if (type === 'pdf') {
      window.print();
    } else if (type === 'excel') {
      exportToExcel(employeeName, employeeId, department, periodText);
    } else if (type === 'csv') {
      exportToCSV(employeeName, employeeId, department, periodText);
    }
  }

  function exportToExcel(employeeName, employeeId, department, periodText) {
    const table = document.getElementById('attendanceTable');
    if (!table) {
      alert('No data to export');
      return;
    }
    
    let csv = [];
    
    // Add UTF-8 BOM for Excel compatibility
    const BOM = '\uFEFF';
    
    // Add header information
    csv.push(['Daily Time Record (DTR) Report']);
    csv.push(['Employee Name:', employeeName]);
    csv.push(['Employee ID:', employeeId]);
    csv.push(['Department:', department]);
    csv.push(['Period:', periodText]);
    csv.push([]); // Empty row
    
    // Get table headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
      headers.push(th.textContent.trim());
    });
    csv.push(headers);
    
    // Get data rows
    table.querySelectorAll('tbody tr').forEach(tr => {
      const row = [];
      tr.querySelectorAll('td').forEach(td => {
        let text = td.textContent.trim().replace(/\s+/g, ' ');
        row.push(text);
      });
      csv.push(row);
    });
    
    // Add summary from tfoot if exists
    const tfoot = table.querySelector('tfoot');
    if (tfoot) {
      csv.push([]); // Empty row
      tfoot.querySelectorAll('tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('th, td').forEach(cell => {
          let text = cell.textContent.trim().replace(/\s+/g, ' ');
          row.push(text);
        });
        csv.push(row);
      });
    }
    
    // Convert to CSV string with BOM
    const csvContent = BOM + csv.map(row => 
      row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')
    ).join('\r\n');
    
    // Download as Excel file
    const blob = new Blob([csvContent], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `DTR_${employeeName.replace(/\s+/g, '_')}_${periodText.replace(/[\s,]/g, '_')}.xls`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  function exportToCSV(employeeName, employeeId, department, periodText) {
    const table = document.getElementById('attendanceTable');
    if (!table) {
      alert('No data to export');
      return;
    }
    
    let csv = [];
    
    // Add header information
    csv.push(['Daily Time Record (DTR) Report']);
    csv.push(['Employee Name:', employeeName]);
    csv.push(['Employee ID:', employeeId]);
    csv.push(['Department:', department]);
    csv.push(['Period:', periodText]);
    csv.push([]); // Empty row
    
    // Get table headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
      headers.push(th.textContent.trim());
    });
    csv.push(headers);
    
    // Get data rows
    table.querySelectorAll('tbody tr').forEach(tr => {
      const row = [];
      tr.querySelectorAll('td').forEach(td => {
        let text = td.textContent.trim().replace(/\s+/g, ' ');
        row.push(text);
      });
      csv.push(row);
    });
    
    // Add summary from tfoot if exists
    const tfoot = table.querySelector('tfoot');
    if (tfoot) {
      csv.push([]); // Empty row
      tfoot.querySelectorAll('tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('th, td').forEach(cell => {
          let text = cell.textContent.trim().replace(/\s+/g, ' ');
          row.push(text);
        });
        csv.push(row);
      });
    }
    
    // Convert to CSV string
    const csvContent = csv.map(row => 
      row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')
    ).join('\n');
    
    // Download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `DTR_${employeeName.replace(/\s+/g, '_')}_${periodText.replace(/[\s,]/g, '_')}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }
</script>
<!-- Bootstrap JS must load before our custom script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Commented out to prevent conflicts with dropdown functionality -->
<!-- <script src="attendancerep.js"></script> -->
=======
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
        <small class="text-muted"><?= $id ?> | <?= $employee['role'] ?></small>
        
        <!-- ✅ SHOW PROFILE BUTTON -->
        <div class="mt-2">
          <a a href="../staffmanagement/staffinfo.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm">
            Show Profile
          </a>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>


    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <!-- Month -->
    <div class="dropdown">
      <button class="btn btn-outline-primary dropdown-toggle" type="button" id="monthDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <span id="selectedMonth">Select Month</span>
      </button>
      <ul class="dropdown-menu" aria-labelledby="monthDropdown">
        <li><a class="dropdown-item month-option" href="#" data-month="1">January</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="2">February</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="3">March</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="4">April</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="5">May</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="6">June</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="7">July</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="8">August</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="9">September</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="10">October</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="11">November</a></li>
        <li><a class="dropdown-item month-option" href="#" data-month="12">December</a></li>
      </ul>
    </div>

    <!-- Year -->
    <div class="dropdown">
      <button class="btn btn-outline-primary dropdown-toggle" type="button" id="yearDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <span id="selectedYear">Select Year</span>
      </button>
      <ul class="dropdown-menu" aria-labelledby="yearDropdown" id="yearDropdownMenu">
        <?php foreach ($yearOptions as $year): ?>
          <li><a class="dropdown-item year-option" href="#" data-year="<?= $year ?>"><?= $year ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Date Range Picker -->
    <div>
      <input type="text" class="form-control" id="dateRangePicker" placeholder="Select Date Range" style="min-width: 250px;">
    </div>

    <!-- Filter Button -->
    <button class="btn btn-primary" id="filterBtn">
      <i class="bi bi-filter me-1"></i> Filter
    </button>

    <!-- Reset Button -->
    <button class="btn btn-outline-secondary" id="resetBtn">
      <i class="bi bi-arrow-clockwise me-1"></i> Reset
    </button>

    <!-- Export -->
    <div class="dropdown ms-auto">
      <button class="btn btn-outline-success dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download me-1"></i> Export
      </button>
      <ul class="dropdown-menu" aria-labelledby="exportDropdown">
        <li><a class="dropdown-item export-option" href="#" data-type="excel">
          <i class="bi bi-file-earmark-excel me-2"></i>Excel
        </a></li>
        <li><a class="dropdown-item export-option" href="#" data-type="pdf">
          <i class="bi bi-file-earmark-pdf me-2"></i>PDF
        </a></li>
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
            <th>Total Hours</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($attendanceRecords) > 0): ?>
            <?php foreach ($attendanceRecords as $record): ?>
              <?php
                // Determine status and badge
                $status = strtolower(trim($record['status']));
                $badgeClass = 'bg-secondary';
                $statusLabel = 'Unknown';
                
                if ($status === 'complete') {
                    $badgeClass = 'bg-success';
                    $statusLabel = 'Present';
                } elseif ($status === 'incomplete') {
                    $badgeClass = 'bg-warning text-dark';
                    $statusLabel = 'Incomplete';
                } elseif ($status === 'absent') {
                    $badgeClass = 'bg-danger';
                    $statusLabel = 'Absent';
                }
                
                // Format date
                $formattedDate = date('F d, Y', strtotime($record['attendance_date']));
                
                // Format time_in and time_out
                $timeIn = $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-';
                $timeOut = $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-';
                
                // Convert minutes to hours for display (scheduled_hours and actual_hours are stored in minutes)
                $scheduledHours = $record['scheduled_hours'] ? round($record['scheduled_hours'] / 60, 1) : '-';
                $actualHours = $record['actual_hours'] ? round($record['actual_hours'] / 60, 1) : '-';
                
                // Add units
                $scheduledHoursDisplay = is_numeric($scheduledHours) ? $scheduledHours . ' hrs' : $scheduledHours;
                $actualHoursDisplay = is_numeric($actualHours) ? $actualHours . ' hrs' : $actualHours;
              ?>
              <tr>
                <td><?= $formattedDate ?></td>
                <td><?= $timeIn ?></td>
                <td><?= $timeOut ?></td>
                <td><?= $scheduledHoursDisplay ?></td>
                <td><?= $actualHoursDisplay ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No attendance records found
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>











<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<!-- jQuery (required for daterangepicker) -->
<script src="../assets/vendor/jquery/jquery.min.js"></script>

<!-- Moment.js (required for daterangepicker) -->
<script src="../assets/vendor/moment/moment.min.js"></script>

<!-- Daterangepicker JS -->
<script src="../assets/vendor/daterangepicker/daterangepicker.min.js"></script>

<!-- Bootstrap Bundle -->
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script src="attendancerep.js"></script>

<script>
// Individual Report Page Specific Scripts
$(document).ready(function() {
  const employeeId = '<?= $id ?>';
  let selectedMonth = null;
  let selectedYear = null;
  let selectedDateRange = null;

  // Get URL parameters and restore filter state
  const urlParams = new URLSearchParams(window.location.search);
  const monthParam = urlParams.get('month');
  const yearParam = urlParams.get('year');
  const startDateParam = urlParams.get('start_date');
  const endDateParam = urlParams.get('end_date');

  // Restore month filter if present
  if (monthParam) {
    selectedMonth = monthParam;
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                        'July', 'August', 'September', 'October', 'November', 'December'];
    $('#selectedMonth').text(monthNames[parseInt(monthParam) - 1]);
  }

  // Restore year filter if present
  if (yearParam) {
    selectedYear = yearParam;
    $('#selectedYear').text(yearParam);
  }

  // Restore date range filter if present
  if (startDateParam && endDateParam) {
    selectedDateRange = {
      start: startDateParam,
      end: endDateParam
    };
    $('#dateRangePicker').val(startDateParam + ' to ' + endDateParam);
  }

  // Initialize Date Range Picker
  $('#dateRangePicker').daterangepicker({
    autoUpdateInput: false,
    locale: {
      cancelLabel: 'Clear',
      format: 'YYYY-MM-DD'
    },
    startDate: startDateParam || moment(),
    endDate: endDateParam || moment()
  });

  // Update date range input when dates are selected
  $('#dateRangePicker').on('apply.daterangepicker', function(ev, picker) {
    $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
    selectedDateRange = {
      start: picker.startDate.format('YYYY-MM-DD'),
      end: picker.endDate.format('YYYY-MM-DD')
    };
  });

  $('#dateRangePicker').on('cancel.daterangepicker', function(ev, picker) {
    $(this).val('');
    selectedDateRange = null;
  });

  // Month selection
  $('.month-option').click(function(e) {
    e.preventDefault();
    selectedMonth = $(this).data('month');
    $('#selectedMonth').text($(this).text());
  });

  // Year selection
  $('.year-option').click(function(e) {
    e.preventDefault();
    selectedYear = $(this).data('year');
    $('#selectedYear').text($(this).text());
  });

  // Filter button
  $('#filterBtn').click(function() {
    let params = new URLSearchParams();
    params.append('id', employeeId);
    
    if (selectedMonth) {
      params.append('month', selectedMonth);
    }
    if (selectedYear) {
      params.append('year', selectedYear);
    }
    if (selectedDateRange) {
      params.append('start_date', selectedDateRange.start);
      params.append('end_date', selectedDateRange.end);
    }
    
    // Reload page with filters
    window.location.href = 'indirep.php?' + params.toString();
  });

  // Reset button
  $('#resetBtn').click(function() {
    window.location.href = 'indirep.php?id=' + employeeId;
  });

  // Export functionality
  $('.export-option').click(function(e) {
    e.preventDefault();
    const exportType = $(this).data('type');
    
    let params = new URLSearchParams();
    params.append('id', employeeId);
    params.append('export', exportType);
    
    if (selectedMonth) {
      params.append('month', selectedMonth);
    }
    if (selectedYear) {
      params.append('year', selectedYear);
    }
    if (selectedDateRange) {
      params.append('start_date', selectedDateRange.start);
      params.append('end_date', selectedDateRange.end);
    }
    
    // Redirect to export handler
    window.location.href = 'export_individual.php?' + params.toString();
  });
});
</script>
>>>>>>> de54bce0e298425ce30c77eb7e2cb27b74dc8ef5
</body>
</html>
