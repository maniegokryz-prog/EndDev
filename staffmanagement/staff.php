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
<link rel="stylesheet" href="staff.css">
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
      <h2 class="fw-bold display-4 text-dark">Staff Management</h2>
     <div class="d-flex justify-content-end mb-3">
      <a href="newstaff.php" class="btn btn-warning">Add New Staff</a>
    </div>
  </div>

 <!-- Page Content -->
      <div class="container-fluid mt-3">
        <div class="row g-3 align-items-center">
          <div class="col-md-3">
            <select id="roleFilter" class="form-select">
              <option>All Roles</option>
              <option>Admin</option>
              <option>Faculty Staff</option>
              <option>Non-Teaching Staff</option>
            </select>
          </div>
          <div class="col-md-5">
            <select id="departmentFilter" class="form-select">
              <option>All Departments</option>
              <option>Information Systems</option>
              <option>Office Management</option>
              <option>Accounting Information Systems</option>
              <option>Technical-Vocational Teacher Education</option>
              <option>Customs Administration</option>
              <option>Hotel and Restaurant Services</option>
              <option>Accounting Office</option>
              <option>Registrar’s Office</option>
              <option>Library Office</option>
              <option>Management Information System Office</option>
              <option>Student Government Office</option>
              <option>SENTRY Office</option>
              <option>National Service Training Program (NSTP) Office</option>
              <option>Guidance and Counseling Office</option>
              <option>Admission Office</option>
            </select>
          </div>
          <div class="col-md-4">
            <input type="text" id="searchInput" class="form-control" placeholder="Search">
          </div>
        </div>

        <div class="table-responsive mt-4">
          <table class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Department</th>
                <th>View Profile</th>
              </tr>
            </thead>
            <tbody id="staffTable">
              <!-- Staff rows inserted via JavaScript -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  
<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="staff.js"></script>
</body>
</html>


 


