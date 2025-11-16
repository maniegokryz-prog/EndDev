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
<link rel="stylesheet" href="settings.css">
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
      <?php
      // Include auth guard if not already included
      if (!function_exists('getCurrentUser')) {
          require_once '../auth_guard.php';
          $currentUser = getCurrentUser();
      }
      ?>
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
<!----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
  <div class="content pt-3" id="content">
  <div class="container-fluid">

    <!-- Back Button -->
  <div class="mb-3">
    <a href="settings.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa-solid fa-arrow-left me-1"></i> Back
    </a>
  </div>
  
    <div class="page">
    <h1>Employee Archive</h1>
    

    <!-- Toolbar -->
    <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
    <button class="btn btn-secondary btn-sm" id="selectAllBtn">Select All</button>
    <button class="btn btn-danger btn-sm" id="deleteSelectedBtn">Delete Selected</button>
    <button class="btn btn-success btn-sm" id="restoreSelectedBtn">Restore Selected</button>
    <div class="ms-auto">
      <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search staff name">
    </div>
  </div>

  <!-- Table -->
  <div class="table-responsive">
    <table class="table table-hover align-middle" id="employeeTable">
      <thead class="table-light">
        <tr>
          <th>Select</th>
          <th>Name & ID</th>
          <th>Email</th>
          <th>Contact No.</th>
          <th>Date Removed</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody id="tableBody">
        <!-- Dynamically loaded archived employees will appear here -->
      </tbody>
    </table>

    <div id="noDataMessage" class="no-data d-none">No archived employee(s).</div>
  </div>
</div>

<!--see details modal-->
<div class="modal fade" id="staffModal" tabindex="-1" aria-labelledby="staffModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3 text-center">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-semibold" id="staffModalLabel">Staff Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <img id="staffImg" src="" alt="Staff Photo" class="rounded-circle mb-3" width="120" height="120">
        <h5 id="staffName" class="fw-bold"></h5>
        <p id="staffId" class="text-muted mb-1"></p>
        <p id="staffDept" class="text-secondary mb-1"></p>
        <p><strong>Email:</strong> <span id="staffEmail"></span></p>
        <p><strong>Contact:</strong> <span id="staffContact"></span></p>
      </div>
    </div>
  </div>
</div>


<!-- Delete Confirmation Modal with Password -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirm Permanent Deletion</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-danger fw-bold"><i class="fa-solid fa-exclamation-triangle me-2"></i>Warning: This action cannot be undone!</p>
        <p>You are about to permanently delete <span id="deleteCount">0</span> employee(s) from the database. All related data including attendance records, schedules, and assignments will be permanently removed.</p>
        <form id="deleteForm">
          <div class="mb-3">
            <label for="deletePasswordInput" class="form-label">Admin Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="deletePasswordInput" required placeholder="Enter your password">
            <div id="deletePasswordError" class="text-danger small mt-1" style="display: none;"></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
          <span id="deleteBtnText">Delete Permanently</span>
          <span id="deleteBtnSpinner" class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Restore Confirmation Modal with Password -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Confirm Employee Restore</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>You are about to restore <span id="restoreCount">0</span> employee(s) to active status. Their schedules and assignments will be reactivated.</p>
        <form id="restoreForm">
          <div class="mb-3">
            <label for="restorePasswordInput" class="form-label">Admin Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="restorePasswordInput" required placeholder="Enter your password">
            <div id="restorePasswordError" class="text-danger small mt-1" style="display: none;"></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmRestoreBtn">
          <span id="restoreBtnText">Restore Employees</span>
          <span id="restoreBtnSpinner" class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
        </button>
      </div>
    </div>
  </div>
</div>


<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <div class="modal-body">
        <i class="fa-solid fa-check-circle text-success fs-1 mb-3"></i>
        <h5 class="fw-bold mb-3 text-success">Success!</h5>
        <p id="successMessage">Operation completed successfully.</p>
        <p class="small text-muted">Page will reload automatically...</p>
      </div>
    </div>
  </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <div class="modal-body">
        <i class="fa-solid fa-exclamation-circle text-danger fs-1 mb-3"></i>
        <h5 class="fw-bold mb-3 text-danger">Error</h5>
        <p id="errorMessage">An error occurred.</p>
        <button type="button" class="btn btn-primary mt-3" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- Center Alert -->
