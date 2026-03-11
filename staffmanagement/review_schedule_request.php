<?php
session_start();
require_once '../auth_guard.php';
requireAdmin();
require_once '../db_connection.php';
include '../navigation.php';

// Fetch pending schedule requests
$requests = [];
$specific_id = isset($_GET['id']) ? intval($_GET['id']) : null;

try {
    $query = "
        SELECT sr.*, e.department, e.position 
        FROM schedule_requests sr
        LEFT JOIN employees e ON sr.employee_id = e.id
        WHERE sr.status = 'pending'
    ";

    if ($specific_id) {
        $query .= " AND sr.id = ?";
    }

    $query .= " ORDER BY sr.created_at DESC";

    $stmt = $conn->prepare($query);

    if ($specific_id) {
        $stmt->bind_param("i", $specific_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching schedule requests: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Review Schedule Requests</title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap CSS -->
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="staff.css">
    <link rel="stylesheet" href="../assets/css/styles.css">

    <style>
        .hover-scale {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hover-scale:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1) !important;
        }

        .request-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }

        .request-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .request-body {
            padding: 20px;
        }

        .schedule-table th {
            background-color: #f1f3f5;
        }

        /* Modal Styles from staff_profile.php */
        #editScheduleModal .add-schedule-btn,
        #editScheduleModal .edit-schedule-btn,
        #editScheduleModal .btn-cancel {
            flex: 0 0 auto;
            width: auto;
            min-width: 140px;
            padding: 8px 16px;
        }

        #editScheduleModal .clear-schedules-btn,
        #editScheduleModal .btn-cancel,
        #editScheduleModal .btn-save {
            min-width: 200px;
            padding: 10px 20px;
        }

        #editScheduleModal .clear-schedules-btn,
        #editScheduleModal .btn-cancel {
            background-color: #ff6b6b !important;
            color: white !important;
            border: none !important;
            border-radius: 6px;
            transition: all 0.2s ease;
        }



        @media (max-width: 768px) {
            #editScheduleModal .form-group[style*="display: flex"] {
                flex-direction: column !important;
                gap: 8px !important;
            }

            #editScheduleModal .add-schedule-btn,
            #editScheduleModal .edit-schedule-btn,
            #editScheduleModal .btn-cancel,
            #editScheduleModal .btn-save {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                padding: 10px !important;
                margin-bottom: 0 !important;
            }

            .schedule-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 10px !important;
                margin-bottom: 15px !important;
            }

            .clear-schedules-btn {
                width: 100% !important;
                margin-left: 0 !important;
            }

            .form-actions {
                display: flex !important;
                flex-direction: column !important;
                gap: 10px !important;
                margin-top: 15px;
            }

            .edit-schedule-modal-dialog {
                margin: 1rem auto;
                max-width: 95%;
            }
        }

        /* Sidebar Toggle Styles (Matched to settings.css) - Using IDs for Specificity */
        @media (min-width: 992px) {
            #sidebar {
                left: 0;
                transition: all 0.3s;
            }

            #content {
                margin-left: 250px;
                transition: margin-left 0.3s;
            }

            #sidebar.collapsed {
                left: -250px !important;
            }

            #content.shift {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }

        @media (max-width: 991px) {
            #sidebar {
                margin-left: -250px;
                left: -250px;
                transition: all 0.3s;
            }

            #sidebar.active {
                margin-left: 0 !important;
                left: 0 !important;
            }

            #content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
    </style>
</head>

