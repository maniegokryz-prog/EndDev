/**
 * newstaff_wizard.js
 * 
 * Specialized logic for the new 3-step staff wizard.
 * Adapted from add_employee.js
 */

// =========================
// CustomDropdown Class
// =========================
class CustomDropdown {
    constructor(inputElement, options = []) {
        this.input = inputElement;
        this.options = options;
        this.filteredOptions = [...options];
        this.selectedIndex = -1;
        this.isOpen = false;
        this.init();
    }
    init() {
        this.createDropdownStructure();
        this.bindEvents();
        const datalist = document.getElementById(this.input.getAttribute('list'));
        if (datalist) datalist.style.display = 'none';
    }
    createDropdownStructure() {
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-dropdown';
        if (this.input.parentNode) {
            this.input.parentNode.insertBefore(wrapper, this.input);
            wrapper.appendChild(this.input);
            const arrow = document.createElement('div');
            arrow.className = 'dropdown-arrow';
            wrapper.appendChild(arrow);
            this.dropdownList = document.createElement('div');
            this.dropdownList.className = 'dropdown-list';
            wrapper.appendChild(this.dropdownList);
            this.wrapper = wrapper;
            this.arrow = arrow;
            this.updateDropdownList();
        }
    }
    bindEvents() {
        this.input.addEventListener('click', (e) => { e.preventDefault(); this.toggleDropdown(); });
        this.input.addEventListener('input', (e) => { this.filterOptions(e.target.value); this.openDropdown(); });
        this.input.addEventListener('keydown', (e) => { this.handleKeyNavigation(e); });
        this.input.addEventListener('blur', (e) => { setTimeout(() => { if (!this.wrapper.contains(document.activeElement)) this.closeDropdown(); }, 150); });
        document.addEventListener('click', (e) => { if (this.wrapper && !this.wrapper.contains(e.target)) this.closeDropdown(); });
    }
    filterOptions(searchTerm) {
        const term = searchTerm.toLowerCase().trim();
        this.filteredOptions = term === '' ? [...this.options] : this.options.filter(option => option.toLowerCase().includes(term));
        this.selectedIndex = -1;
        this.updateDropdownList();
    }
    updateDropdownList() {
        this.dropdownList.innerHTML = '';
        if (this.filteredOptions.length === 0) {
            const noResults = document.createElement('div');
            noResults.className = 'dropdown-item no-results';
            noResults.textContent = 'No options found';
            this.dropdownList.appendChild(noResults);
            return;
        }
        this.filteredOptions.forEach((option, index) => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = option;
            item.addEventListener('click', () => { this.selectOption(option); });
            this.dropdownList.appendChild(item);
        });
    }
    selectOption(option) {
        const uppercaseFields = ['designate_class', 'designate_subject', 'room-number'];
        if (uppercaseFields.includes(this.input.id)) this.input.value = option.toUpperCase();
        else this.input.value = option;
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
        this.closeDropdown();
    }
    openDropdown() { this.isOpen = true; this.wrapper.classList.add('open'); this.updateDropdownList(); }
    closeDropdown() { this.isOpen = false; this.wrapper.classList.remove('open'); this.selectedIndex = -1; }
    toggleDropdown() { this.isOpen ? this.closeDropdown() : this.openDropdown(); }
    handleKeyNavigation(e) {
        if (!this.isOpen) return;
        const items = this.dropdownList.querySelectorAll('.dropdown-item:not(.no-results)');
        switch (e.key) {
            case 'ArrowDown': e.preventDefault(); this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1); this.highlightOption(); break;
            case 'ArrowUp': e.preventDefault(); this.selectedIndex = Math.max(this.selectedIndex - 1, 0); this.highlightOption(); break;
            case 'Enter': e.preventDefault(); if (this.selectedIndex >= 0 && items[this.selectedIndex]) this.selectOption(items[this.selectedIndex].textContent); break;
            case 'Escape': this.closeDropdown(); break;
        }
    }
    highlightOption() {
        const items = this.dropdownList.querySelectorAll('.dropdown-item:not(.no-results)');
        items.forEach(item => item.classList.remove('selected'));
        if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
            items[this.selectedIndex].classList.add('selected');
            items[this.selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }
}

// =========================
// Global Utils
// =========================

