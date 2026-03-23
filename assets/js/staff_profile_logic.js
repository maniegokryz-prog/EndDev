/**
 * staff_profile_logic.js
 * Contains logic for Leave Requests and Manual Attendance Modal
 * Migrated from staffinfo.php
 * 
 * EXPECTS GLOBAL VARIABLES TO BE DEFINED IN PHP VIEW:
 * - employeeIdForLeave (int/string)
 * - isAdmin (boolean)
 * - employeeInternalId (int/string)
 * - employeeCode (string)
 */

document.addEventListener('DOMContentLoaded', function () {
    // ==========================================
    // LEAVE MANAGEMENT LOGIC
    // ==========================================

    // Show/hide admin options on page load
    if (typeof isAdmin !== 'undefined' && isAdmin) {
        const adminDiv = document.getElementById('adminOptionsDiv');
        if (adminDiv) adminDiv.style.display = 'block';
    }

    // Add file size validation
    const fileInput = document.getElementById('leaveAttachment');
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const warningDiv = document.getElementById('fileSizeWarning');
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes

            if (this.files.length > 0) {
                const file = this.files[0];
                if (file.size > maxSize) {
                    warningDiv.style.display = 'block';
                    this.value = ''; // Clear the file input
                } else {
                    warningDiv.style.display = 'none';
                }
            } else {
                warningDiv.style.display = 'none';
            }
        });
    }

    // Check monthly limit when add leave modal is opened
    const addLeaveModal = document.getElementById('addLeaveModal');
    if (addLeaveModal) {
        addLeaveModal.addEventListener('show.bs.modal', function () {
            checkMonthlyLimit();
        });
    }

    // LEAVE REQUEST: Auto-populate times from schedule
    const leaveFromInput = document.getElementById('leaveFrom');
    if (leaveFromInput) {
        leaveFromInput.addEventListener('change', async function () {
            const date = this.value;
            console.log('Leave Request: Date changed to', date);

            if (!date || date.length < 10 || date.startsWith('00')) return;

            // Validate year is reasonable (e.g. >= 2000)
            const year = parseInt(date.substring(0, 4));
            if (year < 2000) return;

            // Auto-fill removed per user request (manual input only)
            const leaveToInput = document.getElementById('leaveTo');

            try {
                console.log(`Fetching schedule for employee ${employeeInternalId} on ${date}`);
                // Fetch schedule for this date
                const res = await fetch(`api/get_employee_schedule.php?employee_id=${employeeInternalId}&date=${date}`);
                const result = await res.json();
                console.log('Schedule fetch result:', result);

                const startTimeInput = document.getElementById('leaveStartTime');
                const endTimeInput = document.getElementById('leaveEndTime');

                if (result.success && result.has_schedule) {
                    // Populate times
                    if (startTimeInput) {
                        startTimeInput.value = result.schedule.start_time;
                        startTimeInput.disabled = false; // Enable if user wants to request partial day? Or keep disabled as per request? User said "automatically input".
                    }
                    if (endTimeInput) {
                        endTimeInput.value = result.schedule.end_time;
                        endTimeInput.disabled = false;
                    }

                    // Clear Previous Errors
                    const validationMsg = document.getElementById("leaveValidationErrorMsg");
                    if (validationMsg) validationMsg.textContent = '';

                } else {
                    // No schedule found
                    showErrorModal('No schedule found for this date. You can only request leave for scheduled working days.');
                    this.value = ''; // Clear the invalid date
                    if (leaveToInput && leaveToInput.value === date) leaveToInput.value = '';

                    if (startTimeInput) { startTimeInput.value = ''; startTimeInput.disabled = true; }
                    if (endTimeInput) { endTimeInput.value = ''; endTimeInput.disabled = true; }
                }
            } catch (error) {
                console.error('Error fetching schedule:', error);
            }
        });
    }

    // Load leaves on page load if container exists
    if (document.getElementById('leaveList')) {
        loadEmployeeLeaves();
    }

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    const leaveFrom = document.getElementById('leaveFrom');
    const leaveTo = document.getElementById('leaveTo');
    if (leaveFrom) leaveFrom.setAttribute('min', today);
    if (leaveTo) leaveTo.setAttribute('min', today);


    // ==========================================
    // MANUAL ATTENDANCE LOGIC (ADMIN ONLY)
    // ==========================================
    const attendanceModalEl = document.getElementById('attendanceModal');
    const openAttendanceBtn = document.getElementById('openAttendance'); // Ensure this button exists in your UI if you want to use it
    const addDayBtn = document.getElementById('addDayBtn');
    const attendanceContainer = document.getElementById('attendanceContainer');
    const saveBtn = document.getElementById('saveBtn');

    if (attendanceModalEl && addDayBtn && attendanceContainer && saveBtn) {
        const attendanceModal = new bootstrap.Modal(attendanceModalEl);

        // Initialize listeners when modal opens
        attendanceModalEl.addEventListener('shown.bs.modal', () => {
            // Setup listener for initial row
            const initialRow = attendanceContainer.querySelector('.attendance-row');
            if (initialRow) attachDateListener(initialRow);
        });


        // Add Another Day
        addDayBtn.addEventListener('click', () => {
            const newRow = document.createElement('div');
            newRow.classList.add('attendance-row', 'row', 'mb-3', 'align-items-start');
            newRow.innerHTML = `
                <div class="col-md-3">
                  <label>Date:</label>
                  <input type="date" class="form-control">
                  <div class="schedule-error-container" style="min-height: 0;">
                    <small class="text-danger schedule-error d-block" style="display:none; font-size: 0.75rem; margin-top: 4px; line-height: 1.2;"></small>
                  </div>
                </div>
                <div class="col-md-3">
                  <label>Time In:</label>
                  <input type="time" class="form-control">
                </div>
                <div class="col-md-3">
                  <label>Time Out:</label>
                  <input type="time" class="form-control">
                </div>
                <div class="col-md-3">
                  <div style="margin-top: 24px;">
                    <button class="btn btn-warning btn-sm me-1 clearRow" title="Clear Times"><i class="bi bi-eraser"></i></button>
                    <button class="btn btn-danger btn-sm removeRow"><i class="bi bi-dash-lg"></i></button>
                  </div>
                </div>`;
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
                const inputs = row.querySelectorAll('input[type="time"]');
                inputs.forEach(input => {
                    input.value = '';
                    input.classList.remove('bg-light');
                });
            }
        });

        // Save Attendance
        saveBtn.addEventListener('click', async () => {
            const rows = attendanceContainer.querySelectorAll('.attendance-row');
            const records = [];
            let hasError = false;
            let validationMessage = '';

            rows.forEach((row, index) => {
                const inputs = row.querySelectorAll('input');
                const date = inputs[0].value;
                const timeIn = inputs[1].value;
                const timeOut = inputs[2].value;

                if (!date || !timeIn) {
                    if (!hasError) validationMessage = `Date and Time In are required in row ${index + 1}`;
                    hasError = true;
                    return;
                }
                records.push({ date, time_in: timeIn, time_out: timeOut });
            });

            if (hasError || records.length === 0) {
                showErrorModal(validationMessage || 'Please fill out all records before saving.');
                return;
            }

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const response = await fetch('api/add_manual_attendance.php?action=add_manual', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        employee_id: employeeInternalId, // GLOBAL
                        records: records
                    })
                });
                const result = await response.json();

                if (result.success) {
                    let message = result.message || 'Records saved successfully.';
                    if (result.warnings && result.warnings.length > 0) message += ' Warnings: ' + result.warnings.join('; ');

                    document.getElementById('attendanceSuccessMessage').textContent = message;

                    // Hide main modal
                    bootstrap.Modal.getInstance(attendanceModalEl).hide();
                    cleanModals();

                    // Show success
                    const sm = new bootstrap.Modal(document.getElementById('attendanceSuccessModal'));
                    sm.show();

                    // Reload
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    let errMsg = result.error || 'Unknown error';
                    if (result.warnings) errMsg = result.warnings.join('\n');
                    showErrorModal(errMsg);
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Records';
                }
            } catch (error) {
                console.error(error);
                showErrorModal('Failed to save: ' + error.message);
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Records';
            }
        });
    }

    // ==========================================
    // EDIT SCHEDULE INIT
    // ==========================================
    const editScheduleModal = document.getElementById('editScheduleModal');
    if (editScheduleModal) {
        editScheduleModal.addEventListener('shown.bs.modal', function () {
            console.log('Edit schedule modal opened, initializing calendar...');
            if (typeof initializeCalendar === 'function') {
                initializeCalendar();
            }
            if (typeof renderSchedules === 'function') {
                renderSchedules();
            }
        });
    }

});

