 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automated Attendance - Login</title>
    <link rel="stylesheet" href="login.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <!-- Left Section -->
        <div class="login-left">
            <div class="logo-section text-center">
                <img src="icon.png" alt="Logo" class="login-image mb-4">
                <h6 class="system-title text-uppercase mb-0">Automated</h6>
                <h6 class="system-title text-uppercase mb-0">Attendance System with</h6>
                <h4 class="facial-title fw-bold text-warning mt-2">Facial Recognition</h4>
            </div>
        </div>

        <!-- Right Section -->
        <div class="login-right d-flex align-items-center justify-content-center">
            <div class="login-box text-center">
                <h4 class="fw-bold text-success mb-4">Log in to your account</h4>

                <form>
                    <div class="mb-3 text-start">
                        <label for="idNumber" class="form-label fw-semibold">ID Number</label>
                        <input type="text" id="idNumber" class="form-control" placeholder="Enter ID Number">
                    </div>

                    <div class="mb-3 text-start position-relative">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <input type="password" id="password" class="form-control" placeholder="Enter your Password">
                        <span class="toggle-password" onclick="togglePassword()">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>

                    <button type="button" class="btn btn-link" data-bs-toggle="modal" data-bs-target="#modalStep1">
                    Forgot Password?
                    </button>


                    <button type="button" class="btn btn-success w-100 fw-semibold" onclick="window.location.href='../dashboard/dashboard.php'">
                    Log in
                    </button>
                </form>

                <p class="mt-4 text-muted small">© 2025 Automated Attendance System</p>
            </div>
        </div>
    </div>

    <!-- STEP 1: VERIFY ACCOUNT -->
<div class="modal fade" id="modalStep1" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content p-3">
      <h4 class="text-center mt-2">Forgot Password?</h4>
      <p class="text-center">Enter your details so we can verify your account</p>
      
      <input type="text" class="form-control mb-2" placeholder="ID Number">
      <input type="text" class="form-control mb-3" placeholder="Email or Contact Number">

      <div class="d-flex justify-content-between">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="toStep2">Continue</button>
      </div>
    </div>
  </div>
</div>

<!-- STEP 2: OTP -->
<div class="modal fade" id="modalStep2" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content p-3">
      <h4 class="text-center mt-2">Confirmation</h4>
      <p class="text-center">You may receive OTP sent to your mobile phone number or email</p>
      
      <input type="text" class="form-control mb-3" placeholder="12345">

      <div class="d-flex justify-content-between">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="toStep3">Continue</button>
      </div>
    </div>
  </div>
</div>

<!-- STEP 3: RESET PASSWORD -->
<div class="modal fade" id="modalStep3" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content p-3">
      <h4 class="text-center mt-2">Reset Your Password</h4>
      <p class="text-center">Enter your new password</p>
      
      <input type="password" class="form-control mb-2" placeholder="New Password">
      <input type="password" class="form-control mb-3" placeholder="Confirm Password">

      <div class="d-flex justify-content-between">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="finalStep">Continue</button>
      </div>
    </div>
  </div>
</div>

<!-- FINAL POPUP -->
<div class="modal fade" id="modalSuccess" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content p-4 text-center">
      <div class="mb-3">
        ✅
      </div>
      <h5>Password Reset Successful</h5>
      <p>Your password has been updated.</p>
      <button class="btn btn-success w-100" id="goLogin">OK</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Show Step 2
document.getElementById("toStep2").onclick = function() {
  new bootstrap.Modal(document.getElementById("modalStep2")).show();
  bootstrap.Modal.getInstance(document.getElementById("modalStep1")).hide();
};

// Show Step 3
document.getElementById("toStep3").onclick = function() {
  new bootstrap.Modal(document.getElementById("modalStep3")).show();
  bootstrap.Modal.getInstance(document.getElementById("modalStep2")).hide();
};

// Show Success Popup
document.getElementById("finalStep").onclick = function() {
  new bootstrap.Modal(document.getElementById("modalSuccess")).show();
  bootstrap.Modal.getInstance(document.getElementById("modalStep3")).hide();
};

// Redirect to login.php after popup
document.getElementById("goLogin").onclick = function() {
  window.location.href = "login.php";
};
</script>

    <script src="login.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.js"></script>
</body>
</html>