function showAppAlert(message, title = 'Notice', type = 'info') {
    try {
        const modalEl = document.getElementById('appAlertModal');
        // If modal doesn't exist in newstaff.php, fallback to standard alert for simplicity, 
        // OR rely on the modal existing (it was in the original newstaff.php, but I didn't include it in my rewrite... WAIT).
        // I might have missed including `appAlertModal` in my `newstaff.php` rewrite. I included `wizardSuccessModal` and `errorModal`.
        // Let's rely on standard alert if elements missing, or the already included ErrorModal for errors.
        if (!modalEl) { alert(message); return; }

        const msgEl = document.getElementById('appAlertModalMessage');
        const titleEl = document.getElementById('appAlertModalLabel');
        if (msgEl) msgEl.innerHTML = String(message).replace(/\n/g, '<br>');
        if (titleEl) titleEl.textContent = title;
        new bootstrap.Modal(modalEl).show();
    } catch (e) { alert(String(message)); }
}

function showAppConfirm(message, title = 'Confirm') {
    return new Promise((resolve) => {
        // Simple fallback
        resolve(window.confirm(message));
    });
}

// =========================
// Schedule Logic
// =========================
let selectedDays = [];
let addedSchedules = [];

// Expose checks for toggle logic
window.addedSchedules = addedSchedules;

/* Colors */
const scheduleColors = ['#4a7c59', '#8b4a6b', '#b85450', '#5b9bd5', '#ffc000', '#c55a11', '#7030a0', '#0070c0', '#00b050', '#ff6b6b'];
function getRandomScheduleColor() { return scheduleColors[Math.floor(Math.random() * scheduleColors.length)]; }

/* Day Toggle */
window.toggleDay = function (button) {
    const day = button.getAttribute('data-day');
    button.classList.toggle('active');
    if (button.classList.contains('active')) {
        if (!selectedDays.includes(day)) selectedDays.push(day);
    } else {
        selectedDays = selectedDays.filter(d => d !== day);
    }
    document.getElementById('work_days').value = JSON.stringify(selectedDays);
}

/* Add Schedule Function */
window.addSchedule = async function () {
    if (selectedDays.length === 0) { showAppAlert('Please select at least one working day!'); return; }

    const shiftStart = document.getElementById('shift_start').value;
    const shiftEnd = document.getElementById('shift_end').value;

    if (!shiftStart || !shiftEnd) { showAppAlert('Please select both start and end times!'); return; }
    if (shiftStart >= shiftEnd) { showAppAlert('Start time must be before end time!'); return; }

    const designateClass = document.getElementById('designate_class').value;
    const designateSubject = document.getElementById('designate_subject').value;
    const roomNumber = document.getElementById('room-number').value;

    // Check if user is looking at faculty enabled fields
    const isFaculty = !document.getElementById('designate_class').disabled;

    if (isFaculty && (!designateClass || !designateSubject || !roomNumber)) {
        showAppAlert('Faculty members must enter class, subject, and room number!');
        return;
    }

    const scheduleData = {
        days: [...selectedDays],
        startTime: shiftStart,
        endTime: shiftEnd,
        class: isFaculty ? designateClass.toUpperCase() : 'N/A',
        subject: isFaculty ? designateSubject.toUpperCase() : 'GENERAL',
        room_num: isFaculty ? roomNumber.toUpperCase() : 'TBD',
        color: getRandomScheduleColor()
    };

    if (checkScheduleConflict(scheduleData)) {
        if (!confirm('This schedule conflicts with an existing schedule. Do you want to add it anyway?')) return;
    }

    addedSchedules.push(scheduleData);
    window.addedSchedules = addedSchedules;

    // Update Hidden Input for Form Submission
    document.getElementById('schedule_data').value = JSON.stringify(addedSchedules);

    renderSchedules();
    clearScheduleForm();
    showAppAlert('Schedule added to preview!', 'Success', 'success');
}

function checkScheduleConflict(newSchedule) {
    return addedSchedules.some(existing => {
        const commonDays = newSchedule.days.filter(d => existing.days.includes(d));
        if (commonDays.length === 0) return false;

        const newStart = parseTime(newSchedule.startTime);
        const newEnd = parseTime(newSchedule.endTime);
        const existingStart = parseTime(existing.startTime);
        const existingEnd = parseTime(existing.endTime);

        return (newStart < existingEnd && newEnd > existingStart);
    });
}

function clearScheduleForm() {
    selectedDays = [];
    document.querySelectorAll('.day-btn.active').forEach(btn => btn.classList.remove('active'));
    document.getElementById('shift_start').value = '';
    document.getElementById('shift_end').value = '';

    if (!document.getElementById('designate_class').disabled) {
        document.getElementById('designate_class').value = '';
        document.getElementById('designate_subject').value = '';
        document.getElementById('room-number').value = '';
    }
    document.getElementById('work_days').value = '';
}

window.deleteSchedule = function (index, day) {
    if (!confirm('Delete this schedule block?')) return;
    const schedule = addedSchedules[index];
    if (schedule.days.length === 1) {
        addedSchedules.splice(index, 1);
    } else {
        schedule.days = schedule.days.filter(d => d !== day);
    }
    window.addedSchedules = addedSchedules;
    document.getElementById('schedule_data').value = JSON.stringify(addedSchedules);
    renderSchedules();
}