// ==========================================
// HELPER FUNCTIONS (Global Scope)
// ==========================================

function checkMonthlyLimit() {
    if (!employeeIdForLeave) return;
    fetch(`api/leave_request_clean.php?action=get_employee_requests&employee_id=${employeeIdForLeave}`)
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                const currentMonth = new Date().toISOString().slice(0, 7);
                const pendingRequests = response.data.filter(leave => leave.status === 'pending');
                const approvedThisMonth = response.data.filter(leave => {
                    const leaveMonth = leave.start_date.slice(0, 7);
                    return leave.status === 'approved' && leaveMonth === currentMonth;
                });

                const limitText = document.getElementById('monthlyLimitText');
                const limitInfo = document.getElementById('monthlyLimitInfo');

                if (pendingRequests.length > 0) {
                    limitText.innerHTML = '<strong>⏳ Pending Request.</strong> Wait for approval.';
                    limitInfo.className = 'alert alert-warning mb-3';
                } else if (approvedThisMonth.length >= 2) {
                    limitText.innerHTML = '<strong>❌ Limit Reached.</strong> 2/2 approved this month.';
                    limitInfo.className = 'alert alert-danger mb-3';
                } else {
                    const remaining = 2 - approvedThisMonth.length;
                    limitText.innerHTML = `<strong>✅ ${remaining} of 2 available</strong>`;
                    limitInfo.className = 'alert alert-success mb-3';
                }
            }
        })
        .catch(console.error);
}

