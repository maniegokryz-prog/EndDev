<?php
// Protect this page - require authentication
require_once '../auth_guard.php';

// Get current user info
$currentUser = getCurrentUser();
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

  <!-- Custom CSS -->
  <link rel="stylesheet" href="dashboard.css">
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

  <div class="calendar-icon-wrapper">
    <button class="btn btn-sm btn-light calendar-toggle-btn" id="calendarToggleBtn">
        <i class="fas fa-calendar-alt"></i> 
    </button>
</div>
<!------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------>
 
 <div class="content" id="content">
    <div class="container-fluid">

<div class="d-flex align-items-center justify-content-between">
  <h2 class="mb-4 display-4 text-dark">Welcome!</h2>
  <button id="calendar-toggle-btn" class="btn btn-success d-sm-none d-block" style="display: none;"></button>
</div>

<div class="container-fluid px-3 mt-3">
  <div class="row g-3 align-items-start">

    <div class="col-xl-9 col-lg-8 col-md-8">
      <div class="row g-3">
        
        <div class="col-3"> 
          <div class="card text-center p-3 shadow-sm border-0 clickable-card">
            <h6 class="fw-semibold text-secondary mb-1">Present</h6>
            <p id="presentPercentage" class="display-6 fw-bold text-success mb-0">0%</p>
          </div>
        </div>

        <div class="col-3">
          <div class="card text-center p-3 shadow-sm border-0 clickable-card">
            <h6 class="fw-semibold text-secondary mb-1">Absent</h6>
            <p id="absentPercentage" class="display-6 fw-bold text-danger mb-0">0%</p>
          </div>
        </div>

        <div class="col-3">
          <div class="card text-center p-3 shadow-sm border-0 clickable-card">
            <h6 class="fw-semibold text-secondary mb-1">On Time</h6>
            <p id="onTimePercentage" class="display-6 fw-bold text-primary mb-0">0%</p>
          </div>
        </div>

        <div class="col-3">
          <div class="card text-center p-3 shadow-sm border-0 clickable-card">
            <h6 class="fw-semibold text-secondary mb-1">Late</h6>
            <p id="latePercentage" class="display-6 fw-bold text-warning mb-0">0%</p>
          </div>
        </div>
        
      </div>
    </div>

    <div class="col-xl-3 col-lg-4 col-md-4 mt-2 mt-md-0">
      <div class="attendance-feed shadow-sm p-3 bg-white rounded h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">
            <i class="bi bi-clock-history me-2"></i>Attendance Feed
            <span class="badge bg-secondary ms-2" id="feedCount" style="font-size: 0.65rem;">0</span>
          </h6>
          <button id="todayBtn" class="btn btn-sm btn-outline-primary" style="font-size: 0.75rem; padding: 2px 8px;">
            <i class="bi bi-calendar-day me-1"></i>Today
          </button>
        </div>
        <div id="attendanceList">
          <!-- Loading state -->
          <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted small mt-2 mb-0">Loading attendance feed...</p>
          </div>
        </div>
      </div>
    </div>
    
  </div>
</div>

<!-- TIME & CALENDAR SECTION (perfectly aligned) -->
<div class="row g-3 mb-4" style="margin-top: -440px;">

  <!-- TIME & DATE CARD -->
<div class="col-lg-6 col-md-12">
  <div class="card text-center d-flex flex-column justify-content-center time-card">
    <h1 id="current-time" class="fw-bold mb-2">--:-- --</h1>
    <h5 id="current-date" class="mt-2 mb-0">Loading...</h5>
  </div>
</div>


<!--calendar  button -->
<div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="calendarModalLabel">Calendar</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <!-- Clone of your calendar card -->
        <div class="card calendar-card1">
          <!-- Same calendar content here -->
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 id="cal-month-year-modal" class="fw-bold mb-0" style="font-size: 2rem;">Month Year</h6>
            <div>
              <button id="cal-prev-modal" class="btn btn-sm btn-outline-secondary me-1 px-2 py-1"
                      type="button" aria-label="Previous month">&lsaquo;</button>
              <button id="cal-next-modal" class="btn btn-sm btn-outline-secondary px-2 py-1"
                      type="button" aria-label="Next month">&rsaquo;</button>
            </div>
          </div>
          <div class="table-responsive" style="font-size: 0.8rem;">
            <table id="calendar-table-modal" class="table table-bordered text-center m-0" style="table-layout: fixed;">
              <thead class="table-light">
                <tr>
                  <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
                  <th>Thu</th><th>Fri</th><th>Sat</th>
                </tr>
              </thead>
              <tbody id="calendar-body-modal">
                <!-- JS will populate -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- CALENDAR-->
