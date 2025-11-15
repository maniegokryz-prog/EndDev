<?php
// Protect this page - require authentication and admin role
require_once '../auth_guard.php';
requireAdmin(); // Only admins can access settings

// Get current user info
$currentUser = getCurrentUser();
?>
    <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Settings - Attendance System</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

 <!-- ✅ BOOTSTRAP 5 -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
 <!-- Bootstrap Icons -->
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

  <!-- ✅ FONT AWESOME (official CDN – works on localhost) -->
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
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold display-4 text-dark">Settings</h2>
     <div class="d-flex justify-content-end mb-3">
    </div>
  </div>

      <div class="row g-4 justify-content-center">
      <div class="col-6 col-md-3">
        <div class="setting-card" id="changePassword">
          <div class="setting-icon">
            <i class="fas fa-lock"></i>
          </div>
          <h6>Change Password</h6>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="setting-card" id="privacyPolicy">
          <div class="setting-icon">
            <i class="fas fa-info-circle"></i>
          </div>
          <h6>Privacy Policy / Terms</h6>
        </div>
      </div>

      <div class="col-6 col-md-3">
  <div class="setting-card" id="employeeArchive" style="cursor: pointer;">
    <div class="setting-icon">
      <i class="fas fa-user-tie"></i>
    </div>
    <h6>Employee Archive</h6>
  </div>
</div>

  <!-- ✅ Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">

      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold" id="changePasswordLabel">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body px-4 pb-4">
        <form id="changePasswordForm">

          <div class="mb-3">
            <label for="currentPassword" class="form-label fw-semibold">Current Password</label>
            <input type="password" class="form-control" id="currentPassword" placeholder="Enter Current Password" required>
          </div>

          <div class="mb-4">
            <label for="newPassword" class="form-label fw-semibold">New Password</label>
            <input type="password" class="form-control" id="newPassword" placeholder="Enter New Password" required>
          </div>

          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-outline-danger px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn text-white px-4" style="background-color: #083c34;">Save</button>
          </div>

        </form>
      </div>

    </div>
  </div>
</div>

<!-- ✅ Privacy Policy / Terms Modal -->
<div class="modal fade" id="privacyPolicyModal" tabindex="-1" aria-labelledby="privacyPolicyLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg">

      <div class="modal-header border-0">
        <h4 class="modal-title fw-bold" id="privacyPolicyLabel">Privacy Policy and Terms</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body px-4 pb-3" style="max-height: 70vh; overflow-y: auto;">
        
        <h5 class="fw-bold mt-2">Privacy Policy</h5>
        <p>This Automated Attendance System uses facial recognition technology to record and manage the attendance of administrators, faculty members, and non-teaching staff. By using this system, you consent to the collection and processing of:</p>

        <ul>
          <li>Facial recognition data (biometric information)</li>
          <li>Personal details (name, employee ID, position)</li>
          <li>Attendance logs and related activity records</li>
        </ul>

        <p>All data is stored securely and will only be used for official attendance monitoring and administrative purposes. Information will not be disclosed to third parties, except when required by institutional policies or applicable laws.</p>

        <p>Authorized users are expected to maintain the confidentiality of their login credentials and avoid unauthorized system access.</p>

        <hr>

        <h5 class="fw-bold">Terms of Use</h5>
        <p>By accessing and using this system, you agree to:</p>
        <ol>
          <li>Allow the system to capture and process your facial data strictly for attendance purposes.</li>
          <li>Use your account only for official work-related activities.</li>
          <li>Avoid sharing your account or attempting to bypass the system’s security and recognition processes.</li>
          <li>Protect sensitive data such as attendance records, staff profiles, and system logs from misuse.</li>
        </ol>

        <p>Violation of these terms may result in account suspension, administrative action, or sanctions based on institutional policies.</p>

      </div>

      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn text-white px-5 py-2" style="background-color: #083c34;" id="acceptPolicyBtn">Accept</button>
      </div>

    </div>
  </div>
</div>


  <!-- CLEAR ALL RECORDS CARD -->
<div class="col-6 col-md-3">
  <div class="setting-card" id="clearRecords" style="cursor:pointer;">
    <div class="setting-icon">
      <i class="fas fa-trash"></i>
    </div>
    <h6>CLEAR ALL RECORDS</h6>
  </div>
</div>

<!-- 🔹 FIRST CONFIRMATION MODAL -->
<div class="modal fade" id="firstConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-3">
      <div class="modal-body">
        <i class="fa-solid fa-triangle-exclamation text-warning mb-3" style="font-size:3rem;"></i>
        <h5 class="mb-3">This will delete all the attendance records (1000).</h5>
        <p class="text-muted">Are you sure you want to continue?</p>
        <div class="d-flex justify-content-center gap-3 mt-3">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-danger" id="continueBtn">Continue</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 🔹 SECOND CONFIRMATION MODAL -->
<div class="modal fade" id="secondConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-3">
      <div class="modal-body">
        <i class="fa-solid fa-triangle-exclamation text-warning mb-3" style="font-size:3rem;"></i>
        <h5 class="mb-3">You cannot undo this action.</h5>
        <p class="text-muted">Do you wish to continue?</p>
        <div class="d-flex justify-content-center gap-3 mt-3">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-danger" id="confirmDeleteBtn">Yes</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 🔹 SUCCESS MODAL -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4">
      <div class="modal-body">
        <i class="fa-solid fa-circle-check text-success mb-3" style="font-size:3rem;"></i>
        <h5>All records are deleted.</h5>
        <p class="text-muted">Returning to settings...</p>
      </div>
    </div>
  </div>
</div>

<!-- ✅ Bootstrap JS and Script -->
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const clearBtn = document.getElementById('clearRecords');
  const continueBtn = document.getElementById('continueBtn');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

  const firstModal = new bootstrap.Modal(document.getElementById('firstConfirmModal'));
  const secondModal = new bootstrap.Modal(document.getElementById('secondConfirmModal'));
  const successModal = new bootstrap.Modal(document.getElementById('successModal'));

  // Step 1 - Click CLEAR ALL RECORDS
  clearBtn.addEventListener('click', () => {
    firstModal.show();
  });

  // Step 2 - Click CONTINUE in first modal
  continueBtn.addEventListener('click', () => {
    firstModal.hide();
    setTimeout(() => {
      secondModal.show();
    }, 300);
  });

  // Step 3 - Click YES in second modal
  confirmDeleteBtn.addEventListener('click', () => {
    secondModal.hide();
    setTimeout(() => {
      successModal.show();

      // Step 4 - Simulate deletion + redirect
      setTimeout(() => {
        window.location.href = "settings.php";
      }, 2000);
    }, 300);
  });
});
</script>


  
<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
 <script src="settings.js"></script>
</body>
</html>
