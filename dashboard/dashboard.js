const menuBtn = document.getElementById("menu-btn");
const sidebar = document.getElementById("sidebar");
const content = document.getElementById("content");

menuBtn.addEventListener('click', () => {
  if (window.innerWidth <= 576) {
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

//-----------------------------------------------------------------------------------------------------------------------------------------------------//
  // Show live clock and date
function updateClock() {
  const now = new Date();

  // Time
  let hours = now.getHours();
  let minutes = now.getMinutes();
  let ampm = hours >= 12 ? "PM" : "AM";
  hours = hours % 12;
  hours = hours ? hours : 12; // 0 -> 12
  minutes = minutes < 10 ? "0" + minutes : minutes;

  const timeStr = `${hours}:${minutes} ${ampm}`;
  document.getElementById("current-time").textContent = timeStr;

  // Date
  const options = { weekday: "long", year: "numeric", month: "long", day: "numeric" };
  const dateStr = now.toLocaleDateString("en-US", options).toUpperCase();
  document.getElementById("current-date").textContent = dateStr;

  // Highlight current day in calendar
  const today = now.getDate();
  const tds = document.querySelectorAll(".card table td");
  tds.forEach(td => {
    if (td.textContent == today) {
      td.classList.add("bg-primary", "text-white", "fw-bold", "rounded-circle");
    } else {
      td.classList.remove("bg-primary", "text-white", "fw-bold", "rounded-circle");
    }
  });
}

// Update every second
setInterval(updateClock, 1000);
updateClock();

//-----------------------------------------------------------------------------------------------------------------------------------------------------//
//calendar
(function () {
  const calendarBody = document.getElementById('calendar-body');
  const monthYearEl = document.getElementById('cal-month-year');
  const prevBtn = document.getElementById('cal-prev');
  const nextBtn = document.getElementById('cal-next');

  let viewDate = new Date();

  function formatDateYMD(d) {
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  }

  function renderCalendar(date) {
    calendarBody.innerHTML = '';

    const year = date.getFullYear();
    const month = date.getMonth();
    const monthName = date.toLocaleString(undefined, { month: 'long' });
    monthYearEl.textContent = `${monthName} ${year}`;

    const firstDayIndex = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const cells = [];

    const prevMonthLastDate = new Date(year, month, 0).getDate(); 
    for (let i = 0; i < firstDayIndex; i++) {
      const dayNum = prevMonthLastDate - firstDayIndex + 1 + i;
      const dObj = new Date(year, month - 1, dayNum);
      cells.push({ day: dayNum, otherMonth: true, dateObj: dObj });
    }

    for (let d = 1; d <= daysInMonth; d++) {
      cells.push({ day: d, otherMonth: false, dateObj: new Date(year, month, d) });
    }

    let nextDay = 1;
    while (cells.length < 42) {
      const dObj = new Date(year, month + 1, nextDay);
      cells.push({ day: nextDay, otherMonth: true, dateObj: dObj });
      nextDay++;
    }

    const today = new Date();

    for (let i = 0; i < 42; i += 7) {
      const tr = document.createElement('tr');

      for (let j = 0; j < 7; j++) {
        const cell = cells[i + j];
        const td = document.createElement('td');
        const link = document.createElement('a');

        link.classList.add('calendar-day');
        link.href = '#';
        link.setAttribute('data-date', formatDateYMD(cell.dateObj));
        link.textContent = cell.day;

        if (cell.otherMonth) {
          link.classList.add('calendar-other');
        }

        const isSameDay =
          cell.dateObj.getFullYear() === today.getFullYear() &&
          cell.dateObj.getMonth() === today.getMonth() &&
          cell.dateObj.getDate() === today.getDate();

        const isSameMonthView =
          date.getFullYear() === today.getFullYear() &&
          date.getMonth() === today.getMonth();

        if (isSameDay && isSameMonthView && !cell.otherMonth) {
          link.classList.add('calendar-today');
        }

        link.addEventListener('click', function (e) {
          e.preventDefault();
          const dateStr = this.getAttribute('data-date');
          window.location.href = `attendanceHistory.php?date=${encodeURIComponent(dateStr)}`;
        });

        td.appendChild(link);
        tr.appendChild(td);
      }

      calendarBody.appendChild(tr);
    }
  }

  prevBtn.addEventListener('click', function () {
    viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
    renderCalendar(viewDate);
  });

  nextBtn.addEventListener('click', function () {
    viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
    renderCalendar(viewDate);
  });

  renderCalendar(viewDate);
})();
//-----------------------------------------------------------------------------------------------------------------------------------------------------//
//attendancefeed
async function loadAttendanceFeed() {
  const container = document.getElementById("attendanceList");
  const countBadge = document.getElementById("feedCount");
  
  // Show loading state
  container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
  
  try {
    const response = await fetch('get_attendance_feed.php?limit=50');
    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.error || 'Failed to load attendance feed');
    }
    
    const attendanceData = result.data;
    
    // Update count badge
    if (countBadge) {
      countBadge.textContent = attendanceData.length;
    }
    
    // Clear container
    container.innerHTML = '';
    
    if (attendanceData.length === 0) {
      container.innerHTML = '<div class="text-center text-muted py-3"><small>No attendance records yet</small></div>';
      return;
    }
    
    // Populate feed items
    attendanceData.forEach(record => {
      const item = document.createElement("div");
      item.classList.add("feed-item");
      
      // Determine badge color based on log type
      const badgeClass = record.log_type === 'time_in' ? 'bg-success' : 'bg-info';
      const badgeText = record.log_type_display;
      
      // Determine status color
      let statusClass = 'text-muted';
      if (record.status) {
        if (record.status.toLowerCase().includes('late')) {
          statusClass = 'text-danger';
        } else if (record.status.toLowerCase().includes('on-time')) {
          statusClass = 'text-success';
        } else if (record.status.toLowerCase().includes('overtime')) {
          statusClass = 'text-primary';
        }
      }
      
      // Create tooltip text
      const tooltipText = `${record.formatted_date} at ${record.formatted_time}\n${record.detailed_time_ago}`;
      
      item.innerHTML = `
        <img src="${record.profile_photo}" alt="${record.full_name}" onerror="this.src='../assets/profile_pic/user.png'">
        <div class="feed-info">
          <h6>${record.full_name} <span class="badge ${badgeClass} ms-1" style="font-size: 0.65rem;">${badgeText}</span></h6>
          <small class="${statusClass}">${record.formatted_time}</small>
        </div>
        <small class="text-muted time-ago-badge" 
               data-bs-toggle="tooltip" 
               data-bs-placement="left" 
               data-bs-html="true"
               title="${tooltipText.replace(/\n/g, '<br>')}">${record.time_ago}</small>
      `;
      
      container.appendChild(item);
    });
    
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    
  } catch (error) {
    console.error('Error loading attendance feed:', error);
    container.innerHTML = '<div class="text-center text-danger py-3"><small>Failed to load attendance feed</small></div>';
  }
}

// Load attendance feed on page load
document.addEventListener('DOMContentLoaded', function() {
  loadAttendanceFeed();
  loadLateToday();
  
  // Auto-refresh every 30 seconds
  setInterval(loadAttendanceFeed, 30000);
  setInterval(loadLateToday, 30000);
});
//-----------------------------------------------------------------------------------------------------------------------------------------------------//
//late today
async function loadLateToday() {
  const container = document.querySelector(".late-list");
  
  if (!container) return;
  
  try {
    const response = await fetch('get_late_today.php');
    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.error || 'Failed to load late employees');
    }
    
    const lateEmployees = result.data;
    
    // Clear container
    container.innerHTML = '';
    
    if (lateEmployees.length === 0) {
      container.innerHTML = '<div class="text-center text-muted py-3"><small>No late employees today</small></div>';
      return;
    }
    
    // Populate late employees using the same template structure
    lateEmployees.forEach(employee => {
      const item = document.createElement("div");
      item.classList.add("d-flex", "align-items-center", "border-bottom", "py-2");
      
      item.innerHTML = `
        <img src="${employee.profile_photo}" class="profile-img me-3" alt="${employee.full_name}" onerror="this.src='../assets/profile_pic/user.png'">
        <div>
          <h6 class="mb-0">${employee.full_name}</h6>
          <small>${employee.position} - ${employee.time_in} (${employee.late_display})</small>
        </div>
      `;
      
      container.appendChild(item);
    });
    
  } catch (error) {
    console.error('Error loading late employees:', error);
    container.innerHTML = '<div class="text-center text-danger py-3"><small>Failed to load late employees</small></div>';
  }
}
//-----------------------------------------------------------------------------------------------------------------------------------------------------//
 






