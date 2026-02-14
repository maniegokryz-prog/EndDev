// Sidebar Toggle
const menuBtn = document.getElementById("menu-btn");
const sidebar = document.getElementById("sidebar");
const content = document.getElementById("content");

if (menuBtn && sidebar && content) {
  menuBtn.addEventListener('click', () => {
    if (window.innerWidth <= 767) {
      sidebar.classList.toggle('mobile-nav');
      document.body.classList.toggle('lock-scroll');

      if (sidebar.classList.contains('mobile-nav')) {
        const backdrop = document.createElement('div');
        backdrop.classList.add('mobile-backdrop');
        backdrop.setAttribute('id', 'mobileBackdrop');
        document.body.appendChild(backdrop);

        backdrop.addEventListener('click', () => {
          sidebar.classList.remove('mobile-nav');
          document.body.classList.remove('lock-scroll');
          backdrop.remove();
        });
      } else {
        const existing = document.getElementById('mobileBackdrop');
        if (existing) existing.remove();
      }
    } else {
      sidebar.classList.toggle('collapsed');
      content.classList.toggle('shift');
    }
  });
}
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

  // Update Selected Date Display
  const dateDisplay = document.getElementById("selectedDateDisplay");
  if (dateDisplay) {
    if (date) {
      const dateObj = new Date(date + 'T00:00:00');
      const options = { year: 'numeric', month: 'long', day: 'numeric' };
      dateDisplay.textContent = dateObj.toLocaleDateString('en-US', options);
    } else {
      // Fallback to today's date if filter is cleared
      const today = new Date();
      const options = { year: 'numeric', month: 'long', day: 'numeric' };
      dateDisplay.textContent = today.toLocaleDateString('en-US', options);
    }
  }

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