function confirmLeave() {
    const leaveType = document.getElementById("leaveType").value;
    const leaveFrom = document.getElementById("leaveFrom").value;
    const leaveTo = document.getElementById("leaveTo").value;
    const leaveReason = document.getElementById("leaveReason").value;

    if (!leaveType || !leaveFrom || !leaveTo) {
        showErrorModal("Please fill out all required fields.");
        return;
    }

    // Close Add Leave modal temporarily
    const addModalEl = document.getElementById('addLeaveModal');
    const addModal = bootstrap.Modal.getInstance(addModalEl);
    if (addModal) addModal.hide();
    cleanModals();

    // Show details
    const startTime = document.getElementById('leaveStartTime').value;
    const endTime = document.getElementById('leaveEndTime').value;

    let timeStr = "";
    if (startTime && endTime) {
        timeStr = ` (${formatTime(startTime)} - ${formatTime(endTime)})`;
    }

    const detailsText = `${leaveType} Leave, from ${formatDate(leaveFrom)} to ${formatDate(leaveTo)}${timeStr}`;
    document.getElementById("leaveDetailsText").innerText = detailsText;

    setTimeout(() => {
        const detailsModal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
        detailsModal.show();
    }, 150);
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    const [hours, minutes] = timeStr.split(':');
    const h = parseInt(hours);
    const m = minutes;
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${m} ${ampm}`;
}

function finalizeLeave() {
    const leaveType = document.getElementById("leaveType").value;
    const leaveFrom = document.getElementById("leaveFrom").value;
    const leaveTo = document.getElementById("leaveTo").value;
    const leaveReason = document.getElementById("leaveReason").value;
    const autoApproveEl = document.getElementById("autoApprove");
    const autoApprove = (isAdmin && autoApproveEl && autoApproveEl.checked);

    // Get Time Inputs (New)
    const startTime = document.getElementById('leaveStartTime').value;
    const endTime = document.getElementById('leaveEndTime').value;

    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsModal'));
    if (detailsModal) detailsModal.hide();
    cleanModals();

    const formData = new FormData();
    formData.append('action', 'submit_request');
    formData.append('employee_id', employeeIdForLeave);
    formData.append('leave_type', leaveType);
    formData.append('start_date', leaveFrom);
    formData.append('end_date', leaveTo);

    if (startTime) formData.append('start_time', startTime);
    if (endTime) formData.append('end_time', endTime);

    formData.append('reason', leaveReason);
    formData.append('is_admin', isAdmin ? '1' : '0');
    formData.append('auto_approve', autoApprove ? '1' : '0');

    const fileInput = document.getElementById('leaveAttachment');
    if (fileInput && fileInput.files.length > 0) {
        formData.append('attachment', fileInput.files[0]);
    }

    fetch('api/leave_request_clean.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                loadEmployeeLeaves();
                checkMonthlyLimit();
                document.getElementById("leaveSuccessMsg").textContent = response.message || "Success!";
                const sm = new bootstrap.Modal(document.getElementById('leaveSuccessModal'));
                sm.show();
                setTimeout(() => { sm.hide(); cleanModals(); }, 3000);
            } else {
                showErrorModal('Error: ' + (response.error || 'Unknown'));
            }
        })
        .catch(error => {
            showErrorModal('Failed: ' + error.message);
        });
}

function loadEmployeeLeaves() {
    if (!employeeIdForLeave) return;
    // Use new V2 API to ensure rejection_reason is fetched correctly (bypassing server cache)
    fetch(`api/get_requests_v2.php?employee_id=${employeeIdForLeave}&_t=${new Date().getTime()}`)
        .then(res => res.json())
        .then(response => {
            const leaveList = document.getElementById("leaveList");
            if (!leaveList) return;
            leaveList.innerHTML = '';

            if (response.success && response.data) {
                if (response.count === 0) {
                    leaveList.innerHTML = '<p class="text-muted small text-center">No scheduled leaves</p>';
                    return;
                }
                response.data.forEach(leave => {
                    const entry = document.createElement("div");
                    entry.className = "leave-entry d-flex justify-content-between align-items-center p-2 border-bottom";
                    entry.style.cursor = "pointer";
                    entry.onclick = (e) => {
                        if (!e.target.closest('button, a')) viewLeaveDetails(leave);
                    };

                    // Simplified status badge logic
                    let badgeClass = 'bg-secondary';
                    if (leave.status === 'approved') badgeClass = 'bg-success';
                    if (leave.status === 'rejected') badgeClass = 'bg-danger';
                    if (leave.status === 'pending') badgeClass = 'bg-warning text-dark';

                    entry.innerHTML = `
                    <div>
                        <strong>${leave.leave_type}</strong> <span class="badge ${badgeClass}">${leave.status}</span>
                        <div class="small text-muted">${leave.formatted_dates}</div>
                    </div>
                `;
                    leaveList.appendChild(entry);
                });
            }
        });
}

function viewLeaveDetails(leave) {
    console.log('Viewing Leave Details:', leave);
    console.log('Rejection Reason Check:', leave.status, leave.rejection_reason);

    document.getElementById('viewLeaveType').textContent = leave.leave_type;
    document.getElementById('viewLeaveStatus').textContent = leave.status; // simplified
    document.getElementById('viewLeaveDates').textContent = leave.formatted_dates;
    document.getElementById('viewLeaveReason').textContent = leave.reason || 'None';

    // Show Rejection Reason if applicable
    const rejectionContainer = document.getElementById('viewLeaveRejectionReasonContainer');
    if (leave.status === 'rejected') {
        rejectionContainer.style.display = 'block';
        document.getElementById('viewLeaveRejectionReason').textContent = leave.rejection_reason || 'No specific reason provided.';
    } else {
        if (rejectionContainer) rejectionContainer.style.display = 'none';
    }

    // Attachment
    const attContainer = document.getElementById('viewLeaveAttachmentContainer');
    if (leave.attachment) {
        attContainer.style.display = 'block';
        document.getElementById('viewLeaveAttachment').href = '../' + leave.attachment;
    } else {
        attContainer.style.display = 'none';
    }

    // Actions
    let actionsHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
    if (leave.status === 'pending' && isAdmin) {
        actionsHTML += `<button class="btn btn-success ms-2" onclick="approveLeave(${leave.id})">Approve</button>`;
        actionsHTML += `<button class="btn btn-danger ms-2" onclick="rejectLeave(${leave.id})">Reject</button>`;
    } else if (leave.status === 'pending') {
        actionsHTML += `<button class="btn btn-warning ms-2" onclick="cancelLeave(${leave.id})">Cancel Request</button>`;
    }

    document.getElementById('viewLeaveActions').innerHTML = actionsHTML;

    const modal = new bootstrap.Modal(document.getElementById('leaveDetailsViewModal'));
    modal.show();
}

let currentLeaveIdToApprove = null;
function approveLeave(id) {
    currentLeaveIdToApprove = id;
    // Hide details modal if open
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsViewModal'));
    if (detailsModal) detailsModal.hide();

    // Show approve confirm modal
    const approveModal = new bootstrap.Modal(document.getElementById('leaveApproveConfirmModal'));
    approveModal.show();
}

let currentLeaveIdToCancel = null;
function cancelLeave(id) {
    currentLeaveIdToCancel = id;
    // Hide details modal if open
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsViewModal'));
    if (detailsModal) detailsModal.hide();

    // Show cancel confirm modal
    const cancelModal = new bootstrap.Modal(document.getElementById('leaveCancelConfirmModal'));
    cancelModal.show();
}

// Attach listeners for confirm buttons
document.addEventListener('DOMContentLoaded', () => {
    // Approve Confirm
    const confirmApproveBtn = document.getElementById('confirmApproveBtn');
    if (confirmApproveBtn) {
        confirmApproveBtn.addEventListener('click', function () {
            if (!currentLeaveIdToApprove) return;
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Approving...';

            const fd = new FormData();
            fd.append('leave_id', currentLeaveIdToApprove);
            fd.append('approved_by', 'admin');

            fetch('api/leave_request_clean.php?action=approve_request', { method: 'POST', body: fd })
                .then(r => r.json()).then(d => {
                    if (d.success) { location.reload(); }
                    else {
                        showErrorModal(d.error || 'Failed to approve');
                        btn.disabled = false;
                        btn.textContent = 'Yes, Approve';
                    }
                })
                .catch(err => {
                    console.error(err);
                    showErrorModal('Network error');
                    btn.disabled = false;
                    btn.textContent = 'Yes, Approve';
                });
        });
    }

    // Cancel Confirm
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    if (confirmCancelBtn) {
        confirmCancelBtn.addEventListener('click', function () {
            if (!currentLeaveIdToCancel) return;
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Cancelling...';

            const fd = new FormData();
            fd.append('leave_id', currentLeaveIdToCancel);
            fd.append('action', 'cancel_request');

            fetch('api/leave_request_clean.php?action=cancel_request', { method: 'POST', body: fd })
                .then(r => r.json()).then(d => {
                    if (d.success) { location.reload(); }
                    else {
                        showErrorModal(d.error || 'Failed to cancel');
                        btn.disabled = false;
                        btn.textContent = 'Yes, Cancel Request';
                    }
                })
                .catch(err => {
                    console.error(err);
                    showErrorModal('Network error');
                    btn.disabled = false;
                    btn.textContent = 'Yes, Cancel Request';
                });
        });
    }

    // Reject Confirm
    const confirmRejectBtn = document.getElementById('confirmRejectBtn');
    if (confirmRejectBtn) {
        confirmRejectBtn.addEventListener('click', function () {
            if (!currentLeaveIdToReject) return;

            const reason = document.getElementById('rejectionReason').value || 'Admin rejected';
            console.log('Capturing Rejection Reason:', reason);
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Rejecting...';

            const fd = new FormData();
            fd.append('leave_id', currentLeaveIdToReject);
            fd.append('action', 'reject_request');
            fd.append('rejection_reason', reason);

            fetch('api/leave_request_clean.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        location.reload();
                    } else {
                        showErrorModal(d.error || 'Failed to reject request');
                        btn.disabled = false;
                        btn.textContent = 'Yes, Reject';
                    }
                })
                .catch(err => {
                    console.error(err);
                    showErrorModal('Network error occurred');
                    btn.disabled = false;
                    btn.textContent = 'Yes, Reject';
                });
        });
    }
});

let currentLeaveIdToReject = null;
function rejectLeave(id) {
    currentLeaveIdToReject = id;
    const reasonInput = document.getElementById('rejectionReason');
    if (reasonInput) reasonInput.value = '';

    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsViewModal'));
    if (detailsModal) detailsModal.hide();

    const rejectModal = new bootstrap.Modal(document.getElementById('leaveRejectConfirmModal'));
    rejectModal.show();
}

function cancelLeaveRequest() {
    document.getElementById('leaveType').value = '';
    document.getElementById('leaveFrom').value = '';
    document.getElementById('leaveTo').value = '';
    document.getElementById('leaveReason').value = '';
}
function goBackToForm() {
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('leaveDetailsModal'));
    if (detailsModal) detailsModal.hide();
    cleanModals();
    setTimeout(() => {
        new bootstrap.Modal(document.getElementById('addLeaveModal')).show();
    }, 150);
}
function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
function cleanModals() {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
}
function showErrorModal(msg) {
    const el = document.getElementById("leaveValidationErrorModal");
    if (!el) return;

    document.getElementById("leaveValidationErrorMsg").textContent = msg;

    // Get or create instance
    let modal = bootstrap.Modal.getInstance(el);
    if (!modal) modal = new bootstrap.Modal(el);

    // safe show
    modal.show();

    // Fix for stacked modals (Bootstrap 5 sometimes removes modal-open from body when top modal closes)
    el.addEventListener('hidden.bs.modal', function () {
        // If Attendance Modal is still open, re-add modal-open to body and ensure its backdrop is fine
        const attendanceModalEl = document.getElementById('attendanceModal');
        if (attendanceModalEl && attendanceModalEl.classList.contains('show')) {
            document.body.classList.add('modal-open');
        }
    }, { once: true });
}

function showSuccessModal(msg) {
    const el = document.getElementById("leaveSuccessModal");
    if (!el) return;
    document.getElementById("leaveSuccessMsg").textContent = msg;
    let modal = bootstrap.Modal.getInstance(el);
    if (!modal) modal = new bootstrap.Modal(el);
    modal.show();
}

// Attendance Schedule Fetcher
async function populateScheduleTimes(dateInput, timeInInput, timeOutInput) {
    const selectedDate = dateInput.value;
    const row = dateInput.closest('.attendance-row');
    const errorMsg = row ? row.querySelector('.schedule-error') : null;

    if (!selectedDate) {
        if (errorMsg) errorMsg.style.display = 'none';
        return;
    }

    // Ignore invalid years (e.g. while typing)
    const y = new Date(selectedDate).getFullYear();
    if (!y || y < 2000) return;

    try {
        // Check existing
        const existsRes = await fetch(`api/check_attendance_exists.php?employee_id=${employeeInternalId}&date=${selectedDate}`);
        const existsResult = await existsRes.json();

        if (existsResult.success && existsResult.exists) {
            timeInInput.value = '';
            timeOutInput.value = '';
            timeInInput.classList.remove('bg-light');
            timeOutInput.classList.remove('bg-light');
            if (errorMsg) {
                errorMsg.textContent = `Record exists for ${selectedDate}`;
                errorMsg.style.display = 'block';
            }
            return;
        }

        // Fetch schedule
        const res = await fetch(`api/get_employee_schedule.php?employee_id=${employeeInternalId}&date=${selectedDate}`);
        const result = await res.json();

        if (result.success && result.has_schedule) {
            timeInInput.value = result.schedule.start_time;
            timeOutInput.value = result.schedule.end_time;
            timeInInput.classList.add('bg-light');
            timeOutInput.classList.add('bg-light');
            if (errorMsg) errorMsg.style.display = 'none';
        } else {
            // Clear inputs (but keep date!)
            // dateInput.value = ''; 
            timeInInput.value = '';
            timeOutInput.value = '';
            timeInInput.classList.remove('bg-light');
            timeOutInput.classList.remove('bg-light');

            // Do NOT show error modal. Allow manual entry.
            if (errorMsg) {
                errorMsg.textContent = 'No preset schedule. Enter manually.';
                errorMsg.style.display = 'block';
            }
        }
    } catch (e) {
        console.error(e);
    }
}

function attachDateListener(row) {
    const dateInput = row.querySelector('input[type="date"]');
    const timeInputs = row.querySelectorAll('input[type="time"]');
    if (dateInput && timeInputs.length >= 2) {
        const timeIn = timeInputs[0];
        const timeOut = timeInputs[timeInputs.length - 1];

        // Clone to remove old listeners
        const newDateInput = dateInput.cloneNode(true);
        dateInput.parentNode.replaceChild(newDateInput, dateInput);

        newDateInput.addEventListener('change', () => populateScheduleTimes(newDateInput, timeIn, timeOut));
    }
}
