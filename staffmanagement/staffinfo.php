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
  <!-- FullCalendar CSS -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">


  <!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


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

  <div class="content pt-3" id="content">
  <div class="container-fluid"></div>
<!----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<!-- PAGE CONTENT -->
<div class="container-fluid p-4">
  <div class="row g-3">

    <!-- LEFT: main card + metrics -->
    <div class="col-xl-8">

      <!-- Staff card -->
     <div class="card shadow-sm mb-4 staff-card">
      
    <div class="header-bar d-flex align-items-center px-3 py-2">
      <i class="bi bi-arrow-left-circle fs-4 me-2" id="backBtn"></i>
      <h5 class="mb-0 fw-bold">Staff Information</h5>
    </div>

    <script>
      document.getElementById('backBtn').addEventListener('click', function () {
        window.history.back(); // or use window.location.href = 'your-target-page.php';
      });
    </script>

 <div class="card-body d-flex flex-column flex-lg-row align-items-center justify-content-center gap-4">
        <div class="profile-wrapper">
          <img src="pic.png" class="profile-img" alt="Profile Picture">
        </div>

          <div class="text-center text-lg-start ms-lg-5">
          <h3 class="fw-bold mb-1">Justine Alianza</h3>
          <p class="text-muted mb-1">MA20230001</p>
          <p class="mb-1">Faculty | Non-Teaching Staff</p>
          <p class="mb-1">Email: Sample@gmail.com</p>
          <p class="mb-3">Contact: 0912-345-6789</p>

          <div class="d-flex justify-content-center justify-content-lg-start gap-2">
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editInfoModal">Edit Info</button>
            <button class="btn btn-danger btn-sm" id="btnRemove" data-bs-toggle="modal" data-bs-target="#passwordModal">Remove Employee</button>
          </div>
        </div>
      </div>
    </div>
  </div>

    <!-- EDIT INFO MODAL -->
<div class="modal fade" id="editInfoModal" tabindex="-1" aria-labelledby="editInfoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content p-4">
      <div class="modal-header border-0">
        <h4 class="fw-bold text-success" id="editInfoModalLabel">Edit Profile</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="text-center mb-4">
          <img src="pic.png" class="profile-img-lg rounded-circle mb-3" alt="Profile Picture">
          <div class="d-flex justify-content-center gap-2">
            <button class="btn btn-outline-success btn-sm">Upload New Photo</button>
            <button class="btn btn-outline-danger btn-sm">Remove Photo</button>
          </div>
        </div>

        <form id="editInfoForm" class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">First Name</label>
            <input type="text" class="form-control" placeholder="Enter your First Name">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Middle Name</label>
            <input type="text" class="form-control" placeholder="Enter your Middle Name">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Last Name</label>
            <input type="text" class="form-control" placeholder="Enter your Last Name">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Email</label>
            <input type="email" class="form-control" placeholder="Enter your Email">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Contact No.</label>
            <input type="text" class="form-control" placeholder="Enter your Contact No. (09*********)">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Role</label>
            <select class="form-select">
              <option selected>Role</option>
              <option>Faculty</option>
              <option>Staff</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Department</label>
            <select class="form-select">
              <option selected>Department</option>
              <option>IS Department</option>
              <option>IT Department</option>
            </select>
          </div>
        </form>
      </div>

      <div class="modal-footer border-0">
        <button type="button" class="btn btn-success w-100">Save Changes</button>
      </div>
    </div>
  </div>
</div>

 <!-- 🔹 Confirm Removal Modal -->
<div class="modal fade" id="removeEmployeeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-danger">Confirm Employee Removal</h5>
      <p>Are you sure you want to remove this employee?</p>
      <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
        <button class="btn btn-secondary" data-bs-dismiss="modal">No</button>
        <button class="btn btn-danger" id="confirmRemoveBtn">Yes</button>
      </div>
    </div>
  </div>
</div>

