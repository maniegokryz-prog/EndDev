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
        <tr>
          <td><input type="checkbox" class="selectBox"></td>
          <td>
            <div class="employee-info">
              <img src="pic.png" alt="">
              <div>
                <strong>Ronnel Borlongan</strong><br>
                <small>MA22000000</small>
              </div>
            </div>
          </td>
          <td>ronnel.borlongan@example.com</td>
          <td>0917-123-4567</td>
          <td>09/23/2025</td>
         <td class="text-center">
            <div class="dropdown">
                <button class="btn btn-link text-dark p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-solid fa-ellipsis-vertical fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <button class="dropdown-item d-flex align-items-center view-details-btn"
                            data-bs-toggle="modal" data-bs-target="#staffModal"
                            data-name="Ronnel P. Borlongan"
                            data-id="MA22010000"
                            data-dept="Faculty | IS Department"
                            data-email="sample@gmail.com"
                            data-contact="0912-345-6789"
                            data-img="pic.png">
                    <i class="fa-solid fa-user me-2 text-primary"></i>
                    See Details
                    </button>
                </li>
                </ul>
            </div>
            </td>
        </tr>

        <tr>
          <td><input type="checkbox" class="selectBox"></td>
          <td>
            <div class="employee-info">
              <img src="pic.png" alt="">
              <div>
                <strong>Justine Alianza</strong><br>
                <small>MA22000000</small>
              </div>
            </div>
          </td>
          <td>justine.alianza@example.com</td>
          <td>0917-123-4567</td>
          <td>09/23/2025</td>
         <td class="text-center">
            <div class="dropdown">
                <button class="btn btn-link text-dark p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-solid fa-ellipsis-vertical fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <button class="dropdown-item d-flex align-items-center view-details-btn"
                            data-bs-toggle="modal" data-bs-target="#staffModal"
                            data-name="Ronnel P. Borlongan"
                            data-id="MA22010000"
                            data-dept="Faculty | IS Department"
                            data-email="sample@gmail.com"
                            data-contact="0912-345-6789"
                            data-img="pic.png">
                    <i class="fa-solid fa-user me-2 text-primary"></i>
                    See Details
                    </button>
                </li>
                </ul>
            </div>
            </td>
        </tr>

        <tr>
          <td><input type="checkbox" class="selectBox"></td>
          <td>
            <div class="employee-info">
              <img src="pic.png" alt="">
              <div>
                <strong>Kryztian Maniego</strong><br>
                <small>MA22000002</small>
              </div>
            </div>
          </td>
          <td>kryztian.maniego@example.com</td>
          <td>0917-999-1234</td>
          <td>09/23/2025</td>
            <td class="text-center">
            <div class="dropdown">
                <button class="btn btn-link text-dark p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-solid fa-ellipsis-vertical fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <button class="dropdown-item d-flex align-items-center view-details-btn"
                            data-bs-toggle="modal" data-bs-target="#staffModal"
                            data-name="Ronnel P. Borlongan"
                            data-id="MA22010000"
                            data-dept="Faculty | IS Department"
                            data-email="sample@gmail.com"
                            data-contact="0912-345-6789"
                            data-img="pic.png">
                    <i class="fa-solid fa-user me-2 text-primary"></i>
                    See Details
                    </button>
                </li>
                </ul>
            </div>
            </td>
        </tr>
        
        <tr>
          <td><input type="checkbox" class="selectBox"></td>
          <td>
            <div class="employee-info">
              <img src="pic.png" alt="">
              <div>
                <strong>Lord Gabrial Castro</strong><br>
                <small>MA22000003</small>
              </div>
            </div>
          </td>
          <td>lord.castro@example.com</td>
          <td>0917-222-8877</td>
          <td>09/23/2025</td>
             <td class="text-center">
            <div class="dropdown">
                <button class="btn btn-link text-dark p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-solid fa-ellipsis-vertical fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <button class="dropdown-item d-flex align-items-center view-details-btn"
                            data-bs-toggle="modal" data-bs-target="#staffModal"
                            data-name="Ronnel P. Borlongan"
                            data-id="MA22010000"
                            data-dept="Faculty | IS Department"
                            data-email="sample@gmail.com"
                            data-contact="0912-345-6789"
                            data-img="https://via.placeholder.com/120">
                    <i class="fa-solid fa-user me-2 text-primary"></i>
                    See Details
                    </button>
                </li>
                </ul>
            </div>
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


