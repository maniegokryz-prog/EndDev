    <?php
require '../db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Employee Archive - Attendance System</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
 <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

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
        <!-- Employee rows will be loaded dynamically via JavaScript -->
        <tr>
          <td colspan="6" class="text-center">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading archived employees...</p>
          </td>
        </tr>
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


<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteConfirmLabel">Confirm Deletion</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to permanently delete the designated employee(s)? This action cannot be undone.
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="confirmDelete">Yes, Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Are you sure you want to delete the selected employees?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmDelete" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- RESTORE MODAL -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="restoreModalLabel">Confirm Restore</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Are you sure you want to restore the selected employees?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmRestore" class="btn btn-success">Restore</button>
      </div>
    </div>
  </div>
</div>


<!-- Restore Success Modal -->
<div class="modal fade" id="restoreSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <i class="fa-solid fa-check-circle text-success fs-1 mb-3"></i>
        <p>Selected employee(s) successfully restored.</p>
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
// Employee Archive Management System
let archivedEmployees = [];
let allSelected = false;

document.addEventListener("DOMContentLoaded", function () {
  loadArchivedEmployees();
  setupEventListeners();
});

// Load archived employees from API
async function loadArchivedEmployees() {
  try {
    const response = await fetch('../staffmanagement/api/archive_employee.php?action=list_archived', {
      method: 'GET',
      credentials: 'same-origin'
    });
    
    const result = await response.json();
    
    if (result.success) {
      archivedEmployees = result.employees;
      renderEmployeeTable();
    } else {
      showError('Failed to load archived employees: ' + result.message);
    }
  } catch (error) {
    console.error('Error loading archived employees:', error);
    showError('Error loading archived employees. Please refresh the page.');
  }
}

// Render employee table
function renderEmployeeTable(filterText = '') {
  const tableBody = document.getElementById("tableBody");
  const noDataMsg = document.getElementById("noDataMessage");
  
  if (archivedEmployees.length === 0) {
    tableBody.innerHTML = '';
    noDataMsg.classList.remove("d-none");
    return;
  }
  
  const filteredEmployees = filterText
    ? archivedEmployees.filter(emp => {
        const fullName = `${emp.first_name} ${emp.middle_name || ''} ${emp.last_name}`.toLowerCase();
        return fullName.includes(filterText.toLowerCase()) || emp.employee_id.toLowerCase().includes(filterText.toLowerCase());
      })
    : archivedEmployees;
  
  if (filteredEmployees.length === 0) {
    tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No matching employees found</td></tr>';
    return;
  }
  
  tableBody.innerHTML = filteredEmployees.map(emp => {
    const fullName = `${emp.first_name} ${emp.middle_name || ''} ${emp.last_name}`.trim();
    const profilePic = emp.profile_photo ? `../staffmanagement/${emp.profile_photo}` : 'pic.png';
    const archivedDate = new Date(emp.archived_at).toLocaleDateString('en-US', { 
      month: '2-digit', 
      day: '2-digit', 
      year: 'numeric' 
    });
    
    return `
      <tr data-employee-id="${emp.employee_id}">
        <td><input type="checkbox" class="selectBox" value="${emp.employee_id}"></td>
        <td>
          <div class="employee-info">
            <img src="${profilePic}" alt="${fullName}" onerror="this.src='pic.png'">
            <div>
              <strong>${fullName}</strong><br>
              <small>${emp.employee_id}</small>
            </div>
          </div>
        </td>
        <td>${emp.email || 'N/A'}</td>
        <td>${emp.phone || 'N/A'}</td>
        <td>${archivedDate}</td>
        <td class="text-center">
          <div class="dropdown">
            <button class="btn btn-link text-dark p-0 border-0" type="button" data-bs-toggle="dropdown">
              <i class="fa-solid fa-ellipsis-vertical fs-5"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
              <li>
                <button class="dropdown-item d-flex align-items-center view-details-btn"
                        onclick="showEmployeeDetails('${emp.employee_id}')">
                  <i class="fa-solid fa-user me-2 text-primary"></i>
                  See Details
                </button>
              </li>
            </ul>
          </div>
        </td>
      </tr>
    `;
  }).join('');
  
  noDataMsg.classList.add("d-none");
}

// Show employee details modal
function showEmployeeDetails(employeeId) {
  const employee = archivedEmployees.find(emp => emp.employee_id === employeeId);
  if (!employee) return;
  
  const fullName = `${employee.first_name} ${employee.middle_name || ''} ${employee.last_name}`.trim();
  const profilePic = employee.profile_photo ? `../staffmanagement/${employee.profile_photo}` : 'pic.png';
  const department = `${employee.roles || 'N/A'} | ${employee.department || 'N/A'}`;
  
  document.getElementById("staffImg").src = profilePic;
  document.getElementById("staffName").textContent = fullName;
  document.getElementById("staffId").textContent = employee.employee_id;
  document.getElementById("staffDept").textContent = department;
  document.getElementById("staffEmail").textContent = employee.email || 'N/A';
  document.getElementById("staffContact").textContent = employee.phone || 'N/A';
  
  const modal = new bootstrap.Modal(document.getElementById("staffModal"));
  modal.show();
}

