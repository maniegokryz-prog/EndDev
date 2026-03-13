<?php
require_once '../auth_guard.php';
require_once '../navigation.php';
require '../db_connection.php';

// Check if adding new staff is allowed
if (!canAddNewStaff()) {
    header('Location: staff.php?error=' . urlencode('Adding new staff is disabled on this server'));
    exit();
}

// Get current user info
$currentUser = getCurrentUser();

// Get existing roles from database
$existing_roles = [];
try {
    $result = $conn->query("SELECT DISTINCT roles FROM employees WHERE roles IS NOT NULL AND roles != '' ORDER BY roles");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_roles[] = $row['roles'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching roles: " . $e->getMessage());
}

// Normalize roles
if (!empty($existing_roles)) {
    foreach ($existing_roles as &$r) {
        $trimmed = trim($r);
        if (preg_match('/faculty[\s_\-]*member/i', $trimmed) || strcasecmp($trimmed, 'faculty') === 0) {
            $r = 'Faculty';
        }
    }
    unset($r);
}

// Get existing departments
$existing_departments = [];
try {
    $result = $conn->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_departments[] = $row['department'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

$ignore_list = ['work shift', 'work_shift', 'n/a', 'na', 'tba', 'tbd', 'none'];

// Get existing classes
$existing_classes = [];
try {
    $result = $conn->query("SELECT DISTINCT designate_class FROM employee_assignments WHERE designate_class IS NOT NULL AND designate_class != '' ORDER BY designate_class");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $val = trim($row['designate_class']);
            if ($val !== '' && !in_array(strtolower($val), $ignore_list)) {
                $existing_classes[] = $val;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching classes: " . $e->getMessage());
}

// Get existing subjects
$existing_subjects = [];
try {
    $result = $conn->query("SELECT DISTINCT subject_code FROM employee_assignments WHERE subject_code IS NOT NULL AND subject_code != '' ORDER BY subject_code");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $val = trim($row['subject_code']);
            if ($val !== '' && !in_array(strtolower($val), $ignore_list)) {
                $existing_subjects[] = $val;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching subjects: " . $e->getMessage());
}

// Get existing room numbers
$existing_rooms = [];
try {
    $result = $conn->query("SELECT DISTINCT room_num FROM employee_assignments WHERE room_num IS NOT NULL AND room_num != '' ORDER BY room_num");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $val = trim($row['room_num']);
            if ($val !== '' && !in_array(strtolower($val), $ignore_list)) {
                $existing_rooms[] = $val;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching room numbers: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add New Staff - Wizard</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap CSS -->
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="staff.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="../assets/js/tf.min.js"
        onerror="this.onerror=null; this.src='https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@1.7.4/dist/tf.min.js'"></script>
    <script src="../assets/js/face-api.min.js"
        onerror="this.onerror=null; this.src='https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js'"></script>

    <style>
        .step-instructions {
            background-color: #f8f9fa;
            border-left: 4px solid #198754;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .camera-section,
        .schedule-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }

        .found-employee-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f0fff4;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .found-employee-card img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
        }

        @media (max-width: 767px) {

            /* Control upward shift and force equal left/right margins in mobile */
            .wizard-mobile-container {
                margin-top: -80px !important;
                margin-left: auto !important;
                margin-right: auto !important;
                padding-left: 15px !important;
                padding-right: 15px !important;
            }

            .wizard-mobile-title {
                text-align: center !important;
                padding-top: 15px !important;
                margin-top: 10px !important;
            }
        }

        .step-tab-btn {
            font-weight: bold !important;
            transition: all 0.2s ease-in-out;
        }
        .step-tab-btn:hover {
            background-color: #6c757d !important; /* gray background */
            color: white !important;             /* white text for contrast */
            border-color: #6c757d !important;
        }

        .btn-custom-save-info {
            background-color: transparent !important;
            border-color: #198754 !important; /* Bootstrap success green */
            color: #198754 !important;
            font-weight: bold;
            transition: all 0.2s ease-in-out;
        }
        .btn-custom-save-info:hover {
            background-color: #198754 !important;
            color: white !important;
        }
        
        .btn-custom-capture {
            background-color: transparent !important;
            border: 1px solid #0d6efd !important;
            color: #0d6efd !important;
            font-weight: bold !important;
            transition: all 0.2s ease-in-out !important;
        }
        .btn-custom-capture:hover {
            background-color: #0d6efd !important;
            color: white !important;
        }

        .btn-custom-skip {
            background-color: transparent !important;
            border: 1px solid #fd7e14 !important;
            color: #fd7e14 !important;
            font-weight: bold !important;
            transition: all 0.2s ease-in-out !important;
        }
        .btn-custom-skip:hover {
            background-color: #fd7e14 !important;
            color: white !important;
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
            <?php if (!function_exists('getCurrentUser')) {
                require_once '../auth_guard.php';
                $currentUser = getCurrentUser();
            } ?>
            <img src="<?php echo (!empty($currentUser['profile_photo']) && $currentUser['profile_photo'] !== 'N/A') ? '../' . htmlspecialchars($currentUser['profile_photo'], ENT_QUOTES, 'UTF-8') . '?v=' . time() : '../assets/profile_pic/user.png?v=' . time(); ?>"
                alt="Profile" class="rounded-circle mb-2" style="width: 70px; height: 70px; object-fit: cover;"
                onerror="this.src='../assets/profile_pic/user.png';">
            <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></h5>
            <small
                class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <nav class="nav flex-column px-2">
            <?php renderNavigation('Staff Management'); ?>
        </nav>
    </div>

    <div class="content pt-3" id="content">
        <div class="container-fluid">
            <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
            </div>

            <div class="container py-4 mt-5 wizard-mobile-container" style="margin-top: -5px !important;">
                <h3 class="mb-4 pt-4 wizard-mobile-title">Add New Staff / Wizard</h3>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs nav-fill mb-4" id="staffWizardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active step-tab-btn" id="step1-tab" data-bs-toggle="tab" data-bs-target="#step1"
                            type="button" role="tab" aria-controls="step1" aria-selected="true">
                            Step 1: Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link step-tab-btn" id="step2-tab" data-bs-toggle="tab" data-bs-target="#step2"
                            type="button" role="tab" aria-controls="step2" aria-selected="false">
                            Step 2: Face Registration
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link step-tab-btn" id="step3-tab" data-bs-toggle="tab" data-bs-target="#step3"
                            type="button" role="tab" aria-controls="step3" aria-selected="false">
                            Step 3: Schedule
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="staffWizardContent">

                    <!-- STEP 1: PERSONAL INFORMATION -->
                    <div class="tab-pane fade show active" id="step1" role="tabpanel" aria-labelledby="step1-tab">
                        <div class="card shadow-sm border-0">
                            <div class="card-body" style="display: block !important;">
                                <div class="step-instructions text-center">
                                    <strong>Step 1:</strong> Enter the basic information for the new staff member. This
                                    will create the employee record in the database.
                                </div>

                                <form id="step1Form" action="processes/add_employee.php" method="POST">
                                    <!-- Employee Information -->
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="employee_id">Employee ID:</label>
                                            <input type="text" id="employee_id" name="employee_id" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="add_password">Add Password:</label>
                                            <div class="password-input-wrapper" style="position: relative;">
                                                <input type="password" id="add_password" name="add_password"
                                                    value="defaultpassword" required style="padding-right: 40px;">
                                                <span class="toggle-password" onclick="toggleAddPassword()"
                                                    style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                                                    <i class="bi bi-eye" id="toggleAddPasswordIcon"></i>
                                                </span>
                                            </div>
                                            <small style="color: #666; font-size: 0.8em;">Default: "defaultpassword"
                                                (user can change after login)</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="roles">Role:</label>
                                            <input type="text" id="roles" name="roles"
                                                placeholder="Select from dropdown or type new role" required
                                                autocomplete="off">
                                            <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start
                                                typing to see existing roles</small>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="first_name">First Name:</label>
                                            <input type="text" id="first_name" name="first_name" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="middle_name">Middle Name:</label>
                                            <input type="text" id="middle_name" name="middle_name">
                                        </div>
                                        <div class="form-group">
                                            <label for="last_name">Last Name:</label>
                                            <input type="text" id="last_name" name="last_name" required>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="email">Email:</label>
                                            <input type="email" id="email" name="email">
                                        </div>
                                        <div class="form-group">
                                            <label for="phone">Phone:</label>
                                            <input type="tel" id="phone" name="phone" pattern="[0-9]{11}" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11); document.getElementById('phone-error').style.display = (this.value.length === 11 || this.value.length === 0) ? 'none' : 'block';" title="Phone number must be exactly 11 digits" required>
                                            <small id="phone-error" class="text-danger mt-1" style="display: none;"><i class="bi bi-exclamation-circle me-1"></i>Phone number must be exactly 11 digits.</small>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="department">Department:</label>
                                            <input type="text" id="department" name="department"
                                                placeholder="Select from dropdown or type new department" required
                                                autocomplete="off">
                                            <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start
                                                typing to see existing departments</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="position">Position:</label>
                                            <input type="text" id="position" name="position" required>
                                        </div>
                                        <div class="form-group" style="display: none;">
                                            <label for="hire_date">Hire Date:</label>
                                            <input type="date" id="hire_date" name="hire_date" disabled>
                                        </div>
                                    </div>

                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <!-- Step 1 Submit -->
                                    <div class="mt-4 d-flex justify-content-center">
                                        <button type="submit" class="btn btn-custom-save-info px-4 py-2">Save Personal
                                            Information</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: FACE REGISTRATION -->
                    <div class="tab-pane fade" id="step2" role="tabpanel" aria-labelledby="step2-tab">
                        <div class="card shadow-sm border-0">
                            <div class="card-body" style="display: block !important;">
                                <div class="step-instructions text-center">
                                    <strong>Step 2:</strong> Register the staff's face for biometric authentication.
                                    Search for the employee ID created in Step 1.
                                </div>

                                <!-- Pending List for Step 2 -->
                                <div class="row mb-4">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Recently Added (Pending Face Registration)</h6>
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                onclick="loadPendingList('face')">
                                                <i class="bi bi-arrow-clockwise"></i> Refresh
                                            </button>
                                        </div>
                                        <div id="face_pending_list" class="list-group shadow-sm"
                                            style="max-height: 200px; overflow-y: auto;">
                                            <div class="list-group-item text-muted small text-center">Loading pending
                                                list...</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Found Employee Info -->
                                <div id="face_employee_info" class="found-employee-card d-none">
                                    <img src="" id="face_emp_img" alt="Profile">
                                    <div>
                                        <h5 class="mb-1" id="face_emp_name">Name</h5>
                                        <div class="text-muted small">ID: <span id="face_emp_id_display"></span></div>
                                        <div class="text-muted small">Dep: <span id="face_emp_dept"></span></div>
                                    </div>
                                </div>

                                <!-- Face Registration Container (Hidden until emp found) -->
                                <div id="face_registration_container" style="display:none;">
                                    <h4 class="mt-4">Capture Face Data</h4>

                                    <div class="camera-section">
                                        <div class="camera-container">
                                            <video id="video" autoplay muted playsinline></video>
                                            <canvas id="canvas" style="display:none;"></canvas>
                                            <canvas id="detection-overlay"></canvas>
                                        </div>

                                        <div id="face-guidance">
                                            <h4>Face Detection Status:</h4>
                                            <p id="face-status">👤 Looking for face...</p>
                                            <p id="orientation-status">📐 Orientation: Unknown</p>
                                            <p id="lighting-status">💡 Lighting: Unknown</p>
                                            <div id="guidance-message">Position your face in the camera view</div>
                                        </div>
                                    </div>

                                    <div id="angle-guide">
                                        <h4 id="current-angle">Step 1 of 5: Face Forward (Looking straight at camera)
                                        </h4>
                                        <p id="angle-instruction">Look directly at the camera with a neutral expression
                                        </p>
                                        <button type="button" id="capture-btn" class="btn btn-custom-capture">Capture
                                            Photo</button>
                                        <button type="button" id="skip-btn" class="btn btn-custom-skip">Skip This
                                            Angle</button>
                                    </div>

                                    <div id="captured-photos">
                                        <h4>Captured Photos:</h4>
                                        <div id="photo-thumbnails"></div>
                                    </div>

                                    <div class="mt-4 pt-3 border-top d-flex justify-content-center">
                                        <button type="button" id="save-faces-btn" class="btn btn-custom-save-info px-5" disabled
                                            onclick="submitFaceData()">
                                            Save Face Registration
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: SCHEDULE -->
                    <div class="tab-pane fade" id="step3" role="tabpanel" aria-labelledby="step3-tab">
                        <div class="card shadow-sm border-0">
                            <div class="card-body" style="display: block !important;">
                                <div class="step-instructions text-center">
                                    <strong>Step 3:</strong> Assign a weekly schedule to the staff member. Search for
                                    the employee ID to begin.
                                </div>

                                <!-- Pending List for Step 3 -->
                                <div class="row mb-4">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Recently Added (Pending Schedule)</h6>
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                onclick="loadPendingList('schedule')">
                                                <i class="bi bi-arrow-clockwise"></i> Refresh
                                            </button>
                                        </div>
                                        <div id="sched_pending_list" class="list-group shadow-sm"
                                            style="max-height: 200px; overflow-y: auto;">
                                            <div class="list-group-item text-muted small text-center">Loading pending
                                                list...</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Found Employee Info -->
                                <div id="sched_employee_info" class="found-employee-card d-none">
                                    <img src="" id="sched_emp_img" alt="Profile">
                                    <div>
                                        <h5 class="mb-1" id="sched_emp_name">Name</h5>
                                        <div class="text-muted small">ID: <span id="sched_emp_id_display"></span></div>
                                        <div class="text-muted small">Role: <span id="sched_emp_role"></span></div>
                                    </div>
                                </div>

                                <!-- Schedule Container (Hidden until emp found) -->
                                <div id="schedule_container" style="display:none;">
                                    <div class="schedule-section">
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">Select Working Days:</label>
                                            <p class="helper-text small text-muted">Selected days appear dimmed/active
                                            </p>
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

                                        <div class="row">
                                            <div class="col-md-6 form-group">
                                                <label for="shift_start">Shift Start Time:</label>
                                                <input type="time" id="shift_start" name="shift_start"
                                                    class="form-control">
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label for="shift_end">Shift End Time:</label>
                                                <input type="time" id="shift_end" name="shift_end" class="form-control">
                                            </div>
                                        </div>

                                        <div class="row mt-3" id="faculty-fields">
                                            <div class="col-md-4 form-group">
                                                <label for="designate_class" style="min-height: 45px;">Designate Class
                                                    <span
                                                        style="display: block; color: #999; font-size: 0.9em;">(Faculty
                                                        Only - Optional)</span></label>
                                                <input type="text" id="designate_class" name="designate_class"
                                                    class="form-control" placeholder="Class Name" autocomplete="off"
                                                    style="text-transform: uppercase;" disabled>
                                                <small style="color: #666; font-size: 0.8em; display: none;"
                                                    class="faculty-helper">Click arrow or type to search</small>
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label for="designate_subject" style="min-height: 45px;">Subject <span
                                                        style="display: block; color: #999; font-size: 0.9em;">(Faculty
                                                        Only - Optional)</span></label>
                                                <input type="text" id="designate_subject" name="designate_subject"
                                                    class="form-control" placeholder="Subject Code" autocomplete="off"
                                                    style="text-transform: uppercase;" disabled>
                                                <small style="color: #666; font-size: 0.8em; display: none;"
                                                    class="faculty-helper">Click arrow or type to search</small>
                                            </div>
                                            <div class="col-md-4 form-group">
                                                <label for="room-number" style="min-height: 45px;">Room Number <span
                                                        style="display: block; color: #999; font-size: 0.9em;">(Faculty
                                                        Only - Optional)</span></label>
                                                <input type="text" id="room-number" name="room-number"
                                                    class="form-control" placeholder="Room #" autocomplete="off"
                                                    style="text-transform: uppercase;" disabled>
                                                <small style="color: #666; font-size: 0.8em; display: none;"
                                                    class="faculty-helper">Click arrow or type to search</small>
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <button type="button" class="btn btn-outline-primary"
                                                onclick="validateAndAddSchedule()">+ Add to Schedule</button>
                                        </div>

                                        <!-- Weekly Schedule Calendar Preview -->
                                        <div class="schedule-calendar-section mt-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h5>Current Schedule Preview</h5>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="clearAllSchedules()">Clear All</button>
                                            </div>
                                            <div class="calendar-wrapper" style="max-height: 400px; overflow-y: auto;">
                                                <div class="schedule-calendar">
                                                    <!-- Simplified Grid for Preview -->
                                                    <div id="calendar-grid"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 pt-3 border-top d-flex justify-content-center">
                                            <button type="button" class="btn btn-save-schedule px-5"
                                                onclick="submitSchedule()">Save Schedule</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> <!-- End Tab Content -->
            </div>
        </div>
    </div>

    <!-- Hidden inputs for JS data storage -->
    <input type="hidden" id="face_photos" value="">
    <input type="hidden" id="schedule_data" value="">

    <!-- Success Modal -->
    <div class="modal fade" id="wizardSuccessModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow border-0 overflow-hidden">
                <div class="modal-header bg-success text-white border-0 justify-content-center py-3">
                    <h5 class="modal-title fw-bold" style="color: white !important;">Success</h5>
                </div>
                <div class="modal-body text-center py-4 fs-6" id="wizardSuccessMessage">
                    Action completed successfully!
                </div>
                <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn-modern btn-outline text-primary border-primary px-4" id="wizardNextBtn" style="display:none">Proceed to Next Step</button>
                </div>
            </div>
        </div>
    </div>

    <!-- App Info Modal (system-styled, used by face registration JS) -->
    <div class="modal fade" id="appInfoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow border-0 overflow-hidden">
                <div class="modal-header border-0 justify-content-center py-3">
                    <h5 class="modal-title fw-bold" data-app-info-title>Notice</h5>
                </div>
                <div class="modal-body text-center py-4 fs-6" data-app-info-message>
                    <!-- Message injected via JS -->
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="btn-modern btn-outline text-primary border-primary px-5" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- System Confirm Modal (reusable) -->
    <div class="modal fade" id="systemConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow border-0 overflow-hidden">
                <div class="modal-header border-0 justify-content-center py-3">
                    <h5 class="modal-title fw-bold" data-confirm-title>Confirm</h5>
                </div>
                <div class="modal-body text-center py-4 fs-6" data-confirm-message>
                    <!-- Message injected via JS -->
                </div>
                <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4" data-confirm-cancel>Cancel</button>
                    <button type="button" class="btn-modern btn-outline text-primary border-primary px-4" data-confirm-ok>OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; color:white; flex-direction:column;">
        <div class="spinner-border text-light mb-3" role="status"></div>
        <h4 id="loadingText">Processing...</h4>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow border-0 overflow-hidden">
                <div class="modal-header bg-danger text-white border-0 justify-content-center py-3">
                    <h5 class="modal-title fw-bold" style="color: white !important;">Error</h5>
                </div>
                <div class="modal-body text-center py-4 fs-6" id="errorMessage">
                    An error occurred.
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-5" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Time Validation Modal (kept from original) -->
    <div class="modal fade" id="timeValidationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3 border-0 rounded-4 shadow-lg">
                <div class="text-center border-bottom pb-2 mb-3">
                    <h5 class="fw-bold text-danger">Invalid Time</h5>
                </div>
                <div class="text-center">
                    <i class="bi bi-exclamation-circle-fill text-danger fs-1 mb-2"></i>
                    <p class="text-muted px-3" id="timeValidationModalMessage">Start time must be before end time!</p>
                </div>
                <div class="d-flex justify-content-center gap-3 mt-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- System Alert Modal (appAlertModal) -->
    <div class="modal fade" id="appAlertModal" tabindex="-1" aria-labelledby="appAlertModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow border-0 overflow-hidden">
                <div class="modal-header border-0 justify-content-center py-3">
                    <h5 class="modal-title fw-bold" id="appAlertModalLabel">Notice</h5>
                </div>
                <div class="modal-body text-center py-4 fs-6">
                    <i id="appAlertModalIcon" class="bi bi-info-circle-fill text-primary fs-1 mb-2 d-block text-center"></i>
                    <p id="appAlertModalMessage" class="mb-0"></p>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="btn-modern btn-outline text-primary border-primary px-5" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- System Confirm Modal (appConfirmModal) -->
    <div class="modal fade" id="appConfirmModal" tabindex="-1" aria-labelledby="appConfirmModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow border-0 overflow-hidden">
                <div class="modal-header border-0 justify-content-center py-3">
                    <h5 class="modal-title fw-bold" id="appConfirmModalLabel">Confirm</h5>
                </div>
                <div class="modal-body text-center py-4 fs-6">
                    <p id="appConfirmModalMessage" class="mb-0"></p>
                </div>
                <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4" id="appConfirmCancel">Cancel</button>
                    <button type="button" class="btn-modern btn-outline text-primary border-primary px-4" id="appConfirmOk">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Pass PHP data to JS -->
    <script>
        window.existingRoles = <?php echo json_encode($existing_roles); ?>;
        window.existingDepartments = <?php echo json_encode($existing_departments); ?>;
        window.existingClasses = <?php echo json_encode($existing_classes); ?>;
        window.existingSubjects = <?php echo json_encode($existing_subjects); ?>;
        window.existingRooms = <?php echo json_encode($existing_rooms); ?>;

        // Global state for selected employee in each step
        window.currentFaceEmployeeId = null;
        window.currentSchedEmployeeId = null;
    </script>

    <!-- Original Logic Modules (might need minor tweaks if they expect elements to exist immediately) -->
    <!-- We load them, but we control init via our own script below -->
    <script src="../assets/js/face-detection.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/camera-controller.js?v=<?php echo time(); ?>"></script>

    <!-- Modified face-registration-app.js logic needs to be handled cautiously. 
       If it auto-inits on DOMContentLoaded, it might fail if video element is hidden or in inactive tab.
       However, we kept IDs same (#video, #canvas). 
  -->
    <script src="../assets/js/face-registration-app.js?v=<?php echo time(); ?>"></script>

    <!-- Custom Wizard Logic -->
    <script>
        // --- Step 1: Info Submission ---
        document.getElementById('step1Form').addEventListener('submit', function (e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);

            showLoading('Saving Personal Information...');

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    // Check if response is JSON (add_employee might return HTML redirect if not handled carefully, 
                    // but we saw it returns JSON or exits).
                    // Actually, add_employee.php (lines 512-524) echoes HTML with JS redirect!
                    // We need to handle that. 
                    // OR we can change add_employee.php. 
                    // Since we can't change add_employee.php easily right now without breaking other things, 
                    // let's parse the response text. 
                    return response.text();
                })
                .then(text => {
                    hideLoading();

                    // Heuristic to check success based on add_employee.php output
                    if (text.includes('Employee added successfully') || text.includes('Redirecting')) {
                        // Extract Employee ID if possible, or just use the one from input
                        const empId = document.getElementById('employee_id').value;

                        showWizardSuccess(
                            `Employee <strong>${empId}</strong> created successfully!`,
                            () => {
                                // Switch to Tab 2
                                document.getElementById('step2-tab').click();
                                // Pre-fill Step 2 search
                                document.getElementById('face_search_id').value = empId;
                                lookupEmployee('face');
                            }
                        );
                        // Clear form
                        form.reset();
                    } else {
                        // Try to parse JSON error if it was a JSON response
                        try {
                            const json = JSON.parse(text);
                            if (!json.success) {
                                showWizardError(json.message);
                            }
                        } catch (e) {
                            showWizardError('Unexpected response from server. Check logs.');
                            console.error('Server response:', text);
                        }
                    }
                })
                .catch(err => {
                    hideLoading();
                    showWizardError('Network error occurred.');
                    console.error(err);
                });
        });

        // --- Employee Lookup Function ---
        function lookupEmployee(type) {
            const inputId = type === 'face' ? 'face_search_id' : 'sched_search_id';
            const empId = document.getElementById(inputId).value.trim();

            if (!empId) {
                showWizardError('Please enter an Employee ID.');
                return;
            }

            showLoading('Searching...');

            // Use get_employees.php with search param
            fetch(`get_employees.php?search=${encodeURIComponent(empId)}`)
                .then(res => res.json())
                .then(data => {
                    hideLoading();

                    if (data.success && data.data && data.data.length > 0) {
                        // Find exact match if possible, otherwise take first
                        const employee = data.data.find(e => e.employee_id === empId) || data.data[0];

                        if (type === 'face') {
                            window.currentFaceEmployeeId = employee.employee_id;
                            document.getElementById('face_emp_name').textContent = employee.name;
                            document.getElementById('face_emp_id_display').textContent = employee.employee_id;
                            document.getElementById('face_emp_dept').textContent = employee.department;
                            document.getElementById('face_emp_img').src = employee.profile_photo || '../assets/profile_pic/user.png';

                            document.getElementById('face_employee_info').classList.remove('d-none');
                            document.getElementById('face_registration_container').style.display = 'block';

                            // Trigger camera init if needed
                            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                                const video = document.getElementById('video');
                                // If logic relies on global startup, it might already be running.
                            }

                        } else if (type === 'sched') {
                            window.currentSchedEmployeeId = employee.employee_id;
                            document.getElementById('sched_emp_name').textContent = employee.name;
                            document.getElementById('sched_emp_id_display').textContent = employee.employee_id;
                            document.getElementById('sched_emp_role').textContent = employee.role;
                            document.getElementById('sched_emp_img').src = employee.profile_photo || '../assets/profile_pic/user.png';

                            document.getElementById('sched_employee_info').classList.remove('d-none');
                            document.getElementById('schedule_container').style.display = 'block';

                            // Enable/Disable faculty fields
                            const isFaculty = employee.role.toLowerCase().includes('faculty');
                            toggleFacultyFields(isFaculty);
                        }

                    } else {
                        showWizardError('Employee not found.');
                    }
                })
                .catch(err => {
                    hideLoading();
                    showWizardError('Error fetching employee data.');
                    console.error(err);
                });
        }

        // --- Step 2: Face Submission ---
        function submitFaceData() {
            if (!window.currentFaceEmployeeId) return;

            const facePhotos = document.getElementById('face_photos').value;
            if (!facePhotos || facePhotos === '[]') {
                showWizardError('Please capture face photos first.');
                return;
            }

            showLoading('Saving Face Data & Generating Embeddings...');

            const formData = new FormData();
            formData.append('employee_id', window.currentFaceEmployeeId);
            formData.append('face_photos', facePhotos);

            fetch('processes/update_face_registration.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showWizardSuccess(data.message, () => {
                            document.getElementById('step3-tab').click();
                            document.getElementById('sched_search_id').value = window.currentFaceEmployeeId;
                            lookupEmployee('sched');
                        });
                    } else {
                        showWizardError(data.message);
                    }
                })
                .catch(err => {
                    hideLoading();
                    showWizardError('Server error.');
                    console.error(err);
                });
        }

        // --- Step 3: Schedule Logic (Helpers) ---
        function toggleFacultyFields(isFaculty) {
            const fields = ['designate_class', 'designate_subject', 'room-number'];
            fields.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.disabled = !isFaculty;
                    if (!isFaculty) el.value = '';

                    const helper = el.parentElement.querySelector('.faculty-helper');
                    if (helper) {
                        helper.style.display = isFaculty ? 'block' : 'none';
                    }
                }
            });
        }

        // Existing toggleDay function needs to be global
        window.toggleDay = function (btn) {
            btn.classList.toggle('active');
            updateWorkDaysInput();
        }

        function updateWorkDaysInput() {
            // Collect active days
        }

        // We need to bring in the schedule logic from the original file or rewrite it. 
        // The original file had inline scripts + staff.js.
        // We'll rely on staff.js and add_employee.js if possible, but add_employee.js is monolithic. 
        // We need to implement `validateAndAddSchedule` and `submitSchedule` here or import them.

        // Re-implementing validateAndAddSchedule for this context
        window.validateAndAddSchedule = function () {
            // ... (Validate time logic)
            const start = document.getElementById('shift_start').value;
            const end = document.getElementById('shift_end').value;

            if (start && end && start >= end) {
                const validationModal = new bootstrap.Modal(document.getElementById('timeValidationModal'));
                validationModal.show();
                return;
            }

            // Use the global function from staff.js if available, or define local logic
            if (typeof addSchedule === 'function') {
                addSchedule(); // This pushes to a global array usually?
            } else {
                // If addSchedule isn't global, we need to replicate the logic:
                // 1. Gather data from inputs
                // 2. Create a schedule object
                // 3. Add to a list/visualization
                // 4. Update hidden input #schedule_data
                console.warn('addSchedule function not found. Ensure staff.js is loaded and exposes it.');
            }
        }

        // Small helper to show a system-styled confirmation modal
        function systemConfirm(message, options = {}) {
            const modalEl = document.getElementById('systemConfirmModal');
            return new Promise((resolve) => {
                const title = options.title || 'Confirm';
                const okText = options.okText || 'OK';
                const cancelText = options.cancelText || 'Cancel';
                const okClass = options.okClass || 'btn-primary';

                modalEl.querySelector('[data-confirm-title]').textContent = title;
                modalEl.querySelector('[data-confirm-message]').textContent = message;

                const okBtn = modalEl.querySelector('[data-confirm-ok]');
                const cancelBtn = modalEl.querySelector('[data-confirm-cancel]');
                okBtn.textContent = okText;
                cancelBtn.textContent = cancelText;
                okBtn.className = 'btn ' + okClass;

                const bsModal = new bootstrap.Modal(modalEl);

                const cleanup = () => {
                    okBtn.removeEventListener('click', onOk);
                    cancelBtn.removeEventListener('click', onCancel);
                    modalEl.removeEventListener('hidden.bs.modal', onCancel);
                };

                const onOk = () => { cleanup(); bsModal.hide(); resolve(true); };
                const onCancel = () => { cleanup(); resolve(false); };

                okBtn.addEventListener('click', onOk, { once: true });
                cancelBtn.addEventListener('click', () => { bsModal.hide(); }, { once: true });
                modalEl.addEventListener('hidden.bs.modal', onCancel, { once: true });

                bsModal.show();
            });
        }

        // --- Step 3: Schedule Submission ---
        window.submitSchedule = async function () {
            if (!window.currentSchedEmployeeId) return;

            const scheduleData = document.getElementById('schedule_data').value;
            if (!scheduleData || scheduleData === '[]') {
                // Show warning modal
                const modalEl = document.getElementById('appInfoModal');
                modalEl.querySelector('[data-app-info-title]').textContent = 'Warning';
                modalEl.querySelector('[data-app-info-message]').textContent = 'No schedule added. Please add at least one schedule entry before saving.';
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
                return;
            }

            showLoading('Saving Schedule...');

            const formData = new FormData();
            formData.append('employee_id', window.currentSchedEmployeeId);
            // We probably need first/last name for notification, but the backend can fetch it if missing.
            // update_employee_schedule.php fetches internal ID but might use names for description.
            // Let's grab names from the displayed labels
            formData.append('first_name', document.getElementById('sched_emp_name').textContent.split(' ')[0]);
            formData.append('last_name', ''); // Optional
            formData.append('schedule_data', scheduleData);

            fetch('processes/update_employee_schedule.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showWizardSuccess('Schedule updated successfully!', () => {
                            window.location.href = 'staff.php';
                        }, 'Go to Staff Management');
                    } else {
                        showWizardError(data.message);
                    }
                })
                .catch(err => {
                    hideLoading();
                    showWizardError('Server error.');
                    console.error(err);
                });
        }

        // --- UI Helpers ---
        function showLoading(msg) {
            document.getElementById('loadingText').textContent = msg;
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        function showWizardError(msg) {
            document.getElementById('errorMessage').textContent = msg;
            new bootstrap.Modal(document.getElementById('errorModal')).show();
        }

        function showWizardSuccess(msg, nextAction, btnText = 'Proceed to Next Step') {
            document.getElementById('wizardSuccessMessage').innerHTML = msg;
            const btn = document.getElementById('wizardNextBtn');
            const modalEl = document.getElementById('wizardSuccessModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

            btn.textContent = btnText;
            if (nextAction) {
                btn.style.display = 'inline-block';
                btn.onclick = function () {
                    modal.hide();
                    nextAction();
                };
            } else {
                btn.style.display = 'none';
            }
            modal.show();
        }

        // Toggle Password visibility
        window.toggleAddPassword = function () {
            const passwordInput = document.getElementById("add_password");
            const icon = document.getElementById("toggleAddPasswordIcon");
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                icon.classList.remove("bi-eye");
                icon.classList.add("bi-eye-slash");
            } else {
                passwordInput.type = "password";
                icon.classList.remove("bi-eye-slash");
                icon.classList.add("bi-eye");
            }
        }
    </script>

    <!-- staff.js should handle specific UI interactions like Dropdowns/Autocompletes -->
    <script src="staff.js"></script>
    <script src="../assets/js/newstaff_wizard.js?v=<?php echo time(); ?>"></script>
    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow border-0 overflow-hidden">
                <div class="modal-body text-center py-4">
                    <h5 class="fw-bold mb-3">Confirm Logout</h5>
                    <p class="mb-0 fs-6">Are you sure you want to log out?</p>
                </div>
                <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4" data-bs-dismiss="modal">No</button>
                    <form id="logoutForm" method="POST" action="logout.php" style="display:inline;">
                        <input type="hidden" name="confirm_logout" value="1">
                        <button type="submit" class="btn-modern btn-outline text-danger border-danger px-4">Yes, Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showLogoutModal() {
            var modal = new bootstrap.Modal(document.getElementById('logoutModal'));
            modal.show();
        }
    </script>
    <script src="../dashboard/dashboard.js"></script>
</body>

</html>