<!-- 🔹 Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3 text-success">Removed Successfully</h5>
      <p>The employee has been moved to the archive.</p>
      <div class="mt-3">
        <button class="btn btn-success" id="successOkBtn">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const removeBtn = document.getElementById("btnRemove");
    const confirmModal = new bootstrap.Modal(document.getElementById("confirmModal"));
    const successModal = new bootstrap.Modal(document.getElementById("successModal"));

    // Step 1: Show confirm modal directly when Remove Employee is clicked
    removeBtn.addEventListener("click", () => {
      confirmModal.show();
    });

    // Step 2: When "Yes" clicked → show success modal
    document.getElementById("confirmRemoveBtn").addEventListener("click", () => {
      confirmModal.hide();
      setTimeout(() => successModal.show(), 400);
    });

    // Step 3: When "OK" clicked → redirect to staff.php
    document.getElementById("successOkBtn").addEventListener("click", () => {
      successModal.hide();
      document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
      document.body.classList.remove('modal-open');
      document.body.style.overflow = '';
      setTimeout(() => window.location.href = "staff.php", 400);
    });
  });
</script>

   <!-- RIGHT COLUMN (Calendar, Add Manual, Export DTR) -->
    <div class="col-xl-4">
      <!--add leave-->

         <div class="container py-4">
          <div class="row justify-content-end">
            <div class="col-xl-4 col-lg-5 col-md-6 px-2 scheduled-leave-card">
              <div class="card shadow-sm border-0">

                <div class="card-body py-3 px-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="card-title mb-0">Scheduled Leave</h6>
                    <button class="btn btn-success btn-sml" data-bs-toggle="modal" data-bs-target="#addLeaveModal">ADD</button>
                  </div>

                  <!-- Leave Entries -->
                  <div id="leaveList"  class="leave-list-container mt-3">
                    <!-- Entries will be added here -->
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- MODAL 1: Add Scheduled Leave -->
        <div class="modal fade" id="addLeaveModal" tabindex="-1" aria-labelledby="addLeaveLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Add Scheduled Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <label class="form-label">Type:</label>
                <input type="text" class="form-control mb-3" placeholder="Maternity, Sick, Vacation etc." id="leaveType">

                <label class="form-label">FROM:</label>
                <input type="date" class="form-control mb-3" id="leaveFrom">

                <label class="form-label">TO:</label>
                <input type="date" class="form-control mb-3" id="leaveTo">
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" onclick="redirectToStaffInfo()">Cancel</button>
                <button class="btn btn-success" onclick="confirmLeave()">Confirm</button>
              </div>
            </div>
          </div>
        </div>

          <!-- MODAL 1.5: Confirm Leave Details -->
          <div class="modal fade" id="leaveDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content text-center p-4">
                <div class="modal-body">
                  <h5 class="mb-3">Schedule a Leave for this Person?</h5>
                  <p id="leaveDetailsText" class="mb-4"></p>
                  <div class="d-flex justify-content-center gap-3">
                    <button class="btn btn-outline-dark" onclick="goBackToForm()">Change</button>
                    <button class="btn btn-success" onclick="finalizeLeave()">Confirm</button>
                  </div>
                </div>
              </div>
            </div>
          </div>


          <!-- MODAL 2: Confirmation -->
        <div class="modal fade" id="confirmModal" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
              <div class="modal-body">
                <div class="mb-3">
                  <div class="bg-success rounded-circle d-inline-block p-3">
                    <i class="bi bi-check-lg text-white fs-3"></i>
                  </div>
                </div>
                <h5 class="mb-2">Schedule Set!</h5>
              </div>
            </div>
          </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script>
          //add button
          let leaveType = "";
          let leaveFrom = "";
          let leaveTo = "";

          function confirmLeave() {
            leaveType = document.getElementById("leaveType").value;
            leaveFrom = document.getElementById("leaveFrom").value;
            leaveTo = document.getElementById("leaveTo").value;

            if (!leaveType || !leaveFrom || !leaveTo) {
              alert("Please fill out all fields.");
              return;
            }

            // Close Add Leave modal
            const addModalEl = document.getElementById('addLeaveModal');
            const addModal = bootstrap.Modal.getInstance(addModalEl);
            if (addModal) addModal.hide();

            // Show confirmation modal with leave details
            const detailsText = `${leaveType} Leave, from ${formatDate(leaveFrom)} - ${formatDate(leaveTo)}`;
            document.getElementById("leaveDetailsText").innerText = detailsText;

            const detailsModal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
            detailsModal.show();
          }

          function finalizeLeave() {
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsModal'));
            if (detailsModal) detailsModal.hide();

            const leaveList = document.getElementById("leaveList");
            const entry = document.createElement("div");
            entry.className = "leave-entry";

            entry.innerHTML = `
              <div>
                <strong>${leaveType}</strong><br>
                <small>${formatDate(leaveFrom)} to ${formatDate(leaveTo)}</small>
              </div>
              <button class="btn btn-outline-danger btn-sm btn-delete" onclick="deleteLeave(this)">
                <i class="bi bi-trash"></i>
              </button>
            `;

            leaveList.appendChild(entry);

            // ✅ Save to localStorage
            const leaveData = {
              type: leaveType,
              from: leaveFrom,
              to: leaveTo
            };

            let savedLeaves = JSON.parse(localStorage.getItem("scheduledLeaves")) || [];
            savedLeaves.push(leaveData);
            localStorage.setItem("scheduledLeaves", JSON.stringify(savedLeaves));

            // Show confirmation modal
            const confirmModalEl = document.getElementById('confirmModal');
            const confirmModal = new bootstrap.Modal(confirmModalEl, { backdrop: false });
            confirmModal.show();

            document.body.classList.remove("modal-open");
            document.querySelector(".modal-backdrop")?.remove();

            setTimeout(() => {
              window.location.href = "staffinfo.php";
            }, 1500);
          }


          function goBackToForm() {
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsModal'));
            if (detailsModal) detailsModal.hide();

            const addModal = new bootstrap.Modal(document.getElementById('addLeaveModal'));
            addModal.show();
          }

          function redirectToStaffInfo() {
            window.location.href = "staffinfo.php";
          }

          function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-PH', {
              month: 'long',
              day: 'numeric',
              year: 'numeric'
            });
          }

          window.addEventListener("DOMContentLoaded", () => {
            const savedLeaves = JSON.parse(localStorage.getItem("scheduledLeaves")) || [];
            const leaveList = document.getElementById("leaveList");

            savedLeaves.forEach(data => {
              const entry = document.createElement("div");
              entry.className = "leave-entry";
              entry.innerHTML = `
                <div>
                  <strong>${data.type}</strong><br>
                  <small>${formatDate(data.from)} to ${formatDate(data.to)}</small>
                </div>
                <button class="btn btn-outline-danger btn-sm btn-delete" onclick="deleteLeave(this)">
                  <i class="bi bi-trash"></i>
                </button>
              `;
              leaveList.appendChild(entry);
            });

            // Optional: clear saved data after loading
            // localStorage.removeItem("scheduledLeaves");
          });

          function deleteLeave(button) {
            const entry = button.closest(".leave-entry");
            if (!entry) return;

            entry.remove();

            const leaveList = document.getElementById("leaveList");
            const entries = leaveList.querySelectorAll(".leave-entry");

            if (entries.length === 0) {
              localStorage.removeItem("scheduledLeaves");
              return;
            }

            const updatedLeaves = Array.from(entries).map(entry => {
              const type = entry.querySelector("strong").innerText;
              const dateRange = entry.querySelector("small").innerText;
              const [from, to] = dateRange.replace(" to ", "|").split("|").map(d => {
                const date = new Date(d.trim());
                return date.toISOString().split("T")[0];
              });

              return { type, from, to };
            });

            localStorage.setItem("scheduledLeaves", JSON.stringify(updatedLeaves));
          }
          </script>

      <!-- Calendar -->
      <div class="card shadow-sm mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <button class="btn btn-sm btn-outline-secondary" id="prevMonth"><i class="bi bi-chevron-left"></i></button>
        <h5 class="mb-0" id="calendarTitle">Month Year</h5>
        <button class="btn btn-sm btn-outline-secondary" id="nextMonth"><i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="card-body">
        <div id="calendar" class="calendar-grid"></div>
      </div>
    </div>
    <script>
          document.addEventListener("DOMContentLoaded", function () {
          const calendar = document.getElementById("calendar");
          const calendarTitle = document.getElementById("calendarTitle");
          const prevBtn = document.getElementById("prevMonth");
          const nextBtn = document.getElementById("nextMonth");

          let currentDate = new Date();

          function generateCalendar(year, month) {
            calendar.innerHTML = "";

            const monthStart = new Date(year, month, 1);
            const monthEnd = new Date(year, month + 1, 0);
            const today = new Date();

            const months = [
              "January", "February", "March", "April", "May", "June",
              "July", "August", "September", "October", "November", "December"
            ];

            calendarTitle.textContent = `${months[month]} ${year}`;

            const weekdays = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
            weekdays.forEach(day => {
              const weekdayDiv = document.createElement("div");
              weekdayDiv.textContent = day;
              weekdayDiv.classList.add("calendar-weekday");
              calendar.appendChild(weekdayDiv);
            });

            let startDay = (monthStart.getDay() + 6) % 7; // shift to make Monday first
            for (let i = 0; i < startDay; i++) {
              const emptyCell = document.createElement("div");
              calendar.appendChild(emptyCell);
            }

            for (let day = 1; day <= monthEnd.getDate(); day++) {
              const dateDiv = document.createElement("div");
              dateDiv.textContent = day;
              dateDiv.classList.add("calendar-day");

              if (day <= 15) dateDiv.classList.add("first-half");
              else dateDiv.classList.add("second-half");

              if (
                day === today.getDate() &&
                month === today.getMonth() &&
                year === today.getFullYear()
              ) {
                dateDiv.classList.add("today");
              }

              dateDiv.addEventListener("click", () => {
                alert(`Selected: ${months[month]} ${day}, ${year}`);
              });

              calendar.appendChild(dateDiv);
            }
          }

          prevBtn.addEventListener("click", () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
          });

          nextBtn.addEventListener("click", () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
          });

          // Initial load
          generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
        });

    </script> 
      <!-- Add Manual Attendance -->
      <div class="d-grid mb-4" style="margin-top: 10px !important;">
        <button class="btn btn-success btn-sm" id="openAttendance">Add Manual Attendance</button>
      </div>

         <!-- 🔹 Manual Attendance Modal -->
        <div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content p-3">
              <div class="modal-header">
                <h5 class="modal-title">Manual Attendance Record</h5>
              </div>
              <div class="modal-body">
                <div id="attendanceContainer">
                  <div class="attendance-row row mb-2">
                    <div class="col-md-3">
                      <label>Date:</label>
                      <input type="date" class="form-control">
                    </div>
                    <div class="col-md-3">
                      <label>Time In:</label>
                      <input type="time" class="form-control">
                    </div>
                    <div class="col-md-3">
                      <label>Time Out:</label>
                      <input type="time" class="form-control">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                      <button class="btn btn-danger removeRow" style="display:none;">−</button>
                    </div>
                  </div>
                </div>
                <button id="addDayBtn" class="btn btn-warning mt-2">+ Add Another Day</button>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="saveBtn">Save Records</button>
              </div>
            </div>
          </div>
        </div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Bootstrap modal setup
  const attendanceModal = new bootstrap.Modal(document.getElementById('attendanceModal'));

  const openAttendanceBtn = document.getElementById('openAttendance');
  const addDayBtn = document.getElementById('addDayBtn');
  const attendanceContainer = document.getElementById('attendanceContainer');
  const saveBtn = document.getElementById('saveBtn');

  // 🔹 Step 1: Open Manual Attendance directly (no password)
  openAttendanceBtn.addEventListener('click', () => {
    attendanceModal.show();
  });

  // 🔹 Step 2: Add another day
  addDayBtn.addEventListener('click', () => {
    const newRow = document.createElement('div');
    newRow.classList.add('attendance-row', 'row', 'mb-2');
    newRow.innerHTML = `
      <div class="col-md-3">
        <label>Date:</label>
        <input type="date" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Time In:</label>
        <input type="time" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Time Out:</label>
        <input type="time" class="form-control">
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-danger removeRow">−</button>
      </div>`;
    attendanceContainer.appendChild(newRow);
  });

  // 🔹 Step 3: Remove a day row
  attendanceContainer.addEventListener('click', (e) => {
    if (e.target.classList.contains('removeRow')) {
      e.target.closest('.attendance-row').remove();
    }
  });

  // 🔹 Step 4: Save & redirect
  saveBtn.addEventListener('click', () => {
    alert("Manual attendance record successfully added!");
    window.location.href = "staffinfo.php";
  });
