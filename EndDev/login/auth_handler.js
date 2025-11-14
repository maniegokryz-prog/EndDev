/**
 * Login Handler - Frontend JavaScript
 * Connects login.php with backend authentication
 */

// Wait for DOM to load
document.addEventListener('DOMContentLoaded', function() {
    initializeLoginForm();
    initializeForgotPassword();
});

// Initialize login form
function initializeLoginForm() {
    const loginForm = document.querySelector('form');
    const loginButton = document.getElementById('loginButton');
    
    if (loginButton && loginForm) {
        // Prevent default button behavior
        loginButton.type = 'button';
        
        loginButton.addEventListener('click', async function(e) {
            e.preventDefault();
            
            const idNumber = document.getElementById('idNumber').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!idNumber || !password) {
                showMessage('Please enter both ID and password', 'danger');
                return;
            }
            
            // Disable button
            loginButton.disabled = true;
            loginButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
            
            try {
                const response = await fetch('authenticate.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({idNumber, password})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 500);
                } else {
                    showMessage(data.message, 'danger');
                    loginButton.disabled = false;
                    loginButton.innerHTML = 'Log in';
                }
            } catch (error) {
                showMessage('Connection error. Please try again.', 'danger');
                loginButton.disabled = false;
                loginButton.innerHTML = 'Log in';
            }
        });
    }
}

// Initialize forgot password flow
function initializeForgotPassword() {
    // Override Step 1 button
    const step1Btn = document.getElementById('toStep2');
    if (step1Btn) {
        step1Btn.onclick = async function() {
            const idNumber = document.querySelector('#modalStep1 input[placeholder="ID Number"]').value.trim();
            const contact = document.querySelector('#modalStep1 input[placeholder="Email or Contact Number"]').value.trim();
            
            if (!idNumber || !contact) {
                showModalMessage('modalStep1', 'Please fill all fields', 'danger');
                return;
            }
            
            step1Btn.disabled = true;
            step1Btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verifying...';
            
            try {
                const response = await fetch('verify_account.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({idNumber, contact})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Store OTP for debugging (REMOVE IN PRODUCTION)
                    if (data.otp) {
                        console.log('OTP:', data.otp);
                        alert('DEBUG: OTP is ' + data.otp);
                    }
                    
                    // Update Step 2 message
                    const otpMessage = document.querySelector('#modalStep2 p');
                    if (otpMessage) {
                        otpMessage.textContent = 'Enter code sent to ' + data.masked_contact;
                    }
                    
                    // Show Step 2
                    new bootstrap.Modal(document.getElementById("modalStep2")).show();
                    bootstrap.Modal.getInstance(document.getElementById("modalStep1")).hide();
                } else {
                    showModalMessage('modalStep1', data.message, 'danger');
                }
            } catch (error) {
                showModalMessage('modalStep1', 'Connection error', 'danger');
            } finally {
                step1Btn.disabled = false;
                step1Btn.innerHTML = 'Continue';
            }
        };
    }
    
    // Override Step 2 button
    const step2Btn = document.getElementById('toStep3');
    if (step2Btn) {
        step2Btn.onclick = async function() {
            const otp = document.querySelector('#modalStep2 input[placeholder="12345"]').value.trim();
            
            if (!otp) {
                showModalMessage('modalStep2', 'Please enter OTP', 'danger');
                return;
            }
            
            step2Btn.disabled = true;
            step2Btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verifying...';
            
            try {
                const response = await fetch('verify_otp.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({otp})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show Step 3
                    new bootstrap.Modal(document.getElementById("modalStep3")).show();
                    bootstrap.Modal.getInstance(document.getElementById("modalStep2")).hide();
                } else {
                    showModalMessage('modalStep2', data.message, 'danger');
                }
            } catch (error) {
                showModalMessage('modalStep2', 'Connection error', 'danger');
            } finally {
                step2Btn.disabled = false;
                step2Btn.innerHTML = 'Continue';
            }
        };
    }
    
    // Override Step 3 button
    const step3Btn = document.getElementById('finalStep');
    if (step3Btn) {
        step3Btn.onclick = async function() {
            const newPassword = document.querySelector('#modalStep3 input[placeholder="New Password"]').value.trim();
            const confirmPassword = document.querySelector('#modalStep3 input[placeholder="Confirm Password"]').value.trim();
            
            if (!newPassword || !confirmPassword) {
                showModalMessage('modalStep3', 'Please fill all fields', 'danger');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showModalMessage('modalStep3', 'Passwords do not match', 'danger');
                return;
            }
            
            step3Btn.disabled = true;
            step3Btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Resetting...';
            
            try {
                const response = await fetch('reset_password.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({newPassword, confirmPassword})
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show success modal
                    new bootstrap.Modal(document.getElementById("modalSuccess")).show();
                    bootstrap.Modal.getInstance(document.getElementById("modalStep3")).hide();
                    
                    // Clear inputs
                    document.querySelector('#modalStep1 input[placeholder="ID Number"]').value = '';
                    document.querySelector('#modalStep1 input[placeholder="Email or Contact Number"]').value = '';
                    document.querySelector('#modalStep2 input[placeholder="12345"]').value = '';
                    document.querySelector('#modalStep3 input[placeholder="New Password"]').value = '';
                    document.querySelector('#modalStep3 input[placeholder="Confirm Password"]').value = '';
                } else {
                    showModalMessage('modalStep3', data.message, 'danger');
                }
            } catch (error) {
                showModalMessage('modalStep3', 'Connection error', 'danger');
            } finally {
                step3Btn.disabled = false;
                step3Btn.innerHTML = 'Continue';
            }
        };
    }
}

// Show message
function showMessage(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 150);
    }, 5000);
}

// Show modal message
function showModalMessage(modalId, message, type) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    const existingAlert = modal.querySelector('.alert');
    if (existingAlert) existingAlert.remove();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show mb-3`;
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    
    const modalContent = modal.querySelector('.modal-content');
    modalContent.insertBefore(alert, modalContent.firstChild);
    
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 150);
    }, 5000);
}
