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

   <div class="content pt-3" id="content">
  <div class="container-fluid">
    <a href="attendancerep.php" class="btn btn-outline-secondary mb-3 mt-1">&larr; Back</a>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold display-4 text-dark">Export Reports</h2>
  </div>
  <!----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
 

     <!-- Filters -->
<div class="row g-2 mb-3 align-items-end">

  <div class="col-md-2">
    <label for="dateFrom" class="form-label">Date From</label>
    <input type="date" id="dateFrom" class="form-control form-control-sm">
  </div>

  <div class="col-md-2">
    <label for="dateTo" class="form-label">Date To</label>
    <input type="date" id="dateTo" class="form-control form-control-sm">
  </div>

  <div class="col-md-2">
    <label class="form-label d-block">&nbsp;</label>
    <button class="btn btn-outline-dark w-100">Select All</button>
  </div>

  <div class="col-md-2">
    <label class="form-label d-block">&nbsp;</label>
    <select class="form-select form-select-sm">
      <option selected>Sort By</option>
      <option>Name</option>
      <option>Role</option>
      <option>Department</option>
    </select>
  </div>

  <div class="col-md-3">
    <label class="form-label d-block">&nbsp;</label>
    <input type="text" class="form-control form-control-sm" placeholder="Search">
  </div>

</div>
<body class="p-4">
<!-- Table -->
<div class="table-responsive mt-5">
  <table class="table table-bordered">
    <thead class="table-light">
      <tr>
        <th>Select</th>
        <th>Name & ID</th>
        <th>Email</th>
        <th>Contact No.</th>
        <th>Role</th>
        <th>Department</th>
      </tr>
    </thead>

    <tbody>
      <tr>
        <td><input type="checkbox"></td>
        <td>
          <div class="d-flex align-items-center">
            <img src="pic.png" class="rounded-circle me-3" width="40" height="40">
            <div class="d-flex flex-column">
              <span class="fw-semibold">Ronnel Borlongan</span>
              <small class="text-muted">MA2020000</small>
            </div>
          </div>
        </td>
        <td>sample@gmail.com</td>
        <td>0917-123-4567</td>
        <td>Faculty Staff</td>
        <td>BSIS</td>
      </tr>
      <tr>
        <td><input type="checkbox"></td>
        <td>
          <div class="d-flex align-items-center">
            <img src="pic.png" class="rounded-circle me-3" width="40" height="40">
            <div class="d-flex flex-column">
              <span class="fw-semibold">Justine Alianza</span>
              <small class="text-muted">MA2020001</small>
            </div>
          </div>
        </td>
        <td>sample@gmail.com</td>
        <td>0917-123-4567</td>
        <td>Non-Teaching Staff</td>
        <td>Registrar's Office</td>
      </tr>
    </tbody>
    
  </table>
</div>

   <!-- Export Buttons -->
<div class="d-flex justify-content-end gap-2 mt-3">
  <button class="btn btn-warning export-btn" onclick="openConfirmModal('PDF')">Export as PDF</button>
  <button class="btn btn-success export-btn" onclick="openConfirmModal('Excel')">Export as Excel</button>
</div>

<!-- Confirm Export Modal -->
<div class="modal fade" id="confirmExportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-3">
      <h5 class="fw-bold">Confirm DTR Export</h5>
      <hr>
      <div class="my-3">
        <i class="bi bi-box-arrow-up fs-1 text-success"></i>
      </div>
      <p>Confirm DTR Report for <span id="staffCount">0</span> staff as <span id="exportType"></span>?</p>
      <div class="d-flex justify-content-center gap-2">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="confirmExportBtn">Yes</button>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successExportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-3">
      <h5 class="fw-bold text-success">Export Completed!</h5>
      <hr>
      <div class="my-3">
        <i class="bi bi-check-circle fs-1 text-success"></i>
      </div>
      <p>DTR report successfully generated as <span id="exportDoneType"></span>.</p>
      <button class="btn btn-success" data-bs-dismiss="modal">Continue</button>
    </div>
  </div>
</div>

<!-- Warning Modal -->
<div class="modal fade" id="warningModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-3">
      <h5 class="fw-bold text-danger">No Staff Selected</h5>
      <hr>
      <div class="my-3">
        <i class="bi bi-exclamation-circle fs-1 text-danger"></i>
      </div>
      <p>Please select at least one staff before exporting.</p>
      <button class="btn btn-danger" data-bs-dismiss="modal">OK</button>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  let exportType = '';

  function openConfirmModal(type) {
    exportType = type;
    const selected = document.querySelectorAll("tbody input[type=checkbox]:checked").length;

    if (selected === 0) {
      new bootstrap.Modal(document.getElementById("warningModal")).show();
      return;
    }

    document.getElementById("staffCount").textContent = selected;
    document.getElementById("exportType").textContent = type;

    const confirmModal = new bootstrap.Modal(document.getElementById("confirmExportModal"));
    confirmModal.show();

    // Attach export confirmation only once
    const confirmBtn = document.getElementById("confirmExportBtn");
    confirmBtn.onclick = () => {
      confirmModal.hide();
      setTimeout(() => {
        document.getElementById("exportDoneType").textContent = exportType;
        new bootstrap.Modal(document.getElementById("successExportModal")).show();

        // Trigger actual export here after success modal is shown
        performExport(exportType);
      }, 300);
    };
  }

  function performExport(type) {
    // Put your actual export logic here (PDF/Excel generation)
    console.log(`Exporting as ${type}...`);
  }
</script>


  <!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="attendancerep.js"></script>
</body>
</html>