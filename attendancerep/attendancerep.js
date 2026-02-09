// Sidebar Toggle handled by dashboard.js
//----------------------------------------------------------------------------------------------//

// Filter handlers
// Filter handlers
document.getElementById("dateFilter").addEventListener("change", () => fetchResults());
document.getElementById("roleFilter").addEventListener("change", () => fetchResults());
document.getElementById("deptFilter").addEventListener("change", () => fetchResults());

// Debounce function
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Attach debounced listener to search box
const debouncedSearch = debounce(() => fetchResults(), 500);
document.getElementById("searchBox").addEventListener("input", debouncedSearch);

function fetchResults() {
  const date = document.getElementById("dateFilter").value;
  const role = document.getElementById("roleFilter").value;
  const dept = document.getElementById("deptFilter").value;
  const search = document.getElementById("searchBox").value;

  // Build query string
  const params = new URLSearchParams();
  if (date) params.append('date', date);
  if (role) params.append('role', role);
  if (dept) params.append('department', dept);
  if (search) params.append('search', search);

  // Update URL without reloading
  const newUrl = window.location.pathname + '?' + params.toString();
  window.history.pushState({}, '', newUrl);

  // Add ajax flag for the fetch request
  params.append('ajax', '1');

  // Fetch results
  fetch('attendancerep.php?' + params.toString())
    .then(response => response.text())
    .then(html => {
      document.querySelector("#attendanceTable tbody").innerHTML = html;
      attachRowHandlers();
    })
    .catch(error => console.error('Error fetching results:', error));
}

function attachRowHandlers() {
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
}

// Initial attachment of handlers
document.addEventListener("DOMContentLoaded", attachRowHandlers);

