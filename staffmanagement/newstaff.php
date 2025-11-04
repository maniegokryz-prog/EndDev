    <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Custom CSS -->
<link rel="stylesheet" href="staff.css">
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
    <button id="backBtn" class="btn btn-outline-secondary mb-3 mt-3">&larr; Back</button>
    </div>
  </div>

  <!-- Discard Changes Modal -->
<div class="modal fade" id="discardModal" tabindex="-1" aria-labelledby="discardLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3 border-0 rounded-4 shadow-lg">

      <div class="text-center border-bottom pb-2 mb-3">
        <h5 class="fw-bold text-success" id="discardLabel">Discard Changes?</h5>
      </div>

      <div class="text-center">
        <i class="bi bi-exclamation-triangle-fill text-warning fs-1 mb-2"></i>
        <p class="text-muted px-3">
          Are you sure to leave this page? Data entered will be lost and cannot be recovered.
        </p>
      </div>

      <div class="d-flex justify-content-center gap-3 mt-3">
        <button type="button" class="btn btn-outline-success px-4 fw-semibold" data-bs-dismiss="modal">No</button>
        <button type="button" id="confirmLeave" class="btn btn-warning text-white px-4 fw-semibold">Yes</button>
      </div>

    </div>
  </div>
</div>

<script>
  document.getElementById("backBtn").addEventListener("click", function (e) {
    e.preventDefault();
    const discardModal = new bootstrap.Modal(document.getElementById('discardModal'));
    discardModal.show();
  });

  document.getElementById("confirmLeave").addEventListener("click", function () {
    window.location.href = "staff.php"; // redirect to staff page
  });
</script>


