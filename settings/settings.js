// Sidebar Toggle handled by dashboard.js
//----------------------------------------------------------------------------------------------//

// Employee Archive - only for admin users
const employeeArchiveBtn = document.getElementById("employeeArchive");
if (employeeArchiveBtn) {
  employeeArchiveBtn.addEventListener("click", function () {
    // Redirect to emploarc.php when card is clicked
    window.location.href = "emploarc.php";
  });
}

//changepass - OTP-based Password Change Flow
let changeEmployeeIdGlobal = '';

// Open SELECTION MODAL when "Change Password" card is clicked
document.getElementById("changePassword").addEventListener("click", () => {
  const modal = new bootstrap.Modal(document.getElementById("changePasswordSelectionModal"));
  modal.show();
});

// Handle "Via Email OTP" button click
document.getElementById("btnSelectOTP").addEventListener("click", () => {
  // Hide selection modal
  bootstrap.Modal.getInstance(document.getElementById("changePasswordSelectionModal")).hide();
  // Show OTP Step 1 modal
  const modal = new bootstrap.Modal(document.getElementById("changePasswordModal"));
  modal.show();
});

// Handle "Via Current Password" button click
document.getElementById("btnSelectCurrent").addEventListener("click", () => {
  // Hide selection modal
  bootstrap.Modal.getInstance(document.getElementById("changePasswordSelectionModal")).hide();
  // Show Current Password Change modal
  const modal = new bootstrap.Modal(document.getElementById("changePasswordViaCurrentModal"));
  modal.show();
});

// Toggle Password Visibility Helpers (for new modal)
function setupToggle(inputId, toggleId) {
  const toggleBtn = document.getElementById(toggleId);
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      const input = document.getElementById(inputId);
      const icon = this.querySelector('i');
      if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("bi-eye");
        icon.classList.add("bi-eye-slash");
      } else {
        input.type = "password";
        icon.classList.remove("bi-eye-slash");
        icon.classList.add("bi-eye");
      }
    });
  }
}
setupToggle('currentPasswordInput', 'toggleCurrentPassword');
setupToggle('newPasswordInput', 'toggleNewPassword');
setupToggle('confirmNewPasswordInput', 'toggleConfirmNewPassword');
setupToggle('changeNewPassword', 'toggleStep3NewPassword');
setupToggle('changeConfirmPassword', 'toggleStep3ConfirmPassword');


// Handle "Change Password" submit via Current Password
document.getElementById("btnSubmitCurrentPassChange").addEventListener("click", async function () {
  const currentPassword = document.getElementById('currentPasswordInput').value;
  const newPassword = document.getElementById('newPasswordInput').value;
  const confirmPassword = document.getElementById('confirmNewPasswordInput').value;
  const errorDiv = document.getElementById('currentPassError');

  // Basic Validation
  if (!currentPassword || !newPassword || !confirmPassword) {
    errorDiv.textContent = 'Please fill in all fields';
    errorDiv.style.display = 'block';
    return;
  }

  if (newPassword !== confirmPassword) {
    errorDiv.textContent = 'New passwords do not match';
    errorDiv.style.display = 'block';
    return;
  }

  if (newPassword.length < 6) {
    errorDiv.textContent = 'New password must be at least 6 characters';
    errorDiv.style.display = 'block';
    return;
  }

  if (!/\d/.test(newPassword)) {
    errorDiv.textContent = 'New password must contain at least one number';
    errorDiv.style.display = 'block';
    return;
  }

  errorDiv.style.display = 'none';
  const btn = this;
  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Verifying...';

  try {
    const formData = new FormData();
    formData.append('current_password', currentPassword);
    formData.append('new_password', newPassword);

    // We reuse the change_password.php process but with specific action
    const response = await fetch('processes/change_password.php?action=verify_old_and_update', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      // Close modal
      bootstrap.Modal.getInstance(document.getElementById("changePasswordViaCurrentModal")).hide();

      // Show Success Modal
      new bootstrap.Modal(document.getElementById("changePasswordSuccess")).show();

      // Clear fields
      document.getElementById('currentPasswordInput').value = '';
      document.getElementById('newPasswordInput').value = '';
      document.getElementById('confirmNewPasswordInput').value = '';
    } else {
      errorDiv.textContent = result.error || 'Password update failed';
      errorDiv.style.display = 'block';
    }
  } catch (error) {
    console.error('Update password error:', error);
    errorDiv.textContent = 'An error occurred. Please try again.';
    errorDiv.style.display = 'block';
  }

  btn.disabled = false;
  btn.textContent = originalText;
});