document.addEventListener("DOMContentLoaded", function () {
  const detailButtons = document.querySelectorAll(".view-details-btn");
  const staffModal = new bootstrap.Modal(document.getElementById("staffModal"));
  
  detailButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      document.getElementById("staffImg").src = btn.getAttribute("data-img");
      document.getElementById("staffName").textContent = btn.getAttribute("data-name");
      document.getElementById("staffId").textContent = btn.getAttribute("data-id");
      document.getElementById("staffDept").textContent = btn.getAttribute("data-dept");
      document.getElementById("staffEmail").textContent = btn.getAttribute("data-email");
      document.getElementById("staffContact").textContent = btn.getAttribute("data-contact");
    });
  });
});


  const selectAllBtn = document.getElementById("selectAllBtn");
  const deleteBtn = document.getElementById("deleteSelectedBtn");
  const restoreBtn = document.getElementById("restoreSelectedBtn");
  const checkboxes = document.querySelectorAll(".selectBox");
  const tableBody = document.getElementById("tableBody");
  const noDataMsg = document.getElementById("noDataMessage");
  const searchInput = document.getElementById("searchInput");
  const centerAlert = document.getElementById("centerAlert");

  let allSelected = false;

  // ✅ Function to show center alert
  function showCenterAlert() {
    centerAlert.style.display = "block";
    centerAlert.style.opacity = "1";
    setTimeout(() => {
      centerAlert.style.transition = "opacity 0.5s";
      centerAlert.style.opacity = "0";
      setTimeout(() => centerAlert.style.display = "none", 500);
    }, 2000); // disappears after 2 seconds
  }

  // ✅ Select All / Deselect All
  selectAllBtn.addEventListener("click", () => {
    allSelected = !allSelected;
    checkboxes.forEach(cb => cb.checked = allSelected);
    selectAllBtn.textContent = allSelected ? "Deselect All" : "Select All";
  });

  // ✅ Live Search (auto filter as you type)
  searchInput.addEventListener("keyup", () => {
    const searchVal = searchInput.value.toLowerCase();
    let visibleRows = 0;

    tableBody.querySelectorAll("tr").forEach(row => {
      const name = row.querySelector("strong").textContent.toLowerCase();
      if (name.includes(searchVal)) {
        row.style.display = "";
        visibleRows++;
      } else {
        row.style.display = "none";
      }
    });

    noDataMsg.classList.toggle("d-none", visibleRows > 0);
  });

  // ✅ Delete Selected
  deleteBtn.addEventListener("click", () => {
    const selected = document.querySelectorAll(".selectBox:checked");
    if (selected.length === 0) {
      showCenterAlert();
      return;
    }

    const modal = new bootstrap.Modal(document.getElementById("deleteConfirmModal"));
    modal.show();

    document.getElementById("confirmDelete").onclick = () => {
      selected.forEach(cb => cb.closest("tr").remove());
      modal.hide();
      checkIfEmpty();
    };
  });

  // ✅ Restore Selected
  restoreBtn.addEventListener("click", () => {
    const selected = document.querySelectorAll(".selectBox:checked");
    if (selected.length === 0) {
      showCenterAlert();
      return;
    }

    const modal = new bootstrap.Modal(document.getElementById("restoreModal"));
    modal.show();

    document.getElementById("confirmRestore").onclick = () => {
      selected.forEach(cb => cb.closest("tr").remove());
      modal.hide();
      new bootstrap.Modal(document.getElementById("restoreSuccessModal")).show();
      checkIfEmpty();
    };
  });

  // ✅ Check if table empty
  function checkIfEmpty() {
    const visibleRows = tableBody.querySelectorAll("tr").length;
    noDataMsg.classList.toggle("d-none", visibleRows > 0);
  }
</script>

  
<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="settings.js"></script>
</body>
</html>
