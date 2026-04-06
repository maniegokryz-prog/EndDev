<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../auth_guard.php';
require_once '../navigation.php';
require_once '../db_connection.php';
require_once 'dtr_utils.php'; // Include shared DTR logic

// Get current user info
$currentUser = getCurrentUser();

$id = $_GET['id'] ?? null;
$employee = null;
$hireYear = date('Y'); // Default to current year

if ($id) {
  // Fetch employee data from database
  $stmt = $conn->prepare("SELECT employee_id, first_name, middle_name, last_name, suffix, roles, hire_date, profile_photo FROM employees WHERE employee_id = ?");
  $stmt->bind_param("s", $id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $fullName = trim(preg_replace('/\s+/', ' ', $row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . $row['last_name'] . ' ' . ($row['suffix'] ?? '')));

    $imagePath = $row['profile_photo'];
    $fullPath = $imagePath ? dirname(__DIR__) . '/' . $imagePath : '';

    $finalImage = (!empty($imagePath) && file_exists($fullPath))
      ? $imagePath
      : 'assets/profile_pic/user.png';

    $employee = [
      'name' => $fullName,
      'role' => $row['roles'] ?? 'N/A',
      'image' => $finalImage,
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

    // Fetch schedule for recalculation
    $schedule = getEmployeeSchedule($conn, $employeeInternalId);

    // Build attendance query with filters
    $query = "SELECT 
                        attendance_date, 
                        time_in, 
                        break_out,
                        break_in,
                        time_out, 
                        scheduled_hours, 
                        actual_hours, 
                        late_minutes,
                        early_departure_minutes,
                        overtime_minutes,
                        status 
                      FROM daily_attendance 
                      WHERE employee_id = ? AND status != 'visit'";

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
      // Recalculate actual_hours for display consistency
      if (!isset($row['actual_hours']) || $row['actual_hours'] === null) {
        $row['actual_hours'] = calculateActualHoursWithClamping(
          $row['time_in'],
          $row['time_out'],
          $schedule,
          $row['attendance_date'],
          $employee['role'],
          $row['break_out'],
          $row['break_in'],
          $employeeInternalId
        );
      }
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
  <!-- Bootstrap CSS -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Font Awesome (Local not found, unused) -->
  <!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"> -->

  <!-- Daterangepicker CSS -->
  <link rel="stylesheet" type="text/css" href="../assets/vendor/daterangepicker/daterangepicker.css" />

  <!-- Custom CSS -->
  <link rel="stylesheet" href="attendancerep.css">

  <style>
    /* Status badge colors */
    .badge-present {
      background-color: #4caf50 !important;
      color: white !important;
      font-weight: 500;
    }

    .badge-absent {
      background-color: #f44336 !important;
      color: white !important;
      font-weight: 500;
    }

    .badge-incomplete {
      background-color: #ffc107 !important;
      color: #333 !important;
      font-weight: 500;
    }

    /* Manual status badge - lowly saturated green */
    .badge-manual {
      background-color: #a8d5ba !important;
      color: #2d5f3f !important;
      font-weight: 500;
    }

    /* Leave status badge - Blue/Purple or something distinct */
    .badge-leave {
      background-color: #17a2b8 !important;
      color: white !important;
      font-weight: 500;
    }

    /* Modern Solid Action Buttons */
    .btn-modern {
      border-radius: 8px !important;
      padding: 10px 24px !important;
      font-weight: 600 !important;
      transition: all 0.3s ease !important;
      border: 1px solid transparent !important;
    }

    .btn-solid-primary {
      background-color: #0d6efd !important;
      border-color: #0d6efd !important;
      color: #fff !important;
    }

    .btn-solid-success {
      background-color: #198754 !important;
      border-color: #198754 !important;
      color: #fff !important;
    }

    .btn-solid-danger {
      background-color: #dc3545 !important;
      border-color: #dc3545 !important;
      color: #fff !important;
    }

    .btn-solid-warning {
      background-color: #ffc107 !important;
      border-color: #ffc107 !important;
      color: #000 !important;
    }

    .btn-solid-light-gray {
      background-color: #e9ecef !important;
      border-color: #e9ecef !important;
      color: #495057 !important;
    }

    /* Unified Hover for Action Buttons */
    .btn-solid-light-gray:hover,
    .btn-solid-danger:hover,
    .btn-solid-success:hover,
    .btn-solid-primary:hover,
    .btn-solid-warning:hover,
    .btn-gray-hover:hover {
      background-color: #6c757d !important;
      border-color: #6c757d !important;
      color: #fff !important;
      opacity: 1 !important;
    }
  </style>
</head>

<body>
  <div class="top-navbar d-flex justify-content-between align-items-center p-2 shadow-sm">

    <!-- Left: Menu & Welcome -->
    <div class="d-flex align-items-center">
      <div class="menu-toggle me-3">
        <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn"></i>
      </div>
    </div>

    <!-- Right: Sync Status & Notifications -->
    <div class="d-flex align-items-center">
      <?php include '../includes/notification_bell.php'; ?>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column pt-5" id="sidebar">
    <div class="profile text-center p-3 mt-4">
      <?php
      // Include auth guard if not already included
      if (!function_exists('getCurrentUser')) {
        require_once '../auth_guard.php';
        $currentUser = getCurrentUser();
      }
      ?>
      <?php
      $sidebarPhoto = 'assets/profile_pic/user.png';
      if (!empty($currentUser['profile_photo'])) {
        $checkSidebarPath = dirname(__DIR__) . '/' . $currentUser['profile_photo'];
        if (file_exists($checkSidebarPath)) {
          $sidebarPhoto = $currentUser['profile_photo'];
        }
      }
      ?>
      <img src="<?php echo '../' . htmlspecialchars($sidebarPhoto, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile"
        class="rounded-circle mb-2" width="70" height="70" onerror="this.src='../assets/profile_pic/user.png';">
      <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h5>
      <small
        class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></small>
    </div>
    <nav class="nav flex-column px-2">
      <?php renderNavigation(isAdmin() ? 'Attendance Reports' : 'My Attendance'); ?>
    </nav>
  </div>

  <div class="content" id="content">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mt-2 mb-4">
        <h2 class="display-4 text-dark mb-0">
          <?php echo isAdmin() ? 'Individual Report' : 'My Attendance'; ?>
        </h2>
      </div>

      <?php if ($employee): ?>
        <div class="card p-4 shadow-sm mb-4">
          <div class="d-flex align-items-center">
            <img src="<?= '../' . $employee['image'] ?>" class="rounded-circle me-3" width="70" height="70" alt="Profile">
            <div>
              <h4 class="mb-1"><?= $employee['name'] ?></h4>
              <small class="text-muted"><?= $id ?> | <?= $employee['role'] ?></small>

              <!-- ✅ SHOW PROFILE BUTTON -->
              <div class="mt-2">
                <a href="../staffmanagement/staff_profile.php?id=<?= $id ?>" class="btn btn-modern btn-solid-primary btn-sm btn-gray-hover">
                  Show Profile
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>


      <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <div class="d-flex gap-2">
          <!-- Month -->
          <div class="dropdown">
            <button class="btn btn-modern btn-solid-primary dropdown-toggle btn-gray-hover" type="button" id="monthDropdown"
              data-bs-toggle="dropdown" aria-expanded="false">
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
            <button class="btn btn-modern btn-solid-primary dropdown-toggle btn-gray-hover" type="button" id="yearDropdown"
              data-bs-toggle="dropdown" aria-expanded="false">
              <span id="selectedYear">Year</span>
            </button>
            <ul class="dropdown-menu" aria-labelledby="yearDropdown" id="yearDropdownMenu">
              <?php foreach ($yearOptions as $year): ?>
                <li><a class="dropdown-item year-option" href="#" data-year="<?= $year ?>"><?= $year ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <!-- Date Range Picker -->
        <div>
          <input type="text" class="form-control" id="dateRangePicker" placeholder="Select Date Range"
            style="min-width: 250px;">
        </div>

        <!-- Filter Button -->
        <button class="btn btn-modern btn-solid-light-gray btn-gray-hover" id="filterBtn">
          <i class="bi bi-filter me-1"></i> Filter
        </button>

        <!-- Reset Button -->
        <button class="btn btn-modern btn-solid-light-gray btn-gray-hover" id="resetBtn">
          <i class="bi bi-arrow-clockwise me-1"></i> Reset Filter
        </button>

        <!-- Export - Admin Only -->
        <?php if (isAdmin()): ?>
          <div class="dropdown ms-auto">
            <button class="btn btn-modern btn-solid-success dropdown-toggle btn-gray-hover" type="button" id="exportDropdown"
              data-bs-toggle="dropdown" aria-expanded="false">
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
        <?php endif; ?>
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
                  $status_lower = strtolower(trim($record['status']));
                  
                  $html_badges = '';
                  
                  $base_status = '';
                  if (strpos($status_lower, 'manual') !== false) {
                      $base_status = 'manual';
                  } elseif (strpos($status_lower, 'incomplete') !== false) {
                      $base_status = 'incomplete';
                  } elseif (strpos($status_lower, 'complete') !== false || strpos($status_lower, 'present') !== false) {
                      $base_status = 'complete';
                  } elseif (strpos($status_lower, 'visit') !== false) {
                      $base_status = 'visit';
                  } elseif (strpos($status_lower, 'absent') !== false) {
                      $base_status = 'absent';
                  } elseif (strpos($status_lower, 'leave') !== false) {
                      $base_status = 'leave';
                  }
                  
                  $is_late = strpos($status_lower, 'late') !== false;
                  $is_undertime = strpos($status_lower, 'undertime') !== false;
                  
                  if ($base_status === 'complete') {
                      if (!$is_late && !$is_undertime) {
                          $html_badges .= '<span class="badge badge-present me-1">On-time</span>';
                      } else {
                          $html_badges .= '<span class="badge bg-warning text-dark me-1">Complete</span>';
                      }
                  } elseif ($base_status === 'manual') {
                      $badge_class = ($is_late || $is_undertime) ? 'bg-warning text-dark' : 'badge-manual';
                      $html_badges .= '<span class="badge ' . $badge_class . ' me-1">Manual</span>';
                  } elseif ($base_status === 'incomplete') {
                      $html_badges .= '<span class="badge badge-incomplete me-1">Incomplete</span>';
                  } elseif ($base_status === 'absent') {
                      $html_badges .= '<span class="badge badge-absent me-1">Absent</span>';
                  } elseif ($base_status === 'leave') {
                      $html_badges .= '<span class="badge badge-leave me-1">On Leave</span>';
                  } elseif ($base_status === 'visit') {
                      $html_badges .= '<span class="badge bg-info text-dark me-1">Visit</span>';
                  } else {
                      $html_badges .= '<span class="badge bg-secondary me-1">' . ucfirst(trim($record['status'])) . '</span>';
                  }
                  
                  if ($is_late) {
                      $html_badges .= '<span class="badge bg-warning text-dark me-1">Late</span>';
                  }
                  if ($is_undertime) {
                      $html_badges .= '<span class="badge bg-warning text-dark me-1">Undertime</span>';
                  }

                  // Format date
                  $formattedDate = date('F d, Y', strtotime($record['attendance_date']));

                  // Format time_in and time_out
                  $timeIn = $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-';
                  $timeOut = $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-';

                  // Convert minutes to hours for display (scheduled_hours and actual_hours are stored in minutes)
                  $scheduledHours = $record['scheduled_hours'] ? round($record['scheduled_hours'] / 60, 1) : '-';
                  $actualHours = $record['actual_hours'] ? round($record['actual_hours'] / 60, 1) : '-';

                  // Add tooltips
                  // Add tooltips
                  if ($record['scheduled_hours']) {
                    $sh_hours = floor($record['scheduled_hours'] / 60);
                    $sh_mins = round(fmod($record['scheduled_hours'], 60));
                    $hoverText = "{$sh_hours} hour" . ($sh_hours != 1 ? 's' : '') . " {$sh_mins} minute" . ($sh_mins != 1 ? 's' : '');
                    $scheduledHoursDisplay = "<span title=\"{$hoverText}\" style=\"cursor: help; border-bottom: 1px solid darkgreen;\">{$scheduledHours} hrs</span>";
                  } else {
                    $scheduledHoursDisplay = '-';
                  }

                  if ($record['actual_hours']) {
                    $ah_hours = floor($record['actual_hours'] / 60);
                    $ah_mins = round(fmod($record['actual_hours'], 60));
                    $hoverText = "{$ah_hours} hour" . ($ah_hours != 1 ? 's' : '') . " {$ah_mins} minute" . ($ah_mins != 1 ? 's' : '');
                    $actualHoursDisplay = "<span title=\"{$hoverText}\" style=\"cursor: help; border-bottom: 1px solid darkgreen;\">{$actualHours} hrs</span>";
                  } else {
                    $actualHoursDisplay = '-';
                  }
                  ?>
                  <tr>
                    <td><?= $formattedDate ?></td>
                    <td><?= $timeIn ?></td>
                    <td><?= $timeOut ?></td>
                    <td><?= $scheduledHoursDisplay ?></td>
                    <td><?= $actualHoursDisplay ?></td>
                    <td><?= $html_badges ?></td>
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
    $(document).ready(function () {
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
      $('#dateRangePicker').on('apply.daterangepicker', function (ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
        selectedDateRange = {
          start: picker.startDate.format('YYYY-MM-DD'),
          end: picker.endDate.format('YYYY-MM-DD')
        };
      });

      $('#dateRangePicker').on('cancel.daterangepicker', function (ev, picker) {
        $(this).val('');
        selectedDateRange = null;
      });

      // Month selection
      $('.month-option').click(function (e) {
        e.preventDefault();
        selectedMonth = $(this).data('month');
        $('#selectedMonth').text($(this).text());
      });

      // Year selection
      $('.year-option').click(function (e) {
        e.preventDefault();
        selectedYear = $(this).data('year');
        $('#selectedYear').text($(this).text());
      });

      // Filter button
      $('#filterBtn').click(function () {
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
      $('#resetBtn').click(function () {
        window.location.href = 'indirep.php?id=' + employeeId;
      });

      // Export functionality
      $('.export-option').click(function (e) {
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

  <script>
    // Logout modal function
    function showLogoutModal() {
      var modal = new bootstrap.Modal(document.getElementById('logoutModal'));
      modal.show();
    }
  </script>

  <!-- Logout Modal -->
  <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 shadow border-0 overflow-hidden">
        <div class="modal-body text-center py-4">
          <h5 class="fw-bold mb-3">Confirm Logout</h5>
          <p class="mb-0 fs-6">Are you sure you want to log out?</p>
        </div>
        <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
          <button type="button" class="btn btn-solid-light-gray px-4" data-bs-dismiss="modal">No</button>
          <form id="logoutForm" method="POST" action="logout.php" style="display:inline;">
            <input type="hidden" name="confirm_logout" value="1">
            <button type="submit" class="btn btn-solid-danger px-4">Yes, Log out</button>
          </form>
        </div>
      </div>
    </div>
  </div>

</body>

</html>