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

// Filter handlers
document.getElementById("roleFilter").addEventListener("change", applyFilters);
document.getElementById("deptFilter").addEventListener("change", applyFilters);
document.getElementById("searchBox").addEventListener("input", applyFilters);

function applyFilters() {
  const role = document.getElementById("roleFilter").value;
  const dept = document.getElementById("deptFilter").value;
  const search = document.getElementById("searchBox").value;
  
  // Build query string
  const params = new URLSearchParams();
  if (role) params.append('role', role);
  if (dept) params.append('department', dept);
  if (search) params.append('search', search);
  
  // Reload page with filters
  window.location.href = 'attendancerep.php?' + params.toString();
}

// Click handler for table rows
document.addEventListener("DOMContentLoaded", function() {
  const rows = document.querySelectorAll("#attendanceTable tbody tr");
  rows.forEach(row => {
    const empId = row.getAttribute("data-id");
    if (empId) {
      row.style.cursor = "pointer";
      row.addEventListener("click", () => {
        window.location.href = "indirep.php?id=" + empId;
      });
    }
  });
});

 