// Sidebar Toggle handled by dashboard.js
//----------------------------------------------------------------------------------------------//

// Store staff data globally
// Pagination State
let currentOffset = 0;
const LIMIT = 15;
let isLoading = false;
let currentFilters = {
  role: "All Roles",
  dept: "All Departments",
  search: ""
};

// Fetch employee data from database
async function fetchEmployees(filterRole = "All Roles", filterDept = "All Departments", searchTerm = "", isLoadMore = false) {
  if (isLoading) return;
  isLoading = true;

  // If new search/filter, reset offset
  if (!isLoadMore) {
    currentOffset = 0;
    currentFilters = { role: filterRole, dept: filterDept, search: searchTerm };
    staffData = []; // Clear local data
  }

  try {
    const params = new URLSearchParams({
      role: currentFilters.role,
      department: currentFilters.dept,
      search: currentFilters.search,
      limit: LIMIT,
      offset: currentOffset
    });

    const loadMoreBtn = document.getElementById("loadMoreBtn");
    if (loadMoreBtn && isLoadMore) {
      loadMoreBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
      loadMoreBtn.disabled = true;
    }

    const response = await fetch(`get_employees.php?${params.toString()}`);
    const result = await response.json();

    if (result.success) {
      if (isLoadMore) {
        staffData = [...staffData, ...result.data];
      } else {
        staffData = result.data;
      }

      renderStaffList(isLoadMore);

      // Manage Load More Button Visibility
      const loadMoreContainer = document.getElementById("loadMoreContainer");
      if (loadMoreContainer) {
        // If we got fewer items than limit, we reached the end
        if (result.data.length < LIMIT) {
          loadMoreContainer.classList.add("d-none");
        } else {
          loadMoreContainer.classList.remove("d-none");
        }
      }

      // Update offset for next call
      if (result.data.length > 0) {
        currentOffset += result.data.length;
      }

    } else {
      console.error('Error fetching employees:', result.error);
      if (!isLoadMore) showError('Failed to load employee data');
    }
  } catch (error) {
    console.error('Fetch error:', error);
    if (!isLoadMore) showError('Failed to connect to server');
  } finally {
    isLoading = false;
    const loadMoreBtn = document.getElementById("loadMoreBtn");
    if (loadMoreBtn) {
      loadMoreBtn.innerHTML = 'See More <i class="bi bi-chevron-down ms-1"></i>';
      loadMoreBtn.disabled = false;
    }
  }
}

