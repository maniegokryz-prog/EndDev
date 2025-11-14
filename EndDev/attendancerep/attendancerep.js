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

 