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

<<<<<<< HEAD
// Placeholder for future features like search and filter
document.getElementById("searchBox").addEventListener("keyup", function() {
  let filter = this.value.toLowerCase();
  let rows = document.querySelectorAll("#attendanceTable tbody tr");

  rows.forEach(row => {
    let text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? "" : "none";
  });
});

 document.querySelectorAll("#attendanceTable tbody tr").forEach(row => {
    row.addEventListener("click", () => {
      document.getElementById("employeeName").innerText = row.dataset.employee;
      document.getElementById("modalDay").innerText = row.dataset.day;
      document.getElementById("modalTimeIn").innerText = row.dataset.timein;
      document.getElementById("modalTimeOut").innerText = row.dataset.timeout;
      document.getElementById("modalStatus").innerText = row.dataset.status;

      new bootstrap.Modal(document.getElementById("attendanceModal")).show();
    });
  });

//click display
  document.addEventListener("DOMContentLoaded", function() {
      const rows = document.querySelectorAll("#attendanceTable tbody tr");
      rows.forEach(row => {
        row.addEventListener("click", () => {
          const empId = row.getAttribute("data-id");
          window.location.href = "indirep.php?id=" + empId;
        });
      });
    });
 
// Handle Month selection
  document.querySelectorAll('.month-option').forEach(item => {
    item.addEventListener('click', function () {
      alert("Filtering by month: " + this.textContent);
      // dito mo ilalagay filtering logic (AJAX or JS filter)
    });
  });

  // Handle Year selection
  document.querySelectorAll('.year-option').forEach(item => {
    item.addEventListener('click', function () {
      alert("Filtering by year: " + this.textContent);
      // dito mo ilalagay filtering logic
    });
  });

  // Handle Export
  document.querySelectorAll('.export-option').forEach(item => {
    item.addEventListener('click', function () {
      alert("Exporting as: " + this.dataset.type.toUpperCase());
      // dito mo ilalagay export function (PDF, Excel, CSV)
    });
  });

   function notifyExport(type) {
      alert("You selected to export as " + type + ". Proceeding with export...");
      // dito pwede mong idagdag yung actual export logic (e.g., PHP backend call)
    }

=======
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

>>>>>>> de54bce0e298425ce30c77eb7e2cb27b74dc8ef5
 