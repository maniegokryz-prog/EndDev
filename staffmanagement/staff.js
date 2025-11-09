   // Sidebar Toggle
const menuBtn = document.getElementById("menu-btn");
const sidebar = document.getElementById("sidebar");
const content = document.getElementById("content");
menuBtn.addEventListener('click', () => {
  if (window.innerWidth < 992) {
    // MOBILE: overlay style
    sidebar.classList.toggle('active');
  } else {
    // DESKTOP: push style
    sidebar.classList.toggle('collapsed');
    content.classList.toggle('shift');
  }
});
//----------------------------------------------------------------------------------------------//

// Store staff data globally
let staffData = [];

// Fetch employee data from database
async function fetchEmployees(filterRole = "All Roles", filterDept = "All Departments", searchTerm = "") {
  try {
    const params = new URLSearchParams({
      role: filterRole,
      department: filterDept,
      search: searchTerm
    });

    const response = await fetch(`get_employees.php?${params.toString()}`);
    const result = await response.json();

    if (result.success) {
      staffData = result.data;
      renderStaffList();
    } else {
      console.error('Error fetching employees:', result.error);
      showError('Failed to load employee data');
    }
  } catch (error) {
    console.error('Fetch error:', error);
    showError('Failed to connect to server');
  }
}

function renderStaffList() {
  const tbody = document.getElementById("staffTable");
  tbody.innerHTML = "";

  if (staffData.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center py-4 text-muted">
          <i class="bi bi-inbox fs-1 d-block mb-2"></i>
          No employees found
        </td>
      </tr>
    `;
    return;
  }

  staffData.forEach(staff => {
      const row = `
        <tr>
          <td>
            <div class="d-flex align-items-center">
              <img src="../${staff.profile_photo}" 
                   onerror="this.src='../assets/profile_pic/user.png'" 
                   class="rounded-circle me-3" 
                   width="40" 
                   height="40">
              <div>
                <div class="fw-semibold">${staff.name}</div>
                <small class="text-muted">${staff.employee_id}</small>
              </div>
            </div>
          </td>
          <td>${staff.role}</td>
          <td>${staff.department}</td>
          <td>
            <button 
              class="btn btn-outline-dark btn-sm d-flex flex-column align-items-center py-2 px-3 view-btn" 
              data-id="${staff.employee_id}">
              <i class="fas fa-user fa-lg mb-1"></i>
              <span class="small fw-semibold">View</span>
            </button>
          </td>
        </tr>
      `;
      tbody.innerHTML += row;
    });
}

// Show error message
function showError(message) {
  const tbody = document.getElementById("staffTable");
  tbody.innerHTML = `
    <tr>
      <td colspan="4" class="text-center py-4 text-danger">
        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
        ${message}
      </td>
    </tr>
  `;
}

// ✅ Global event listener for "View" buttons (only needs to be declared ONCE)
document.addEventListener("click", function (e) {
  const viewBtn = e.target.closest(".view-btn");
  if (!viewBtn) return;

  const staffId = viewBtn.getAttribute("data-id");
  if (staffId) {
    window.location.href = `staffinfo.php?id=${encodeURIComponent(staffId)}`;
  }
});


// Filters
document.getElementById("roleFilter").addEventListener("change", applyFilters);
document.getElementById("departmentFilter").addEventListener("change", applyFilters);
document.getElementById("searchInput").addEventListener("input", applyFilters);

function applyFilters() {
  const role = document.getElementById("roleFilter").value;
  const dept = document.getElementById("departmentFilter").value;
  const search = document.getElementById("searchInput").value;
  fetchEmployees(role, dept, search);
}

// Initial load - fetch employees when page loads
document.addEventListener('DOMContentLoaded', () => {
  fetchEmployees();
});


const dayButtons = document.querySelectorAll(".day-btn");
const scheduleBody = document.getElementById("scheduleBody");
const addBtn = document.getElementById("addScheduleBtn");
const clearAllBtn = document.getElementById("clearAllBtn");

let selectedDays = [];
let schedules = [];

dayButtons.forEach(btn => {
  btn.addEventListener("click", () => {
    btn.classList.toggle("active");
    const day = btn.dataset.day;

    if (selectedDays.includes(day)) {
      selectedDays = selectedDays.filter(d => d !== day);
    } else {
      selectedDays.push(day);
    }
  });
});

addBtn.addEventListener("click", () => {
  const className = document.getElementById("classInput").value.trim();
  const subject = document.getElementById("subjectInput").value.trim();
  const startTime = document.getElementById("startTime").value;
  const endTime = document.getElementById("endTime").value;

  if (selectedDays.length === 0 || !className || !subject || !startTime || !endTime) {
    alert("Please fill in all fields and select at least one day.");
    return;
  }

  selectedDays.forEach(day => {
    schedules.push({ className, subject, day, startTime, endTime });
  });

  renderSchedule();
  resetInputs();
});

clearAllBtn.addEventListener("click", () => {
  if (confirm("Are you sure you want to clear all schedules?")) {
    schedules = [];
    renderSchedule();
  }
});

function deleteSchedule(index) {
  schedules.splice(index, 1);
  renderSchedule();
}

function renderSchedule() {
  scheduleBody.innerHTML = "";
  schedules.forEach((sched, index) => {
    const row = `
      <tr>
        <td>${sched.className}</td>
        <td>${sched.subject}</td>
        <td>${sched.day}</td>
        <td>${formatTime(sched.startTime)}</td>
        <td>${formatTime(sched.endTime)}</td>
        <td>
          <button class="btn btn-outline-danger btn-sm" onclick="deleteSchedule(${index})">
            <i class="fas fa-trash"></i>
          </button>
        </td>
      </tr>
    `;
    scheduleBody.innerHTML += row;
  });
}

function formatTime(timeStr) {
  const [hour, minute] = timeStr.split(":");
  const h = parseInt(hour);
  const ampm = h >= 12 ? "PM" : "AM";
  const formattedHour = h % 12 || 12;
  return `${formattedHour}:${minute} ${ampm}`;
}

function resetInputs() {
  document.getElementById("classInput").value = "";
  document.getElementById("subjectInput").value = "";
  document.getElementById("startTime").value = "";
  document.getElementById("endTime").value = "";
  selectedDays = [];
  dayButtons.forEach(btn => btn.classList.remove("active"));
}

// STAFF INFO
document.addEventListener("DOMContentLoaded", () => {
  console.log("Staff info page loaded.");
});

document.getElementById('editInfoForm').addEventListener('submit', function (e) {
  e.preventDefault();
  alert('Changes saved successfully!');
});

//MATRIX
const createDonut = (id, value) => {
  const ctx = document.getElementById(id).getContext('2d');
  return new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Value', 'Remaining'],
      datasets: [{
        data: [value, 100 - value]
      }]
    },
    options: {
      responsive: false,
      cutout: '70%'
    }
  });
};

// Example values (palitan mo ito mamaya)
createDonut('chartPresent', 75);
createDonut('chartAbsent', 5);
createDonut('chartOntime', 15);
createDonut('chartLate', 5);

//edit sched
// Toggle day button active state
document.querySelectorAll('.day-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    btn.classList.toggle('active');
  });
});

// Handle Add Schedule button
document.getElementById('addScheduleBtn').addEventListener('click', () => {
  const selectedDays = Array.from(document.querySelectorAll('.day-btn.active')).map(btn => btn.dataset.day);
  const className = document.getElementById('classInput').value.trim();
  const subject = document.getElementById('subjectInput').value.trim();
  const startTime = document.getElementById('startTime').value;
  const endTime = document.getElementById('endTime').value;

  if (!selectedDays.length || !className || !subject || !startTime || !endTime) {
    alert("Please fill out all fields and select at least one day.");
    return;
  }

  const scheduleData = {
    days: selectedDays,
    class: className,
    subject: subject,
    start: startTime,
    end: endTime
  };

  console.log("✅ Schedule Added:", scheduleData);
  alert("Schedule added successfully!");

  // Close modal after adding
  const modal = bootstrap.Modal.getInstance(document.getElementById('editScheduleModal'));
  modal.hide();
});

//add leave
  function addLeave() {
      const leaveList = document.getElementById("leaveList");

      // Sample leave data — replace with form/modal input later
      const leaveType = "Vacation";
      const leaveDates = "September 30 to October 2";

      // Create entry container
      const entry = document.createElement("div");
      entry.className = "d-flex justify-content-between align-items-center bg-light rounded px-2 py-2 mb-2";

      // Entry content
      entry.innerHTML = `
        <div class="leave-entry">
          <strong>${leaveType}</strong><br>
          <small>${leaveDates}</small>
        </div>
        <button class="btn btn-outline-danger btn-sm" onclick="this.closest('.d-flex').remove()">
          <i class="bi bi-trash"></i>
        </button>
      `;

      leaveList.appendChild(entry);
    }




