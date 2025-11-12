 <?php
$employees = [
  "MA20230001" => ["name" => "Justine Alianza", "role" => "Non-Teaching Staff", "image" => "pic.png"],
  "MA20230002" => ["name" => "Krystian Maniego", "role" => "Admin", "image" => "pic.png"],
  "MA20230003" => ["name" => "Lord Gabriel Castro", "role" => "Faculty Staff", "image" => "pic.png"],
  "MA20230004" => ["name" => "John Adrian Mateo", "role" => "Non-Teaching Staff", "image" => "pic.png"],
  "MA20230005" => ["name" => "Marvin De Leon", "role" => "Faculty Staff", "image" => "pic.png"],
  "MA20230006" => ["name" => "Jilmer Cruz", "role" => "Admin Personnel", "image" => "pic.png"],
];

$id = $_GET['id'] ?? null;
$employee = $employees[$id] ?? null;
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
            <th>Total Hours</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>January 10, 2025</td>
            <td>08:00 AM</td>
            <td>04:00 PM</td>
            <td>1.0 hr</td>
            <td>7.0 hrs</td>
            <td><span class="badge bg-success">Present</span></td>
          </tr>
          <tr>
            <td>January 11, 2025</td>
            <td>09:00 AM</td>
            <td>05:00 PM</td>
            <td>1.0 hr</td>
            <td>7.0 hrs</td>
            <td><span class="badge bg-danger">Absent</span></td>
          </tr>
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