<div id="centerAlert" class="position-fixed top-50 start-50 translate-middle bg-warning text-dark fw-semibold py-3 px-4 rounded shadow"
     style="display:none; z-index: 1100;">
  ⚠️ Please select at least one employee before proceeding.
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Store archived employees data
let archivedEmployees = [];
let selectedEmployeeIds = [];

// Load archived employees on page load
document.addEventListener("DOMContentLoaded", async function () {
  await loadArchivedEmployees();
  initializeEventListeners();
});

// Fetch archived employees from database
async function loadArchivedEmployees() {
  try {
    const response = await fetch('processes/get_archived_employees.php');
    const result = await response.json();
    
    if (result.success) {
      archivedEmployees = result.data;
      renderEmployeeTable();
    } else {
      console.error('Error loading archived employees:', result.error);
      showError('Failed to load archived employees');
    }
  } catch (error) {
    console.error('Fetch error:', error);
    showError('Failed to connect to server');
  }
}

// Render employee table
function renderEmployeeTable() {
  const tbody = document.getElementById('tableBody');
  const noDataMsg = document.getElementById('noDataMessage');
  
  tbody.innerHTML = '';
  
  if (archivedEmployees.length === 0) {
    noDataMsg.classList.remove('d-none');
    return;
  }
  
  noDataMsg.classList.add('d-none');
  
  archivedEmployees.forEach(emp => {
    const row = `
      <tr data-employee-id="${emp.employee_id}">
        <td><input type="checkbox" class="selectBox" data-id="${emp.employee_id}"></td>
        <td>
          <div class="employee-info">
            <img src="../${emp.profile_photo}" alt="Profile" onerror="this.src='../assets/profile_pic/user.png';">
            <div>
              <strong>${emp.name}</strong><br>
              <small>${emp.employee_id}</small>
            </div>
          </div>
        </td>
        <td>${emp.email}</td>
        <td>${emp.phone}</td>
        <td>${emp.date_removed}</td>
        <td class="text-center">
          <div class="dropdown">
            <button class="btn btn-link text-dark p-0 border-0" type="button" data-bs-toggle="dropdown">
              <i class="fa-solid fa-ellipsis-vertical fs-5"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
              <li>
                <button class="dropdown-item d-flex align-items-center view-details-btn"
                        data-name="${emp.name}"
                        data-id="${emp.employee_id}"
                        data-dept="${emp.department}"
                        data-role="${emp.role}"
                        data-position="${emp.position}"
                        data-email="${emp.email}"
                        data-contact="${emp.phone}"
                        data-img="../${emp.profile_photo}">
                  <i class="fa-solid fa-user me-2 text-primary"></i>
                  See Details
                </button>
              </li>
            </ul>
          </div>
        </td>
      </tr>
    `;
    tbody.innerHTML += row;
  });
}