window.clearAllSchedules = async function () {
    if (addedSchedules.length === 0) return;
    if (!confirm('Clear all schedules?')) return;
    addedSchedules = [];
    window.addedSchedules = [];
    document.getElementById('schedule_data').value = '[]';
    renderSchedules();
}

/* Visualization */
function renderSchedules() {
    document.querySelectorAll('.schedule-block').forEach(b => b.remove());
    addedSchedules.forEach((s, i) => renderScheduleBlock(s, i));
}

function renderScheduleBlock(schedule, index) {
    const startTimeMinutes = parseTime(schedule.startTime);
    const endTimeMinutes = parseTime(schedule.endTime);
    const baseTimeMinutes = 420; // 7:00 AM
    const slotDuration = 30;
    const slotHeight = 40;

    const startSlotIndex = Math.floor((startTimeMinutes - baseTimeMinutes) / slotDuration);
    const endSlotIndex = Math.ceil((endTimeMinutes - baseTimeMinutes) / slotDuration);
    const slotsSpanned = endSlotIndex - startSlotIndex;

    schedule.days.forEach(day => {
        if (startSlotIndex >= 0 && endSlotIndex <= 34) {
            const targetCell = document.querySelector(`[data-day="${day}"][data-time-index="${startSlotIndex}"]`);
            if (targetCell) {
                const block = document.createElement('div');
                block.className = 'schedule-block';
                block.style.background = schedule.color || '#4a7c59';
                block.style.height = `${slotsSpanned * slotHeight}px`;
                block.innerHTML = `<span onclick="deleteSchedule(${index}, '${day}')" style="cursor:pointer;float:right;margin-right:2px;font-weight:bold;">&times;</span>
                                   <div style="font-size:0.75rem;padding:2px;">
                                     <strong>${schedule.class !== 'N/A' ? schedule.class : 'Work'}</strong><br>
                                     ${formatTime(schedule.startTime)} - ${formatTime(schedule.endTime)}
                                   </div>`;
                targetCell.appendChild(block);
            }
        }
    });
}

function parseTime(t) { return t.split(':').reduce((h, m) => h * 60 + +m); }
function formatTime(t) {
    const [h, m] = t.split(':').map(Number);
    const period = h >= 12 ? 'PM' : 'AM';
    const displayH = h > 12 ? h - 12 : (h === 0 ? 12 : h);
    return `${displayH}:${m.toString().padStart(2, '0')}${period}`;
}

window.initializeCalendar = function () {
    const calendar = document.querySelector('.schedule-calendar');
    if (!calendar) return;

    // Generate Time Slots 7AM - 12AM
    const slots = [];
    for (let m = 420; m < 1440; m += 30) slots.push(m);

    // Rows: Header + Slots
    calendar.style.gridTemplateRows = `40px repeat(${slots.length}, 40px)`;

    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    // Clear old but keep headers if any? My CSS grid might need explicit placement
    // Actually, safer to clear all dynamic cells but keep headers
    document.querySelectorAll('.time-slot, .calendar-cell').forEach(e => e.remove());

    slots.forEach((m, i) => {
        // Time Label
        const label = document.createElement('div');
        label.className = 'time-slot';
        label.textContent = formatTime(`${Math.floor(m / 60)}:${(m % 60).toString().padStart(2, '0')}`);
        label.style.gridColumn = '1';
        label.style.gridRow = `${i + 2}`;
        calendar.appendChild(label);

        // Cells
        days.forEach((day, di) => {
            const cell = document.createElement('div');
            cell.className = 'calendar-cell';
            cell.dataset.day = day;
            cell.dataset.timeSlot = m;
            cell.dataset.timeIndex = i;
            cell.style.gridColumn = `${di + 2}`;
            cell.style.gridRow = `${i + 2}`;
            calendar.appendChild(cell);
        });
    });
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    // initializeCalendar(); // Called manually or when tab switches?
    // Better to call it immediately so it is ready
    setTimeout(window.initializeCalendar, 100);

    // Setup Dropdowns
    const rolesInput = document.getElementById('roles');
    if (rolesInput && window.existingRoles) new CustomDropdown(rolesInput, [...new Set(['Administrator', 'Faculty_Member', 'Non Teaching Staff', ...window.existingRoles])]);

    const deptInput = document.getElementById('department');
    if (deptInput && window.existingDepartments) new CustomDropdown(deptInput, window.existingDepartments);

    // Faculty Toggle Logic
    if (rolesInput) {
        rolesInput.addEventListener('change', () => {
            const isFaculty = rolesInput.value === 'Faculty_Member';
        });
    }

    // --- Face Step Initialization ---
    const faceTabBtn = document.getElementById('step2-tab');
    if (faceTabBtn) {
        faceTabBtn.addEventListener('shown.bs.tab', (e) => {
            console.log('Face tab shown, initializing camera...');
            if (window.faceApp) {
                // We could check if already initialized, but initialize() usually handles idempotency or we can just try
                // However, initialize() in face-js usually sets up everything.
                // Best to call it.
                window.faceApp.initialize().catch(console.error);
            }
            // Auto-load pending list
            loadPendingList('face');
        });

        // Optional: Stop camera when leaving tab?
        // For now, let's keep it running as switching back and forth is common.
        // But if we want to be clean:
        // document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(t => {
        //    if (t.id !== 'face-tab') {
        //        t.addEventListener('shown.bs.tab', () => { if(window.faceApp && window.faceApp.camera) window.faceApp.camera.cleanup(); });
        //    }
        // });
    }

    const schedTabBtn = document.getElementById('step3-tab');
    if (schedTabBtn) {
        schedTabBtn.addEventListener('shown.bs.tab', (e) => {
            loadPendingList('schedule');
        });
    }

    // Also, handle the case where "Next Step" buttons programmatically switch tabs
    // The bootstrap event 'shown.bs.tab' should still fire even if clicked via JS .show() if using bootstrap API.
    // But if we just click() the element, it works too.
});

