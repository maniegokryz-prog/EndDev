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

  // Initialize Date Range Picker
  $('#dateRangePicker').daterangepicker({
    autoUpdateInput: false,
    locale: {
      cancelLabel: 'Clear',
      format: 'YYYY-MM-DD'
    }
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
</body>
</html>