</script>

      <!-- Export DTR -->
      <div class="card" style="margin-top: 0 !important;">
        <div class="card-body">
          <button class="btn btn-success w-100 mb-3">Export DTR</button>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <div><small class="text-muted">Daily Time Record</small></div>
            <a href="../attendancerep/indirep.php?id=MA20230001" class="small">See more...</a>
          </div>

          <div class="dtr-list">
            <div class="dtr-item d-flex align-items-start mb-3">
              <div class="dtr-icon bg-success text-white rounded-circle me-3">
                <i class="bi bi-check-lg"></i>
              </div>
              <div class="flex-grow-1">
                <div class="fw-semibold">
                  Monday, September 16, 2025
                  <span class="badge bg-success ms-2">On Time</span>
                </div>
                <div class="small text-muted">
                  Time In: 8:00 AM — Time Out: 5:02 PM
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>
</div> 

<!-- PERFORMANCE METRICS -->
<div class="card mb-3 metrics-card mt-3">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
    <strong>Performance Metrics</strong>
    <div class="d-flex gap-2 mt-2 mt-sm-0">
      <select class="form-select form-select-sm w-auto" id="selectMonth">
        <option>September</option>
        <option>October</option>
      </select>
      <select class="form-select form-select-sm w-auto" id="selectYear">
        <option>2024</option>
        <option selected>2025</option>
      </select>
    </div>
  </div>

  <div class="card-body">
    <div class="row text-center gx-3 gy-3">
      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartPresent"></canvas>
          <div class="mt-2 fw-semibold">Present</div>
          <div class="text-muted small" id="presentValue"></div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartAbsent"></canvas>
          <div class="mt-2 fw-semibold">Absent</div>
          <div class="text-muted small" id="absentValue"></div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartOntime"></canvas>
          <div class="mt-2 fw-semibold">On Time</div>
          <div class="text-muted small" id="ontimeValue"></div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="metric-box p-3">
          <canvas id="chartLate"></canvas>
          <div class="mt-2 fw-semibold">Late</div>
          <div class="text-muted small" id="lateValue"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- SCHEDULE SECTION -->