<body>
    <div class="top-navbar d-flex justify-content-between align-items-center p-2 shadow-sm">
        <div class="menu-toggle">
            <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn"></i>
        </div>
        <?php include '../includes/notification_bell.php'; ?>
    </div>

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column pt-5" id="sidebar">
        <div class="profile text-center p-3 mt-4">
            <?php $currentUser = getCurrentUser(); ?>
            <img src="<?php echo (!empty($currentUser['profile_photo']) && $currentUser['profile_photo'] !== 'N/A') ? '../' . htmlspecialchars($currentUser['profile_photo'], ENT_QUOTES, 'UTF-8') . '?v=' . time() : '../assets/profile_pic/user.png?v=' . time(); ?>"
                alt="Profile" class="rounded-circle mb-2" style="width: 70px; height: 70px; object-fit: cover;"
                onerror="this.src='../assets/profile_pic/user.png';">
            <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h5>
            <?php
            // Check for status messages from redirect
            $updateStatus = $_GET['status'] ?? '';

            // Fetch unique classes, subjects, and rooms for the dropdowns
            $existing_classes = [];
            $existing_subjects = [];
            $existing_rooms = [];

            $ignore_list = ['work shift', 'work_shift', 'n/a', 'na', 'tba', 'tbd', 'none'];

            try {
                $res = $conn->query("SELECT DISTINCT designate_class FROM employee_assignments WHERE designate_class IS NOT NULL AND designate_class != '' ORDER BY designate_class");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $val = trim($row['designate_class']);
                        if ($val !== '' && !in_array(strtolower($val), $ignore_list)) {
                            $existing_classes[] = $val;
                        }
                    }
                }
            } catch (Exception $e) {
            }

            try {
                $res = $conn->query("SELECT DISTINCT subject_code FROM employee_assignments WHERE subject_code IS NOT NULL AND subject_code != '' ORDER BY subject_code");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $val = trim($row['subject_code']);
                        if ($val !== '' && !in_array(strtolower($val), $ignore_list)) {
                            $existing_subjects[] = $val;
                        }
                    }
                }
            } catch (Exception $e) {
            }

            try {
                $res = $conn->query("SELECT DISTINCT room_num FROM employee_assignments WHERE room_num IS NOT NULL AND room_num != '' ORDER BY room_num");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $val = trim($row['room_num']);
                        if ($val !== '' && !in_array(strtolower($val), $ignore_list)) {
                            $existing_rooms[] = $val;
                        }
                    }
                }
            } catch (Exception $e) {
            }
            ?>
            <small
                class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <nav class="nav flex-column px-2">
            <?php renderNavigation('Staff Management'); ?>
        </nav>
    </div>

    <div class="content pt-3" id="content">
        <div class="container-fluid">
            <div class="container py-4 mt-5" style="margin-top: -5px !important;">
                <div class="d-flex justify-content-between align-items-center mb-4 pt-4">
                    <h3 class="fw-bold mb-0">Pending Schedule Edits</h3>
                    <?php if ($specific_id): ?>
                        <a href="review_schedule_request.php" class="btn btn-outline-primary shadow-sm hover-scale">
                            <i class="bi bi-arrow-left me-2"></i>View All Pending Requests
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($requests)): ?>
                    <div class="alert alert-info border-0 shadow-sm text-center py-4">
                        <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
                        <h5>No Pending Requests</h5>
                        <p class="text-muted mb-0">There are currently no schedule edits waiting for your approval.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($requests as $request): ?>
                            <?php $scheduleData = json_decode($request['schedule_data'], true); ?>
                            <div class="col-12" id="request-card-<?php echo $request['id']; ?>">
                                <div class="request-card">
                                    <div class="request-header">
                                        <div>
                                            <h5 class="mb-1 fw-bold">
                                                <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                            </h5>
                                            <div class="text-muted small">
                                                ID: <?php echo htmlspecialchars($request['employee_id_string']); ?> |
                                                Department: <?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?> |
                                                Requested:
                                                <?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column flex-md-row gap-2 mt-3 mt-md-0">
                                            <button type="button" class="btn btn-primary edit-request-btn"
                                                data-req-id="<?php echo $request['id']; ?>"
                                                data-emp-id="<?php echo htmlspecialchars($request['employee_id_string'], ENT_QUOTES); ?>"
                                                data-first="<?php echo htmlspecialchars($request['first_name'], ENT_QUOTES); ?>"
                                                data-last="<?php echo htmlspecialchars($request['last_name'], ENT_QUOTES); ?>">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            <button class="btn btn-success" data-bs-toggle="modal"
                                                data-bs-target="#confirmActionModal"
                                                onclick="setupConfirmModal(<?php echo $request['id']; ?>, 'approve')">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                            <button class="btn btn-danger"
                                                onclick="openRejectModal(<?php echo $request['id']; ?>)">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </div>
                                    </div>
                                    <div class="request-body">
                                        <h6 class="fw-bold mb-3">Requested Schedule:</h6>
                                        <!-- Container for JS rendered calendar -->
                                        <div id="calendar-view-<?php echo $request['id']; ?>" class="schedule-calendar-preview"
                                            style="min-height: 200px;"></div>

                                        <!-- Pass raw JSON for this specific request to JS -->
                                        <script>
                                            window.requestSchedules = window.requestSchedules || {};
                                            window.requestSchedules[<?php echo $request['id']; ?>] = <?php echo json_encode($scheduleData); ?>;
                                        </script>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Processing Overlay -->
    <div id="loadingOverlay"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; color:white; flex-direction:column;">
        <div class="spinner-border text-light mb-3" role="status"></div>
        <h4 id="loadingText">Processing...</h4>
    </div>

    <!-- Alert Modal -->
    <div class="modal fade" id="appAlertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title w-100 text-center">Notice</h5>
                </div>
                <div class="modal-body text-center">
                    <p id="appAlertModalMessage" class="mb-0"></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Action Modal (Approve) -->
    <div class="modal fade" id="confirmActionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center pt-0">
                    <div class="mb-3">
                        <i id="confirmActionIcon" class="bi bi-check-circle-fill text-success"
                            style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="modal-title fw-bold mb-3 text-success" id="confirmActionTitle">Confirm Request Approval
                    </h4>
                    <p id="confirmActionMessage" class="text-secondary fs-5 px-3">Are you sure you want to approve this
                        schedule request? This will automatically update the employee's active schedule.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4 pt-0 gap-3">
                    <button type="button" class="btn btn-light px-4 py-2 text-secondary fw-semibold"
                        style="border-radius: 8px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success px-4 py-2 fw-bold" style="border-radius: 8px;"
                        id="confirmActionButton">Yes, Approve Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject with Remarks Modal -->
    <div class="modal fade" id="rejectRemarksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-x-circle me-2"></i>Reject Schedule
                        Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-3">Provide a reason for rejecting this schedule request. The employee
                        will be notified with your remarks.</p>
                    <label for="rejectRemarksInput" class="form-label fw-semibold">Remarks <span
                            class="text-danger">*</span></label>
                    <textarea id="rejectRemarksInput" class="form-control" rows="4"
                        placeholder="e.g. Schedule conflicts with existing assignments..."></textarea>
                    <div id="rejectRemarksError" class="text-danger small mt-1" style="display:none;">Please provide a
                        reason for rejection.</div>
                </div>
                <div class="modal-footer border-0 justify-content-end pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmRejectBtn"><i
                            class="bi bi-x-circle me-1"></i>Confirm Reject</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const menuBtn = document.getElementById('menu-btn');
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');

        if (menuBtn && sidebar && content) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('active');
                content.classList.toggle('shift');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', (e) => {
                // Only on mobile widths (where 'active' class is used)
                if (window.innerWidth <= 991) {
                    if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
                        sidebar.classList.remove('active');
                        content.classList.remove('shift');
                    }
                }
            });
        }
    </script>

    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel"
        aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered edit-schedule-modal-dialog">
            <div class="modal-content border-0 shadow-sm">
                <div class="modal-header">
                    <h4 class="modal-title fw-semibold" id="editScheduleModalLabel">Edit Schedule</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <form id="editScheduleForm" action="processes/update_pending_request.php" method="POST">
                        <input type="hidden" name="request_id" id="edit_request_id" value="">
                        <input type="hidden" name="employee_id" id="edit_employee_id" value="">
                        <input type="hidden" name="first_name" id="edit_first_name" value="">
                        <input type="hidden" name="last_name" id="edit_last_name" value="">
                        <div class="schedule-section">
                            <div class="form-group">
                                <label>Select Working Days:</label>
                                <p class="helper-text">Selected days appear dimmed</p>
                                <div class="day-buttons">
                                    <button type="button" class="day-btn" data-day="Monday"
                                        onclick="toggleDay(this)">Mon</button>
                                    <button type="button" class="day-btn" data-day="Tuesday"
                                        onclick="toggleDay(this)">Tue</button>
                                    <button type="button" class="day-btn" data-day="Wednesday"
                                        onclick="toggleDay(this)">Wed</button>
                                    <button type="button" class="day-btn" data-day="Thursday"
                                        onclick="toggleDay(this)">Thu</button>
                                    <button type="button" class="day-btn" data-day="Friday"
                                        onclick="toggleDay(this)">Fri</button>
                                    <button type="button" class="day-btn" data-day="Saturday"
                                        onclick="toggleDay(this)">Sat</button>
                                    <button type="button" class="day-btn" data-day="Sunday"
                                        onclick="toggleDay(this)">Sun</button>
                                </div>
                                <input type="hidden" name="work_days" id="work_days" value="">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="shift_start">Shift Start Time:</label>
                                    <input type="time" id="shift_start" name="shift_start">
                                </div>
                                <div class="form-group">
                                    <label for="shift_end">Shift End Time:</label>
                                    <input type="time" id="shift_end" name="shift_end">
                                </div>
                            </div>
                            <!-- Faculty specific styling is handled via CSS or JS in this unified modal -->
                            <div class="form-row" id="faculty-fields">
                                <div class="form-group custom-dropdown">
                                    <label for="designate_class" style="min-height: 45px;">Designate Class <span
                                            style="display: block; color: #999; font-size: 0.9em;">(Faculty Only -
                                            Optional)</span></label>
                                    <input type="text" id="designate_class" name="designate_class"
                                        placeholder="Select or type class name" autocomplete="off"
                                        style="text-transform: uppercase;">
                                    <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing
                                        to see existing classes</small>
                                </div>
                                <div class="form-group custom-dropdown">
                                    <label for="designate_subject" style="min-height: 45px;">Subject <span
                                            style="display: block; color: #999; font-size: 0.9em;">(Faculty Only -
                                            Optional)</span></label>
                                    <input type="text" id="designate_subject" name="designate_subject"
                                        placeholder="Select or type subject" autocomplete="off"
                                        style="text-transform: uppercase;">
                                    <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing
                                        to see existing subjects</small>
                                </div>
                                <div class="form-group custom-dropdown">
                                    <label for="room-number" style="min-height: 45px;">Room Number <span
                                            style="display: block; color: #999; font-size: 0.9em;">(Faculty Only -
                                            Optional)</span></label>
                                    <input type="text" id="room-number" name="room-number"
                                        placeholder="Select or type room number" autocomplete="off"
                                        style="text-transform: uppercase;">
                                    <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing
                                        to see existing rooms</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="display: flex; gap: 10px; justify-content: flex-end;">
                                    <button type="button" id="add-schedule-btn"
                                        class="btn-modern btn-outline text-success border-success"
                                        style="min-width: 140px;" onclick="addSchedule()">Add
                                        Schedule</button>
                                    <button type="button" id="edit-schedule-btn"
                                        class="btn-modern btn-outline text-secondary border-secondary"
                                        style="min-width: 140px;" onclick="editSchedule()" disabled>Update Selected
                                        Schedule</button>
                                    <button type="button" class="btn-modern btn-outline text-danger border-danger"
                                        style="min-width: 140px;" onclick="clearScheduleForm()">Cancel</button>
                                </div>
                            </div>

                            <div class="schedule-calendar-section">
                                <div class="schedule-header">
                                    <h3>Schedule</h3>
                                    <button type="button"
                                        class="btn-modern btn-outline text-danger border-danger fw-bold"
                                        style="min-width: 200px;" onclick="clearAllSchedules()">
                                        Clear All Schedules
                                    </button>
                                </div>
                                <div class="calendar-wrapper">
                                    <div class="schedule-calendar" id="edit-schedule-calendar">
                                        <div class="time-header"></div>

                                        <div class="day-header" data-day="Monday">Mon</div>
                                        <div class="day-header" data-day="Tuesday">Tue</div>
                                        <div class="day-header" data-day="Wednesday">Wed</div>
                                        <div class="day-header" data-day="Thursday">Thu</div>
                                        <div class="day-header" data-day="Friday">Fri</div>
                                        <div class="day-header" data-day="Saturday">Sat</div>
                                        <div class="day-header" data-day="Sunday">Sun</div>

                                        <div id="calendar-grid"></div>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="schedule_data" id="schedule_data">

                        </div>

                        <div class="form-actions text-end mt-4">
                            <button type="button" class="btn-modern btn-outline text-danger border-danger px-4"
                                style="min-width: 150px;" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit"
                                class="btn-modern btn-outline btn-save text-success border-success px-4"
                                style="min-width: 150px;">Save Changes</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>


    <!-- HELPER MODALS FOR EDIT SCHEDULE (Moved to end for z-index stacking) -->
    <div class="modal fade" id="scheduleNoWorkDayModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-warning">No Working Day Selected</h5>
                <p id="scheduleNoWorkDayMsg">Please select at least one working day first!</p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4"
                        data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleMissingTimeModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-warning">Missing Information</h5>
                <p id="scheduleMissingTimeMsg">Please select both start and end times!</p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4"
                        data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleInvalidTimeModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-danger">Invalid Time Range</h5>
                <p id="scheduleInvalidTimeMsg">Start time must be before end time!</p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4"
                        data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleFacultyMissingModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-warning">Required Fields</h5>
                <p id="scheduleFacultyMissingMsg">Faculty members must enter class, subject, and room number for
                    schedules!</p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4"
                        data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleAddedSuccessModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-success">Schedule Added Successfully</h5>
                <p id="scheduleAddedSuccessMsg">Your schedule has been added.</p>
                <div class="mt-3">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleUpdatedSuccessModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-success">Schedule Updated Successfully</h5>
                <p id="scheduleUpdatedSuccessMsg">Your schedule has been updated.</p>
                <div class="mt-3">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleClearConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-danger">Confirm Clear All</h5>
                <p id="scheduleClearConfirmMsg">Are you sure you want to clear all schedules?</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4"
                        data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4"
                        id="scheduleClearConfirmBtn">Yes, Clear All</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleDeleteConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-danger">Confirm Delete</h5>
                <p id="scheduleDeleteConfirmMsg">Are you sure you want to delete this schedule?</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="scheduleDeleteConfirmBtn">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleClearedSuccessModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-success">Schedules Cleared</h5>
                <p id="scheduleClearedSuccessMsg">All schedules have been cleared!</p>
                <div class="mt-3">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleSavedSuccessModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-success">Schedules Saved</h5>
                <p id="scheduleSavedSuccessMsg">Schedule request updated successfully!</p>
                <div class="mt-3">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal"
                        onclick="window.location.reload();">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleNoDataModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center shadow">
                <h5 class="fw-bold mb-3 text-info">No Schedules</h5>
                <p id="scheduleNoDataMsg">No schedules to clear!</p>
                <div class="mt-3">
                    <button type="button" class="btn btn-info text-white" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>


    <script src="../assets/js/edit_employee.js?v=<?php echo time(); ?>"></script>
    <script>
        // Options for User Dropdowns inside the Edit Modal
        window.existingClasses = <?php echo json_encode($existing_classes); ?>;
        window.existingSubjects = <?php echo json_encode($existing_subjects); ?>;
        window.existingRooms = <?php echo json_encode($existing_rooms); ?>;

        // Define function to open modal with specifics
        window.openEditRequestModal = function (requestId, employeeIdString, firstName, lastName) {

            // Set fields inside form
            const idField = document.getElementById('edit_request_id');
            if (idField) idField.value = requestId;
            const empIdField = document.getElementById('edit_employee_id');
            if (empIdField) empIdField.value = employeeIdString;
            const fnameField = document.getElementById('edit_first_name');
            if (fnameField) fnameField.value = firstName;
            const lnameField = document.getElementById('edit_last_name');
            if (lnameField) lnameField.value = lastName;

            // Re-populate modal's logic arrays directly to memory without breaking the reference used by edit_employee.js
            if (window.editAddedSchedules) {
                window.editAddedSchedules.length = 0;
                const sourceSchedules = window.requestSchedules[requestId] || [];
                sourceSchedules.forEach(schedule => {
                    if (!schedule.color && typeof getRandomEditScheduleColor === 'function') {
                        schedule.color = getRandomEditScheduleColor();
                    }
                    window.editAddedSchedules.push(schedule);
                });
            } else {
                window.editAddedSchedules = [...(window.requestSchedules[requestId] || [])];
            }
            window.existingSchedules = [...window.editAddedSchedules];

            // Clear inputs using edit_employee.js standard function
            if (typeof clearScheduleForm === 'function') {
                clearScheduleForm();
            }

            // Open modal
            const editModalEl = document.getElementById('editScheduleModal');
            if (editModalEl) {
                const editModal = new bootstrap.Modal(editModalEl);
                editModal.show();

                // Initialize calendars (this will setup grid and render schedules)
                setTimeout(() => {
                    if (typeof initializeCalendar === 'function') {
                        initializeCalendar();
                    } else if (typeof renderSchedules === 'function') {
                        renderSchedules();
                    }

                    const cal = document.getElementById('edit-schedule-calendar');
                    if (cal) cal.style.display = 'grid'; // Ensure grid is visible

                    const noSchMsg = document.getElementById('noScheduleMsg');
                    if (noSchMsg && window.editAddedSchedules.length > 0) {
                        noSchMsg.style.display = 'none';
                    } else if (noSchMsg) {
                        noSchMsg.style.display = 'block';
                    }
                }, 300);
            } else {
                alert('Edit modal not found.');
            }
        };

        // Add click listeners to all Edit buttons
        document.addEventListener('DOMContentLoaded', function () {
            const editBtns = document.querySelectorAll('.edit-request-btn');
            editBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    console.log('Edit button clicked for request:', this.dataset.reqId);
                    window.openEditRequestModal(
                        this.dataset.reqId,
                        this.dataset.empId,
                        this.dataset.first,
                        this.dataset.last
                    );
                });
            });
        });
    </script>

    <script>
        function showAppAlert(message) {
            document.getElementById('appAlertModalMessage').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('appAlertModal'));
            modal.show();
        }

        // --- Standard 12-Hour Time Formatter ---
        function formatAmPm(timeString) {
            if (!timeString) return '';
            // Handle both "HH:MM" and "HH:MM:SS" formats
            let [hours, minutes] = timeString.split(':');
            hours = parseInt(hours, 10);
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            return hours + ':' + minutes + ' ' + ampm;
        }

        // --- Calendar Builder ---
        function renderRequestCalendars() {
            const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const colors = ['#4a7c59', '#8b4a6b', '#b85450', '#5b9bd5', '#ffc000', '#c55a11', '#7030a0'];

            if (!window.requestSchedules) return;

            for (const [reqId, scheduleBlocks] of Object.entries(window.requestSchedules)) {
                const container = document.getElementById(`calendar-view-${reqId}`);
                if (!container) continue;

                // Build a CSS grid approach simulating the staff profile visual schedule
                let html = `
                    <style>
                        .visual-schedule {
                            display: grid;
                            grid-template-columns: 80px repeat(7, 1fr);
                            gap: 1px;
                            background-color: var(--border-color, #e2e8f0);
                            border: 1px solid var(--border-color, #e2e8f0);
                            border-radius: var(--border-radius, 8px);
                            overflow: hidden;
                            min-width: 800px;
                        }
                        .vs-header {
                            background-color: var(--bg-light, #f8fafc);
                            font-weight: 600;
                            text-align: center;
                            padding: 10px 5px;
                            color: var(--text-color, #333);
                            font-size: 0.9em;
                        }
                        .vs-col {
                            background-color: white;
                            display: flex;
                            flex-direction: column;
                            position: relative;
                            min-height: 200px;
                        }
                        .vs-time-label {
                            text-align: right;
                            padding: 5px 10px;
                            color: var(--text-color-light, #64748b);
                            font-size: 0.8em;
                            border-bottom: 1px solid var(--border-color, #e2e8f0);
                            height: 40px;
                            display: flex;
                            align-items: center;
                            justify-content: flex-end;
                            background-color: var(--bg-light, #f8fafc);
                        }
                        .vs-cell {
                            border-bottom: 1px dashed var(--border-color, #e2e8f0);
                            height: 40px;
                            box-sizing: border-box;
                        }
                        .vs-block {
                            position: relative;
                            margin: 2px;
                            padding: 8px 4px;
                            border-radius: 4px;
                            color: white;
                            font-size: 0.75em;
                            display: flex;
                            flex-direction: column;
                            justify-content: center;
                            align-items: center;
                            text-align: center;
                            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                            transition: transform 0.2s, box-shadow 0.2s;
                            min-height: 60px;
                            border-left: 3px solid rgba(255,255,255,0.3);
                        }
                        .calendar-scroll-wrap {
                            overflow-x: auto;
                            padding-bottom: 10px;
                        }
                    </style>
                    <div class="calendar-scroll-wrap">
                        <div class="visual-schedule">
                            <!-- Headers -->
                            <div class="vs-header">Time</div>
                `;

                dayNames.forEach(day => {
                    html += `<div class="vs-header">${day}</div>`;
                });

                // Get min/max times to bound the grid, defaulting to 7AM to 5PM
                let minHour = 7;
                let maxHour = 17;

                scheduleBlocks.forEach(b => {
                    if (b.startTime) minHour = Math.min(minHour, parseInt(b.startTime.split(':')[0], 10));
                    if (b.endTime) maxHour = Math.max(maxHour, Math.ceil(parseInt(b.endTime.split(':')[0], 10) + (parseInt(b.endTime.split(':')[1], 10) > 0 ? 1 : 0)));
                });

                minHour = Math.max(0, minHour - 1); // 1 hr padding
                maxHour = Math.min(24, maxHour + 1);

                // Build time column
                html += `<div class="vs-col" style="background-color: var(--bg-light, #f8fafc);">`;
                for (let h = minHour; h <= maxHour; h++) {
                    const timeLabel = formatAmPm(`${h.toString().padStart(2, '0')}:00`);
                    html += `<div class="vs-time-label" style="height:60px;">${timeLabel}</div>`;
                }
                html += `</div>`;

                // Build Day Columns
                dayNames.forEach(day => {
                    html += `<div class="vs-col">`;

                    // Add background grid lines
                    for (let h = minHour; h <= maxHour; h++) {
                        html += `<div class="vs-cell" style="height:60px;"></div>`;
                    }

                    // Map blocks that fall on this day
                    const dayBlocks = scheduleBlocks.filter(b => b.days.includes(day));
                    dayBlocks.forEach((block, idx) => {
                        const startParts = block.startTime.split(':');
                        const endParts = block.endTime.split(':');

                        const startHour = parseInt(startParts[0], 10);
                        const startMin = parseInt(startParts[1], 10);
                        const endHour = parseInt(endParts[0], 10);
                        const endMin = parseInt(endParts[1], 10);

                        // Calculate pixel positions (60px per hour)
                        const startPos = ((startHour - minHour) * 60) + (startMin / 60 * 60);
                        const endPos = ((endHour - minHour) * 60) + (endMin / 60 * 60);
                        const height = endPos - startPos;

                        const color = block.color || colors[idx % colors.length];

                        let blockContent = '';
                        if (block.class && block.class !== 'N/A' && block.subject && block.subject !== 'GENERAL') {
                            blockContent = `<div class="fw-bold">${block.subject}</div><div>${block.class}</div>`;
                        } else {
                            blockContent = `<div class="fw-bold text-uppercase">Work Shift</div>`;
                        }

                        const timeRange = `${formatAmPm(block.startTime)} - ${formatAmPm(block.endTime)}`;

                        html += `
                            <div class="vs-block" style="position: absolute; top: ${startPos}px; left: 0; right: 0; height: ${height}px; background-color: ${color};">
                                ${blockContent}
                                <div style="font-size: 0.9em; opacity: 0.9;">${timeRange}</div>
                            </div>
                        `;
                    });

                    html += `</div>`;
                });

                html += `</div></div>`; // End grid and scroller wrap
                container.innerHTML = html;
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            renderRequestCalendars();
        });

        let currentRequestId = null;
        let currentAction = null;

        function setupConfirmModal(requestId, action) {
            currentRequestId = requestId;
            currentAction = action;
        }

        function openRejectModal(requestId) {
            currentRequestId = requestId;
            currentAction = 'reject';
            // Reset state
            document.getElementById('rejectRemarksInput').value = '';
            document.getElementById('rejectRemarksError').style.display = 'none';
            const modal = new bootstrap.Modal(document.getElementById('rejectRemarksModal'));
            modal.show();
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('confirmRejectBtn').addEventListener('click', function () {
                const remarks = document.getElementById('rejectRemarksInput').value.trim();
                if (!remarks) {
                    document.getElementById('rejectRemarksError').style.display = 'block';
                    return;
                }
                document.getElementById('rejectRemarksError').style.display = 'none';
                const modalEl = document.getElementById('rejectRemarksModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                executeProcessRequest(currentRequestId, 'reject', remarks);
            });
        });

        document.getElementById('confirmActionButton').addEventListener('click', function () {
            const modalEl = document.getElementById('confirmActionModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }

            executeProcessRequest(currentRequestId, currentAction);
        });

        function executeProcessRequest(requestId, action, remarks) {
            document.getElementById('loadingOverlay').style.display = 'flex';
            document.getElementById('loadingText').textContent = action === 'approve' ? 'Approving...' : 'Rejecting...';

            const formData = new FormData();
            formData.append('request_id', requestId);
            formData.append('action', action);
            if (remarks) {
                formData.append('remarks', remarks);
            }

            fetch('processes/process_schedule_approval.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingOverlay').style.display = 'none';
                    if (data.success) {
                        // Remove card from UI
                        const card = document.getElementById('request-card-' + requestId);
                        card.style.transition = "opacity 0.4s";
                        card.style.opacity = "0";
                        setTimeout(() => {
                            card.remove();
                            // Check if it was the last one
                            const remaining = document.querySelectorAll('.request-card');
                            if (remaining.length === 0) {
                                location.reload(); // Reload to show empty state
                            }
                        }, 400);
                    } else {
                        showAppAlert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    document.getElementById('loadingOverlay').style.display = 'none';
                    showAppAlert('An error occurred while processing the request.');
                    console.error(error);
                });
        }
        function showLogoutModal() {
            var modal = new bootstrap.Modal(document.getElementById('logoutModal'));
            modal.show();
        }
    </script>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <h5 class="mb-3">Confirm Logout</h5>
                    <p class="mb-0">Are you sure you want to log out?</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <form method="POST" action="logout.php" style="display:inline;">
                        <input type="hidden" name="confirm_logout" value="1">
                        <button type="submit" class="btn btn-danger">Yes, Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
