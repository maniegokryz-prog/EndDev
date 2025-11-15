function togglePassword() {
  const password = document.getElementById("password");
  const icon = document.querySelector(".toggle-password i");

  if (password.type === "password") {
    password.type = "text";
    icon.classList.remove("bi-eye");
    icon.classList.add("bi-eye-slash");
  } else {
    password.type = "password";
    icon.classList.remove("bi-eye-slash");
    icon.classList.add("bi-eye");
  }
}

// Global variable to store employee ID during password reset
let resetEmployeeIdGlobal = '';

// Handle login form submission
document.addEventListener('DOMContentLoaded', function() {
  const loginForm = document.getElementById('loginForm');
  const loginBtn = document.getElementById('loginBtn');
  const errorMessage = document.getElementById('errorMessage');

  if (loginForm) {
    loginForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const employeeId = document.getElementById('idNumber').value.trim();
      const password = document.getElementById('password').value;
      
      if (!employeeId || !password) {
        showError('Please enter both ID number and password');
        return;
      }
      
      // Disable button and show loading
      loginBtn.disabled = true;
      loginBtn.textContent = 'Logging in...';
      errorMessage.style.display = 'none';
      
      try {
        const formData = new FormData();
        formData.append('employee_id', employeeId);
        formData.append('password', password);
        
        const response = await fetch('auth.php?action=login', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          // Redirect to dashboard
          window.location.href = result.redirect_url || '../dashboard/dashboard.php';
        } else {
          showError(result.error || 'Invalid credentials');
          loginBtn.disabled = false;
          loginBtn.textContent = 'Log in';
        }
      } catch (error) {
        console.error('Login error:', error);
        showError('An error occurred. Please try again.');
        loginBtn.disabled = false;
        loginBtn.textContent = 'Log in';
      }
    });
  }
  
  function showError(message) {
    errorMessage.textContent = message;
    errorMessage.style.display = 'block';
  }
});

// Password Recovery Flow

// Step 1: Verify Account and Send OTP
document.getElementById("toStep2").onclick = async function() {
  const employeeId = document.getElementById('resetEmployeeId').value.trim();
  const contact = document.getElementById('resetContact').value.trim();
  const errorDiv = document.getElementById('step1Error');
  
  if (!employeeId || !contact) {
    errorDiv.textContent = 'Please fill in all fields';
    errorDiv.style.display = 'block';
    return;
  }
  
  errorDiv.style.display = 'none';
  this.disabled = true;
  this.textContent = 'Verifying...';
  
  try {
    const formData = new FormData();
    formData.append('employee_id', employeeId);
    formData.append('contact', contact);
    
    const response = await fetch('password_recovery.php?action=verify_account', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      resetEmployeeIdGlobal = employeeId;
      
      // Show OTP in modal (ONLY FOR DEVELOPMENT - REMOVE IN PRODUCTION)
      if (result.otp) {
        document.getElementById('otpDisplay').textContent = 'Your OTP: ' + result.otp;
        document.getElementById('otpDisplay').style.display = 'block';
      }
      
      // Close step 1, open step 2
      bootstrap.Modal.getInstance(document.getElementById("modalStep1")).hide();
      new bootstrap.Modal(document.getElementById("modalStep2")).show();
    } else {
      errorDiv.textContent = result.error || 'Verification failed';
      errorDiv.style.display = 'block';
    }
  } catch (error) {
    console.error('Verification error:', error);
    errorDiv.textContent = 'An error occurred. Please try again.';
    errorDiv.style.display = 'block';
  }
  
  this.disabled = false;
  this.textContent = 'Continue';
};

// Step 2: Verify OTP
document.getElementById("toStep3").onclick = async function() {
  const otp = document.getElementById('otpCode').value.trim();
  const errorDiv = document.getElementById('step2Error');
  
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
    formData.append('employee_id', resetEmployeeIdGlobal);
    formData.append('otp', otp);
    
    const response = await fetch('password_recovery.php?action=verify_otp', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Close step 2, open step 3
      bootstrap.Modal.getInstance(document.getElementById("modalStep2")).hide();
      new bootstrap.Modal(document.getElementById("modalStep3")).show();
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

// Step 3: Reset Password
document.getElementById("finalStep").onclick = async function() {
  const newPassword = document.getElementById('newPassword').value;
  const confirmPassword = document.getElementById('confirmPassword').value;
  const errorDiv = document.getElementById('step3Error');
  
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
  
  errorDiv.style.display = 'none';
  this.disabled = true;
  this.textContent = 'Resetting...';
  
  try {
    const formData = new FormData();
    formData.append('employee_id', resetEmployeeIdGlobal);
    formData.append('new_password', newPassword);
    formData.append('confirm_password', confirmPassword);
    
    const response = await fetch('password_recovery.php?action=reset_password', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Close step 3, show success modal
      bootstrap.Modal.getInstance(document.getElementById("modalStep3")).hide();
      new bootstrap.Modal(document.getElementById("modalSuccess")).show();
      
      // Clear form fields
      document.getElementById('resetEmployeeId').value = '';
      document.getElementById('resetContact').value = '';
      document.getElementById('otpCode').value = '';
      document.getElementById('newPassword').value = '';
      document.getElementById('confirmPassword').value = '';
      resetEmployeeIdGlobal = '';
    } else {
      errorDiv.textContent = result.error || 'Password reset failed';
      errorDiv.style.display = 'block';
    }
  } catch (error) {
    console.error('Password reset error:', error);
    errorDiv.textContent = 'An error occurred. Please try again.';
    errorDiv.style.display = 'block';
  }
  
  this.disabled = false;
  this.textContent = 'Reset Password';
};

// Redirect to login after success
document.getElementById("goLogin").onclick = function() {
  window.location.href = "login.php";
};