function renderStaffList(isAppend = false) {
  const tbody = document.getElementById("staffTable");

  if (!isAppend) {
    tbody.innerHTML = "";
  }

  if (staffData.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="text-center py-4 text-muted">
          <i class="bi bi-inbox fs-1 d-block mb-2"></i>
          No employees found
        </td>
      </tr>
    `;
    const loadMoreContainer = document.getElementById("loadMoreContainer");
    if (loadMoreContainer) loadMoreContainer.classList.add("d-none");
    return;
  }

  // If appending, we only need to render the NEW items? 
  // For simplicity and correctness with the global staffData array, let's just render the slice we just got?
  // Actually, fetchEmployees updates staffData with ALL data. 
  // If we clear and re-render everything, it might be slow if list is huge.
  // Better: render ONLY the new items if isAppend is true.
  // BUT `renderStaffList` uses `staffData`.
  // Let's change `renderStaffList` to accept data to render to decouple it slightly?
  // Or just iterate from `currentOffset - (last fetched count)`?
  // Simpler approach for now: Clear ONLY if !isAppend. Re-render ALL? No, that defeats the purpose of pagination visuals (jumping).
  // Let's change `staffData` handling in `fetchEmployees` slightly.

  // Actually, to keep it simple: fetchEmployees appends to staffData.
  // If `isAppend` is true, we need to locate where we stopped rendering.
  // Let's just assume `staffData` contains everything.
  // We can clear and re-render all (easy but maybe flicker). 
  // OR we can just append the LAST fetched batch.
  // Refactor: Let `renderStaffList` take the items to render.

  // Changed approach inside this function:
  // If !isAppend, clear innerHTML.
  // If isAppend, don't clear.
  // We need to know WHICH items to add. 
  // `fetchEmployees` knows the new items (`result.data`). 
  // Let's modify `fetchEmployees` to pass `result.data` to `renderStaffList`?
  // No, I can't change signature easily without changing caller.
  // I will just rely on `staffData` and `isAppend`.
  // If `isAppend`, I will render `staffData` starting from `currentOffset - lastBatchSize`.
  // Wait, `currentOffset` is updated AFTER render.
  // Let's fix logic.
}

// Redefining renderStaffList to accept data argument to render
function renderEmployeesToTable(employees, shouldClear = true) {
  const tbody = document.getElementById("staffTable");
  if (shouldClear) tbody.innerHTML = "";

  if (employees.length === 0 && shouldClear) {
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

  employees.forEach(staff => {
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
    tbody.insertAdjacentHTML('beforeend', row);
  });
}

// Override original renderStaffList to simply call new function with full data? 
// No, we need to integrate with `fetchEmployees`.
// Let's fix `fetchEmployees` to call `renderEmployeesToTable`.

// ... adjusting `fetchEmployees` ...
// I will rewrite `fetchEmployees` entirely below to use `renderEmployeesToTable`.

// Start of REPLACEMENT for fetchEmployees and renderStaffList and event listeners
async function fetchEmployees(filterRole = "All Roles", filterDept = "All Departments", searchTerm = "", isLoadMore = false) {
  if (isLoading) return;
  isLoading = true;

  if (!isLoadMore) {
    currentOffset = 0;
    currentFilters = { role: filterRole, dept: filterDept, search: searchTerm };
    staffData = [];
  }

  try {
    const params = new URLSearchParams({
      role: currentFilters.role,
      department: currentFilters.dept,
      search: currentFilters.search,
      limit: LIMIT,
      offset: currentOffset
    });

    const loadMoreBtn = document.getElementById("loadMoreBtn");
    if (loadMoreBtn && isLoadMore) {
      loadMoreBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
      loadMoreBtn.disabled = true;
    }

    const response = await fetch(`get_employees.php?${params.toString()}`);
    const result = await response.json();

    if (result.success) {
      const newStaff = result.data;

      if (isLoadMore) {
        staffData = [...staffData, ...newStaff];
      } else {
        staffData = newStaff;
      }

      renderEmployeesToTable(newStaff, !isLoadMore);

      const loadMoreContainer = document.getElementById("loadMoreContainer");
      if (loadMoreContainer) {
        if (newStaff.length < LIMIT) {
          loadMoreContainer.classList.add("d-none");
        } else {
          loadMoreContainer.classList.remove("d-none");
        }
      }

      if (newStaff.length > 0) {
        currentOffset += newStaff.length;
      }

    } else {
      console.error('Error fetching employees:', result.error);
      if (!isLoadMore) showError('Failed to load employee data');
    }
  } catch (error) {
    console.error('Fetch error:', error);
    if (!isLoadMore) showError('Failed to connect to server');
  } finally {
    isLoading = false;
    const loadMoreBtn = document.getElementById("loadMoreBtn");
    if (loadMoreBtn) {
      loadMoreBtn.innerHTML = 'See More <i class="bi bi-chevron-down ms-1"></i>';
      loadMoreBtn.disabled = false;
    }
  }
}

// Renamed helper to avoid confusion
function renderEmployeesToTable(employees, shouldClear = true) {
  const tbody = document.getElementById("staffTable");
  if (shouldClear) tbody.innerHTML = "";

  if (employees.length === 0 && shouldClear) {
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

  employees.forEach(staff => {
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
    tbody.insertAdjacentHTML('beforeend', row);
  });
}

function renderStaffList() {
  // Legacy support if called elsewhere (unlikely)
  renderEmployeesToTable(staffData, true);
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

// ✅ Global event listener for "View" buttons
document.addEventListener("click", function (e) {
  const viewBtn = e.target.closest(".view-btn");
  if (viewBtn) {
    const staffId = viewBtn.getAttribute("data-id");
    if (staffId) {
      window.location.href = `staff_profile.php?id=${encodeURIComponent(staffId)}`;
    }
    return;
  }

  // Load More Button listener (delegate)
  const loadMoreBtn = e.target.closest("#loadMoreBtn");
  if (loadMoreBtn) {
    fetchEmployees(currentFilters.role, currentFilters.dept, currentFilters.search, true);
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

  fetchEmployees(role, dept, search, false);
}