// Step 1: Send OTP to Email (Existing Flow)
document.getElementById("changeToStep2").onclick = async function () {
  const email = document.getElementById('changeEmail').value.trim();
  const errorDiv = document.getElementById('changeStep1Error');

  if (!email) {
    errorDiv.textContent = 'Please enter your email address';
    errorDiv.style.display = 'block';
    return;
  }

  // Validate email format
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email)) {
    errorDiv.textContent = 'Please enter a valid email address';
    errorDiv.style.display = 'block';
    return;
  }

  errorDiv.style.display = 'none';
  this.disabled = true;
  this.textContent = 'Sending OTP...';

  try {
    const formData = new FormData();
    formData.append('email', email);

    const response = await fetch('processes/change_password.php?action=send_otp', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      changeEmployeeIdGlobal = result.employee_id;

      // Show success message in Step 2
      document.getElementById('changeStep2Success').textContent = 'OTP sent to ' + email;
      document.getElementById('changeStep2Success').style.display = 'block';

      // Close step 1, open step 2
      bootstrap.Modal.getInstance(document.getElementById("changePasswordModal")).hide();
      new bootstrap.Modal(document.getElementById("changePasswordModalStep2")).show();
    } else {
      errorDiv.textContent = result.error || 'Failed to send OTP';
      errorDiv.style.display = 'block';
    }
  } catch (error) {
    console.error('Send OTP error:', error);
    errorDiv.textContent = 'An error occurred. Please try again.';
    errorDiv.style.display = 'block';
  }

  this.disabled = false;
  this.textContent = 'Send OTP';
};

// Step 2: Verify OTP
document.getElementById("changeToStep3").onclick = async function () {
  const otp = document.getElementById('changeOtpCode').value.trim();
  const errorDiv = document.getElementById('changeStep2Error');

  if (!otp || otp.length !== 6) {
    errorDiv.textContent = 'Please enter a valid 6-digit OTP';
    errorDiv.style.display = 'block';
    return;
  }

  errorDiv.style.display = 'none';
  this.disabled = true;
  this.textContent = 'Verifying...';

  try {
    const formData = new FormData();
    formData.append('employee_id', changeEmployeeIdGlobal);
    formData.append('otp', otp);

    const response = await fetch('processes/change_password.php?action=verify_otp', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      // Close step 2, open step 3
      bootstrap.Modal.getInstance(document.getElementById("changePasswordModalStep2")).hide();
      new bootstrap.Modal(document.getElementById("changePasswordModalStep3")).show();
    } else {
      errorDiv.textContent = result.error || 'Invalid OTP';
      errorDiv.style.display = 'block';
    }
  } catch (error) {
    console.error('OTP verification error:', error);
    errorDiv.textContent = 'An error occurred. Please try again.';
    errorDiv.style.display = 'block';
  }

  this.disabled = false;
  this.textContent = 'Verify OTP';
};