// Initialize event listeners
function initializeEventListeners() {
  const selectAllBtn = document.getElementById('selectAllBtn');
  const deleteBtn = document.getElementById('deleteSelectedBtn');
  const restoreBtn = document.getElementById('restoreSelectedBtn');
  const searchInput = document.getElementById('searchInput');
  const centerAlert = document.getElementById('centerAlert');
  
  let allSelected = false;
  
  // Select All / Deselect All
  selectAllBtn.addEventListener('click', () => {
    allSelected = !allSelected;
    const checkboxes = document.querySelectorAll('.selectBox');
    checkboxes.forEach(cb => cb.checked = allSelected);
    selectAllBtn.textContent = allSelected ? 'Deselect All' : 'Select All';
  });
  
  // Live Search
  searchInput.addEventListener('input', () => {
    const searchVal = searchInput.value.toLowerCase();
    const tbody = document.getElementById('tableBody');
    const noDataMsg = document.getElementById('noDataMessage');
    let visibleRows = 0;
    
    tbody.querySelectorAll('tr').forEach(row => {
      const name = row.querySelector('strong')?.textContent.toLowerCase() || '';
      const id = row.querySelector('small')?.textContent.toLowerCase() || '';
      
      if (name.includes(searchVal) || id.includes(searchVal)) {
        row.style.display = '';
        visibleRows++;
      } else {
        row.style.display = 'none';
      }
    });
    
    noDataMsg.classList.toggle('d-none', visibleRows > 0);
  });
  
  // Delete Selected
  deleteBtn.addEventListener('click', () => {
    const selected = document.querySelectorAll('.selectBox:checked');
    if (selected.length === 0) {
      showCenterAlert();
      return;
    }
    
    selectedEmployeeIds = Array.from(selected).map(cb => cb.getAttribute('data-id'));
    document.getElementById('deleteCount').textContent = selectedEmployeeIds.length;
    document.getElementById('deletePasswordInput').value = '';
    document.getElementById('deletePasswordError').style.display = 'none';
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
  });
  
  // Restore Selected
  restoreBtn.addEventListener('click', () => {
    const selected = document.querySelectorAll('.selectBox:checked');
    if (selected.length === 0) {
      showCenterAlert();
      return;
    }
    
    selectedEmployeeIds = Array.from(selected).map(cb => cb.getAttribute('data-id'));
    document.getElementById('restoreCount').textContent = selectedEmployeeIds.length;
    document.getElementById('restorePasswordInput').value = '';
    document.getElementById('restorePasswordError').style.display = 'none';
    
    const modal = new bootstrap.Modal(document.getElementById('restoreModal'));
    modal.show();
  });
  
  // Confirm Delete
  document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
    await handleDelete();
  });
  
  // Confirm Restore
  document.getElementById('confirmRestoreBtn').addEventListener('click', async () => {
    await handleRestore();
  });
  
  // View Details (Event delegation)
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.view-details-btn');
    if (!btn) return;
    
    document.getElementById('staffImg').src = btn.getAttribute('data-img');
    document.getElementById('staffName').textContent = btn.getAttribute('data-name');
    document.getElementById('staffId').textContent = btn.getAttribute('data-id');
    document.getElementById('staffDept').textContent = btn.getAttribute('data-dept') + ' | ' + btn.getAttribute('data-role');
    document.getElementById('staffEmail').textContent = btn.getAttribute('data-email');
    document.getElementById('staffContact').textContent = btn.getAttribute('data-contact');
    
    const modal = new bootstrap.Modal(document.getElementById('staffModal'));
    modal.show();
  });
  
  // Clear password errors on input
  document.getElementById('deletePasswordInput').addEventListener('input', () => {
    document.getElementById('deletePasswordError').style.display = 'none';
  });
  
  document.getElementById('restorePasswordInput').addEventListener('input', () => {
    document.getElementById('restorePasswordError').style.display = 'none';
  });
}

