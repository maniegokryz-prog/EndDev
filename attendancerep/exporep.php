<?php
require_once '../db_connection.php';

// Fetch all employees from the database
$sql = "SELECT id, employee_id, first_name, middle_name, last_name, email, phone, roles, department, position, profile_photo, status 
        FROM employees 
        WHERE status = 'active'
        ORDER BY last_name, first_name";
$result = $conn->query($sql);
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
  
  <!-- Daterangepicker CSS -->
  <link rel="stylesheet" type="text/css" href="../assets/vendor/daterangepicker/daterangepicker.css" />

  <!-- Custom CSS -->
<link rel="stylesheet" href="attendancerep.css">
<style>
  .table-wrapper {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
  }
  .table-wrapper table {
    margin-bottom: 0;
  }
  .table-wrapper thead th {
    position: sticky;
    top: 0;
    background-color: #f8f9fa;
    z-index: 10;
  }
</style>
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
    <label class="form-label d-block">&nbsp;</label>
    <button class="btn btn-outline-dark w-100" id="selectAllBtn" onclick="toggleSelectAll()">Select All</button>
  </div>

  <div class="col-md-3">
    <label for="dateRangePicker" class="form-label">Date Range</label>
    <input type="text" class="form-control form-control-sm" id="dateRangePicker" placeholder="Select Date Range">
  </div>

  <div class="col-md-2">
    <label for="sortBy" class="form-label">Sort By</label>
    <select class="form-select form-select-sm" id="sortBy" onchange="sortTable()">
      <option value="">Sort By</option>
      <option value="name">Name</option>
      <option value="role">Role</option>
      <option value="department">Department</option>
    </select>
  </div>

  <div class="col-md-3">
    <label for="searchInput" class="form-label">Search</label>
    <input type="text" class="form-control form-control-sm" placeholder="Search by name, ID, email, etc." id="searchInput" onkeyup="searchTable()">
  </div>

</div>
<body class="p-4">
<!-- Table -->
<div class="table-wrapper mt-5">
  <table class="table table-bordered" id="employeeTable">
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
      <?php
      if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
          $fullName = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
          $profilePic = !empty($row['profile_photo']) ?'' . $row['profile_photo'] : 'pic.png';
          $email = !empty($row['email']) ? htmlspecialchars($row['email']) : 'N/A';
          $phone = !empty($row['phone']) ? htmlspecialchars($row['phone']) : 'N/A';
          $roles = !empty($row['roles']) ? htmlspecialchars($row['roles']) : 'N/A';
          $department = !empty($row['department']) ? htmlspecialchars($row['department']) : 'N/A';
          
          echo '<tr data-employee-id="' . $row['id'] . '">';
          echo '<td><input type="checkbox" class="employee-checkbox" data-employee-id="' . $row['id'] . '"></td>';
          echo '<td>';
          echo '  <div class="d-flex align-items-center">';
          echo '    <img src="' . $profilePic . '" class="rounded-circle me-3" width="40" height="40" onerror="this.src=\'pic.png\'">';
          echo '    <div class="d-flex flex-column">';
          echo '      <span class="fw-semibold employee-name">' . htmlspecialchars($fullName) . '</span>';
          echo '      <small class="text-muted employee-id">' . htmlspecialchars($row['employee_id']) . '</small>';
          echo '    </div>';
          echo '  </div>';
          echo '</td>';
          echo '<td class="employee-email">' . $email . '</td>';
          echo '<td class="employee-phone">' . $phone . '</td>';
          echo '<td class="employee-role">' . $roles . '</td>';
          echo '<td class="employee-department">' . $department . '</td>';
          echo '</tr>';
        }
      } else {
        echo '<tr><td colspan="6" class="text-center">No employees found</td></tr>';
      }
      ?>
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

<!-- jQuery (required for daterangepicker) -->
<script src="../assets/vendor/jquery/jquery.min.js"></script>

<!-- Moment.js (required for daterangepicker) -->
<script src="../assets/vendor/moment/moment.min.js"></script>

<!-- Daterangepicker JS -->
<script src="../assets/vendor/daterangepicker/daterangepicker.min.js"></script>

<!-- Bootstrap Bundle -->
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>
  let exportType = '';
  let allSelected = false;
  let selectedDateRange = null;

  // Initialize Date Range Picker
  $(document).ready(function() {
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
  });

  // Toggle Select All functionality
  function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    const visibleCheckboxes = Array.from(checkboxes).filter(cb => {
      const row = cb.closest('tr');
      return row.style.display !== 'none';
    });
    
    allSelected = !allSelected;
    visibleCheckboxes.forEach(checkbox => {
      checkbox.checked = allSelected;
    });
    
    document.getElementById('selectAllBtn').textContent = allSelected ? 'Deselect All' : 'Select All';
  }

  // Search functionality
  function searchTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('employeeTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
      const row = rows[i];
      const name = row.querySelector('.employee-name')?.textContent.toLowerCase() || '';
      const id = row.querySelector('.employee-id')?.textContent.toLowerCase() || '';
      const email = row.querySelector('.employee-email')?.textContent.toLowerCase() || '';
      const role = row.querySelector('.employee-role')?.textContent.toLowerCase() || '';
      const department = row.querySelector('.employee-department')?.textContent.toLowerCase() || '';

      if (name.includes(filter) || id.includes(filter) || email.includes(filter) || 
          role.includes(filter) || department.includes(filter)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    }
  }

  // Sort functionality
  function sortTable() {
    const sortBy = document.getElementById('sortBy').value;
    const table = document.getElementById('employeeTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    if (!sortBy) return;

    rows.sort((a, b) => {
      let aValue, bValue;
      
      switch(sortBy) {
        case 'name':
          aValue = a.querySelector('.employee-name')?.textContent || '';
          bValue = b.querySelector('.employee-name')?.textContent || '';
          break;
        case 'role':
          aValue = a.querySelector('.employee-role')?.textContent || '';
          bValue = b.querySelector('.employee-role')?.textContent || '';
          break;
        case 'department':
          aValue = a.querySelector('.employee-department')?.textContent || '';
          bValue = b.querySelector('.employee-department')?.textContent || '';
          break;
        default:
          return 0;
      }
      
      return aValue.localeCompare(bValue);
    });

    rows.forEach(row => tbody.appendChild(row));
  }

  function openConfirmModal(type) {
    exportType = type;
    const selected = document.querySelectorAll(".employee-checkbox:checked").length;

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
    // Get selected employee IDs
    const selectedCheckboxes = document.querySelectorAll('.employee-checkbox:checked');
    const employeeIds = Array.from(selectedCheckboxes).map(cb => cb.dataset.employeeId);
    
    // Get date range
    let dateFrom = '';
    let dateTo = '';
    if (selectedDateRange) {
      dateFrom = selectedDateRange.start;
      dateTo = selectedDateRange.end;
    }
    
    console.log(`Exporting as ${type}...`);
    console.log('Selected employee IDs:', employeeIds);
    console.log('Date range:', dateFrom, 'to', dateTo);
    
    // TODO: Implement actual export logic here
    // You can create a form and submit it to a PHP script that generates PDF/Excel
  }
</script>

<!-- Custom JS -->
<script src="attendancerep.js"></script>
</body>
</html>