// --- Pending List Logic ---

window.loadPendingList = function (type) {
    const listId = type === 'face' ? 'face_pending_list' : 'sched_pending_list';
    const listEl = document.getElementById(listId);
    if (!listEl) return;

    listEl.innerHTML = '<div class="list-group-item text-muted small text-center"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...</div>';

    const filter = type === 'face' ? 'pending_face' : 'pending_schedule';

    fetch(`get_employees.php?filter=${filter}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                listEl.innerHTML = '';
                data.data.forEach(emp => {
                    // Create list item
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                    item.innerHTML = `
                        <div>
                            <div class="fw-bold text-dark">${emp.name}</div>
                            <small class="text-muted">${emp.employee_id} - ${emp.department}</small>
                        </div>
                        <span class="badge bg-primary rounded-pill">Select</span>
                    `;
                    item.onclick = (e) => {
                        e.preventDefault();
                        selectEmployeeFromList(type, emp);
                    };
                    listEl.appendChild(item);
                });
            } else {
                listEl.innerHTML = '<div class="list-group-item text-muted small text-center">No pending employees found.</div>';
            }
        })
        .catch(err => {
            console.error(err);
            listEl.innerHTML = '<div class="list-group-item text-danger small text-center">Error loading list.</div>';
        });
}

window.selectEmployeeFromList = function (type, emp) {
    // Populate Info
    const prefix = type === 'face' ? 'face' : 'sched';
    document.getElementById(`${prefix}_emp_name`).textContent = emp.name;
    document.getElementById(`${prefix}_emp_id_display`).textContent = emp.employee_id;

    const roleOrDeptEl = document.getElementById(`${prefix}_emp_dept`) || document.getElementById(`${prefix}_emp_role`);
    if (roleOrDeptEl) roleOrDeptEl.textContent = type === 'face' ? emp.department : emp.role;

    const imgEl = document.getElementById(`${prefix}_emp_img`);
    if (imgEl) {
        imgEl.src = emp.profile_photo || '../assets/profile_pic/user.png';
        imgEl.onerror = () => { imgEl.src = '../assets/profile_pic/user.png'; };
    }

    // Show Card
    document.getElementById(`${prefix}_employee_info`).classList.remove('d-none');

    // Show Main Container
    const container = type === 'face' ? document.getElementById('face_registration_container') : document.getElementById('schedule_container');
    if (container) container.style.display = 'block';

    // Set Global State
    if (type === 'face') {
        window.currentFaceEmployeeId = emp.employee_id;
        // Also potentially pre-fill hidden ID inputs if any
    } else {
        window.currentSchedEmployeeId = emp.employee_id;
        
        // Check for Faculty Role to enable fields
        if (typeof toggleFacultyFields === 'function') {
            // Check intersection with any known faculty role strings
            const role = (emp.role || '').toLowerCase();
            const isFaculty = role.includes('faculty');
            toggleFacultyFields(isFaculty);
        }

        // If Schedule step has specific loaded data requirements (like fetching existing schedule to show conflicts?), 
        // we might need to clear previous schedule data.
        // Clear previous schedule data without prompt
        if (window.addedSchedules) {
            window.addedSchedules = [];
            document.getElementById('schedule_data').value = '[]';
            renderSchedules();
        }
    }
}
