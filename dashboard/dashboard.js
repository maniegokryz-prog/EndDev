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
 const attendanceData = [
    {
      name: "Ronnel Borlongan",
      time: "7:00 AM",
      ago: "10m Ago",
      image: "pic.png"
    },
    
    
  ];

  const container = document.getElementById("attendanceList");

  attendanceData.forEach(person => {
    const item = document.createElement("div");
    item.classList.add("feed-item");
    item.innerHTML = `
      <img src="${person.image}" alt="${person.name}">
      <div class="feed-info">
        <h6>${person.name}</h6>
        <small>Time in: ${person.time}</small>
      </div>
      <small class="text-muted">${person.ago}</small>
    `;
    container.appendChild(item);
  });
//-----------------------------------------------------------------------------------------------------------------------------------------------------//
 