// Setup event listeners
function setupEventListeners() {
  const selectAllBtn = document.getElementById("selectAllBtn");
  const deleteBtn = document.getElementById("deleteSelectedBtn");
  const restoreBtn = document.getElementById("restoreSelectedBtn");
  const searchInput = document.getElementById("searchInput");
  
  // Select All / Deselect All
  selectAllBtn.addEventListener("click", () => {
    allSelected = !allSelected;
    const checkboxes = document.querySelectorAll(".selectBox");
    checkboxes.forEach(cb => cb.checked = allSelected);
    selectAllBtn.textContent = allSelected ? "Deselect All" : "Select All";
  });
  
  // Live Search
  searchInput.addEventListener("keyup", (e) => {
    renderEmployeeTable(e.target.value);
  });
  
  // Delete Selected
  deleteBtn.addEventListener("click", () => {
    const selected = getSelectedEmployees();
    if (selected.length === 0) {
      showCenterAlert();
      return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById("deleteConfirmModal"));
    modal.show();
  });
  
  // Restore Selected
  restoreBtn.addEventListener("click", () => {
    const selected = getSelectedEmployees();
    if (selected.length === 0) {
      showCenterAlert();
      return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById("restoreModal"));
    modal.show();
  });
  
  // Confirm Delete
  document.getElementById("confirmDelete").addEventListener("click", async () => {
    const selected = getSelectedEmployees();
    await permanentlyDeleteEmployees(selected);
  });
  
  // Confirm Restore
  document.getElementById("confirmRestore").addEventListener("click", async () => {
    const selected = getSelectedEmployees();
    await restoreEmployees(selected);
  });
}

// Get selected employee IDs
function getSelectedEmployees() {
  const checkboxes = document.querySelectorAll(".selectBox:checked");
  return Array.from(checkboxes).map(cb => cb.value);
}

// Permanently delete employees from archive
async function permanentlyDeleteEmployees(employeeIds) {
  try {
    const formData = new FormData();
    formData.append('action', 'delete');
    employeeIds.forEach(id => formData.append('employee_ids[]', id));
    
    const response = await fetch('../staffmanagement/api/archive_employee.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Hide delete modal
      const deleteModal = bootstrap.Modal.getInstance(document.getElementById("deleteConfirmModal"));
      if (deleteModal) deleteModal.hide();
      
      // Reload employees
      await loadArchivedEmployees();
      
      // Show success message
      showSuccess(`Successfully deleted ${result.deleted_count} employee(s)`);
    } else {
      showError('Delete operation failed: ' + result.message);
    }
  } catch (error) {
    console.error('Error deleting employees:', error);
    showError('Error deleting employees. Please try again.');
  }
}

// Restore employees to active
async function restoreEmployees(employeeIds) {
  try {
    const formData = new FormData();
    formData.append('action', 'restore');
    employeeIds.forEach(id => formData.append('employee_ids[]', id));
    
    const response = await fetch('../staffmanagement/api/archive_employee.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Hide restore modal
      const restoreModal = bootstrap.Modal.getInstance(document.getElementById("restoreModal"));
      if (restoreModal) restoreModal.hide();
      
      // Show success modal
      const successModal = new bootstrap.Modal(document.getElementById("restoreSuccessModal"));
      successModal.show();
      
      // Reload employees after delay
      setTimeout(async () => {
        await loadArchivedEmployees();
      }, 1500);
      
      showSuccess(`Successfully restored ${result.restored_count} employee(s)`);
    } else {
      showError('Restore operation failed: ' + result.message);
    }
  } catch (error) {
    console.error('Error restoring employees:', error);
    showError('Error restoring employees. Please try again.');
  }
}

// Show center alert for no selection
function showCenterAlert() {
  const centerAlert = document.getElementById("centerAlert");
  centerAlert.style.display = "block";
  centerAlert.style.opacity = "1";
  setTimeout(() => {
    centerAlert.style.transition = "opacity 0.5s";
    centerAlert.style.opacity = "0";
    setTimeout(() => centerAlert.style.display = "none", 500);
  }, 2000);
}

// Show success message
function showSuccess(message) {
  // You can implement a toast notification here
  console.log('Success:', message);
}

// Show error message
function showError(message) {
  alert('Error: ' + message);
  console.error('Error:', message);
}
</script>

  
<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="settings.js"></script>
</body>
</html>
