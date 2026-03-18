<?php
require_once '../auth_guard.php';
require_once '../navigation.php';
requireAdmin(); // Only admins can access batch export
require_once '../db_connection.php';

// Get current user info
$currentUser = getCurrentUser();

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
  <!-- Bootstrap CSS -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Daterangepicker CSS -->
  <link rel="stylesheet" type="text/css" href="../assets/vendor/daterangepicker/daterangepicker.css" />

  <!-- Custom CSS -->
  <link rel="stylesheet" href="../settings/settings.css">
  <link rel="stylesheet" href="attendancerep.css?v=<?php echo time(); ?>">
  <style>
    .btn-solid-light-gray {
        background-color: #dee2e6 !important;
        border-color: #dee2e6 !important;
        color: #212529 !important;
    }
    
    .btn-solid-success {
        background-color: #198754 !important;
        border-color: #198754 !important;
        color: white !important;
    }
    
    .btn-solid-light-gray:hover,
    .btn-solid-success:hover {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
        color: white !important;
    }

    .btn-modern {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-weight: bold;
        transition: all 0.2s;
        border: 1px solid transparent;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        white-space: nowrap;
    }

    .btn-outline {
        background: transparent !important;
        border: 1px solid #cbd5e0 !important;
        color: #2d3748 !important;
    }

    .btn-outline.text-danger.border-danger {
        border-color: #dc3545 !important;
        color: #dc3545 !important;
    }

    .btn-outline.text-danger.border-danger:hover {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
        color: white !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
    }

    .btn-outline.text-success.border-success {
        border-color: #198754 !important;
        color: #198754 !important;
    }

    .btn-outline.text-success.border-success:hover {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
        color: white !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2) !important;
    }

    /* ID Overrides to ensure consistency */
    #selectAllBtn {
        background-color: #198754 !important;
        border-color: #198754 !important;
        color: white !important;
    }
    #selectAllBtn:hover {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
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
      <img
        src="<?php echo !empty($currentUser['profile_photo']) ? '../' . htmlspecialchars($currentUser['profile_photo'], ENT_QUOTES, 'UTF-8') . '?v=' . time() : '../assets/profile_pic/user.png?v=' . time(); ?>"
        alt="Profile" class="rounded-circle mb-2" width="70" height="70"
        onerror="this.src='../assets/profile_pic/user.png';">
      <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h5>
      <small
        class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></small>
    </div>
    <nav class="nav flex-column px-2">
      <?php renderNavigation('Attendance Reports'); ?>
    </nav>
  </div>

  <div class="content pt-3" id="content">
    <div class="container-fluid">

      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2 gap-md-3">
        <h2 class="display-4 text-dark page-title mb-0">Export DTR Reports</h2>
      </div>
      <!----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->


      <!-- Toolbar: Filters & Search Layout -->
      <div class="row g-3 mb-4 align-items-end">
        <div class="col-md-auto">
          <button class="btn btn-solid-success fw-bold" id="selectAllBtn" onclick="toggleSelectAll()">Select All</button>
        </div>
        <div class="col-md-3">
          <label for="dateRangePicker" class="form-label small text-muted"><i class="bi bi-calendar3"></i> Date
            Range</label>
          <input type="text" class="form-control" id="dateRangePicker" placeholder="Select Date Range">
        </div>

        <div class="col-md-2">
          <label for="sortBy" class="form-label small text-muted">Sort By</label>
          <select class="form-select" id="sortBy">
            <option value="">Default Order</option>
            <option value="name">Name</option>
            <option value="role">Role</option>
            <option value="department">Department</option>
          </select>
        </div>

        <div class="col-md-auto d-flex gap-2">
          <button class="btn btn-solid-success px-4 fw-bold"
            onclick="applyFilters()">Apply</button>
          <button class="btn btn-solid-light-gray px-3 fw-bold" onclick="resetFilters()">Reset</button>
        </div>

        <div class="col-md-3 ms-auto">
          <label for="searchInput" class="form-label small text-muted">Search Staff</label>
          <input type="text" class="form-control" placeholder="Search by name, ID, email..." id="searchInput"
            onkeyup="searchTable()">
        </div>
      </div>

      <!-- Table Card -->
      <div class="card p-3 shadow-sm">
        <div class="table-responsive">
          <table class="table align-middle" id="employeeTable">
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
  while ($row = $result->fetch_assoc()) {
    $fullName = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
    $profilePic = !empty($row['profile_photo']) ? '' . $row['profile_photo'] : 'pic.png';
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
}
else {
  echo '<tr><td colspan="6" class="text-center">No employees found</td></tr>';
}
?>
            </tbody>

          </table>
        </div>
      </div>

      <!-- Export Buttons -->
      <div class="d-flex justify-content-end gap-2 mt-3">
        <button class="btn btn-outline-warning export-btn fw-bold" onclick="openConfirmModal('PDF')">Export as PDF</button>
        <button class="btn btn-solid-success export-btn fw-bold" onclick="openConfirmModal('Excel')">Export as Excel</button>
      </div>

    </div> <!-- container-fluid -->
  </div> <!-- content pt-3 -->

  <!-- Confirm Export Modal -->
  <div class="modal fade" id="confirmExportModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg text-center p-4">
        <h5 class="fw-bold mb-3">Confirm DTR Export</h5>
        <div class="my-3">
          <i class="bi bi-box-arrow-up fs-1 text-success"></i>
        </div>
        <p class="mb-4">Confirm DTR Report for <span id="staffCount" class="fw-bold">0</span> staff as <span id="exportType" class="fw-bold"></span>?</p>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn-modern btn-solid-success fw-bold px-4" id="confirmExportBtn">Yes</button>
          <button class="btn-modern btn-solid-light-gray fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div class="modal fade" id="successExportModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg text-center p-4">
        <h5 class="fw-bold text-success mb-3">Export Completed!</h5>
        <div class="my-3">
          <i class="bi bi-check-circle fs-1 text-success"></i>
        </div>
        <p class="mb-4">DTR report successfully generated as <span id="exportDoneType" class="fw-bold"></span>.</p>
        <div class="d-flex justify-content-center">
          <button class="btn-modern btn-outline text-success border-success fw-bold px-5" data-bs-dismiss="modal">Continue</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Warning Modal -->
  <div class="modal fade" id="warningModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg text-center p-4">
        <h5 class="fw-bold text-danger mb-3">No Staff Selected</h5>
        <div class="my-3">
          <i class="bi bi-exclamation-circle fs-1 text-danger"></i>
        </div>
        <p class="mb-4">Please select at least one staff before exporting.</p>
        <div class="d-flex justify-content-center">
          <button class="btn-modern btn-outline text-danger border-danger fw-bold px-5" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

  <!-- jQuery (required for daterangepicker) -->
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
    $(document).ready(function () {
      $('#dateRangePicker').daterangepicker({
        autoUpdateInput: false,
        locale: {
          cancelLabel: 'Clear',
          format: 'YYYY-MM-DD'
        }
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

    function applyFilters() {
      // Apply Sort
      sortTable();

      // Could add any future dynamic filters here
    }

    function resetFilters() {
      // Reset Date Range
      $('#dateRangePicker').val('');
      selectedDateRange = null;

      // Reset Sort By
      document.getElementById('sortBy').value = '';

      // Reset Search
      document.getElementById('searchInput').value = '';

      // Reset Select All State
      const checkboxes = document.querySelectorAll('.employee-checkbox');
      checkboxes.forEach(cb => cb.checked = false);
      allSelected = false;
      document.getElementById('selectAllBtn').textContent = 'Select All';

      // Show all rows
      searchTable();

      // Resetting sort back to default order visually is slightly complex without full reload,
      // but by clearing the value, the next sort will override. A page reload is simpler for full DB sort.
      // To provide a smooth UX, we just filter normally.
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

        switch (sortBy) {
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

      // Create logic to submit form
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'export_batch.php';
      form.target = '_blank'; // Open in new tab

      // Add Export Type
      const typeInput = document.createElement('input');
      typeInput.type = 'hidden';
      typeInput.name = 'export_type';
      typeInput.value = type.toLowerCase();
      form.appendChild(typeInput);

      // Add Employee IDs
      const idsInput = document.createElement('input');
      idsInput.type = 'hidden';
      idsInput.name = 'employee_ids';
      idsInput.value = JSON.stringify(employeeIds);
      form.appendChild(idsInput);

      // Add Date Range if exists
      if (dateFrom && dateTo) {
        const startInput = document.createElement('input');
        startInput.type = 'hidden';
        startInput.name = 'start_date';
        startInput.value = dateFrom;
        form.appendChild(startInput);

        const endInput = document.createElement('input');
        endInput.type = 'hidden';
        endInput.name = 'end_date';
        endInput.value = dateTo;
        form.appendChild(endInput);
      }

      document.body.appendChild(form);
      form.submit();
      document.body.removeChild(form);
    }

    // Logout modal function
    function showLogoutModal() {
      var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
      logoutModal.show();
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
          <form method="POST" action="logout.php" style="display: inline;">
            <input type="hidden" name="confirm_logout" value="1">
            <button type="submit" class="btn btn-solid-danger px-4">Yes, Log out</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Custom JS -->
  <script src="attendancerep.js"></script>
  <script src="../dashboard/dashboard.js"></script>
</body>

</html>