<div class="card mb-3 schedule-card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <strong>Schedule</strong>
<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editScheduleModal">
  Edit Schedule
</button>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 schedule-table text-center align-middle">
        <tbody>
          <tr>
            <td class="time">7:00</td>
            <td class="slot"></td>
            <td class="slot bg-success text-white">RM-203<br><small>IS-CP 123</small></td>
            <td class="slot"></td>
            <td class="slot"></td>
            <td class="slot bg-primary text-white">RM-203<br><small>IS-CP 123</small></td>
            <td class="slot"></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-sm">
      <div class="modal-header">
        <h4 class="modal-title fw-semibold" id="editScheduleModalLabel">Edit Schedule</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <!-- Select Days -->
        <div class="mb-4">
          <label class="form-label fw-semibold">Select Days</label>
          <div class="d-flex gap-2 flex-wrap">
            <div class="day-btn" data-day="M">M</div>
            <div class="day-btn" data-day="T">T</div>
            <div class="day-btn" data-day="W">W</div>
            <div class="day-btn" data-day="TH">TH</div>
            <div class="day-btn" data-day="F">F</div>
            <div class="day-btn" data-day="S">S</div>
          </div>
        </div>

        <!-- Input Fields -->
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Designate Class</label>
            <input type="text" class="form-control" id="classInput" placeholder="Course, Year and Section">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Subject</label>
            <input type="text" class="form-control" id="subjectInput" placeholder="Enter Subject">
          </div>
        </div>

        <div class="row g-3 mt-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Start Time:</label>
            <input type="time" class="form-control" id="startTime">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">End Time:</label>
            <input type="time" class="form-control" id="endTime">
          </div>
        </div>

        <div class="mt-4">
          <button class="btn btn-add" id="addScheduleBtn">Add Schedule</button>
        </div>
      </div>
    </div>
  </div>
</div>





<!---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
 <script src="staff.js"></script>
</body>
</html>