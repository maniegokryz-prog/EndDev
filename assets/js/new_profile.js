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
        // Toggle Popup
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
    const timeEnd = 23 * 60 + 30; // 11:30 PM
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

// ==========================================
// Leave Management System (API-based)
// ==========================================
function initLeaveManagement() {
    const addLeaveModal = document.getElementById('addLeaveModal');
    if (!addLeaveModal) return;

    // Load initial list
    loadEmployeeLeaves();

    // Check limit on modal open
    addLeaveModal.addEventListener('show.bs.modal', checkMonthlyLimit);

    // Submit button
    const btnSubmit = document.getElementById('btnSubmitLeave');
    if (btnSubmit) {
        btnSubmit.addEventListener('click', submitLeaveRequest);
    }

    // Confirmation Action
    const btnConfirm = document.getElementById('btnConfirmAction');
    if (btnConfirm) btnConfirm.addEventListener('click', executeLeaveAction);
}

let pendingLeaveAction = null; // { type: 'approve'|'reject'|'cancel', id: 123 }

function showConfirmModal(title, message, actionType, id) {
    document.getElementById('leaveConfirmTitle').textContent = title;
    document.getElementById('leaveConfirmMsg').textContent = message;
    pendingLeaveAction = { type: actionType, id: id };
    new bootstrap.Modal(document.getElementById('leaveConfirmModal')).show();
}

function showSuccessModal(msg) {
    document.getElementById('leaveSuccessMsg').textContent = msg;
    new bootstrap.Modal(document.getElementById('leaveSuccessModal')).show();
}

function showErrorModal(msg) {
    document.getElementById('leaveErrorMsg').textContent = msg;
    new bootstrap.Modal(document.getElementById('leaveErrorModal')).show();
}

function checkMonthlyLimit() {
    const limitInfo = document.getElementById('monthlyLimitInfo');
    if (!limitInfo) return;

    fetch(`api/leave_request_clean.php?action=get_employee_requests&employee_id=${window.employeeInternalId}`)
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                const currentMonth = new Date().toISOString().slice(0, 7);
                const pending = response.data.filter(l => l.status === 'pending');
                const approvedThisMonth = response.data.filter(l => l.status === 'approved' && l.start_date.startsWith(currentMonth));

                if (pending.length > 0) {
                    limitInfo.innerHTML = '<i class="bi bi-hourglass-split"></i> <strong>Pending Request:</strong> Please wait for approval before submitting another.';
                    limitInfo.className = 'alert alert-warning mb-3';
                } else if (approvedThisMonth.length >= 2) {
                    limitInfo.innerHTML = '<i class="bi bi-x-circle"></i> <strong>Limit Reached:</strong> 2/2 approved leaves used this month.';
                    limitInfo.className = 'alert alert-danger mb-3';
                } else {
                    const remaining = 2 - approvedThisMonth.length;
                    limitInfo.innerHTML = `<i class="bi bi-check-circle"></i> <strong>Available:</strong> ${remaining} of 2 requests this month.`;
                    limitInfo.className = 'alert alert-info mb-3';
                }
            }
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
        });
}

// Make available globally for inline onclicks
window.openLeaveDetails = function (leave) {
    document.getElementById('viewLeaveType').textContent = leave.leave_type;
    document.getElementById('viewLeaveStatus').textContent = leave.status; // Styled in PHP maybe?
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
    } else if (window.isAdmin && leave.status === 'approved') {
        // Maybe allow revert?
    }

    actionContainer.innerHTML = btns;
    new bootstrap.Modal(document.getElementById('leaveDetailsViewModal')).show();
};

function submitLeaveRequest() {
    const type = document.getElementById('leaveType').value;
    const from = document.getElementById('leaveFrom').value;
    const to = document.getElementById('leaveTo').value;
    const reason = document.getElementById('leaveReason').value;
    const file = document.getElementById('leaveAttachment').files[0];
    const autoApprove = document.getElementById('autoApprove') ? document.getElementById('autoApprove').checked : false;

    if (!type || !from || !to) {
        document.getElementById("leaveValidationErrorMsg").textContent = "Please fill all required fields.";
        new bootstrap.Modal(document.getElementById("leaveValidationErrorModal")).show();
        return;
    }

    const formData = new FormData();
    formData.append('action', 'submit_request');
    formData.append('employee_id', window.employeeInternalId);
    formData.append('leave_type', type);
    formData.append('start_date', from);
    formData.append('end_date', to);
    formData.append('reason', reason);
    if (file) formData.append('attachment', file);
    if (autoApprove) formData.append('auto_approve', '1');
    if (window.isAdmin) formData.append('is_admin', '1');

    const btn = document.getElementById('btnSubmitLeave');
    btn.disabled = true;
    btn.textContent = 'Submitting...';

    fetch('api/leave_request_clean.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Submit Request';
            bootstrap.Modal.getInstance(document.getElementById('addLeaveModal')).hide();

            if (data.success) {
                showSuccessModal(data.message);
                loadEmployeeLeaves();
            } else {
                showErrorModal(data.error);
            }
        })
        .catch(err => {
            btn.disabled = false;
            showErrorModal('Network Error');
        });
}

function executeLeaveAction() {
    if (!pendingLeaveAction) return;

    const action = pendingLeaveAction.type; // approve, reject, cancel
    let apiAction = '';

    if (action === 'approve') apiAction = 'approve_request';
    if (action === 'reject') apiAction = 'reject_request';
    if (action === 'cancel') apiAction = 'delete_request';

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
