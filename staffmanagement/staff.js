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
        <td colspan="5" class="text-center py-4 text-muted">
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
                   onerror="this.src='../assets/profile_pic/user.png';" 
                   class="rounded-circle me-3" 
                   width="40" 
                   height="40"
                   alt="Profile">
              <div>
                <div class="fw-semibold">${staff.name}</div>
                <small class="text-muted">${staff.employee_id}</small>
              </div>
            </div>
          </td>
          <td>${staff.role}</td>
          <td>${staff.department}</td>
          <td>${staff.position || 'N/A'}</td>
          <td>
            <button 
              class="btn btn-outline-dark btn-sm d-flex flex-column align-items-center py-2 px-3 view-btn" 
              data-id="${staff.employee_id}">
              <i class="bi bi-person-circle fs-5 mb-1"></i>
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
      <td colspan="5" class="text-center py-4 text-danger">
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
    window.location.href = `staff_profile.php?id=${encodeURIComponent(staffId)}`;
  }
});


// Filters
// Initial load - fetch employees when page loads
document.addEventListener('DOMContentLoaded', () => {
  // Attach filter listeners if elements exist
  const roleFilter = document.getElementById("roleFilter");
  if (roleFilter) roleFilter.addEventListener("change", applyFilters);

  const deptFilter = document.getElementById("departmentFilter");
  if (deptFilter) deptFilter.addEventListener("change", applyFilters);

  const searchInput = document.getElementById("searchInput");
  if (searchInput) searchInput.addEventListener("input", applyFilters);

  // Only fetch employees if we are on a page that needs it (e.g. has the table)
  if (document.getElementById("staffTable")) {
    fetchEmployees();
  }
});

function applyFilters() {
  const roleFilter = document.getElementById("roleFilter");
  const deptFilter = document.getElementById("departmentFilter");
  const searchInput = document.getElementById("searchInput");

  const role = roleFilter ? roleFilter.value : "All Roles";
  const dept = deptFilter ? deptFilter.value : "All Departments";
  const search = searchInput ? searchInput.value : "";

  fetchEmployees(role, dept, search);
}





