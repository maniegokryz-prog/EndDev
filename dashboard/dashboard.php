<?php
// Protect this page - require authentication
require_once '../auth_guard.php';
require_once '../navigation.php';

// TOGGLE: Set to true to hide the Sync Status widget entirely (for VPS deployment)
$hide_sync_status = false;

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
  <!-- Top Navbar -->
  <div class="top-navbar d-flex justify-content-between align-items-center p-2 shadow-sm">

    <!-- Left: Menu & Welcome -->
    <div class="d-flex align-items-center">
      <div class="menu-toggle me-3">
        <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn"></i>
      </div>
      <div class="welcome-message">
        <h5 class="mb-0 text-white">Welcome,
          <?php echo htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?>!
        </h5>
      </div>
    </div>

    <!-- Right: Sync Status & Notifications -->
    <div class="d-flex align-items-center">
      <?php
      // Sync Status Widget
      if (!$hide_sync_status) {
        if (file_exists('../admin/includes/sync_status_topbar.php')) {
          include '../admin/includes/sync_status_topbar.php';
        }
      }
      ?>
      <?php include '../includes/notification_bell.php'; ?>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column pt-5" id="sidebar">
    <div class="profile text-center p-3 mt-4">
      <img
        src="<?php echo !empty($currentUser['profile_photo']) ? '../' . htmlspecialchars($currentUser['profile_photo'], ENT_QUOTES, 'UTF-8') . '?v=' . time() : '../assets/profile_pic/user.png?v=' . time(); ?>"
        alt="Profile" class="rounded-circle mb-2" width="70" height="70"
        onerror="this.src='../assets/profile_pic/user.png';">
      <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h5>
      <small
        class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></small>
    </div>

    <nav class="nav flex-column px-2">
      <?php renderNavigation('Dashboard'); ?>
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
        <!-- <h2 class="mb-4 display-4 text-dark">Welcome!</h2> -->

      </div>

      <div class="container-fluid px-3 mt-3">

        <!-- Mobile Date Picker Button (Visible only on mobile/tablet) -->
        <div class="d-flex justify-content-center d-lg-none mb-3" style="margin-top: -24px;">
          <button id="mobile-date-btn" class="btn btn-success fw-bold shadow py-2 px-4" data-bs-toggle="modal"
            data-bs-target="#mobileCalendarModal" style="border-radius: 8px; font-size: 0.95rem; width: 100%;">
            <i class="bi bi-calendar-event me-2"></i><span id="mobile-date-btn-text">Loading...</span>
          </button>
        </div>

        <!-- Mobile Calendar Modal -->
        <div class="modal fade" id="mobileCalendarModal" tabindex="-1" aria-labelledby="mobileCalendarModalLabel"
          aria-hidden="true" style="z-index: 10500;">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px;">
              <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="mobileCalendarModalLabel">Select Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body pt-2">
                <!-- Calendar Controls -->
                <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                  <h5 id="mobile-cal-month-year" class="fw-bold mb-0 text-dark">Month Year</h5>
                  <div>
                    <button id="mobile-cal-prev" class="btn btn-sm btn-outline-secondary me-1 px-2 py-1" type="button"
                      style="border-radius: 6px;">&lsaquo;</button>
                    <button id="mobile-cal-next" class="btn btn-sm btn-outline-secondary px-2 py-1" type="button"
                      style="border-radius: 6px;">&rsaquo;</button>
                  </div>
                </div>
                <!-- Calendar Table -->
                <div class="table-responsive">
                  <table class="table table-bordered text-center m-0 shadow-sm"
                    style="table-layout: fixed; border-radius: 8px; overflow: hidden;">
                    <thead class="table-light">
                      <tr>
                        <th class="py-2 text-secondary small">Sun</th>
                        <th class="py-2 text-secondary small">Mon</th>
                        <th class="py-2 text-secondary small">Tue</th>
                        <th class="py-2 text-secondary small">Wed</th>
                        <th class="py-2 text-secondary small">Thu</th>
                        <th class="py-2 text-secondary small">Fri</th>
                        <th class="py-2 text-secondary small">Sat</th>
                      </tr>
                    </thead>
                    <tbody id="mobile-calendar-body">
                      <!-- JS will populate -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Mobile Time Container (Moved Here as requested) -->
        <div class="row justify-content-center d-lg-none mb-4">
          <div class="col-12">
            <div class="card text-center py-3 shadow-sm border-0" style="border-radius: 12px;">
              <h1 id="current-time-mobile" class="fw-bold mb-0 display-3">--:-- --</h1>
            </div>
          </div>
        </div>

        <div class="row g-3 align-items-start">

          <!-- Removed old widget code -->

          <div class="col-xl-9 col-lg-8 col-md-8">
            <div class="row g-2 mt-1">

              <div class="col-3">
                <div class="card text-center p-3 shadow-sm border-0 clickable-card">
                  <h6 class="fw-semibold text-secondary mb-1">Present</h6>
                  <p id="presentPercentage" class="display-6 fw-bold text-success mb-0">0</p>
                </div>
              </div>

              <div class="col-3">
                <div class="card text-center p-3 shadow-sm border-0 clickable-card">
                  <h6 class="fw-semibold text-secondary mb-1">Absent</h6>
                  <p id="absentPercentage" class="display-6 fw-bold text-danger mb-0">0</p>
                </div>
              </div>

              <div class="col-3">
                <div class="card text-center p-3 shadow-sm border-0 clickable-card">
                  <h6 class="fw-semibold text-secondary mb-1">On Time</h6>
                  <p id="onTimePercentage" class="display-6 fw-bold text-primary mb-0">0</p>
                </div>
              </div>

              <div class="col-3">
                <div class="card text-center p-3 shadow-sm border-0 clickable-card">
                  <h6 class="fw-semibold text-secondary mb-1">Late</h6>
                  <p id="latePercentage" class="display-6 fw-bold text-warning mb-0">0</p>
                </div>
              </div>

            </div>
          </div>

          <div class="col-xl-3 col-lg-4 col-md-4 mt-2 mt-md-0">
            <div class="attendance-feed shadow-sm p-3 bg-white rounded h-100">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0 d-flex align-items-center">
                  <i class="bi bi-clock-history me-2"></i>
                  <span>Attendance Feed</span>
                  <span class="badge bg-secondary ms-2" id="feedCount"
                    style="font-size: 0.65rem; vertical-align: middle;">0</span>
                </h6>
                <button id="todayBtn" class="btn btn-sm btn-outline-primary"
                  style="font-size: 0.75rem; padding: 2px 8px;">
                  <i class="bi bi-calendar-day me-1"></i>Today
                </button>
              </div>
              <!-- Selected Date Badge -->
              <div id="selectedDateBadge" style="display: none;"></div>
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
        <div class="col-lg-6 d-none d-lg-block">
          <div class="card text-center d-flex flex-column justify-content-center time-card">
            <h1 id="current-time" class="fw-bold mb-2">--:-- --</h1>
            <h5 id="current-date" class="mt-2 mb-0 d-none d-lg-block">Loading...</h5>
          </div>
        </div>


        <!--calendar  button -->
        <div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel"
          aria-hidden="true">
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
                      <button id="cal-prev-modal" class="btn btn-sm btn-outline-secondary me-1 px-2 py-1" type="button"
                        aria-label="Previous month">&lsaquo;</button>
                      <button id="cal-next-modal" class="btn btn-sm btn-outline-secondary px-2 py-1" type="button"
                        aria-label="Next month">&rsaquo;</button>
                    </div>
                  </div>
                  <div class="table-responsive" style="font-size: 0.8rem;">
                    <table id="calendar-table-modal" class="table table-bordered text-center m-0"
                      style="table-layout: fixed;">
                      <thead class="table-light">
                        <tr>
                          <th>Sun</th>
                          <th>Mon</th>
                          <th>Tue</th>
                          <th>Wed</th>
                          <th>Thu</th>
                          <th>Fri</th>
                          <th>Sat</th>
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
            <div class="d-flex align-items-center justify-content-between mb-2" style="padding: 1.5rem;">
              <h6 id="cal-month-year" class="fw-bold mb-0" style="font-size: 2rem;">Month Year</h6>
              <div>
                <button id="cal-prev" class="btn btn-sm btn-outline-secondary me-1 px-2 py-1" type="button"
                  aria-label="Previous month">&lsaquo;</button>
                <button id="cal-next" class="btn btn-sm btn-outline-secondary px-2 py-1" type="button"
                  aria-label="Next month">&rsaquo;</button>
              </div>
            </div>

            <div class="table-responsive" style="font-size: 0.8rem;">
              <table id="calendar-table" class="table table-bordered text-center m-0" style="table-layout: fixed;">
                <thead class="table-light">
                  <tr>
                    <th>Sun</th>
                    <th>Mon</th>
                    <th>Tue</th>
                    <th>Wed</th>
                    <th>Thu</th>
                    <th>Fri</th>
                    <th>Sat</th>
                  </tr>
                </thead>
                <tbody id="calendar-body">
                  <!-- JS will populate -->
                </tbody>
              </table>


            </div>
          </div>
        </div>



        <div class="col-md-6 col-12 mb-3">
          <div class="card p-4 shadow-sm h-100 d-flex flex-column text-start late-card"
            style="min-height: 200px; justify-content: flex-start; align-items: stretch;">
            <div class="d-flex align-items-center mb-3 border-bottom pb-2">
              <i class="bi bi-clock-history fs-4 me-2" style="color: #CBA135;"></i>
              <h3 class="fw-bold mb-0" style="font-size: 1.1rem; color: #103932;">Late Today</h3>
            </div>
            <div class="late-list w-100 d-flex flex-column flex-grow-1" style="overflow-y: auto; max-height: 300px;">
              <!-- JavaScript will populate this dynamically -->
            </div>
          </div>
        </div>

        <div class="col-md-6 col-12 mb-3">
          <div class="card p-4 shadow-sm h-100 d-flex flex-column text-start on-leave-card"
            style="min-height: 200px; justify-content: flex-start; align-items: stretch;">
            <div class="d-flex align-items-center mb-3 border-bottom pb-2">
              <i class="bi bi-calendar2-x fs-4 me-2" style="color: #103932;"></i>
              <h3 class="fw-bold mb-0" style="font-size: 1.1rem; color: #103932;">On Leave</h3>
            </div>
            <div class="on-leave-list w-100 d-flex flex-column flex-grow-1"
              style="overflow-y: auto; max-height: 300px;">
              <!-- JavaScript will populate this dynamically -->
            </div>
          </div>
        </div>





        <!------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------>
      </div> <!-- container-fluid -->
    </div> <!-- content -->

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
    <script src="dashboard.js?v=<?php echo time(); ?>"></script>
    <script>
      function showLogoutModal() {
        var modal = new bootstrap.Modal(document.getElementById('logoutModal'));
        modal.show();
      }
    </script>

</body>

</html>