<div class="col-lg-6 col-md-12">
  <div class="card h-100 calendar-card" id="calendar-container">
      <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 id="cal-month-year" class="fw-bold mb-0" style="font-size: 2rem;">Month Year</h6>
      <div>
        <button id="cal-prev" class="btn btn-sm btn-outline-secondary me-1 px-2 py-1"
                type="button" aria-label="Previous month">&lsaquo;</button>
        <button id="cal-next" class="btn btn-sm btn-outline-secondary px-2 py-1"
                type="button" aria-label="Next month">&rsaquo;</button>
      </div>
    </div>

    <div class="table-responsive" style="font-size: 0.8rem;">
      <table id="calendar-table" class="table table-bordered text-center m-0" style="table-layout: fixed;">
        <thead class="table-light">
          <tr>
            <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th>
            <th>Thu</th><th>Fri</th><th>Sat</th>
          </tr>
        </thead>
        <tbody id="calendar-body">
          <!-- JS will populate -->
        </tbody>
    </table>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
  let currentMonth, currentYear;

  function renderCalendar(targetBodyId, targetHeaderId, month, year) {
    const monthNames = ["January", "February", "March", "April", "May", "June",
                        "July", "August", "September", "October", "November", "December"];
    const firstDay = new Date(year, month).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const calendarBody = document.getElementById(targetBodyId);
    const calendarHeader = document.getElementById(targetHeaderId);

    calendarBody.innerHTML = "";
    calendarHeader.textContent = `${monthNames[month]} ${year}`;

    let date = 1;
    for (let i = 0; i < 6; i++) {
      const row = document.createElement("tr");
      for (let j = 0; j < 7; j++) {
        const cell = document.createElement("td");
        if (i === 0 && j < firstDay) {
          cell.textContent = "";
        } else if (date > daysInMonth) {
          cell.textContent = "";
        } else {
          cell.textContent = date;
          cell.classList.add("calendar-date");
          cell.setAttribute("data-date", `${year}-${month + 1}-${date}`);

          cell.addEventListener("click", function () {
            const selectedDate = this.getAttribute("data-date");
            loadAttendanceHistory(selectedDate);
          });

          date++;
        }
        row.appendChild(cell);
      }
      calendarBody.appendChild(row);
    }
  }

  function loadAttendanceHistory(dateString) {
    alert("Loading attendance for: " + dateString);
    // Replace this with actual logic to fetch/display attendance
  }

  document.addEventListener("DOMContentLoaded", function () {
    const toggleBtn = document.getElementById("calendar-toggle-btn");
    const prevBtn = document.getElementById("cal-prev-modal");
    const nextBtn = document.getElementById("cal-next-modal");

    const today = new Date();
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    toggleBtn.textContent = today.toLocaleDateString('en-US', options);

    toggleBtn.addEventListener("click", function () {
      currentMonth = today.getMonth();
      currentYear = today.getFullYear();

      renderCalendar("calendar-body-modal", "cal-month-year-modal", currentMonth, currentYear);

      const calendarModal = new bootstrap.Modal(document.getElementById('calendarModal'));
      calendarModal.show();
    });

    prevBtn.addEventListener("click", function () {
      currentMonth--;
      if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
      }
      renderCalendar("calendar-body-modal", "cal-month-year-modal", currentMonth, currentYear);
    });

    nextBtn.addEventListener("click", function () {
      currentMonth++;
      if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
      }
      renderCalendar("calendar-body-modal", "cal-month-year-modal", currentMonth, currentYear);
    });
  });
</script>


    </div>
  </div>
</div>



 <div class="col-md-6 d-flex justify-content-left">
  <div class="card p-5 shadow-sm h-100 late-card">
    <h6 class="fw-bold">Late Today</h6>

    <!-- Scrollable Area -->
    <div class="late-list">
      <!-- JavaScript will populate this dynamically -->
    </div>
  </div>
</div>

<div class="col-md-6 d-flex justify-content-left">
  <div class="card p-5 shadow-sm h-100 on-leave-card">
    <h6 class="fw-bold">On Leave</h6>

    <!-- Scrollable Area -->
    <div class="on-leave-list">
      <!-- JavaScript will populate this dynamically -->
    </div>
  </div>
</div>





<!------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------>
  </div> <!-- container-fluid -->
  </div> <!-- content -->

  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="dashboard.js"></script>







</body>
</html>
