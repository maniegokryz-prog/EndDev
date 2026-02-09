<?php
// Protect this page - require authentication
require_once '../auth_guard.php';
require_once '../navigation.php';

// Get current user info
$currentUser = getCurrentUser();
$isAdmin = isAdmin();

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Settings - Attendance System</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- ✅ BOOTSTRAP 5 -->

  <!-- Bootstrap Icons -->
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">



  <!-- Custom CSS -->
  <link rel="stylesheet" href="settings.css">

  <!-- CSRF Token for JavaScript -->
  <script>
    const CSRF_TOKEN = "<?php echo $csrfToken; ?>";
  </script>
</head>

<body>


  <div class="top-navbar d-flex justify-content-between align-items-center p-2 shadow-sm">
    <div class="menu-toggle">
      <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn"></i>
    </div>
    <?php include '../includes/notification_bell.php'; ?>
  </div>

  <!-- Sidebar -->
  <div class="sidebar d-flex flex-column pt-5" id="sidebar">
    <div class="profile text-center p-3 mt-4">
      <img
        src="<?php echo !empty($currentUser['profile_photo']) ? '../' . htmlspecialchars($currentUser['profile_photo'], ENT_QUOTES, 'UTF-8') . '?v=' . time() : '../assets/profile_pic/user.png?v=' . time(); ?>"
        alt="Profile" class="rounded-circle mb-2" width="70" height="70"
        onerror="this.src='../assets/profile_pic/user.png';">
      <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h5>
      <small
        class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></small>
    </div>
    <nav class="nav flex-column px-2">
      <?php renderNavigation('Settings'); ?>
    </nav>
  </div>
  <!----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
  <div class="content pt-3" id="content">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="display-4 text-dark">Settings</h2>
        <div class="d-flex justify-content-end mb-3">
        </div>
      </div>

      <div class="row g-4 justify-content-center">
        <div class="col-6 col-md-3">
          <div class="setting-card" id="changePassword">
            <div class="setting-icon">
              <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h6>Change Password</h6>
          </div>
        </div>

        <div class="col-6 col-md-3">
          <div class="setting-card" id="privacyPolicy">
            <div class="setting-icon">
              <i class="bi bi-shield-check"></i>
            </div>
            <h6>Privacy Policy / Terms</h6>
          </div>
        </div>

        <?php if ($isAdmin): ?>
        <div class="col-6 col-md-3">
          <div class="setting-card" id="leaveSettings" style="cursor: pointer;">
            <div class="setting-icon">
              <i class="bi bi-calendar2-check-fill"></i>
            </div>
            <h6>Leave Settings</h6>
          </div>
        </div>

        <div class="col-6 col-md-3">
          <div class="setting-card" id="employeeArchive" style="cursor: pointer;">
            <div class="setting-icon">
              <i class="bi bi-archive-fill"></i>
            </div>
            <h6>Employee Archive</h6>
          </div>
        </div>
        <?php endif; ?>

        <!-- ✅ Change Password Modal - Step 1: Verify Email -->
        <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordLabel"
          aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">

              <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="changePasswordLabel">Change Password - Step 1</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>

              <div class="modal-body px-4 pb-4">
                <p class="text-muted mb-3">We'll send a verification code to your email</p>

                <div class="alert alert-danger" id="changeStep1Error" style="display:none;"></div>

                <div class="mb-3">
                  <label for="changeEmail" class="form-label fw-semibold">Email Address</label>
                  <input type="email" class="form-control" id="changeEmail" placeholder="Enter your registered email"
                    required>
                </div>

                <div class="d-flex justify-content-between mt-4">
                  <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn text-white px-4" style="background-color: #083c34;"
                    id="changeToStep2">Send OTP</button>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- ✅ Change Password Modal - Step 2: Verify OTP -->
        <div class="modal fade" id="changePasswordModalStep2" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">

              <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Change Password - Step 2</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>

              <div class="modal-body px-4 pb-4">
                <p class="text-muted mb-3">Enter the 6-digit code sent to your email</p>

                <div class="alert alert-success" id="changeStep2Success" style="display:none;"></div>
                <div class="alert alert-danger" id="changeStep2Error" style="display:none;"></div>

                <div class="mb-3">
                  <label for="changeOtpCode" class="form-label fw-semibold">OTP Code</label>
                  <input type="text" class="form-control" id="changeOtpCode" maxlength="6" pattern="[0-9]{6}"
                    placeholder="Enter 6-digit code" required>
                </div>

                <div class="d-flex justify-content-between mt-4">
                  <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn text-white px-4" style="background-color: #083c34;"
                    id="changeToStep3">Verify OTP</button>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- ✅ Change Password Modal - Step 3: Enter New Password -->
        <div class="modal fade" id="changePasswordModalStep3" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">

              <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Change Password - Step 3</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>

              <div class="modal-body px-4 pb-4">
                <p class="text-muted mb-3">Enter your new password</p>

                <div class="alert alert-danger" id="changeStep3Error" style="display:none;"></div>

                <div class="mb-3">
                  <label for="changeNewPassword" class="form-label fw-semibold">New Password</label>
                  <input type="password" class="form-control" id="changeNewPassword" placeholder="Enter new password"
                    required>
                </div>

                <div class="mb-3">
                  <label for="changeConfirmPassword" class="form-label fw-semibold">Confirm Password</label>
                  <input type="password" class="form-control" id="changeConfirmPassword"
                    placeholder="Confirm new password" required>
                </div>

                <div class="d-flex justify-content-between mt-4">
                  <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn text-white px-4" style="background-color: #083c34;"
                    id="changeFinalStep">Change Password</button>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- ✅ Change Password Success Modal -->
        <div class="modal fade" id="changePasswordSuccess" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-center">
              <div class="modal-body px-4 py-5">
                <div class="mb-3">
                  <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                </div>
                <h5 class="fw-bold mb-2">Password Changed Successfully!</h5>
                <p class="text-muted">Your password has been updated.</p>
                <button type="button" class="btn text-white px-4 mt-3" style="background-color: #083c34;"
                  id="changeGoSettings">Back to Settings</button>
              </div>
            </div>
          </div>
        </div>

        <!-- ✅ Privacy Policy / Terms Modal -->
        <div class="modal fade" id="privacyPolicyModal" tabindex="-1" aria-labelledby="privacyPolicyLabel"
          aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">

              <div class="modal-header border-0">
                <h4 class="modal-title fw-bold" id="privacyPolicyLabel">Privacy Policy and Terms</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>

              <div class="modal-body px-4 pb-3" style="max-height: 70vh; overflow-y: auto;">

                <h5 class="fw-bold mt-2">Privacy Policy</h5>
                <p>This Automated Attendance System uses facial recognition technology to record and manage the
                  attendance of administrators, faculty members, and non-teaching staff. By using this system, you
                  consent to the collection and processing of:</p>

                <ul>
                  <li>Facial recognition data (biometric information)</li>
                  <li>Personal details (name, employee ID, position)</li>
                  <li>Attendance logs and related activity records</li>
                </ul>

                <p>All data is stored securely and will only be used for official attendance monitoring and
                  administrative purposes. Information will not be disclosed to third parties, except when required by
                  institutional policies or applicable laws.</p>

                <p>Authorized users are expected to maintain the confidentiality of their login credentials and avoid
                  unauthorized system access.</p>

                <hr>

                <h5 class="fw-bold">Terms of Use</h5>
                <p>By accessing and using this system, you agree to:</p>
                <ol>
                  <li>Allow the system to capture and process your facial data strictly for attendance purposes.</li>
                  <li>Use your account only for official work-related activities.</li>
                  <li>Avoid sharing your account or attempting to bypass the system’s security and recognition
                    processes.</li>
                  <li>Protect sensitive data such as attendance records, staff profiles, and system logs from misuse.
                  </li>
                </ol>

                <p>Violation of these terms may result in account suspension, administrative action, or sanctions
                  based on institutional policies.</p>

              </div>

              <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn text-white px-5 py-2" style="background-color: #083c34;"
                  id="acceptPolicyBtn">Accept</button>
              </div>

            </div>
          </div>
        </div>


        <!-- ✅ Leave Settings Modal -->
        <div class="modal fade" id="leaveSettingsModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
              <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Leave Request Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body px-4 pb-4">
                <p class="text-muted mb-3">Set restrictions for leave requests.</p>

                <div class="mb-3">
                  <label for="noticePeriodInput" class="form-label fw-semibold">Min. Notice Period (Days)</label>
                  <input type="number" class="form-control" id="noticePeriodInput" min="0" value="0">
                  <small class="text-muted">Users must request leave at least this many days in advance.</small>
                </div>

                <div class="d-flex justify-content-end mt-4">
                  <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn text-white" style="background-color: #083c34;"
                    id="saveLeaveSettingsBtn">Save Changes</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- CLEAR ALL RECORDS CARD - ADMIN ONLY -->
        <?php if ($isAdmin): ?>
          <div class="col-6 col-md-3">
            <div class="setting-card" id="clearAllRecords" style="cursor:pointer;" onclick="handleClearRecordsClick()">
              <div class="setting-icon">
                <i class="bi bi-trash3-fill"></i>
              </div>
              <h6>CLEAR ALL RECORDS</h6>
            </div>
          </div>
        <?php endif; ?>

        <!-- 🔹 FIRST CONFIRMATION MODAL -->
        <div class="modal fade" id="clearAllRecordsModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-3">
              <div class="modal-body">
                <i class="bi bi-exclamation-triangle-fill text-warning mb-3" style="font-size:3rem;"></i>
                <h5 class="mb-3">
                  This will delete all the attendance records
                  <span id="recordsCountDisplay">
                    (<span class="spinner-border spinner-border-sm" role="status"></span>)
                  </span>.
                </h5>
                <p class="text-muted">Are you sure you want to continue?</p>
                <div class="d-flex justify-content-center gap-3 mt-3">
                  <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-danger" id="proceedDeleteBtn">Continue</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 🔹 SECOND CONFIRMATION MODAL WITH PASSWORD -->
        <div class="modal fade" id="secondConfirmModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3">
              <div class="modal-header border-0">
                <h5 class="modal-title">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body text-center">
                <i class="bi bi-exclamation-triangle-fill text-warning mb-3" style="font-size:3rem;"></i>
                <h5 class="mb-3">You cannot undo this action.</h5>
                <p class="text-muted mb-3">Do you wish to continue?</p>

                <form id="clearRecordsForm">
                  <div class="mb-3 text-start">
                    <label for="clearPasswordInput" class="form-label">Admin Password <span
                        class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="clearPasswordInput" required
                      placeholder="Enter your password">
                    <div id="clearPasswordError" class="text-danger small mt-1" style="display: none;"></div>
                  </div>
                </form>
              </div>
              <div class="modal-footer border-0 justify-content-center">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">
                  <span id="deleteBtnText">Yes, Clear All</span>
                  <span id="deleteBtnSpinner" class="spinner-border spinner-border-sm ms-2"
                    style="display: none;"></span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- 🔹 SUCCESS MODAL -->
        <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
              <div class="modal-body">
                <i class="bi bi-check-circle-fill text-success mb-3" style="font-size:3rem;"></i>
                <h5>All records are deleted.</h5>
                <p class="text-muted">Returning to settings...</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ✅ Bootstrap JS and Script -->



        <!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
        <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="settings.js"></script>

        <script>
          // Clear All Records functionality
          async function handleClearRecordsClick() {
            console.log('Clear All Records clicked!');

            // Fetch the actual count of records
            try {
              const response = await fetch('processes/get_records_count.php');
              const result = await response.json();

              console.log('Records count result:', result);

              if (result.success) {
                document.getElementById('recordsCountDisplay').textContent = `(${result.count.toLocaleString()})`;
              } else {
                document.getElementById('recordsCountDisplay').textContent = '(unknown)';
              }
            } catch (error) {
              console.error('Failed to fetch record count:', error);
              document.getElementById('recordsCountDisplay').textContent = '(unknown)';
            }

            const modal = new bootstrap.Modal(document.getElementById("clearAllRecordsModal"));
            modal.show();
          }

          // Handle proceeding to second confirmation
          document.getElementById("proceedDeleteBtn").addEventListener("click", () => {
            bootstrap.Modal.getInstance(document.getElementById("clearAllRecordsModal")).hide();
            const secondModal = new bootstrap.Modal(document.getElementById("secondConfirmModal"));
            secondModal.show();
          });

          // Handle final confirmation with password
          document.getElementById("confirmDeleteBtn").addEventListener("click", async function () {
            const password = document.getElementById('clearPasswordInput').value.trim();
            const errorDiv = document.getElementById('clearPasswordError');
            const btnText = document.getElementById('deleteBtnText');
            const btnSpinner = document.getElementById('deleteBtnSpinner');

            if (!password) {
              errorDiv.textContent = 'Please enter your password';
              errorDiv.style.display = 'block';
              return;
            }

            errorDiv.style.display = 'none';
            this.disabled = true;
            btnText.style.display = 'none';
            btnSpinner.style.display = 'inline-block';

            try {
              const formData = new FormData();
              formData.append('admin_password', password);
              formData.append('csrf_token', CSRF_TOKEN);
              const response = await fetch('processes/clear_all_records.php', {
                method: 'POST',
                body: formData
              });

              const result = await response.json();

              if (result.success) {
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById("secondConfirmModal")).hide();

                // Clear password input
                document.getElementById('clearPasswordInput').value = '';

                // Show success message with count
                const message = result.count > 0
                  ? `Successfully cleared ${result.count} attendance record(s)`
                  : 'No records found to clear';
                showPopupMessage(message);

                // Redirect after a moment
                setTimeout(() => {
                  window.location.href = "settings.php";
                }, 2000);
              } else {
                errorDiv.textContent = result.message || result.error || 'Failed to clear records';
                errorDiv.style.display = 'block';
              }
            } catch (error) {
              console.error('Clear records error:', error);
              errorDiv.textContent = 'An error occurred. Please try again.';
              errorDiv.style.display = 'block';
            }

            this.disabled = false;
            btnText.style.display = 'inline';
            btnSpinner.style.display = 'none';
          });

          function showPopupMessage(message) {
            const popup = document.createElement("div");
            popup.textContent = message;
            popup.style.position = "fixed";
            popup.style.top = "50%";
            popup.style.left = "50%";
            popup.style.transform = "translate(-50%, -50%)";
            popup.style.backgroundColor = "#083c34";
            popup.style.color = "white";
            popup.style.padding = "15px 25px";
            popup.style.borderRadius = "10px";
            popup.style.fontWeight = "500";
            popup.style.boxShadow = "0 3px 10px rgba(0,0,0,0.2)";
            popup.style.zIndex = "2000";
            document.body.appendChild(popup);
            setTimeout(() => popup.remove(), 1500);
          }

          // Logout modal function
          function showLogoutModal() {
            var logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            logoutModal.show();
          }
        </script>

        <script>
          // Leave Settings Logic
          document.addEventListener('DOMContentLoaded', function () {
            const leaveSettingsCard = document.getElementById('leaveSettings');
            if (leaveSettingsCard) {
              leaveSettingsCard.addEventListener('click', function () {
                // Load current setting
                fetch('../staffmanagement/api/settings_api.php?action=get_leave_settings')
                  .then(res => res.json())
                  .then(data => {
                    if (data.success) {
                      document.getElementById('noticePeriodInput').value = data.notice_period_days;
                      new bootstrap.Modal(document.getElementById('leaveSettingsModal')).show();
                    } else {
                      alert('Failed to load settings');
                    }
                  });
              });
            }

            const saveBtn = document.getElementById('saveLeaveSettingsBtn');
            if (saveBtn) {
              saveBtn.addEventListener('click', function () {
                const days = document.getElementById('noticePeriodInput').value;
                const formData = new FormData();
                formData.append('action', 'update_leave_settings');
                formData.append('notice_period_days', days);

                fetch('../staffmanagement/api/settings_api.php', { method: 'POST', body: formData })
                  .then(res => res.json())
                  .then(data => {
                    if (data.success) {
                      bootstrap.Modal.getInstance(document.getElementById('leaveSettingsModal')).hide();
                      showPopupMessage('Leave settings updated successfully');
                    } else {
                      alert('Failed to save: ' + (data.error || 'Unknown error'));
                    }
                  });
              });
            }
          });
        </script>

        <!-- Logout Modal -->
        <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header border-0">
                <h5 class="modal-title w-100 text-center" id="logoutModalLabel">Confirm Logout</h5>
              </div>
              <div class="modal-body text-center">
                Are you sure you want to logout?
              </div>
              <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="logout.php" style="display: inline;">
                  <input type="hidden" name="confirm_logout" value="1">
                  <button type="submit" class="btn btn-danger">Yes, Log out</button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <script src="../dashboard/dashboard.js"></script>
</body>

</html>