// Handle Delete
async function handleDelete() {
  const password = document.getElementById('deletePasswordInput').value.trim();
  const errorDiv = document.getElementById('deletePasswordError');
  const btn = document.getElementById('confirmDeleteBtn');
  const btnText = document.getElementById('deleteBtnText');
  const btnSpinner = document.getElementById('deleteBtnSpinner');
  
  if (!password) {
    errorDiv.textContent = 'Password is required';
    errorDiv.style.display = 'block';
    return;
  }
  
  // Disable button and show spinner
  btn.disabled = true;
  btnText.textContent = 'Processing...';
  btnSpinner.style.display = 'inline-block';
  errorDiv.style.display = 'none';
  
  try {
    const formData = new FormData();
    formData.append('employee_ids', selectedEmployeeIds.join(','));
    formData.append('admin_password', password);
    formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?? "" ?>');
    
    const response = await fetch('processes/delete_employee.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Close delete modal
      bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
      
      // Show success modal
      document.getElementById('successMessage').textContent = result.message;
      const successModal = new bootstrap.Modal(document.getElementById('successModal'));
      successModal.show();
      
      // Reload page after 2 seconds
      setTimeout(() => {
        window.location.reload();
      }, 2000);
    } else {
      if (result.message.toLowerCase().includes('password')) {
        errorDiv.textContent = result.message;
        errorDiv.style.display = 'block';
      } else {
        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        document.getElementById('errorMessage').textContent = result.message;
        new bootstrap.Modal(document.getElementById('errorModal')).show();
      }
    }
  } catch (error) {
    console.error('Error deleting employees:', error);
    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
    document.getElementById('errorMessage').textContent = 'Failed to connect to server';
    new bootstrap.Modal(document.getElementById('errorModal')).show();
  } finally {
    btn.disabled = false;
    btnText.textContent = 'Delete Permanently';
    btnSpinner.style.display = 'none';
  }
}

// Handle Restore
async function handleRestore() {
  const password = document.getElementById('restorePasswordInput').value.trim();
  const errorDiv = document.getElementById('restorePasswordError');
  const btn = document.getElementById('confirmRestoreBtn');
  const btnText = document.getElementById('restoreBtnText');
  const btnSpinner = document.getElementById('restoreBtnSpinner');
  
  if (!password) {
    errorDiv.textContent = 'Password is required';
    errorDiv.style.display = 'block';
    return;
  }
  
  // Disable button and show spinner
  btn.disabled = true;
  btnText.textContent = 'Processing...';
  btnSpinner.style.display = 'inline-block';
  errorDiv.style.display = 'none';
  
  try {
    const formData = new FormData();
    formData.append('employee_ids', selectedEmployeeIds.join(','));
    formData.append('admin_password', password);
    formData.append('csrf_token', '<?= $_SESSION["csrf_token"] ?? "" ?>');
    
    const response = await fetch('processes/restore_employee.php', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Close restore modal
      bootstrap.Modal.getInstance(document.getElementById('restoreModal')).hide();
      
      // Show success modal
      document.getElementById('successMessage').textContent = result.message;
      const successModal = new bootstrap.Modal(document.getElementById('successModal'));
      successModal.show();
      
      // Reload page after 2 seconds
      setTimeout(() => {
        window.location.reload();
      }, 2000);
    } else {
      if (result.message.toLowerCase().includes('password')) {
        errorDiv.textContent = result.message;
        errorDiv.style.display = 'block';
      } else {
        bootstrap.Modal.getInstance(document.getElementById('restoreModal')).hide();
        document.getElementById('errorMessage').textContent = result.message;
        new bootstrap.Modal(document.getElementById('errorModal')).show();
      }
    }
  } catch (error) {
    console.error('Error restoring employees:', error);
    bootstrap.Modal.getInstance(document.getElementById('restoreModal')).hide();
    document.getElementById('errorMessage').textContent = 'Failed to connect to server';
    new bootstrap.Modal(document.getElementById('errorModal')).show();
  } finally {
    btn.disabled = false;
    btnText.textContent = 'Restore Employees';
    btnSpinner.style.display = 'none';
  }
}

// Show center alert
function showCenterAlert() {
  const centerAlert = document.getElementById('centerAlert');
  centerAlert.style.display = 'block';
  centerAlert.style.opacity = '1';
  setTimeout(() => {
    centerAlert.style.transition = 'opacity 0.5s';
    centerAlert.style.opacity = '0';
    setTimeout(() => centerAlert.style.display = 'none', 500);
  }, 2000);
}

// Show error
function showError(message) {
  const tbody = document.getElementById('tableBody');
  tbody.innerHTML = `
    <tr>
      <td colspan="6" class="text-center py-4 text-danger">
        <i class="fa-solid fa-exclamation-triangle fs-1 d-block mb-2"></i>
        ${message}
      </td>
    </tr>
  `;
}
</script>

  
<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="settings.js"></script>
</body>
</html>