// Step 3: Change Password
document.getElementById("changeFinalStep").onclick = async function () {
  const newPassword = document.getElementById('changeNewPassword').value;
  const confirmPassword = document.getElementById('changeConfirmPassword').value;
  const errorDiv = document.getElementById('changeStep3Error');

  if (!newPassword || !confirmPassword) {
    errorDiv.textContent = 'Please fill in all fields';
    errorDiv.style.display = 'block';
    return;
  }

  if (newPassword !== confirmPassword) {
    errorDiv.textContent = 'Passwords do not match';
    errorDiv.style.display = 'block';
    return;
  }

  if (newPassword.length < 6) {
    errorDiv.textContent = 'Password must be at least 6 characters';
    errorDiv.style.display = 'block';
    return;
  }

  if (!/\d/.test(newPassword)) {
    errorDiv.textContent = 'Password must contain at least one number';
    errorDiv.style.display = 'block';
    return;
  }

  errorDiv.style.display = 'none';
  this.disabled = true;
  this.textContent = 'Changing...';

  try {
    const formData = new FormData();
    formData.append('employee_id', changeEmployeeIdGlobal);
    formData.append('new_password', newPassword);
    formData.append('confirm_password', confirmPassword);

    const response = await fetch('processes/change_password.php?action=change_password', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      // Close step 3, show success modal
      bootstrap.Modal.getInstance(document.getElementById("changePasswordModalStep3")).hide();
      new bootstrap.Modal(document.getElementById("changePasswordSuccess")).show();

      // Clear form fields
      document.getElementById('changeEmail').value = '';
      document.getElementById('changeOtpCode').value = '';
      document.getElementById('changeNewPassword').value = '';
      document.getElementById('changeConfirmPassword').value = '';
      document.getElementById('changeStep2Success').style.display = 'none';
      changeEmployeeIdGlobal = '';
    } else {
      errorDiv.textContent = result.error || 'Password change failed';
      errorDiv.style.display = 'block';
    }
  } catch (error) {
    console.error('Password change error:', error);
    errorDiv.textContent = 'An error occurred. Please try again.';
    errorDiv.style.display = 'block';
  }

  this.disabled = false;
  this.textContent = 'Change Password';
};

// Redirect to settings after success
document.getElementById("changeGoSettings").onclick = function () {
  window.location.href = "settings.php";
};

// ✅ Centered popup function
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

//privacy
// When the "Privacy Policy/Terms" card is clicked
document.getElementById("privacyPolicy").addEventListener("click", () => {
  const modal = new bootstrap.Modal(document.getElementById("privacyPolicyModal"));
  modal.show();
});

// Handle Accept button
document.getElementById("acceptPolicyBtn").addEventListener("click", () => {
  // Close modal
  const modalEl = document.getElementById("privacyPolicyModal");
  const modalInstance = bootstrap.Modal.getInstance(modalEl);
  modalInstance.hide();

  // Optional redirect
  setTimeout(() => {
    window.location.href = "settings.php";
  }, 2000);
});

// ✅ Reuse popup message function
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

// ✅ Clear All Records functionality
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

// Make function globally accessible
window.handleClearRecordsClick = handleClearRecordsClick;

// Handle proceeding to second confirmation
const proceedBtn = document.getElementById("proceedDeleteBtn");
if (proceedBtn) {
  proceedBtn.addEventListener("click", () => {
    bootstrap.Modal.getInstance(document.getElementById("clearAllRecordsModal")).hide();
    const secondModal = new bootstrap.Modal(document.getElementById("secondConfirmModal"));
    secondModal.show();
  });
}

// Handle Export and Proceed
const exportAndProceedBtn = document.getElementById("exportAndProceedBtn");
if (exportAndProceedBtn) {
  exportAndProceedBtn.addEventListener("click", () => {
    // Trigger download
    window.location.href = "processes/export_all_records.php";

    // Switch modals
    setTimeout(() => {
      bootstrap.Modal.getInstance(document.getElementById("clearAllRecordsModal")).hide();
      const secondModal = new bootstrap.Modal(document.getElementById("secondConfirmModal"));
      secondModal.show();
    }, 500);
  });
}

// Handle final confirmation with password
const confirmBtn = document.getElementById("confirmDeleteBtn");
if (confirmBtn) {
  confirmBtn.addEventListener("click", async function () {
    const password = document.getElementById('clearPasswordInput').value.trim();
    const errorDiv = document.getElementById('clearPasswordError');
    const btnText = document.getElementById('confirmDeleteBtnText');
    const btnSpinner = document.getElementById('confirmDeleteBtnSpinner');

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
      formData.append('password', password);
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
        errorDiv.textContent = result.error || 'Failed to clear records';
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
}