<body class="bg-light">
  <div class="container py-4">
    <a href="staffmanagement.php" class="text-dark fs-4"><i class="fas fa-arrow-left me-2"></i></a>
    <div class="card shadow-sm border-0">

      <div class="card-body">
        <h4 class="fw-bold text-success">Add New Staff / Step 1 - Input Data</h4>
        <form id="staffForm" class="mt-4">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">First Name</label>
              <input type="text" class="form-control" placeholder="Enter First Name" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Middle Name</label>
              <input type="text" class="form-control" placeholder="Enter Middle Name">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Last Name</label>
              <input type="text" class="form-control" placeholder="Enter Last Name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email</label>
              <input type="email" class="form-control" placeholder="Enter your Email" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Contact No.</label>
              <input type="text" class="form-control" placeholder="Enter your Contact No. (09*********)" required>
            </div>

             <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Role</label>
                <select id="roleSelect" class="form-select">
                    <option value="" selected disabled>Select Role</option>
                    <option value="Faculty">Faculty</option>
                    <option value="Admin">Admin</option>
                    <option value="Non Teaching Staff">Non-Teaching Staff</option>
                </select>
                <p id="fixedScheduleInfo" class="text-muted small mt-2"></p>
                </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Department</label>
              <select class="form-select" required>
                <option selected disabled>Department</option>
                <option>Information Systems</option>
                <option>Office Management</option>
                <option>Accounting</option>
                <option>Registrar’s Office</option>
                <option>Guidance and Counseling</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Position</label>
              <input type="text" class="form-control" placeholder="Enter Position">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Password</label>
              <input type="password" class="form-control" placeholder="Enter Password">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">ID Number</label>
              <input type="text" class="form-control" placeholder="Enter ID Number" required>
            </div>
          </div>
        </form>

        <div id="setScheduleSection" class="mt-5">
        <h3 class="fw-bold text-success">Set Schedule</h3>

        <div class="mb-3">
            <label class="form-label fw-semibold">Select Days</label>
            <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-success day-btn" data-day="Monday">M</button>
            <button type="button" class="btn btn-outline-success day-btn" data-day="Tuesday">T</button>
            <button type="button" class="btn btn-outline-success day-btn" data-day="Wednesday">W</button>
            <button type="button" class="btn btn-outline-success day-btn" data-day="Thursday">TH</button>
            <button type="button" class="btn btn-outline-success day-btn" data-day="Friday">F</button>
            <button type="button" class="btn btn-outline-success day-btn" data-day="Saturday">S</button>
            </div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-md-6">
            <label class="form-label fw-semibold">Designate Class</label>
            <input type="text" id="classInput" class="form-control" placeholder="Course, Year and Section">
            </div>
            <div class="col-md-6">
            <label class="form-label fw-semibold">Subject</label>
            <input type="text" id="subjectInput" class="form-control" placeholder="Enter Subject">
            </div>
            <div class="col-md-6">
            <label class="form-label fw-semibold">Start Time</label>
            <input type="time" id="startTime" class="form-control">
            </div>
            <div class="col-md-6">
            <label class="form-label fw-semibold">End Time</label>
            <input type="time" id="endTime" class="form-control">
            </div>
        </div>

        <div class="text-center mt-4">
            <button id="addScheduleBtn" class="btn btn-success w-50 py-2">
            <i class="fas fa-plus me-2"></i>Add Schedule
            </button>
        </div>

        <h5 class="mt-5 fw-bold">Schedule</h5>
        <div class="text-end mb-2">
            <button id="clearAllBtn" class="btn btn-warning btn-sm">Clear All</button>
        </div>

        <div id="scheduleTable" class="table-responsive border-top pt-3">
            <table class="table table-bordered text-center align-middle">
            <thead class="table-success">
                <tr>
                <th>Class</th>
                <th>Subject</th>
                <th>Day</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Action</th>
                </tr>
            </thead>
            <tbody id="scheduleBody"></tbody>
            </table>
        </div>
        </div>

          <!-- Trigger Button -->
        <div class="text-center mt-4 proceed-btn-wrapper">
        <button class="btn btn-success w-100 py-2" data-bs-toggle="modal" data-bs-target="#step2Modal">
            Proceed to Step 2
        </button>
        </div>

        <!-- Step 2 Modal -->
        <div class="modal fade" id="step2Modal" tabindex="-1" aria-labelledby="step2Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content text-center">
            <div class="modal-header border-0">
                <h4 class="w-100 fw-bold text-success mt-2">STEP 2</h4>
            </div>

            <div class="modal-body">
                <!-- Camera Section -->
                <div id="cameraSection">
                <div class="d-flex justify-content-center mb-3 position-relative">
                    <video id="video" width="380" height="280" class="border border-2 rounded" autoplay></video>
                    <div class="position-absolute top-50 start-50 translate-middle border border-white rounded-circle" 
                        style="width: 80px; height: 80px; opacity: 0.4;"></div>
                </div>

                <p class="text-muted small">
                    Ensure the subject has bright, even lighting, directly facing the camera and in a fixed position.
                </p>

                <div class="d-flex justify-content-center gap-3 mt-3">
                    <button type="button" class="btn btn-warning px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="captureBtn" class="btn btn-primary px-4">Capture</button>
                </div>
                </div>

                <!-- Preview Section -->
                <div id="previewSection" style="display: none;">
                <h5 class="fw-bold text-success mb-3">Image Preview</h5>
                <canvas id="canvas" width="380" height="280" class="border border-2 rounded mb-3"></canvas>

                <p class="text-muted small">
                    Ensure the subject has bright, even lighting, directly facing the camera and in a fixed position.
                </p>

                <div class="d-flex justify-content-center gap-3 mt-3">
                    <button type="button" id="retakeBtn" class="btn btn-warning px-4">Retake</button>
                    <button type="button" id="confirmBtn" class="btn btn-primary px-4">Confirm and Proceed</button>
                </div>
                </div>
            </div>
            </div>
        </div>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", () => {
        const video = document.getElementById("video");
        const canvas = document.getElementById("canvas");
        const captureBtn = document.getElementById("captureBtn");
        const retakeBtn = document.getElementById("retakeBtn");
        const confirmBtn = document.getElementById("confirmBtn");

        const cameraSection = document.getElementById("cameraSection");
        const previewSection = document.getElementById("previewSection");

        let stream;

        // ✅ Automatically start camera when modal opens
        const step2Modal = document.getElementById("step2Modal");
        step2Modal.addEventListener("shown.bs.modal", async () => {
            try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;
            } catch (err) {
            alert("Camera access denied or not available.");
            }
        });

        // ✅ Stop camera when modal closes
        step2Modal.addEventListener("hidden.bs.modal", () => {
            if (stream) {
            stream.getTracks().forEach(track => track.stop());
            }
            cameraSection.style.display = "block";
            previewSection.style.display = "none";
        });

        // ✅ Capture Button — switch to Preview
        captureBtn.addEventListener("click", () => {
            const ctx = canvas.getContext("2d");
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            cameraSection.style.display = "none";
            previewSection.style.display = "block";

            // Optional: pause video while previewing
            video.pause();
        });

        // ✅ Retake Button — go back to camera
        retakeBtn.addEventListener("click", () => {
            cameraSection.style.display = "block";
            previewSection.style.display = "none";
            video.play();
        });

        // ✅ Confirm Button — proceed to next step/modal
        confirmBtn.addEventListener("click", () => {
            // stop camera
            if (stream) {
            stream.getTracks().forEach(track => track.stop());
            }

            // close Step 2 modal
            const modalInstance = bootstrap.Modal.getInstance(step2Modal);
            modalInstance.hide();

            // example: open next modal (Step 3) if you have one
            const step3Modal = document.getElementById("step3Modal");
            if (step3Modal) {
            const nextModal = new bootstrap.Modal(step3Modal);
            nextModal.show();
            }

            console.log("✅ Step 2 image captured and confirmed.");
        });
        });
        </script>

        <!-- ✅ Step 3 Modal -->
            <div class="modal fade" id="step3Modal" tabindex="-1" aria-labelledby="step3Label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content p-4">
                <div class="text-center border-bottom pb-2 mb-3">
                    <h4 class="fw-bold text-success">Review Information</h4>
                </div>

                <div class="row">
                    <div class="col-md-6">
                    <p><strong>Name:</strong> Gabriel Castro</p>
                    <p><strong>Email:</strong> gabriel@gmail.com</p>
                    <p><strong>Contact:</strong> 09560944656</p>
                    <p><strong>Role:</strong> Faculty Member</p>
                    <p><strong>Position:</strong> Instructor</p>
                    <p><strong>Department:</strong> Management Information System</p>
                    </div>
                    <div class="col-md-6">
                    <p><strong>ID:</strong> MA22012345</p>
                    <p><strong>Password:</strong> 
                        <span id="showPass">@Password01</span> 
                        <i class="fa fa-eye ms-1 text-muted" style="cursor:pointer;" onclick="togglePassword()"></i>
                    </p>
                    </div>
                </div>

                <h5 class="fw-bold mt-4 mb-2">Schedule</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center">
                    <thead class="table-light">
                        <tr>
                        <th>Time</th>
                        <th>Monday</th>
                        <th>Tuesday</th>
                        <th>Wednesday</th>
                        <th>Thursday</th>
                        <th>Friday</th>
                        <th>Saturday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                        <td>7:00 - 9:00</td>
                        <td class="bg-success text-white">RM-203<br>IS-CP 123<br>BIS-2B</td>
                        <td></td>
                        <td class="bg-danger text-white">RM-203<br>IS-CP 123<br>BIS-2B</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        </tr>
                        <tr>
                        <td>9:00 - 11:00</td>
                        <td></td>
                        <td class="bg-warning text-dark">RM-203<br>IS-CP 123<br>BIS-2B</td>
                        <td class="bg-danger text-white">RM-203<br>IS-CP 123<br>BIS-2B</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        </tr>
                    </tbody>
                    </table>
                </div>

                <!-- ✅ Buttons -->
                <div class="text-center mt-4">
                    <button class="btn btn-secondary px-4" data-bs-dismiss="modal">Back</button>
                    <button id="saveFinishBtn" class="btn btn-success px-4">Add Staff</button>
                </div>

                </div>
            </div>
            </div>

            <!-- ✅ Success Notification Modal (MUST be outside Step 3 modal) -->
            <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content text-center border-0 shadow">
                <div class="modal-body p-5">
                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                    <h4 class="fw-bold text-success">New Staff Added!</h4>
                    <p class="text-muted mb-4">The staff record has been successfully saved.</p>
                </div>
                </div>
            </div>
            </div>

            <!-- ✅ Script -->
            <script>
            document.addEventListener("DOMContentLoaded", () => {
            const saveBtn = document.getElementById("saveFinishBtn");

            if (saveBtn) {
                saveBtn.addEventListener("click", () => {
                // close Step 3 modal first
                const step3Modal = bootstrap.Modal.getInstance(document.getElementById("step3Modal"));
                if (step3Modal) step3Modal.hide();

                // show success modal
                const successModal = new bootstrap.Modal(document.getElementById("successModal"));
                successModal.show();

                // wait 3 seconds, then hide and redirect
                setTimeout(() => {
                    successModal.hide();
                    window.location.href = "staff.php"; // redirect after success
                }, 3000);
                });
            }
            });
            </script>

  <!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="staff.js"></script>
</body>
</html>
