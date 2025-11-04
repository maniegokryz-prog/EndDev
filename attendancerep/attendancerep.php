    <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

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
     <div class="d-flex justify-content-end mb-3">
      <a href="exporep.php" class="btn btn-warning">Export</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <select class="form-select" id="roleFilter">
        <option>All Roles</option>
        <option>Admin</option>
        <option>Faculty Staff</option>
        <option>Non-Teaching Staff</option>
      </select>
    </div>
    <div class="col-md-4">
      <select class="form-select" id="deptFilter">
        <option>All Departments</option>
        <option>Information Systems</option>
        <option>Office Management</option>
        <option>Accounting Information Systems</option>
        <option>Hotel and Restaurant Services</option>
        <option>Registrar’s Office</option>
        <option>Guidance and Counseling Office</option>
      </select>
    </div>
    <div class="col-md-3">
      <input type="text" id="searchBox" class="form-control" placeholder="Search">
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
          <th>Vacant Hours</th>
          <th>Total Hours</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr data-id="MA20230001">
          <td>
            <div class="d-flex align-items-center">
              <img src="pic.png" class="employee-img rounded-circle me-2" width="40" height="40">
              <div>
                <span class="fw-semibold">Justine Alianza</span><br>
                <small class="text-muted">Non-Teaching Staff</small>
              </div>
            </div>
          </td>
          <td>2025-09-10</td>
          <td>8:00 AM</td><td>4:00 PM</td><td>1.0 hr</td><td>6.0 hrs</td>
          <td><span class="status-dot status-ontime"></span> On-Time</td>
        </tr>

        <tr data-id="MA20230002">
          <td>
            <div class="d-flex align-items-center">
              <img src="pic.png" class="employee-img rounded-circle me-2" width="40" height="40">
              <div>
                <span class="fw-semibold">Krystian Maniego</span><br>
                <small class="text-muted">Admin</small>
              </div>
            </div>
          </td>
          <td>2025-09-10</td>
          <td>9:00 AM</td><td>5:00 PM</td><td>1.0 hr</td><td>8.0 hrs</td>
          <td><span class="status-dot status-late"></span> Late</td>
        </tr>

        <tr data-id="MA20230003">
          <td>
            <div class="d-flex align-items-center">
              <img src="pic.png" class="employee-img rounded-circle me-2" width="40" height="40">
              <div>
                <span class="fw-semibold">Lord Gabriel Castro</span><br>
                <small class="text-muted">Faculty Staff</small>
              </div>
            </div>
          </td>
          <td>2025-09-10</td>
          <td>—</td><td>—</td><td>0.0 hr</td><td>0.0 hrs</td>
          <td><span class="status-dot status-absent"></span> Absent</td>
        </tr>

        <tr data-id="MA20230004">
          <td>
            <div class="d-flex align-items-center">
              <img src="pic.png" class="employee-img rounded-circle me-2" width="40" height="40">
              <div>
                <span class="fw-semibold">John Adrian Mateo</span><br>
                <small class="text-muted">Non-Teaching Staff</small>
              </div>
            </div>
          </td>
          <td>2025-09-10</td>
          <td>8:30 AM</td><td>4:30 PM</td><td>1.0 hr</td><td>8.0 hrs</td>
          <td><span class="status-dot status-undertime"></span> Undertime</td>
        </tr>

        <tr data-id="MA20230005">
          <td>
            <div class="d-flex align-items-center">
              <img src="pic.png" class="employee-img rounded-circle me-2" width="40" height="40">
              <div>
                <span class="fw-semibold">Marvin De Leon</span><br>
                <small class="text-muted">Faculty Staff</small>
              </div>
            </div>
          </td>
          <td>2025-09-10</td>
          <td>9:30 AM</td><td>3:30 PM</td><td>1.0 hr</td><td>4.0 hrs</td>
          <td><span class="status-dot status-late"></span> Late</td>
        </tr>

        <tr data-id="MA20230006">
          <td>
            <div class="d-flex align-items-center">
              <img src="pic.png" class="employee-img rounded-circle me-2" width="40" height="40">
              <div>
                <span class="fw-semibold">Jilmer Cruz</span><br>
                <small class="text-muted">Admin Personnel</small>
              </div>
            </div>
          </td>
          <td>2025-09-10</td>
          <td>8:00 AM</td><td>2:00 PM</td><td>1.0 hr</td><td>6.0 hrs</td>
          <td><span class="status-dot status-ontime"></span> On-Time</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="attendancerep.js"></script>
</body>
</html>
