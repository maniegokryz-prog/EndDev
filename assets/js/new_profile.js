/**
 * New Staff Profile JS
 * Handles Charts, Calendar, Schedule, DTR, and Leave Requests.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize components
    initPerformanceMetrics();
    initCalendarWidget();
    initScheduleDisplay();
    initDTR();
    initLeaveManagement();
});

// ==========================================
// Performance Metrics (Chart.js)
// ==========================================
let metricsCharts = {
    present: null,
    absent: null,
    ontime: null,
    late: null
};

function initPerformanceMetrics() {
    const monthSelect = document.getElementById('selectMonth');
    const yearSelect = document.getElementById('selectYear');

    if (monthSelect && yearSelect) {
        monthSelect.addEventListener('change', loadPerformanceMetrics);
        yearSelect.addEventListener('change', loadPerformanceMetrics);
        // Initial load
        loadPerformanceMetrics();
    }
}

function createDonutChart(canvasId, percentage, color) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    // Destroy existing chart if it exists
    const chartKey = canvasId.replace('chart', '').toLowerCase();
    if (metricsCharts[chartKey]) {
        metricsCharts[chartKey].destroy();
    }

    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [percentage, 100 - percentage],
                backgroundColor: [color, '#e9ecef'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '75%',
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false }
            }
        }
    });
}

async function loadPerformanceMetrics() {
    const month = document.getElementById('selectMonth').value;
    const year = document.getElementById('selectYear').value;
    const loadingEl = document.getElementById('metricsLoading');
    const contentEl = document.getElementById('metricsContent');
    const errorEl = document.getElementById('metricsError');

    if (loadingEl) loadingEl.style.display = 'block';
    if (contentEl) contentEl.style.display = 'none';
    if (errorEl) errorEl.style.display = 'none';

    try {
        const params = new URLSearchParams({
            employee_id: window.employeeId, // Global from PHP
            year: year
        });
        if (month) params.append('month', month);

        const response = await fetch(`get_performance_metrics.php?${params.toString()}`);
        const data = await response.json();

        if (!data.success) throw new Error(data.error || 'Failed to load metrics');

        const metrics = data.metrics;

        // Render Charts & Values
        metricsCharts.present = createDonutChart('chartPresent', metrics.present.percentage, '#48bb78');
        updateMetricValue('presentValue', metrics.present.count);
        updateMetricValue('presentCount', metrics.present.percentage + '%');

        metricsCharts.absent = createDonutChart('chartAbsent', metrics.absent.percentage, '#f56565');
        updateMetricValue('absentValue', metrics.absent.count);
        updateMetricValue('absentCount', metrics.absent.percentage + '%');

        metricsCharts.ontime = createDonutChart('chartOntime', metrics.onTime.percentage, '#4299e1');
        updateMetricValue('ontimeValue', metrics.onTime.count);
        updateMetricValue('ontimeCount', metrics.onTime.percentage + '%');

        metricsCharts.late = createDonutChart('chartLate', metrics.late.percentage, '#ed8936');
        updateMetricValue('lateValue', metrics.late.count);
        updateMetricValue('lateCount', metrics.late.percentage + '%');

        if (loadingEl) loadingEl.style.display = 'none';
        if (contentEl) contentEl.style.display = 'grid'; // Changed to grid (css)

    } catch (error) {
        console.error('Error loading metrics:', error);
        if (loadingEl) loadingEl.style.display = 'none';
        if (errorEl) errorEl.style.display = 'block';
    }
}

function updateMetricValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

// ==========================================
// Date Range Picker / Calendar Widget
// ==========================================
let selectedDates = [];
let startDate = null;
let endDate = null;
const MAX_DAYS = 16;
let currentDate = new Date();

function initCalendarWidget() {
    const trigger = document.getElementById("dateRangeTrigger");
    const popup = document.getElementById("calendarPopup");
    const closeBtn = document.querySelector(".close-calendar-btn");
    const clearBtn = document.getElementById("clearDatesBtn");

    if (trigger && popup) {
        // Add info text if not present
        if (!popup.querySelector('.calendar-info-text')) {
            const info = document.createElement('div');
            info.className = 'calendar-info-text text-muted small text-center pb-2 border-bottom mb-2';
            info.style.fontSize = '0.75rem';
            info.textContent = `You can select up to ${MAX_DAYS} days range.`;
            const header = popup.querySelector('.calendar-header');
            if (header) header.insertAdjacentElement('afterend', info);
        }

        // ==========================================
        // Visit Offset Logic
        // ==========================================
        let pendingOffsetId = null;

        window.confirmSetOffset = function (recordId) {
            pendingOffsetId = recordId;
            const modalEl = document.getElementById('leaveConfirmModal');
            if (modalEl) {
                document.getElementById('leaveConfirmTitle').textContent = 'Confirm Offset';
                document.getElementById('leaveConfirmMsg').textContent = 'Are you sure you want to set this visit as an official offset? This will mark the attendance as Present.';
                const btn = document.getElementById('btnConfirmAction');
                // Remove previous listeners to avoid duplicates (cleaner would be to use a persistent handler that checks a variable, but this works for simple cases)
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);

                newBtn.addEventListener('click', () => {
                    executeSetOffset();
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                });

                new bootstrap.Modal(modalEl).show();
            } else {
                if (confirm('Are you sure you want to set this visit as an official offset?')) {
                    executeSetOffset();
                }
            }
        };

        async function executeSetOffset() {
            if (!pendingOffsetId) return;

            try {
                const response = await fetch('processes/set_visit_offset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ record_id: pendingOffsetId })
                });

                const result = await response.json();

                if (result.success) {
                    // Reload DTR
                    loadDTRForSelectedRange();
                    // Optional: Show success
                    // alert('Visit successfully set as Offset.'); 
                } else {
                    alert('Failed: ' + result.message);
                }
            } catch (e) {
                console.error(e);
                alert('An error occurred.');
            } finally {
                pendingOffsetId = null;
            }
        }


        trigger.addEventListener("click", (e) => {
            e.stopPropagation();
            const isHidden = popup.style.display === "none" || popup.style.display === "";
            popup.style.display = isHidden ? "block" : "none";

            if (isHidden) {
                // Reset selection on open to prevent sticky range issues
                selectedDates = [];
                startDate = null; endDate = null;
                updateDateInput();
                generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            }
        });

        // Close on outside click
        document.addEventListener("click", (e) => {
            if (!popup.contains(e.target) && !trigger.contains(e.target)) {
                popup.style.display = "none";
            }
        });


        // Prevent closing when clicking inside popup
        popup.addEventListener("click", (e) => {
            e.stopPropagation();
        });

        if (closeBtn) closeBtn.addEventListener("click", () => popup.style.display = "none");

        if (clearBtn) clearBtn.addEventListener("click", () => {
            selectedDates = [];
            startDate = null; endDate = null;
            updateDateInput();
            generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
            loadDTRForSelectedRange();
        });
    }

    const prevBtn = document.getElementById("prevMonth");
    const nextBtn = document.getElementById("nextMonth");

    if (prevBtn) prevBtn.addEventListener("click", () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
    });

    if (nextBtn) nextBtn.addEventListener("click", () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
    });

    generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
}

function updateDateInput() {
    const input = document.getElementById("dateRangeInput");
    if (!input) return;

    if (selectedDates.length > 0) {
        if (selectedDates.length === 1) {
            input.value = selectedDates[0];
        } else {
            input.value = `${selectedDates[0]} to ${selectedDates[selectedDates.length - 1]}`;
        }
    } else {
        input.value = "";
    }
}

function generateCalendar(year, month) {
    const calendar = document.getElementById("calendar");
    const calendarTitle = document.getElementById("calendarTitle");
    if (!calendar) return;

    calendar.innerHTML = "";

    const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    if (calendarTitle) calendarTitle.textContent = `${months[month]} ${year}`;

    // Headers
    const weekdays = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
    weekdays.forEach(day => {
        const div = document.createElement("div");
        div.textContent = day;
        div.classList.add("weekday-label");
        calendar.appendChild(div);
    });

    const monthStart = new Date(year, month, 1);
    const monthEnd = new Date(year, month + 1, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Empty cells
    let startDay = (monthStart.getDay() + 6) % 7;
    for (let i = 0; i < startDay; i++) {
        const empty = document.createElement("div");
        empty.classList.add("calendar-day", "empty");
        calendar.appendChild(empty);
    }

    // Days
    for (let day = 1; day <= monthEnd.getDate(); day++) {
        const dateDiv = document.createElement("div");
        dateDiv.textContent = day;
        dateDiv.classList.add("calendar-day");

        const currentDateStr = formatDateISO(year, month, day);
        const dateObj = new Date(year, month, day);
        dateObj.setHours(0, 0, 0, 0);

        if (dateObj.getTime() === today.getTime()) dateDiv.classList.add("today");

        if (selectedDates.includes(currentDateStr)) {
            dateDiv.classList.add("selected");
            if (dateDiv.classList.contains("selected") && selectedDates.length > 1) {
                dateDiv.classList.add("in-range");
            }
        }

        if (dateObj > today) {
            dateDiv.classList.add("disabled");
        } else {
            dateDiv.addEventListener("click", () => handleDateClick(year, month, day));
        }

        calendar.appendChild(dateDiv);
    }
}

function handleDateClick(year, month, day) {
    const clickedDate = formatDateISO(year, month, day);
    const clickedDateObj = new Date(year, month, day);

    if (selectedDates.length === 0) {
        selectedDates = [clickedDate];
        startDate = clickedDateObj;
        endDate = null;
    } else if (selectedDates.length === 1) {
        const firstDate = new Date(startDate);
        if (clickedDateObj < firstDate) {
            startDate = clickedDateObj;
            endDate = firstDate;
        } else {
            endDate = clickedDateObj;
        }

        const daysDiff = Math.round(Math.abs((startDate - endDate) / (1000 * 60 * 60 * 24))) + 1;
        if (daysDiff > MAX_DAYS) {
            // Instead of clearing, assume user wants to start a NEW selection
            selectedDates = [clickedDate];
            startDate = clickedDateObj;
            endDate = null;
        } else {
            selectedDates = [];
            let curr = new Date(startDate);
            while (curr <= endDate) {
                selectedDates.push(formatDateISO(curr.getFullYear(), curr.getMonth(), curr.getDate()));
                curr.setDate(curr.getDate() + 1);
            }
        }
    } else {
        selectedDates = [clickedDate];
        startDate = clickedDateObj;
        endDate = null;
    }

    updateDateInput();
    generateCalendar(year, month);
    loadDTRForSelectedRange();
}

function formatDateISO(year, month, day) {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

// ==========================================
// DTR Logic
// ==========================================
let isInitialDTRLoad = true;

function initDTR() {
    loadRecentDTR();

    // Export DTR Button
    const exportBtn = document.getElementById('exportDtrBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            const employeeIdEncoded = window.employeeIdEncoded;
            let url = `../attendancerep/indirep.php?id=${employeeIdEncoded}`;

            if (startDate && endDate) {
                const startStr = formatDateISO(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                const endStr = formatDateISO(endDate.getFullYear(), endDate.getMonth(), endDate.getDate());
                url += `&start_date=${startStr}&end_date=${endStr}`;
            }

            window.location.href = url;
        });
    }
}

function loadRecentDTR() {
    fetchDTR(`limit=15`);
}

function loadDTRForSelectedRange() {
    if (selectedDates.length === 0) {
        if (!isInitialDTRLoad) loadRecentDTR();
        return;
    }
    isInitialDTRLoad = false;
    const start = selectedDates[0];
    const end = selectedDates[selectedDates.length - 1];
    fetchDTR(`start_date=${start}&end_date=${end}`);
}

async function fetchDTR(queryString) {
    const listEl = document.getElementById('dtrList');
    const loadEl = document.getElementById('dtrLoading');

    if (listEl) listEl.style.display = 'none';
    if (loadEl) loadEl.style.display = 'block';

    try {
        const response = await fetch(`get_employee_attendance.php?employee_id=${window.employeeInternalId}&${queryString}&_=${Date.now()}`);
        const result = await response.json();

        if (loadEl) loadEl.style.display = 'none';
        if (listEl) listEl.style.display = 'block';

        if (result.success && result.count > 0) {
            renderDTRList(result.data);
        } else {
            if (listEl) listEl.innerHTML = '<p class="text-center text-muted p-3">No records found.</p>';
        }
    } catch (e) {
        console.error(e);
        if (loadEl) loadEl.style.display = 'none';
        if (listEl) {
            listEl.style.display = 'block';
            listEl.innerHTML = '<p class="text-center text-danger p-3">Error loading records.</p>';
        }
    }
}

function renderDTRList(records) {
    const listEl = document.getElementById('dtrList');
    if (!listEl) return;

    let html = '';
    records.forEach(record => {
        const statusClass = getStatusClass(record.status_info.badge_class);
        const iconInfo = record.status_info.icon;

        html += `
        <div class="dtr-item ${statusClass}">
            <div class="dtr-icon" style="background-color: ${getStatusColor(record.status_info.badge_class)}">
                <i class="bi ${iconInfo}"></i>
            </div>
            <div class="dtr-info">
                <div class="dtr-date">${record.formatted_date} <span class="dtr-status">${record.status_info.badge_text}</span></div>
                <div class="dtr-time">
                    <span>In: ${record.time_in_formatted || '--'}</span> • 
                    <span>Out: ${record.time_out_formatted || '--'}</span>
                    ${record.hours_worked !== 'N/A' ? ` • ${record.hours_worked} hrs` : ''}
                </div>
                ${window.isAdmin ? `
                <div class="mt-1 d-flex gap-2 align-items-center">
                    <button class="btn btn-sm btn-link p-0 text-primary" style="font-size: 0.8em;" onclick='openEditAttendanceModal(${JSON.stringify(record)})'>
                        <i class="bi bi-pencil-square"></i> Edit
                    </button>
                    ${(record.status && record.status.toLowerCase() === 'visit') ? `
                    <button class="btn btn-sm btn-link p-0 text-success" style="font-size: 0.8em;" onclick="confirmSetOffset(${record.id})">
                        <i class="bi bi-check-circle-fill"></i> Set as Offset
                    </button>` : ''}
                </div>` : ''}
            </div>
        </div>`;
    });
    listEl.innerHTML = html;
}

function getStatusClass(badgeClass) {
    if (badgeClass.includes('success')) return 'present';
    if (badgeClass.includes('danger')) return 'absent';
    if (badgeClass.includes('warning')) return 'late';
    return '';
}

function getStatusColor(badgeClass) {
    if (badgeClass.includes('success')) return '#48bb78';
    if (badgeClass.includes('danger')) return '#f56565';
    if (badgeClass.includes('warning')) return '#ed8936';
    if (badgeClass.includes('manual')) return '#a8d5ba';
    return '#a0aec0';
}


// ==========================================
// Visual Schedule
// ==========================================
function initScheduleDisplay() {
    const calendar = document.getElementById('visualScheduleCalendar');
    const mobileContainer = document.getElementById('mobileScheduleView');

    if (!window.schedulesData || window.schedulesData.length === 0) {
        if (calendar) calendar.innerHTML = '<p class="text-center text-muted p-4">No schedule assigned.</p>';
        if (mobileContainer) mobileContainer.innerHTML = '<p class="text-center text-muted p-4">No schedule assigned.</p>';
        return;
    }

    if (calendar) renderVisualSchedule(calendar, window.schedulesData);
    if (mobileContainer) renderMobileSchedule(mobileContainer, window.schedulesData);
}

function renderMobileSchedule(container, schedules) {
    const daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    let html = '';

    daysOrder.forEach(day => {
        // Find schedules for this day
        const dayScheds = schedules.filter(s => s.days.includes(day));

        if (dayScheds.length > 0) {
            // Sort by start time
            dayScheds.sort((a, b) => parseTimeStr(a.startTime) - parseTimeStr(b.startTime));

            html += `<div class="mobile-day-group mb-3">`;
            html += `<h6 class="mobile-day-header text-primary border-bottom pb-1 mb-2 fw-bold">${day}</h6>`;
            html += `<div class="d-flex flex-column gap-2">`;

            dayScheds.forEach(s => {
                html += `
                <div class="mobile-sched-card p-3 rounded" style="background-color: ${s.color}15; border-left: 4px solid ${s.color};">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold text-dark">${s.subject || 'Work'}</span>
                        <span class="badge bg-secondary">${s.class || 'N/A'}</span>
                    </div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-clock me-1"></i> ${s.startTime} - ${s.endTime}
                    </div>
                    ${s.room_num ? `<div class="small text-muted"><i class="bi bi-geo-alt me-1"></i> ${s.room_num}</div>` : ''}
                </div>`;
            });

            html += `</div></div>`;
        }
    });

    if (html === '') {
        html = '<p class="text-muted text-center py-3">No schedule found for this week.</p>';
    }

    container.innerHTML = html;
}

function renderVisualSchedule(container, schedules) {
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const timeStart = 7 * 60; // 7:00 AM in minutes
    // Maximum school schedule is 9:00 PM, show up to 9:30 PM so 9:00 PM label appears
    const timeEnd = 21 * 60 + 30; // 9:30 PM
    const interval = 30; // minutes
    const totalSlots = (timeEnd - timeStart) / interval;

    let gridHtml = '';
    gridHtml += `<div class="schedule-header-cell">Time</div>`;
    days.forEach(day => {
        gridHtml += `<div class="schedule-header-cell">${day}</div>`;
    });

    for (let i = 0; i < totalSlots; i++) {
        const currentMinutes = timeStart + (i * interval);
        const timeLabel = formatMinutesToTime(currentMinutes);
        gridHtml += `<div class="time-slot-label">${timeLabel}</div>`;
        days.forEach(day => {
            gridHtml += `<div class="schedule-grid-cell" data-day="${day}" data-time="${currentMinutes}"></div>`;
        });
    }

    container.innerHTML = gridHtml;
    container.style.display = 'grid';
    container.style.gridTemplateColumns = '85px repeat(7, 1fr)';

    schedules.forEach(sched => {
        placeScheduleBlock(container, sched, days, timeStart, interval);
    });

    // Calculate Total Weekly Hours
    let totalWeeklyMinutes = 0;
    schedules.forEach(sched => {
        const start = parseTimeStr(sched.startTime);
        const end = parseTimeStr(sched.endTime);
        let duration = end - start;

        // Apply configurable break deduction for Admin/Non-Teaching if worked > 5 hours
        if (window.employeeRole && window.breakDeductionMinutes > 0) {
            const role = window.employeeRole.toLowerCase();
            if ((role.includes('admin') || role.includes('non-teaching') || role.includes('non_teaching')) && duration >= 300) {
                duration = Math.max(0, duration - window.breakDeductionMinutes);
            }
        }

        // Check if days is array, just to be safe though expected
        if (Array.isArray(sched.days)) {
            totalWeeklyMinutes += duration * sched.days.length;
        }
    });
    const totalWeeklyHours = +(totalWeeklyMinutes / 60).toFixed(2);

    // Append Total Row (spans all columns)
    const totalRow = document.createElement('div');
    totalRow.style.gridColumn = '1 / -1';
    totalRow.style.padding = '15px';
    totalRow.style.textAlign = 'center';
    totalRow.style.fontWeight = 'bold';
    totalRow.style.borderTop = '1px solid #e2e8f0';
    totalRow.style.marginTop = '10px';
    totalRow.style.color = '#2d3748';
    totalRow.innerHTML = `Total Weekly Hours: <span class="text-primary" style="font-size: 1.1em;">${totalWeeklyHours} hrs</span>`;
    container.appendChild(totalRow);
}

function placeScheduleBlock(container, schedule, days, gridStartMinutes, interval) {
    const startMins = parseTimeStr(schedule.startTime);
    const endMins = parseTimeStr(schedule.endTime);
    const duration = endMins - startMins;

    if (startMins < gridStartMinutes) return;

    schedule.days.forEach(day => {
        const slotIndex = Math.floor((startMins - gridStartMinutes) / interval);
        const cell = container.querySelector(`.schedule-grid-cell[data-day="${day}"][data-time="${gridStartMinutes + (slotIndex * interval)}"]`);

        if (cell) {
            const block = document.createElement('div');
            block.className = 'schedule-block';
            block.style.backgroundColor = schedule.color || '#4299e1';

            // Height calculation: (duration / 30) * 32px (height of cell)
            const height = (duration / interval) * 32; // 32px is cell height in CSS
            block.style.height = `${height - 2}px`; // minus margins

            let content = '';
            if (schedule.class && schedule.class !== 'N/A') {
                content = `<strong>${schedule.subject}</strong><br>${schedule.class}<br><span class="schedule-time">${formatMinutesToTime(startMins)} - ${formatMinutesToTime(endMins)}</span>`;
            } else {
                content = `<span class="schedule-time">${formatMinutesToTime(startMins)} - ${formatMinutesToTime(endMins)}</span><br>Work`;
            }

            block.innerHTML = content;
            block.title = `${schedule.subject || 'Work'} (${formatMinutesToTime(startMins)} - ${formatMinutesToTime(endMins)})`;

            cell.appendChild(block);
        }
    });
}

function parseTimeStr(timeStr) {
    const [h, m] = timeStr.split(':').map(Number);
    return h * 60 + m;
}

function formatMinutesToTime(minutes) {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${m.toString().padStart(2, '0')} ${ampm}`;
}

// Global function to update schedule based on leaves
window.updateVisualScheduleWithLeaves = function (leaves) {
    if (!window.schedulesData || !leaves) return;

    // Clone original data to avoid permanent mutation on re-renders
    // distinct from window.originalSchedulesData if we wanted to revert, but 
    // for now we'll just rebuild from window.schedulesData (assuming it's the source)
    // Actually, purely visual manipulation might be safer? 
    // Let's modify the DOM directly or re-render with a merged list.

    // Better: Helper to checks if a specific day name has an ACTIVE leave
    // Problem: Leaves are by Date (YYYY-MM-DD), Schedule is by Day Name (Monday).
    // Strategy: Look for leaves in the CURRENT week or upcoming? 
    // To be simple and immediate for the user who just added a leave:
    // We will simple check if there are ANY pending/approved leaves for the days of the week.
    // If a user has a leave on ANY Monday, we highlight Monday? 
    // Or closer: Check leaves overlapping "Today" or specific range?
    // User expectation: "I added a leave for Feb 2. Show it."

    // We will map leaves to Day Names.
    const leavesMap = {}; // { 'Monday': { type: 'Sick', status: 'pending', date: '...' } }

    leaves.forEach(leave => {
        // Allow all statuses to be visualized per user request
        // if (leave.status === 'rejected' || leave.status === 'cancelled') return;

        // Convert start_date to Day Name
        // We handle single day leaves (start=end) or ranges. 
        // For simplicity v1: Handle start_date 's day.

        try {
            // Manual parse to avoid timezone issues (YYYY-MM-DD to local midnight)
            const parts = leave.start_date.split('-');
            const year = parseInt(parts[0]);
            const month = parseInt(parts[1]) - 1;
            const day = parseInt(parts[2]);
            const dateObj = new Date(year, month, day);

            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const dayName = days[dateObj.getDay()];

            console.log(`Processing Leave: ${leave.start_date} (${dayName}) - ${leave.status}`);

            // Determine Color
            let color = '#ed8936'; // Pending (Orange)
            if (leave.status === 'approved') color = '#48bb78'; // Green
            if (leave.status === 'rejected') color = '#f56565'; // Red
            if (leave.status === 'cancelled') color = '#cbd5e0'; // Gray

            // Prioritize: Approved > Pending > Rejected? 
            // If multiple on same day, we might overwrite. 
            // Logic: If map has something, only overwrite if current is 'more important'?
            // Simple logic: Overwrite if current is NOT rejected, or if map is empty.
            // Actually, let's just show the latest one or prioritize Approved.
            // For now, simple overwrite or keep existing logic.

            if (!leavesMap[dayName] || (leavesMap[dayName].status !== 'approved' && leave.status === 'approved')) {
                leavesMap[dayName] = {
                    subject: leave.leave_type + ' Leave',
                    class: leave.status.charAt(0).toUpperCase() + leave.status.slice(1), // Pending/Approved
                    color: color,
                    status: leave.status,
                    startTime: '08:00', // Default visual time
                    endTime: '17:00'
                };
            }
        } catch (e) { console.error('Error parsing leave date:', e); }
    });

    console.log('Leaves Map:', leavesMap);

    // Re-render with overrides
    const calendar = document.getElementById('visualScheduleCalendar');
    // const mobileContainer = document.getElementById('mobileScheduleView');

    // 1. Re-render base schedule first to clear previous leave overlays
    if (calendar && window.schedulesData && window.schedulesData.length > 0) {
        renderVisualSchedule(calendar, window.schedulesData);
    }

    // 2. Overlay leaves
    Object.keys(leavesMap).forEach(day => {
        const leave = leavesMap[day]; // { subject, class (Status), color }
        console.log(`Overlaying ${day}:`, leave);

        // Find existing schedule blocks for this day and HIDE/MODIFY them or Overlay
        const cells = document.querySelectorAll(`.schedule-grid-cell[data-day="${day}"]`);

        // Remove existing blocks in this day column to avoid overlap mess
        cells.forEach(cell => {
            cell.innerHTML = ''; // Clear work blocks
        });

        const startMins = 8 * 60; // 480
        const endMins = 17 * 60; // 1020
        const interval = 30;

        if (calendar) {
            // NOTE: data-time must match exactly what renderVisualSchedule generates (multiples of 30 starting from 420)
            const startCell = calendar.querySelector(`.schedule-grid-cell[data-day="${day}"][data-time="${startMins}"]`);
            if (startCell) {
                const block = document.createElement('div');
                block.className = 'schedule-block leave-block';
                block.style.backgroundColor = leave.color;
                // FIXED: Start with simple border, removed invalid 'darken' SCSS function
                block.style.borderLeft = '4px solid rgba(0,0,0,0.2)';

                const height = ((endMins - startMins) / interval) * 32;
                block.style.height = `${height - 2}px`;

                block.innerHTML = `
                    <div class="text-white small fw-bold">${leave.subject}</div>
                    <div class="text-white small" style="opacity:0.9;">${leave.class}</div>
                `;
                block.title = `${leave.subject} (${leave.class})`;

                startCell.appendChild(block);
            } else {
                console.warn(`Could not find start cell for ${day} at ${startMins}`);
            }
        }
    });

};

// ==========================================
// Leave Management System (API-based)
// ==========================================
function initLeaveManagement() {
    const addLeaveModal = document.getElementById('addLeaveModal');
    if (!addLeaveModal) return;

    // Load initial list
    loadEmployeeLeaves();

    // Check limit on modal open
    addLeaveModal.addEventListener('show.bs.modal', () => {
        checkMonthlyLimit();
        fetchLeaveSettings();
    });

    // Submit button
    // REMOVED: Conflicting event listener. We use onclick="confirmLeave()" in HTML.
    // const btnSubmit = document.getElementById('btnSubmitLeave');
    // if (btnSubmit) {
    //    btnSubmit.removeEventListener('click', submitLeaveRequest); // Ensure no doubles if re-init
    // }

    // Confirmation Action
    const btnConfirm = document.getElementById('btnConfirmAction');
    if (btnConfirm) btnConfirm.addEventListener('click', executeLeaveAction);
}

// pendingLeaveAction and showConfirmModal moved to global scope at bottom of file

function showSuccessModal(msg) {
    document.getElementById('leaveSuccessMsg').textContent = msg;
    new bootstrap.Modal(document.getElementById('leaveSuccessModal')).show();
}

function showErrorModal(msg) {
    document.getElementById('leaveErrorMsg').textContent = msg;
    new bootstrap.Modal(document.getElementById('leaveErrorModal')).show();
}

function checkMonthlyLimit() {
    const limitText = document.getElementById('monthlyLimitText');
    const limitInfo = document.getElementById('monthlyLimitInfo');

    if (!limitText) return;

    limitText.innerHTML = 'Checking...';

    // Use the global variable if available, otherwise fallback
    const empId = window.employeeInternalId || window.employeeIdForLeave;

    fetch(`api/leave_request_clean.php?action=get_employee_requests&employee_id=${empId}`)
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                const currentMonth = new Date().toISOString().slice(0, 7); // YYYY-MM

                // Check for pending requests
                const pendingRequests = response.data.filter(leave => leave.status === 'pending');

                // Count approved requests in current month
                const approvedThisMonth = response.data.filter(leave => {
                    const leaveMonth = leave.start_date.slice(0, 7);
                    return leave.status === 'approved' && leaveMonth === currentMonth;
                });

                if (limitInfo) {
                    // Priority check: Pending requests block new submissions
                    if (pendingRequests.length > 0) {
                        limitText.innerHTML = '<strong>⏳ You have a pending leave request.</strong><br>Please wait for approval.';
                        limitInfo.className = 'alert alert-warning mb-3';
                    }
                    // Check approved monthly limit
                    else if (approvedThisMonth.length >= 2) {
                        limitText.innerHTML = '<strong>❌ Monthly limit reached.</strong><br>2/2 approved used.';
                        limitInfo.className = 'alert alert-danger mb-3';
                    }
                    // Show remaining available requests
                    else {
                        const remaining = 2 - approvedThisMonth.length;
                        limitText.innerHTML = `<strong>✅ ${remaining} of 2 available</strong>`;
                        limitInfo.className = 'alert alert-success mb-3';
                    }
                }
            } else {
                if (limitText) limitText.textContent = 'Error checking.';
            }
        })
        .catch(error => {
            console.error('Error checking limit:', error);
            if (limitText) limitText.textContent = 'Network error.';
        });
}

function loadEmployeeLeaves() {
    const list = document.getElementById('leaveList');
    if (!list) return;

    fetch(`api/leave_request_clean.php?action=get_employee_requests&employee_id=${window.employeeInternalId}`)
        .then(res => res.json())
        .then(response => {
            if (!response.success) return;

            if (response.count === 0) {
                list.innerHTML = '<div class="text-muted w-100 text-center py-2">No active leave requests.</div>';
                return;
            }

            let html = '';
            response.data.forEach(leave => {
                let badge = '';
                if (leave.status === 'pending') badge = '<span class="badge bg-warning text-dark">Pending</span>';
                else if (leave.status === 'approved') badge = '<span class="badge bg-success">Approved</span>';
                else if (leave.status === 'rejected') badge = '<span class="badge bg-danger">Rejected</span>';

                html += `
                <div class="card p-2 border shadow-sm" style="min-width: 200px; flex: 1 1 200px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${leave.leave_type}</strong> ${badge}
                            <div class="small text-muted">${leave.formatted_dates}</div>
                        </div>
                        <button class="btn btn-sm btn-link text-primary" onclick='openLeaveDetails(${JSON.stringify(leave)})'>
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>`;
            });
            list.innerHTML = html;

            // DISABLED: Don't overlay leaves on work schedule
            // Leaves are shown in the "Scheduled Leave" section instead
            // if (typeof window.updateVisualScheduleWithLeaves === 'function') {
            //     window.updateVisualScheduleWithLeaves(response.data);
            // }
        });
}

// Make available globally for inline onclicks
window.openLeaveDetails = function (leave) {
    document.getElementById('viewLeaveType').textContent = leave.leave_type;
    document.getElementById('viewLeaveStatus').textContent = leave.status;
    document.getElementById('viewLeaveDates').textContent = leave.formatted_dates;
    document.getElementById('viewLeaveReason').textContent = leave.reason || 'No reason';

    // Attachment
    const attContainer = document.getElementById('viewLeaveAttachmentContainer');
    if (leave.attachment) {
        attContainer.style.display = 'block';
        document.getElementById('viewLeaveAttachment').href = '../' + leave.attachment;
    } else {
        attContainer.style.display = 'none';
    }

    // Buttons
    const actionContainer = document.getElementById('viewLeaveActions');
    let btns = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';

    if (window.isAdmin && leave.status === 'pending') {
        btns += `<button class="btn btn-success ms-2" onclick="showConfirmModal('Approve Leave', 'Are you sure?', 'approve', ${leave.id})">Approve</button>`;
        btns += `<button class="btn btn-danger ms-2" onclick="showConfirmModal('Reject Leave', 'Are you sure?', 'reject', ${leave.id})">Reject</button>`;
    } else if (!window.isAdmin && leave.status === 'pending') {
        btns += `<button class="btn btn-warning ms-2" onclick="showConfirmModal('Cancel Request', 'Cancel this pending request?', 'cancel', ${leave.id})">Cancel</button>`;
    }

    actionContainer.innerHTML = btns;
    new bootstrap.Modal(document.getElementById('leaveDetailsViewModal')).show();
};

// Confirm Leave - Shows confirmation modal
window.leaveNoticeDays = 0; // Default

function fetchLeaveSettings() {
    fetch('api/settings_api.php?action=get_leave_settings')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.leaveNoticeDays = parseInt(data.notice_period_days) || 0;
                // Optional: Update UI to show requirement
                const msgEl = document.getElementById('leaveNoticeMsg');
                if (window.leaveNoticeDays > 0 && msgEl) {
                    msgEl.textContent = `Note: Requests must be made at least ${window.leaveNoticeDays} days in advance.`;
                    msgEl.style.display = 'block';
                } else if (msgEl) {
                    msgEl.style.display = 'none';
                }
            }
        })
        .catch(err => console.log('Settings fetch error', err));
}
function confirmLeave() {
    const leaveType = document.getElementById("leaveType").value;
    const leaveFrom = document.getElementById("leaveFrom").value;
    const leaveTo = document.getElementById("leaveTo").value;
    const leaveReason = document.getElementById("leaveReason").value;

    if (!leaveType || !leaveFrom || !leaveTo) {
        document.getElementById("leaveValidationErrorMsg").textContent = "Please fill out all required fields.";
        new bootstrap.Modal(document.getElementById("leaveValidationErrorModal")).show();
        return;
    }

    // Validate dates - No past dates allowed
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Reset time to midnight for accurate comparison
    const startDate = new Date(leaveFrom);
    // Fix timezone offset issue where date string parsing might result in previous day depending on local time
    // Adding time component ensures it parses correctly as local date
    const startDateCheck = new Date(leaveFrom + 'T00:00:00');

    if (startDateCheck <= today) {
        document.getElementById("leaveValidationErrorMsg").textContent = "You cannot select a past date or the current date. Please choose a future date.";
        new bootstrap.Modal(document.getElementById("leaveValidationErrorModal")).show();
        return;
    }

    // Notice Period Check
    if (window.leaveNoticeDays > 0) {
        const minDate = new Date();
        minDate.setDate(today.getDate() + window.leaveNoticeDays);
        minDate.setHours(0, 0, 0, 0);

        if (startDateCheck < minDate) {
            document.getElementById("leaveValidationErrorMsg").textContent = `Requests must be made at least ${window.leaveNoticeDays} days in advance. Earliest available date: ${minDate.toLocaleDateString()}`;
            new bootstrap.Modal(document.getElementById("leaveValidationErrorModal")).show();
            return;
        }
    }

    // Notice Period Check
    if (window.leaveNoticeDays > 0) {
        const minDate = new Date();
        minDate.setDate(today.getDate() + window.leaveNoticeDays);
        minDate.setHours(0, 0, 0, 0);

        if (startDateCheck < minDate) {
            document.getElementById("leaveValidationErrorMsg").textContent = `Requests must be made at least ${window.leaveNoticeDays} days in advance. Earliest available date: ${minDate.toLocaleDateString()}`;
            new bootstrap.Modal(document.getElementById("leaveValidationErrorModal")).show();
            return;
        }
    }

    if (new Date(leaveTo) < new Date(leaveFrom)) {
        document.getElementById("leaveValidationErrorMsg").textContent = "End date cannot be before start date.";
        new bootstrap.Modal(document.getElementById("leaveValidationErrorModal")).show();
        return;
    }

    // Close Add Leave modal
    const addModalEl = document.getElementById('addLeaveModal');
    const addModal = bootstrap.Modal.getInstance(addModalEl);
    if (addModal) addModal.hide();

    // Show confirmation modal with leave details
    const detailsText = `${leaveType} Leave, from ${formatDate(leaveFrom)} to ${formatDate(leaveTo)}`;
    document.getElementById("leaveDetailsText").innerText = detailsText;

    setTimeout(() => {
        const detailsModal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
        detailsModal.show();
    }, 150);
}

function finalizeLeave() {
    const leaveType = document.getElementById("leaveType").value;
    const leaveFrom = document.getElementById("leaveFrom").value;
    const leaveTo = document.getElementById("leaveTo").value;
    const leaveReason = document.getElementById("leaveReason").value;
    // const autoApprove = isAdmin && document.getElementById("autoApprove").checked; 
    // ^ In new_profile.js isAdmin is window.isAdmin boolean
    const autoApproveEl = document.getElementById("autoApprove");
    const autoApprove = window.isAdmin && autoApproveEl && autoApproveEl.checked;

    // Hide confirmation modal
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsModal'));
    if (detailsModal) detailsModal.hide();

    const formData = new FormData();
    formData.append('action', 'submit_request');
    formData.append('employee_id', window.employeeInternalId);
    formData.append('leave_type', leaveType);
    formData.append('start_date', leaveFrom);
    formData.append('end_date', leaveTo);
    formData.append('reason', leaveReason);
    formData.append('is_admin', window.isAdmin ? '1' : '0');
    formData.append('auto_approve', autoApprove ? '1' : '0');

    const fileInput = document.getElementById('leaveAttachment');
    if (fileInput && fileInput.files.length > 0) {
        formData.append('attachment', fileInput.files[0]);
    }

    setTimeout(() => {
        fetch('api/leave_request_clean.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById("leaveSuccessMsg").textContent = data.message || "Request submitted!";
                    const successModal = new bootstrap.Modal(document.getElementById('leaveSuccessModal'));
                    successModal.show();
                    loadEmployeeLeaves();
                    checkMonthlyLimit(); // Refresh limit status
                } else {
                    document.getElementById("leaveValidationErrorMsg").textContent = data.error || "Failed.";
                    new bootstrap.Modal(document.getElementById("leaveValidationErrorModal")).show();

                    // Re-open form? Or just stay closed?
                    // Logic from staffinfo suggests re-opening if known error, but here we just show error.
                }
            })
            .catch(err => {
                document.getElementById("leaveValidationErrorMsg").textContent = "Network error.";
                new bootstrap.Modal(document.getElementById("leaveValidationErrorModal")).show();
            });
    }, 150);
}

function goBackToForm() {
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsModal'));
    if (detailsModal) detailsModal.hide();

    setTimeout(() => {
        const addModal = new bootstrap.Modal(document.getElementById('addLeaveModal'));
        addModal.show();
    }, 150);
}

function cancelLeaveRequest() {
    // Just close the modal or reset form?
    // HTML has data-bs-dismiss="modal" so it closes automatically.
    // We just need to reset form.
    document.getElementById('leaveType').value = '';
    document.getElementById('leaveFrom').value = '';
    document.getElementById('leaveTo').value = '';
    document.getElementById('leaveReason').value = '';
    document.getElementById('leaveAttachment').value = '';
    const limitText = document.getElementById('monthlyLimitText');
    if (limitText) limitText.innerHTML = 'Checking...';
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

function executeLeaveAction() {
    if (!pendingLeaveAction) return;

    const action = pendingLeaveAction.type; // approve, reject, cancel
    let apiAction = '';

    if (action === 'approve') apiAction = 'approve_request';
    if (action === 'reject') apiAction = 'reject_request';
    if (action === 'cancel') apiAction = 'cancel_request';

    const formData = new FormData();
    formData.append('leave_id', pendingLeaveAction.id);
    if (action === 'approve') formData.append('approved_by', 'admin');

    fetch(`api/leave_request_clean.php?action=${apiAction}`, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            bootstrap.Modal.getInstance(document.getElementById('leaveConfirmModal')).hide();
            bootstrap.Modal.getInstance(document.getElementById('leaveDetailsViewModal')).hide();

            if (data.success) {
                showSuccessModal(data.message || 'Action completed.');
                loadEmployeeLeaves();
            } else {
                showErrorModal(data.error || 'Failed.');
            }
        });
}

// Make pendingLeaveAction global
let pendingLeaveAction = null;

function showConfirmModal(title, message, type, id) {
    pendingLeaveAction = { id: id, type: type };

    // Update modal content
    const modalTitle = document.querySelector('#leaveConfirmModal .modal-title') || document.querySelector('#leaveConfirmModal h5');
    const modalBody = document.querySelector('#leaveConfirmModal .modal-body p') || document.querySelector('#leaveConfirmModal .modal-body');

    if (modalTitle) modalTitle.textContent = title;
    if (modalBody) {
        // If modalBody is the p tag use it, otherwise set innerHTML of body
        if (modalBody.tagName === 'P') modalBody.textContent = message;
        else modalBody.innerHTML = `<p>${message}</p>`;
    }

    // Show modal
    new bootstrap.Modal(document.getElementById('leaveConfirmModal')).show();
}

// ==========================================
// Manual Attendance Logic
// ==========================================
function initManualAttendance() {
    const attendanceModalEl = document.getElementById('attendanceModal');
    const addDayBtn = document.getElementById('addDayBtn');
    const attendanceContainer = document.getElementById('attendanceContainer');
    const saveBtn = document.getElementById('saveBtn');

    if (!attendanceModalEl || !addDayBtn || !attendanceContainer || !saveBtn) {
        // Elements might not exist if user is not admin
        return;
    }

    const attendanceModal = new bootstrap.Modal(attendanceModalEl);

    // Function to fetch and populate schedule times for a date
    async function populateScheduleTimes(dateInput, timeInInput, timeOutInput) {
        const selectedDate = dateInput.value;

        // Find the error message element for this row
        const row = dateInput.closest('.attendance-row');
        const errorMsg = row ? row.querySelector('.schedule-error') : null;

        if (!selectedDate) {
            if (errorMsg) {
                errorMsg.style.display = 'none';
                errorMsg.textContent = '';
            }
            return;
        }

        try {
            // Use window.employeeInternalId instead of PHP tag
            const employeeId = window.employeeInternalId;

            // First, check if attendance record already exists for this date
            const existsResponse = await fetch('api/check_attendance_exists.php?employee_id=' + employeeId + '&date=' + selectedDate);
            const existsResult = await existsResponse.json();

            if (existsResult.success && existsResult.exists) {
                timeInInput.value = '';
                timeOutInput.value = '';
                timeInInput.classList.remove('bg-light');
                timeOutInput.classList.remove('bg-light');

                if (errorMsg) {
                    const dateObj = new Date(selectedDate);
                    const formattedDate = dateObj.toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });

                    errorMsg.textContent = 'Attendance record already exists for ' + formattedDate;
                    errorMsg.style.display = 'block';
                }
                return;
            }

            // If no existing record, proceed to fetch schedule
            const response = await fetch('api/get_employee_schedule.php?employee_id=' + employeeId + '&date=' + selectedDate);
            const result = await response.json();

            if (result.success && result.has_schedule) {
                timeInInput.value = result.schedule.start_time;
                timeOutInput.value = result.schedule.end_time;
                timeInInput.classList.add('bg-light');
                timeOutInput.classList.add('bg-light');

                if (errorMsg) {
                    errorMsg.style.display = 'none';
                    errorMsg.textContent = '';
                }
            } else {
                timeInInput.value = '';
                timeOutInput.value = '';
                timeInInput.classList.remove('bg-light');
                timeOutInput.classList.remove('bg-light');

                if (selectedDate && errorMsg) {
                    const dateObj = new Date(selectedDate);
                    const formattedDate = dateObj.toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });

                    errorMsg.textContent = 'No schedule assigned for ' + formattedDate;
                    errorMsg.style.display = 'block';
                }
            }
        } catch (error) {
            console.error('Error fetching schedule:', error);
            if (errorMsg) {
                errorMsg.textContent = 'Error checking schedule';
                errorMsg.style.display = 'block';
            }
        }
    }

    // Function to attach date change listener to a row
    function attachDateListener(row) {
        const dateInput = row.querySelector('input[type=\'date\']');
        const timeInputs = row.querySelectorAll('input[type=\'time\']');
        if (!timeInputs || timeInputs.length < 2) return;

        const timeInInput = timeInputs[0];
        const timeOutInput = timeInputs[1];

        if (dateInput && timeInInput && timeOutInput) {
            const oldVal = dateInput.value;
            const newDateInput = dateInput.cloneNode(true);
            newDateInput.value = oldVal;
            dateInput.parentNode.replaceChild(newDateInput, dateInput);

            newDateInput.addEventListener('change', () => {
                populateScheduleTimes(newDateInput, timeInInput, timeOutInput);
            });

            newDateInput.addEventListener('input', () => {
                populateScheduleTimes(newDateInput, timeInInput, timeOutInput);
            });
        }
    }

    // Initialize when modal opens
    attendanceModalEl.addEventListener('shown.bs.modal', () => {
        // Setup listener for initial row when modal opens
        const initialRow = attendanceContainer.querySelector('.attendance-row');
        if (initialRow) {
            attachDateListener(initialRow);
        }
    });

    // Add another day
    addDayBtn.addEventListener('click', () => {
        const newRow = document.createElement('div');
        newRow.classList.add('attendance-row', 'row', 'mb-3', 'align-items-start');
        newRow.innerHTML = '<div class=\'col-md-3\'>' +
            '<label>Date:</label>' +
            '<input type=\'date\' class=\'form-control\'>' +
            '<div class=\'schedule-error-container\' style=\'min-height: 0;\'>' +
            '<small class=\'text-danger schedule-error d-block\' style=\'display:none; font-size: 0.75rem; margin-top: 4px; line-height: 1.2;\'></small>' +
            '</div>' +
            '</div>' +
            '<div class=\'col-md-3\'>' +
            '<label>Time In:</label>' +
            '<input type=\'time\' class=\'form-control\'>' +
            '</div>' +
            '<div class=\'col-md-3\'>' +
            '<label>Time Out:</label>' +
            '<input type=\'time\' class=\'form-control\'>' +
            '</div>' +
            '<div class=\'col-md-3\'>' +
            '<div style=\'margin-top: 32px;\'>' +
            '<button class=\'btn btn-warning btn-sm me-1 clearRow\' title=\'Clear Times\'><i class=\'bi bi-eraser\'></i></button>' +
            '<button class=\'btn btn-danger btn-sm removeRow\'><i class=\'bi bi-dash-lg\'></i></button>' +
            '</div>' +
            '</div>';
        attendanceContainer.appendChild(newRow);

        attachDateListener(newRow);
    });

    // Handle Row Actions (Remove / Clear)
    attendanceContainer.addEventListener('click', (e) => {
        // Remove
        if (e.target.closest('.removeRow')) {
            e.target.closest('.attendance-row').remove();
        }
        // Clear
        if (e.target.closest('.clearRow')) {
            const row = e.target.closest('.attendance-row');
            const inputs = row.querySelectorAll('input[type=\'time\']');
            inputs.forEach(input => {
                input.value = '';
                input.classList.remove('bg-light');
            });
        }
    });

    // Save attendance records
    saveBtn.addEventListener('click', async () => {
        const rows = attendanceContainer.querySelectorAll('.attendance-row');
        const records = [];
        let hasError = false;
        let validationMessage = '';

        const employeeId = window.employeeInternalId;

        rows.forEach((row, index) => {
            const inputs = row.querySelectorAll('input');
            const date = inputs[0].value;
            const timeIn = inputs[1].value;
            const timeOut = inputs[2].value;

            if (!date || !timeIn) {
                if (!hasError) validationMessage = 'Date and Time In are required in row ' + (index + 1);
                hasError = true;
                return;
            }

            records.push({
                date: date,
                time_in: timeIn,
                time_out: timeOut
            });
        });

        if (hasError || records.length === 0) {
            const errEl = document.getElementById('attendanceErrorMessage');
            if (errEl) errEl.textContent = validationMessage || 'Please fill out all records before saving.';
            const errModalEl = document.getElementById('attendanceErrorModal');
            if (errModalEl) {
                const em = new bootstrap.Modal(errModalEl);
                em.show();
                // Auto-hide after 5s
                setTimeout(() => {
                    try { em.hide(); } catch (e) { }
                }, 5000);
            }
            return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const response = await fetch('api/add_manual_attendance.php?action=add_manual', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    employee_id: employeeId,
                    records: records
                })
            });

            const result = await response.json();

            if (result.success) {
                let message = result.message || 'Records saved successfully.';
                if (result.warnings && result.warnings.length > 0) {
                    message += ' Warnings: ' + result.warnings.join('; ');
                }
                const successEl = document.getElementById('attendanceSuccessMessage');
                if (successEl) successEl.textContent = message;

                try { attendanceModal.hide(); } catch (e) { }

                const successModalEl = document.getElementById('attendanceSuccessModal');
                if (successModalEl) {
                    const sm = new bootstrap.Modal(successModalEl);
                    sm.show();
                    successModalEl.addEventListener('hidden.bs.modal', function () {
                        window.location.reload();
                    }, { once: true });
                }

            } else {
                let errMsg = result.error || 'Unknown error occurred';
                if (result.warnings && result.warnings.length > 0) {
                    errMsg = result.warnings.join('\n');
                }
                const errEl = document.getElementById('attendanceErrorMessage');
                if (errEl) errEl.textContent = errMsg;
                const errModalEl = document.getElementById('attendanceErrorModal');
                if (errModalEl) {
                    const em = new bootstrap.Modal(errModalEl);
                    em.show();
                }
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Records';
            }
        } catch (error) {
            console.error('Error saving attendance:', error);
            const errEl = document.getElementById('attendanceErrorMessage');
            if (errEl) errEl.textContent = 'Failed: ' + error.message;
            const errModalEl = document.getElementById('attendanceErrorModal');
            if (errModalEl) new bootstrap.Modal(errModalEl).show();

            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Records';
        }
    });
}



// ==========================================
// Edit Attendance Logic
// ==========================================
window.openEditAttendanceModal = function (record) {
    const modalEl = document.getElementById('editAttendanceModal');
    if (!modalEl) return;

    // Populate Fields
    document.getElementById('editAttDate').value = record.formatted_date;
    document.getElementById('editAttDateValue').value = record.attendance_date; // YYYY-MM-DD
    document.getElementById('editAttTimeIn').value = record.time_in || '';
    document.getElementById('editAttTimeOut').value = record.time_out || '';
    document.getElementById('editAttError').classList.add('d-none');

    // Show Modal
    new bootstrap.Modal(modalEl).show();
};

document.addEventListener('DOMContentLoaded', () => {
    const btnSaveEdit = document.getElementById('btnSaveEditAttendance');
    if (btnSaveEdit) {
        btnSaveEdit.addEventListener('click', async () => {
            const date = document.getElementById('editAttDateValue').value;
            const timeIn = document.getElementById('editAttTimeIn').value;
            const timeOut = document.getElementById('editAttTimeOut').value;
            const errorEl = document.getElementById('editAttError');

            if (!timeIn) {
                errorEl.textContent = 'Time In is required.';
                errorEl.classList.remove('d-none');
                return;
            }

            // Simple validation
            if (timeOut && timeIn && timeOut <= timeIn) {
                // Allow overnight shifts? API supports it, but let's warn or simpler validation first.
                // For now, let API handle complex validation, but basic check here:
                // Actually, API handles it.
            }

            btnSaveEdit.disabled = true;
            btnSaveEdit.textContent = 'Saving...';

            try {
                // Reuse existing add_manual_attendance.php logic which handles UPDATE if record exists
                const employeeId = window.employeeInternalId;

                const payload = {
                    employee_id: employeeId,
                    records: [{
                        date: date,
                        time_in: timeIn,
                        time_out: timeOut
                    }]
                };

                const response = await fetch('api/add_manual_attendance.php?action=add_manual', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (result.success) {
                    // Success
                    const modalEl = document.getElementById('editAttendanceModal');
                    bootstrap.Modal.getInstance(modalEl).hide();

                    // Show success feedback
                    const successModalEl = document.getElementById('attendanceSuccessModal');
                    if (successModalEl) {
                        document.getElementById('attendanceSuccessMessage').textContent = 'Attendance updated successfully.';
                        new bootstrap.Modal(successModalEl).show();
                    }

                    // Reload DTR list
                    loadRecentDTR(); // Or loadDTRForSelectedRange() if active

                    // Reset button
                    btnSaveEdit.disabled = false;
                    btnSaveEdit.textContent = 'Save Changes';

                } else {
                    throw new Error(result.error || (result.warnings ? result.warnings.join(', ') : 'Unknown error'));
                }

            } catch (error) {
                console.error('Error updating attendance:', error);
                errorEl.textContent = error.message;
                errorEl.classList.remove('d-none');
                btnSaveEdit.disabled = false;
                btnSaveEdit.textContent = 'Save Changes';
            }
        });
    }
});

// Initialize Manual Attendance
document.addEventListener('DOMContentLoaded', () => {
    initManualAttendance();
});

