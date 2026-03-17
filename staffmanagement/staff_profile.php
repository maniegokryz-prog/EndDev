<?php
// Protect this page - require authentication
require_once '../auth_guard.php';
require_once '../navigation.php';
require '../db_connection.php';

// TOGGLE: Set to true to hide the Re-register Face button entirely (for VPS deployment)
$hide_re_register_face_button = false;

// Get current user info
$currentUser = getCurrentUser();

// Check for status messages from redirect
$updateStatus = $_GET['status'] ?? '';
$updateTimestamp = $_GET['t'] ?? '';
$updateError = $_SESSION['update_error'] ?? '';
// Clear the error from session after reading it
if (isset($_SESSION['update_error'])) {
    unset($_SESSION['update_error']);
}
$isAdmin = isset($currentUser['role']) && strtolower($currentUser['role']) === 'admin';

// CONFIGURATION: Set to true when deploying to IONOS server to disable editing
$is_ionos_server = false;

// Check if user can request leave
function canRequestLeave($employeeRoles)
{
    if (empty($employeeRoles))
        return false;
    $rolesLower = strtolower($employeeRoles);
    if (stripos($rolesLower, 'faculty') !== false)
        return false;
    if (stripos($rolesLower, 'admin') !== false)
        return true;
    if (stripos($rolesLower, 'non-teaching') !== false || stripos($rolesLower, 'non_teaching') !== false)
        return true;
    return true;
}

// --- Classes (Copied from staffinfo.php) ---

class EmployeeEditor
{
    private $db;
    private $employee = null;
    private $errors = [];

    public function __construct($database)
    {
        $this->db = $database;
    }

    public function loadEmployee($employee_id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM employees WHERE employee_id = ?");
            if (!$stmt)
                throw new Exception('Failed to prepare statement: ' . $this->db->error);
            $stmt->bind_param('s', $employee_id);
            if (!$stmt->execute())
                throw new Exception('Failed to execute query: ' . $stmt->error);
            $result = $stmt->get_result();
            $this->employee = $result->fetch_assoc();
            $stmt->close();
            if (!$this->employee) {
                $this->errors[] = "Employee not found with ID: " . htmlspecialchars($employee_id);
                return false;
            }
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
            return false;
        }
    }
    public function getEmployee()
    {
        return $this->employee;
    }
    public function getErrors()
    {
        return $this->errors;
    }
    public function hasErrors()
    {
        return !empty($this->errors);
    }
}

class EmployeeDetailViewer
{
    private $db;
    private $employee = null;
    private $schedules = [];
    private $errors = [];

    public function __construct($database)
    {
        $this->db = $database;
    }

    public function loadEmployeeDetails($employee_id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, employee_id, first_name, middle_name, last_name, suffix,
                       email, phone, roles, department, position, hire_date, 
                       status, created_at, updated_at, profile_photo
                FROM employees 
                WHERE employee_id = ?
            ");
            if (!$stmt)
                throw new Exception('Failed to prepare statement: ' . $this->db->error);
            $stmt->bind_param('s', $employee_id);
            if (!$stmt->execute())
                throw new Exception('Failed to execute employee query: ' . $stmt->error);
            $result = $stmt->get_result();
            $this->employee = $result->fetch_assoc();
            $stmt->close();

            if (!$this->employee) {
                $this->errors[] = "Employee not found with ID: " . htmlspecialchars($employee_id);
                return false;
            }

            $this->employee = $this->sanitizeData($this->employee);
            $this->loadEmployeeSchedules($this->employee['id']);
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
            return false;
        }
    }

    private function loadEmployeeSchedules($internal_employee_id)
    {
        try {
            $query = "
                SELECT 
                    s.schedule_name,
                    sp.day_of_week,
                    sp.start_time,
                    sp.end_time,
                    ea.subject_code,
                    ea.designate_class,
                    ea.room_num
                FROM employee_schedules es
                JOIN schedules s ON es.schedule_id = s.id
                JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                LEFT JOIN employee_assignments ea ON ea.employee_id = es.employee_id 
                    AND ea.schedule_period_id = sp.id
                WHERE es.employee_id = ? 
                AND es.is_active = 1
                AND sp.is_active = 1
                ORDER BY sp.day_of_week, sp.start_time
            ";

            $stmt = $this->db->prepare($query);
            if (!$stmt)
                throw new Exception('Failed to prepare schedule query: ' . $this->db->error);
            $stmt->bind_param('i', $internal_employee_id);
            if (!$stmt->execute())
                throw new Exception('Failed to execute schedule query: ' . $stmt->error);
            $result = $stmt->get_result();

            $this->schedules = [];
            while ($row = $result->fetch_assoc()) {
                $this->schedules[] = $this->sanitizeData($row);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->errors[] = "Error loading schedules: " . $e->getMessage();
        }
    }

    private function sanitizeData($data)
    {
        if (!$data || !is_array($data)) {
            return [];
        }
        $sanitized = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                $sanitized[$key] = 'N/A';
            } else {
                $sanitized[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }
        return $sanitized;
    }

    public function getEmployee()
    {
        return $this->employee;
    }
    public function getSchedules()
    {
        return $this->schedules;
    }
    public function getFullName()
    {
        if (!$this->employee || !is_array($this->employee))
            return 'Unknown';
        $nameParts = [];
        if ($this->employee['first_name'] && $this->employee['first_name'] !== 'N/A')
            $nameParts[] = $this->employee['first_name'];
        if ($this->employee['middle_name'] && $this->employee['middle_name'] !== 'N/A')
            $nameParts[] = $this->employee['middle_name'];
        if ($this->employee['last_name'] && $this->employee['last_name'] !== 'N/A')
            $nameParts[] = $this->employee['last_name'];
        if (isset($this->employee['suffix']) && $this->employee['suffix'] && $this->employee['suffix'] !== 'N/A')
            $nameParts[] = $this->employee['suffix'];
        return implode(' ', $nameParts);
    }
}

// Get Data
$employee_id = $_GET['id'] ?? '';
if (empty($employee_id)) {
    header('Location: staff.php?error=no_id');
    exit;
}

$viewer = new EmployeeDetailViewer($conn);
$loadSuccess = $viewer->loadEmployeeDetails($employee_id);
$employee = $viewer->getEmployee();
$schedules = $viewer->getSchedules();

if (!$loadSuccess || !$employee) {
    header("Location: staff.php?error=employee_not_found");
    exit;
}

// Initialize Editor for Modals
$editor = new EmployeeEditor($conn);
$editor->loadEmployee($employee_id);

// Prepare Schedule Data for JS
$scheduleColors = ['#4a7c59', '#8b4a6b', '#b85450', '#5b9bd5', '#ffc000', '#c55a11', '#7030a0', '#0070c0', '#00b050', '#ff6b6b'];
$processedSchedules = [];
if ($schedules) {
    // Group logic similar to staffinfo.php JS logic but in PHP for initial load
    $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $groups = [];
    foreach ($schedules as $s) {
        $key = $s['start_time'] . '-' . $s['end_time'] . '-' . $s['subject_code'] . '-' . $s['designate_class'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'startTime' => substr($s['start_time'], 0, 5),
                'endTime' => substr($s['end_time'], 0, 5),
                'subject' => $s['subject_code'],
                'class' => $s['designate_class'],
                'room_num' => $s['room_num'],
                'days' => [],
                'color' => $scheduleColors[count($groups) % count($scheduleColors)]
            ];
        }
        $dayIdx = (int) $s['day_of_week'];
        if (isset($dayNames[$dayIdx]) && !in_array($dayNames[$dayIdx], $groups[$key]['days'])) {
            $groups[$key]['days'][] = $dayNames[$dayIdx];
        }
    }
    $processedSchedules = array_values($groups);
}

// Profile Photo Logic (Robust)
$profilePhoto = '../assets/profile_pic/user.png';
// Use the stored path directly. Assuming the path in DB is relative to project root (e.g. 'uploads/file.png' or 'assets/profile_pic/file.png')
$storedPath = $employee['profile_photo'];
$absolutePath = dirname(__DIR__) . '/' . $storedPath;

if (file_exists($absolutePath)) {
    $profilePhoto = '../' . $storedPath;
} else {
    $profilePhoto = '../assets/profile_pic/user.png';
}
// Add microtime to guarantee uniqueness on update
$profilePhoto .= '?v=' . microtime(true);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile - <?php echo $viewer->getFullName(); ?></title>

    <!-- Dependencies -->
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo time(); ?>"> <!-- Base styles for sidebar/nav -->
    <link rel="stylesheet" href="../assets/css/new_profile.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="staff.css?v=<?php echo time(); ?>">
    <!-- Keep for Sidebar styles specific to staff module -->

    <script src="../assets/vendor/chartjs/chart.umd.min.js"></script>
    <style>
        /* Force button sizing for edit schedule modal - DESKTOP DEFAULT */
        /* Custom gray hover for specific buttons */
        .btn-gray-hover:hover {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }

        #editScheduleModal .add-schedule-btn,
        #editScheduleModal .edit-schedule-btn,
        #editScheduleModal .btn-cancel {
            flex: 0 0 auto;
            width: auto;
            min-width: 140px;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        /* Add Schedule Button - Green Outline */


        /* Update Selected Schedule Button - Gray Outline */


        /* Clear All & Cancel Buttons - Shared Sizing */
        #editScheduleModal .clear-schedules-btn,
        #editScheduleModal .btn-cancel,
        #editScheduleModal .btn-save {
            min-width: 200px;
            padding: 10px 20px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        /* Clear/Cancel Sizing */
        #editScheduleModal .clear-schedules-btn,
        #editScheduleModal .btn-cancel {
            min-width: 150px;
        }

        /* Default Bold for all modal buttons */
        #editScheduleModal .btn-modern {
            font-weight: bold !important;
        }

        /* Unified Hover states to Gray #6c757d */
        #editScheduleModal .add-schedule-btn:hover,
        #editScheduleModal .btn-save:hover,
        #editScheduleModal .edit-schedule-btn:hover,
        #editScheduleModal .clear-schedules-btn:hover,
        #editScheduleModal .btn-cancel:hover {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            background-image: none !important;
            color: #fff !important;
        }

        /* Modern Solid Action Buttons */
        .btn-solid-success {
            background-color: #198754 !important;
            border: 1px solid #198754 !important;
            color: #fff !important;
        }
        .btn-solid-danger {
            background-color: #dc3545 !important;
            border: 1px solid #dc3545 !important;
            color: #fff !important;
        }
        .btn-solid-warning {
            background-color: #ffc107 !important;
            border: 1px solid #ffc107 !important;
            color: #000 !important;
        }
        .btn-solid-orange {
            background-color: #fd7e14 !important; /* Light Orange */
            border: 1px solid #fd7e14 !important;
            color: #fff !important;
        }
        .btn-solid-primary {
            background-color: #0d6efd !important;
            border: 1px solid #0d6efd !important;
            color: #fff !important;
        }
        .btn-solid-light-gray {
            background-color: #e9ecef !important;
            border: 1px solid #e9ecef !important;
            color: #495057 !important;
        }

        /* Unified Hover for Action Buttons */
        .btn-modern.btn-outline:hover,
        .btn-modern.btn-solid-success:hover,
        .btn-modern.btn-solid-danger:hover,
        .btn-modern.btn-solid-warning:hover,
        .btn-modern.btn-solid-orange:hover,
        .btn-modern.btn-solid-primary:hover,
        .btn-modern.btn-solid-light-gray:hover,
        .btn-gray-hover:hover {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: #fff !important;
            background-image: none !important;
        }


        /* Responsive Fixes for Modal */
        @media (max-width: 768px) {

            /* 1. Stack the top action buttons */
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

            /* 2. Fix Schedule Header (Clear All overlapping title) */
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

            /* 3. Bottom Form Actions */
            .form-actions {
                display: flex !important;
                /* Ensure flex is on */
                flex-direction: column !important;
                gap: 10px !important;
                margin-top: 15px;
                /* Some space above buttons */
            }

            /* 4. Ensure Modal is centered and fits */
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

        /* Schedule Delete Button in Grid */
        .schedule-delete-btn {
            position: absolute !important;
            top: 2px !important;
            right: 5px !important;
            width: 22px !important;
            height: 22px !important;
            background: rgba(0, 0, 0, 0.4) !important;
            color: #fff !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            font-size: 16px !important;
            font-weight: bold !important;
            transition: all 0.2s ease !important;
            z-index: 10 !important;
            line-height: 1 !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
        }

        .schedule-delete-btn:hover {
            background: rgba(220, 53, 69, 0.9) !important;
            transform: scale(1.15) !important;
            color: #fff !important;
        }

        .schedule-block {
            position: relative !important;
        }
    </style>
</head>

<body>

    <!-- Top Navbar -->
    <div class="top-navbar d-flex justify-content-between align-items-center p-2 shadow-sm">
        <div class="d-flex align-items-center">
            <div class="menu-toggle me-3">
                <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn" onclick="toggleSidebar()"></i>
            </div>
        </div>
        <?php include '../includes/notification_bell.php'; ?>
    </div>

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column pt-5" id="sidebar">
        <div class="profile text-center p-3 mt-4">
            <img src="<?php echo (!empty($currentUser['profile_photo']) && $currentUser['profile_photo'] !== 'N/A') ? '../' . htmlspecialchars($currentUser['profile_photo'], ENT_QUOTES, 'UTF-8') . '?v=' . time() : '../assets/profile_pic/user.png?v=' . time(); ?>"
                alt="Profile" class="rounded-circle mb-2" style="width: 70px; height: 70px; object-fit: cover;"
                onerror="this.src='../assets/profile_pic/user.png';">
            <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?></h5>
            <small class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User')); ?></small>
        </div>
        <nav class="nav flex-column px-2">
            <?php 
                $isOwnProfile = (isset($currentUser['employee_id']) && isset($employee['employee_id']) && $currentUser['employee_id'] === $employee['employee_id']);
                $navLabel = $isOwnProfile ? ($isAdmin ? 'My Profile' : 'My Info') : ($isAdmin ? 'Staff Management' : 'My Info');
                renderNavigation($navLabel); 
            ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="content pt-2" id="content">
        <div class="container-fluid px-4 pb-4 pt-2">

            <?php
            // Check for pending schedule requests
            $hasPendingRequest = false;
            $pendingScheduleData = '[]';
            $pendingRequestId = null;
            try {
                $stmt = $conn->prepare("SELECT id, schedule_data FROM schedule_requests WHERE employee_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
                $stmt->bind_param("i", $employee['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $hasPendingRequest = true;
                    $pendingScheduleData = $row['schedule_data'];
                    $pendingRequestId = $row['id'];
                }
                $stmt->close();
            } catch (Exception $e) {
            }
            ?>

            <?php if ($hasPendingRequest): ?>
                <?php if (isset($currentUser['employee_id']) && $currentUser['employee_id'] === $employee['employee_id']): ?>
                    <div class="alert alert-warning mb-4 border-0 shadow-sm d-flex flex-column align-items-start gap-3 px-4 py-3" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-circle fs-4 m-0 text-warning-emphasis"></i>
                            <div class="lh-sm m-0">
                                <strong>Pending Schedule Request:</strong> You have submitted a schedule edit request that is
                                currently waiting for Admin approval. Your previous active schedule is shown below until the new one is approved.
                            </div>
                        </div>
                        <button class="btn btn-dark btn-sm fw-semibold text-nowrap m-0 px-4 py-2 btn-gray-hover" data-bs-toggle="modal"
                                data-bs-target="#viewPendingRequestModal"
                                onclick="try { renderPendingRequestCalendar(); } catch(e) { alert('Function error: ' + e.message); console.error(e); }">
                                <i class="bi bi-eye"></i> View Request
                        </button>
                    </div>
                <?php elseif ($isAdmin): ?>
                    <div class="alert alert-warning mb-4 border-0 shadow-sm d-flex flex-column align-items-start gap-3 px-4 py-3"
                        role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-circle fs-4 m-0 text-warning-emphasis"></i>
                            <div class="lh-sm m-0">
                                <strong>Pending Schedule Request:</strong> This user has submitted a schedule request. The
                                previous active schedule is shown below until the new one is approved.
                            </div>
                        </div>
                        <a href="review_schedule_request.php?id=<?php echo $pendingRequestId; ?>&referrer=staff_profile&emp_id=<?php echo urlencode($employee['employee_id']); ?>"
                            class="btn btn-dark btn-sm fw-semibold text-nowrap m-0 px-4 py-2 btn-gray-hover">
                            <i class="bi bi-pencil-square"></i> Review Request
                        </a>
                    </div>
                <?php endif; ?>

                <script>
                    window.pendingRequestScheduleData = <?php
                    $decoded_data = json_decode($pendingScheduleData, true);
                    $json_out = json_encode(is_array($decoded_data) ? $decoded_data : []);
                    echo $json_out !== false ? $json_out : '[]';
                    ?>;

                    window.renderPendingRequestCalendar = function () {
                        console.log("Rendering pending request calendar...");
                        const container = document.getElementById('pending-request-calendar-view');
                        if (!container) return; // Exit if no container

                        try {
                            const scheduleBlocks = window.pendingRequestScheduleData || [];
                            if (!Array.isArray(scheduleBlocks)) {
                                container.innerHTML = `<h5 class="text-danger">Error: Data format incorrect.</h5>`;
                                return;
                            }
                            if (scheduleBlocks.length === 0) {
                                container.innerHTML = '<p class="text-center text-muted py-4">No schedule data found.</p>';
                                return;
                            }

                            if (typeof renderVisualSchedule === 'function') {
                                // Reset the container first
                                container.innerHTML = '';
                                container.style.cssText = 'min-height: 200px; display: grid; gap: 1px; background-color: #e2e8f0; border: 1px solid #e2e8f0; border-radius: 8px; min-width: 800px;';
                                // Call the main schedule render function defined in new_profile.js
                                renderVisualSchedule(container, scheduleBlocks, true); // Added true for showLegend
                            } else {
                                throw new Error("renderVisualSchedule is not defined.");
                            }
                        } catch (error) {
                            container.innerHTML = `<div class="alert alert-danger"><strong>Error Rendering Schedule:</strong><br/>${error.message}</div>`;
                            console.error("Rendering failed:", error);
                            alert("Rendering warning check console: " + error.message);
                        }
                    };

                    window.cancelPendingRequest = function (requestId) {
                        const modalEl = document.getElementById('cancelPendingRequestConfirmModal');
                        const btn = document.getElementById('cancelPendingRequestConfirmBtn');

                        if (!modalEl || !btn) {
                            alert("Error: Confirmation modal not found.");
                            return;
                        }

                        // Create new modal instance or get existing
                        let modal = bootstrap.Modal.getInstance(modalEl);
                        if (!modal) {
                            modal = new bootstrap.Modal(modalEl);
                        }

                        // Remove old listeners to prevent multiple triggers
                        const newBtn = btn.cloneNode(true);
                        btn.parentNode.replaceChild(newBtn, btn);

                        newBtn.addEventListener('click', function () {
                            const originalHtml = newBtn.innerHTML;
                            newBtn.disabled = true;
                            newBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Canceling...';

                            const csrfToken = '<?php echo $_SESSION["csrf_token"] ?? ""; ?>';
                            fetch('processes/cancel_schedule_request.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `request_id=${requestId}&csrf_token=${csrfToken}`
                            })
                                .then(async r => {
                                    const text = await r.text();
                                    try {
                                        const data = JSON.parse(text);
                                        if (data.success) {
                                            modal.hide();
                                            window.location.reload();
                                        } else {
                                            alert("Error: " + data.message);
                                            newBtn.disabled = false;
                                            newBtn.innerHTML = originalHtml;
                                        }
                                    } catch(e) {
                                        console.error("Server returned invalid data:", text);
                                        alert("An error occurred during cancellation. Please check your connection and try again.");
                                        newBtn.disabled = false;
                                        newBtn.innerHTML = originalHtml;
                                    }
                                })
                                .catch(err => {
                                    console.error("Network Error:", err);
                                    alert("A network error occurred. Please check your connection.");
                                    newBtn.disabled = false;
                                    newBtn.innerHTML = originalHtml;
                                });
                        });

                        modal.show();
                    };
                </script>
            <?php endif; ?>

            <!-- Top Section: Info & Metrics Grid -->
            <div class="top-section-grid">

                <!-- Staff Info Card -->
                <div class="profile-card">
                    <div class="staff-info-container">
                        <div class="profile-avatar-container">
                            <img src="<?php echo $profilePhoto; ?>" class="profile-avatar" alt="Profile">
                        </div>
                        <div class="staff-details flex-grow-1">
                            <h2><?php echo $viewer->getFullName(); ?></h2>
                            <span class="staff-id"><?php echo htmlspecialchars($employee['employee_id']); ?></span>
                            <div class="staff-role">
                                <?php echo htmlspecialchars($employee['roles']); ?> |
                                <?php echo htmlspecialchars($employee['department']); ?>
                            </div>
                            <div class="contact-info">
                                <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($employee['email']); ?>
                                </div>
                                <div><i class="bi bi-telephone"></i>
                                    <?php echo htmlspecialchars($employee['phone'] !== 'N/A' ? $employee['phone'] : 'No contact info'); ?>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <button class="btn-modern btn-solid-success btn-gray-hover"
                                    data-bs-toggle="modal" data-bs-target="#editInfoModal">
                                    <i class="bi bi-pencil"></i> Edit Info
                                </button>
                                <?php if ($isAdmin): ?>
                                    <button class="btn-modern btn-solid-danger btn-gray-hover" data-bs-toggle="modal"
                                        data-bs-target="#removeEmployeeModal">
                                        <i class="bi bi-trash"></i> Remove
                                    </button>
                                    <?php if (!$hide_re_register_face_button): ?>
                                        <a href="re_register_face.php?id=<?php echo htmlspecialchars($employee['employee_id']); ?>"
                                            class="btn-modern btn-solid-warning btn-gray-hover"
                                            title="Re-register Face Data">
                                            <i class="bi bi-person-bounding-box"></i> Re-register Face
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="profile-card">
                    <div class="card-header-custom">
                        <h3 class="card-title">Performance Metrics</h3>
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-sm" id="selectMonth" style="width: auto;">
                                <option value="">All Months</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo date('n') == $m ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select class="form-select form-select-sm" id="selectYear" style="width: auto;">
                                <?php
                                $curYear = date('Y');
                                $hire = !empty($employee['hire_date']) && $employee['hire_date'] !== 'N/A' ? date('Y', strtotime($employee['hire_date'])) : $curYear;
                                for ($y = $hire; $y <= $curYear; $y++) {
                                    echo "<option value='$y' " . ($y == $curYear ? 'selected' : '') . ">$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div id="metricsLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                    <div id="metricsError" class="text-center py-4 text-danger" style="display:none;">Failed to load
                        metrics</div>

                    <div id="metricsContent" class="metrics-row" style="display:none;">
                        <div class="metric-item">
                            <div class="metric-canvas-container"><canvas id="chartPresent"></canvas></div>
                            <div class="metric-label">Present</div>
                            <div class="metric-value" id="presentValue">0</div>
                            <div class="metric-percentage" id="presentCount">0%</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-canvas-container"><canvas id="chartAbsent"></canvas></div>
                            <div class="metric-label">Absent</div>
                            <div class="metric-value" id="absentValue">0</div>
                            <div class="metric-percentage" id="absentCount">0%</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-canvas-container"><canvas id="chartOntime"></canvas></div>
                            <div class="metric-label">On Time</div>
                            <div class="metric-value" id="ontimeValue">0</div>
                            <div class="metric-percentage" id="ontimeCount">0%</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-canvas-container"><canvas id="chartLate"></canvas></div>
                            <div class="metric-label">Late</div>
                            <div class="metric-value" id="lateValue">0</div>
                            <div class="metric-percentage" id="lateCount">0%</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-canvas-container"><canvas id="chartUndertime"></canvas></div>
                            <div class="metric-label">Undertime</div>
                            <div class="metric-value" id="undertimeValue">0</div>
                            <div class="metric-percentage" id="undertimeCount">0%</div>
                        </div>
                    </div>
                </div>

            </div> <!-- End Top Grid -->

            <!-- Split Grid for DTR and Schedule -->
            <div class="profile-split-grid">

                <!-- Left Column Wrapper (DTR + Leave) -->
                <div class="left-column-wrapper">
                    <!-- DTR Section -->
                    <div class="profile-card dtr-section-card">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">Daily Time Record</h4>
                            <!-- Popup Date Picker Trigger -->
                            <div class="position-relative">
                                <button
                                    class="btn-modern btn-solid-warning btn-sm d-flex align-items-center gap-2"
                                    id="dateRangeTrigger" title="Select Dates">
                                    <i class="bi bi-calendar3"></i>
                                    <span>Filter Dates</span>
                                </button>
                                <!-- Calendar Popup Dropdown -->
                                <div id="calendarPopup" class="calendar-popup shadow-lg" style="display: none;">
                                    <div class="calendar-header p-2 border-bottom">
                                        <button class="calendar-nav-btn btn-sm" id="prevMonth"><i
                                                class="bi bi-chevron-left"></i></button>
                                        <span class="fw-bold small" id="calendarTitle">Month Year</span>
                                        <button class="calendar-nav-btn btn-sm" id="nextMonth"><i
                                                class="bi bi-chevron-right"></i></button>
                                    </div>
                                    <div id="calendar" class="calendar-days p-2"></div>
                                    <div class="p-2 border-top text-end d-flex justify-content-end gap-2">
                                        <button class="btn-modern btn-solid-success btn-sm close-calendar-btn">Done</button>
                                        <button class="btn-modern btn-solid-danger btn-sm" id="clearDatesBtn">Clear</button>
                                    </div>
                                    <!-- Hidden input for value storage -->
                                    <input type="hidden" id="dateRangeInput">
                                </div>
                            </div>
                        </div>

                        <!-- Compact Actions -->
                        <div class="d-flex gap-2 mb-3">
                            <?php if ($isAdmin && !$is_ionos_server): ?>
                                <button class="btn-modern btn-solid-success btn-sm flex-grow-1"
                                    data-bs-toggle="modal" data-bs-target="#attendanceModal">
                                    <i class="bi bi-plus-lg"></i> Add
                                </button>
                            <?php endif; ?>
                            <button class="btn-modern btn-solid-primary btn-sm flex-grow-1"
                                id="exportDtrBtn">
                                <i class="bi bi-box-arrow-up-right"></i> Details / Export
                            </button>
                        </div>

                        <div id="dtrLoading" class="text-center py-2" style="display:none;">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                        <div id="dtrList" class="dtr-list-vertical"></div>
                    </div>

                    <!-- Leave Request Section (Restored) -->
                    <?php if (canRequestLeave($employee['roles'])): ?>
                        <div class="profile-card leave-section-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="card-title mb-0">Scheduled Leave</h4>
                                <button class="btn-modern btn-solid-success btn-sm btn-icon-sq"
                                    data-bs-toggle="modal" data-bs-target="#addLeaveModal" title="Add Request">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                            <div id="leaveList" class="leave-list-vertical d-flex flex-column gap-2">
                                <!-- JS populated -->
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Schedule Section (Right Side) -->
                <div class="profile-card schedule-section-card">
                    <div class="card-header-custom">
                        <h3 class="card-title">Schedule</h3>
                        <?php if ($isAdmin && isset($pendingRequestId) && $pendingRequestId): ?>
                            <button class="btn-modern btn-solid-success btn-sm btn-gray-hover" data-bs-toggle="modal"
                                data-bs-target="#adminPendingRequestWarningModal">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        <?php else: ?>
                            <button class="btn-modern btn-solid-success btn-sm btn-gray-hover" data-bs-toggle="modal"
                                data-bs-target="#editScheduleModal">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="schedule-container">
                        <!-- Desktop View (d-none d-md-grid) -->
                        <div id="visualScheduleCalendar" class="visual-schedule d-none d-md-grid">
                            <!-- JS will populate this -->
                        </div>

                        <!-- Mobile View (d-md-none) -->
                        <div id="mobileScheduleView" class="mobile-schedule-view d-block d-md-none">
                            <!-- JS will populate this -->
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- ========================= MODALS ========================= -->

    <!-- Edit Info Modal (Ported) -->
    <div class="modal fade" id="editInfoModal" tabindex="-1" aria-labelledby="editInfoModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content p-4">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container">
                        <h1>Edit Employee Details</h1>
                        <?php if ($editor->hasErrors()): ?>
                            <div class="alert alert-danger">
                                <strong>Errors:</strong>
                                <ul><?php foreach ($editor->getErrors() as $error): ?>
                                        <li><?php echo $error; ?></li><?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($loadSuccess && $employee): ?>
                            <form action="processes/update_employee.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="employee_id"
                                    value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                                <div class="form-group mb-3 text-center">
                                    <label class="fw-bold mb-2">Profile Picture</label>
                                    <img id="profile-preview"
                                        src="<?php echo $employee['profile_photo'] !== 'N/A' ? '../' . htmlspecialchars($employee['profile_photo']) . '?v=' . time() : '../assets/profile_pic/user.png?v=' . time(); ?>"
                                        alt="Profile Preview"
                                        style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; display: block; margin: 0 auto 15px auto;"
                                        onerror="this.src='../assets/profile_pic/user.png'">
                                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*"
                                        class="form-control text-start">
                                </div>
                                <div class="row">
                                    <div class="col-md-4 col-sm-12 mb-3"><label>First Name</label><input type="text" name="first_name" id="edit_first_name"
                                            class="form-control" autocapitalize="words"
                                            style="text-transform:capitalize"
                                            oninput="var p=this.selectionStart;this.value=this.value.replace(/(^|\s)\S/g,function(c){return c.toUpperCase();});this.setSelectionRange(p,p);"
                                            value="<?php echo htmlspecialchars($employee['first_name']); ?>" required></div>
                                    <div class="col-md-3 col-sm-12 mb-3"><label>Middle Name</label><input type="text"
                                            name="middle_name" id="edit_middle_name" class="form-control" autocapitalize="words"
                                            style="text-transform:capitalize"
                                            oninput="var p=this.selectionStart;this.value=this.value.replace(/(^|\s)\S/g,function(c){return c.toUpperCase();});this.setSelectionRange(p,p);"
                                            value="<?php echo htmlspecialchars($employee['middle_name']); ?>"></div>
                                    <div class="col-md-3 col-sm-12 mb-3"><label>Last Name</label><input type="text" name="last_name" id="edit_last_name"
                                            class="form-control" autocapitalize="words"
                                            style="text-transform:capitalize"
                                            oninput="var p=this.selectionStart;this.value=this.value.replace(/(^|\s)\S/g,function(c){return c.toUpperCase();});this.setSelectionRange(p,p);"
                                            value="<?php echo htmlspecialchars($employee['last_name']); ?>" required></div>
                                    <div class="col-md-2 col-sm-12 mb-3"><label>Suffix <span class="text-muted d-none d-lg-inline">(Opt)</span></label><input type="text" name="suffix" id="edit_suffix"
                                            class="form-control" autocapitalize="words"
                                            style="text-transform:capitalize"
                                            oninput="var p=this.selectionStart;this.value=this.value.replace(/(^|\s)\S/g,function(c){return c.toUpperCase();});this.setSelectionRange(p,p);"
                                            value="<?php echo htmlspecialchars($employee['suffix'] ?? ''); ?>"></div>
                                </div>
                                <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control"
                                        value="<?php echo htmlspecialchars($employee['email']); ?>" required></div>
                                <div class="mb-3">
                                    <label>Phone</label>
                                    <input type="tel" name="phone" id="edit_phone" class="form-control"
                                        pattern="[0-9]{11}" maxlength="11"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11); document.getElementById('edit-phone-error').style.display = (this.value.length === 11 || this.value.length === 0) ? 'none' : 'block';"
                                        title="Phone number must be exactly 11 digits"
                                        value="<?php echo htmlspecialchars($employee['phone']); ?>">
                                    <small id="edit-phone-error" class="text-danger mt-1" style="display: none;">
                                        <i class="bi bi-exclamation-circle me-1"></i>Phone number must be exactly 11 digits.
                                    </small>
                                </div>
                                <?php if ($isAdmin): ?>
                                    <div class="row">
                                        <div class="col-md-4 mb-3"><label>Role</label><input type="text" name="roles" id="roles"
                                                class="form-control"
                                                value="<?php echo htmlspecialchars($employee['roles']); ?>"></div>
                                        <div class="col-md-4 mb-3"><label>Department</label><input type="text" name="department" id="edit_department"
                                                class="form-control" autocapitalize="words"
                                                style="text-transform:capitalize"
                                                oninput="var p=this.selectionStart;this.value=this.value.replace(/(^|\s)\S/g,function(c){return c.toUpperCase();});this.setSelectionRange(p,p);"
                                                value="<?php echo htmlspecialchars($employee['department']); ?>"></div>
                                        <div class="col-md-4 mb-3"><label>Position</label><input type="text" name="position" id="edit_position"
                                                class="form-control" autocapitalize="words"
                                                style="text-transform:capitalize"
                                                oninput="var p=this.selectionStart;this.value=this.value.replace(/(^|\s)\S/g,function(c){return c.toUpperCase();});this.setSelectionRange(p,p);"
                                                value="<?php echo htmlspecialchars($employee['position']); ?>"></div>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-4 d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn-modern btn-solid-success btn-sm px-4">Save
                                        Changes</button>
                                    <button type="button" class="btn-modern btn-solid-danger btn-sm px-4"
                                        data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <p>Could not load data.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="saveSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 rounded-4 shadow border-0">
                <div class="modal-body text-center">
                    <div class="success-icon-container">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-success">Save Successful</h5>
                    <p class="text-muted">Your changes have been saved successfully.</p>
                    <div class="mt-4">
                        <button type="button" class="btn-modern btn-outline text-success border-success fw-bold px-5" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Employee Modal -->
    <div class="modal fade" id="removeEmployeeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <h5 class="fw-bold mb-3 text-danger text-center">Confirm Employee Removal</h5>
                <p class="text-center">This will move the employee to the archive. Enter your admin password to confirm.
                </p>
                <form id="removeEmployeeForm">
                    <div class="mb-3">
                        <label class="form-label">Admin Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="adminPasswordInput" name="admin_password"
                            required placeholder="Enter password">
                        <div id="passwordError" class="text-danger small mt-1" style="display: none;"></div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="submit" class="btn-modern btn-solid-orange btn-sm px-4" id="confirmRemoveBtn">
                            <span id="removeBtnText">Remove Employee</span>
                            <span id="removeBtnSpinner" class="spinner-border spinner-border-sm ms-2"
                                style="display: none;"></span>
                        </button>
                        <button type="button" class="btn-modern btn-solid-danger btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="removeSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 rounded-4 shadow border-0">
                <div class="modal-body text-center">
                    <div class="success-icon-container">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h5 class="fw-bold text-success mb-2">Employee Archived</h5>
                    <p class="text-muted">Redirecting you to the staff list...</p>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="errorRemoveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 rounded-4 shadow border-0">
                <div class="modal-body text-center">
                    <div class="success-icon-container" style="background: #fff5f5; color: #e53e3e;">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                    <h5 class="fw-bold text-danger mb-2">Operation Failed</h5>
                    <p id="errorRemoveMessage" class="text-muted mb-4"></p>
                    <button class="btn-modern btn-outline text-secondary border-secondary px-5 fw-bold" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LEAVE REQUEST MODALS -->

    <!-- Add Leave Modal -->
    <div class="modal fade" id="addLeaveModal" tabindex="-1" aria-labelledby="addLeaveLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header justify-content-center">
                    <h5 class="modal-title fw-bold">Add Scheduled Leave</h5>
                </div>
                <div class="modal-body">
                    <label class="form-label">Type:</label>
                    <select class="form-control mb-3" id="leaveType">
                        <option value="">Select leave type...</option>
                        <option value="Vacation">Vacation Leave</option>
                        <option value="Maternity">Maternity Leave</option>
                        <option value="Paternity">Paternity Leave</option>
                        <option value="Emergency">Emergency Leave</option>
                        <option value="Other">Other</option>
                    </select>

                    <label class="form-label">FROM:</label>
                    <input type="date" class="form-control mb-3" id="leaveFrom">
                    <label class="form-label">TO:</label>
                    <input type="date" class="form-control mb-3" id="leaveTo">

                    <label class="form-label">Reason:</label>
                    <textarea class="form-control mb-3" id="leaveReason" rows="3"
                        placeholder="Explain reason"></textarea>

                    <label class="form-label">Attachment (Optional):</label>
                    <input type="file" class="form-control mb-2" id="leaveAttachment"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <div class="alert alert-danger mb-3" id="fileSizeWarning"
                        style="display: none; font-size: 0.85rem;">File size exceeds 5MB.</div>

                    <div class="alert alert-info mb-3" id="monthlyLimitInfo" style="font-size: 0.9rem;"><i
                            class="bi bi-info-circle"></i> <strong>Monthly Limit:</strong> <span
                            id="monthlyLimitText">Checking...</span></div>

                    <div class="form-check mb-2" id="adminOptionsDiv" style="display: none;">
                        <input class="form-check-input" type="checkbox" id="autoApprove">
                        <label class="form-check-label" for="autoApprove"><strong>Auto-approve</strong> (Admin)</label>
                    </div>
                </div>
                <div class="modal-footer justify-content-end gap-2">
                    <button class="btn-modern btn-solid-success fw-bold px-4" onclick="confirmLeave ? confirmLeave() : null"
                        id="btnSubmitLeave">Submit Request</button>
                    <button class="btn-modern btn-solid-danger fw-bold px-4" onclick="cancelLeaveRequest ? cancelLeaveRequest() : null"
                        data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pre-Submit Confirmation -->
    <div class="modal fade" id="leaveDetailsModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <h5 class="mb-3">Schedule a Leave for this Person?</h5>
                    <p id="leaveDetailsText" class="mb-4"></p>
                    <div class="d-flex justify-content-center gap-3">
                        <button class="btn-modern btn-outline text-secondary border-secondary fw-bold px-4"
                            onclick="goBackToForm ? goBackToForm() : null">Change</button>
                        <button class="btn-modern btn-outline text-success border-success fw-bold px-4"
                            onclick="finalizeLeave ? finalizeLeave() : null">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="leaveDetailsViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Leave Request Details</h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="fw-bold">Type:</label>
                        <div id="viewLeaveType" class=""></div>
                    </div>
                    <div class="mb-3"><label class="fw-bold">Status:</label>
                        <div id="viewLeaveStatus" class=""></div>
                    </div>
                    <div class="mb-3"><label class="fw-bold">Duration:</label>
                        <div id="viewLeaveDates" class=""></div>
                    </div>
                    <div class="mb-3"><label class="fw-bold">Reason:</label>
                        <div id="viewLeaveReason" class="text-muted"></div>
                    </div>
                    <div class="mb-3" id="viewLeaveRejectionReasonContainer" style="display: none;">
                        <label class="fw-bold text-danger">Rejection Reason:</label>
                        <div id="viewLeaveRejectionReason" class="text-danger"></div>
                    </div>
                    <div class="mb-3" id="viewLeaveAttachmentContainer" style="display: none;">
                        <label class="fw-bold">Attachment:</label>
                        <div class=""><a href="#" id="viewLeaveAttachment" target="_blank"
                                class="btn btn-outline-primary btn-sm"><i class="bi bi-paperclip"></i> View</a></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-end" id="viewLeaveActions"></div>
            </div>
        </div>
    </div>

    <!-- Alerts/Confirmations -->
    <div class="modal fade" id="leaveValidationErrorModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center"><i class="bi bi-exclamation-circle text-danger fs-1"></i>
                <h5 class="text-danger mt-3 fw-bold">Validation Error</h5>
                <p id="leaveValidationErrorMsg"></p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-danger border-danger fw-bold px-4"
                        data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="leaveSuccessModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center"><i class="bi bi-check-circle text-success fs-1"></i>
                <h5 class="text-success mt-3 fw-bold">Success</h5>
                <p id="leaveSuccessMsg"></p>
                <div class="mt-3">
                    <button class="btn-modern btn-outline text-success border-success fw-bold px-4"
                        onclick="window.location.reload()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="leaveDeleteConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="text-danger fw-bold">Confirm Delete</h5>
                <p id="leaveDeleteConfirmMsg"></p>
                <div class="mt-3"><button class="btn-modern btn-outline text-secondary border-secondary fw-bold me-2" data-bs-dismiss="modal">No</button><button
                        class="btn-modern btn-outline text-danger border-danger fw-bold" id="leaveDeleteConfirmBtn">Yes, Delete</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="leaveApproveConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="text-success fw-bold">Approve Request</h5>
                <p id="leaveApproveConfirmMsg"></p>
                <div class="mt-3 d-flex justify-content-center gap-2"><button class="btn-modern btn-outline text-secondary border-secondary fw-bold" data-bs-dismiss="modal">Cancel</button><button
                        class="btn-modern btn-outline text-success border-success fw-bold" id="leaveApproveConfirmBtn">Yes, Approve</button></div>
            </div>
        </div>
    </div>

    <!-- Generic Confirm for JS usage -->
    <div class="modal fade" id="leaveConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 id="leaveConfirmTitle" class="fw-bold">Confirm</h5>
                <p id="leaveConfirmMsg"></p>
                <div class="mt-3 d-flex justify-content-center gap-2"><button class="btn-modern btn-outline text-secondary border-secondary fw-bold" data-bs-dismiss="modal">No</button><button
                        class="btn-modern btn-outline text-success border-success fw-bold" id="btnConfirmAction">Yes</button></div>
            </div>
        </div>
    </div>

    <!-- Reject Confirmation Modal -->
    <div class="modal fade" id="leaveRejectConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header justify-content-center">
                    <h5 class="modal-title text-danger fw-bold">Reject Leave Request</h5>
                </div>
                <div class="modal-body text-center">
                    <p>Are you sure you want to reject this leave request?</p>
                    <div class="mb-3">
                        <label for="rejectionReason" class="form-label">Reason (Optional):</label>
                        <textarea class="form-control" id="rejectionReason" rows="3"
                            placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-modern btn-outline text-danger border-danger fw-bold" id="confirmRejectBtn">Yes, Reject</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Leave Confirmation Modal -->
    <div class="modal fade" id="leaveApproveConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-success">Confirm Approval</h5>
                <p>Are you sure you want to approve this leave request?</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-modern btn-outline text-success border-success fw-bold" id="confirmApproveBtn">Yes, Approve</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Leave Confirmation Modal -->
    <div class="modal fade" id="leaveCancelConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-warning">Confirm Cancellation</h5>
                <p>Are you sure you want to cancel this leave request?</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep it</button>
                    <button type="button" class="btn btn-warning" id="confirmCancelBtn">Yes, Cancel Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Manual Modal -->
    <div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content p-3">
                <div class="modal-header justify-content-center">
                    <h5 class="modal-title fw-bold">Manual Attendance Record</h5>
                </div>
                <div class="modal-body">
                    <div id="attendanceContainer">
                        <div class="attendance-row row mb-3 align-items-end">
                            <div class="col-md-3">
                                <label>Date:</label>
                                <input type="date" class="form-control">
                                <div class="schedule-error-container" style="min-height: 0;">
                                    <small class="text-danger schedule-error d-block"
                                        style="display:none; font-size: 0.75rem; margin-top: 4px; line-height: 1.2;"></small>
                                </div>
                            </div>
                            <div class="col-md-3"><label>Time In:</label><input type="time" class="form-control"></div>
                            <div class="col-md-3"><label>Time Out:</label><input type="time" class="form-control"></div>
                            <div class="col-md-3">
                                <div class="pb-1">
                                    <button class="btn-modern btn-solid-warning btn-sm me-1 clearRow" title="Clear Times"><i
                                            class="bi bi-eraser"></i></button>
                                    <button class="btn-modern btn-solid-danger btn-sm removeRow" style="display:none;" title="Remove Row"><i
                                            class="bi bi-x-lg"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
                        <button id="addDayBtn" class="btn-modern btn-solid-warning mt-2">+ Add Day</button>
                    </div>
                </div>
                <div class="modal-footer justify-content-end gap-2">
                    <button class="btn-modern btn-solid-danger fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn-modern btn-solid-success fw-bold px-4" id="saveBtn">Save Records</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Attendance Modal -->
    <div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3">
                <div class="modal-header justify-content-center position-relative">
                    <h5 class="modal-title fw-bold">Edit Attendance</h5>
                </div>
                <div class="modal-body">
                    <form id="editAttendanceForm">
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="text" class="form-control-plaintext fw-bold" id="editAttDate" readonly>
                            <input type="hidden" id="editAttDateValue">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time In</label>
                                <input type="time" class="form-control" id="editAttTimeIn">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time Out</label>
                                <input type="time" class="form-control" id="editAttTimeOut">
                            </div>
                        </div>
                        <div id="editAttError" class="alert alert-danger d-none p-2 mb-3 small"></div>
                    </form>
                </div>
                <div class="modal-footer justify-content-end gap-3 mt-3">
                    <button type="button" class="btn-modern btn-solid-danger fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-modern btn-solid-success fw-bold px-4" id="btnSaveEditAttendance">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attendanceSuccessModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="text-success">Attendance Saved</h5>
                <p id="attendanceSuccessMessage">Records saved successfully.</p>
                <div class="mt-3">
                    <button class="btn-modern btn-outline text-success border-success fw-bold px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="attendanceErrorModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="text-danger">Save Failed</h5>
                <p id="attendanceErrorMessage"></p>
            </div>
        </div>
    </div>



    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel"
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered edit-schedule-modal-dialog">
            <div class="modal-content border-0 shadow-sm">
                <div class="modal-header justify-content-center position-relative">
                    <h4 class="modal-title fw-bold" id="editScheduleModalLabel">Edit Schedule</h4>
                </div>

                <div class="modal-body">
                    <form id="editScheduleForm" action="processes/update_employee_schedule.php" method="POST">
                        <input type="hidden" id="roles" value="<?php echo htmlspecialchars($employee['roles']); ?>">
                        <input type="hidden" name="employee_id"
                            value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                        <input type="hidden" name="first_name"
                            value="<?php echo htmlspecialchars($employee['first_name']); ?>">
                        <input type="hidden" name="last_name"
                            value="<?php echo htmlspecialchars($employee['last_name']); ?>">
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
                            <?php
                            $isFacultyProfile = (strpos(strtolower($employee['roles']), 'faculty') !== false);
                            $disabledAttr = $isFacultyProfile ? '' : 'disabled';
                            $opacityStyle = $isFacultyProfile ? '1' : '0.6';
                            $cursorStyle = $isFacultyProfile ? 'text' : 'not-allowed';
                            $pointerEvents = $isFacultyProfile ? 'auto' : 'none';
                            ?>
                            <div class="form-row" id="faculty-fields" style="opacity: <?php echo $opacityStyle; ?>;">
                                <div class="form-group custom-dropdown"
                                    style="opacity: <?php echo $opacityStyle; ?>; pointer-events: <?php echo $pointerEvents; ?>;">
                                    <label for="designate_class" style="min-height: 45px;">Designate Class <span
                                            style="display: block; color: #999; font-size: 0.9em;">(Faculty Only -
                                            Optional)</span></label>
                                    <input type="text" id="designate_class" name="designate_class"
                                        placeholder="<?php echo $isFacultyProfile ? 'Select or type class name' : 'Available for Faculty_Members only'; ?>"
                                        autocomplete="off"
                                        style="text-transform: uppercase; cursor: <?php echo $cursorStyle; ?>;" <?php echo $disabledAttr; ?>>
                                    <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing
                                        to see existing classes</small>
                                </div>
                                <div class="form-group custom-dropdown"
                                    style="opacity: <?php echo $opacityStyle; ?>; pointer-events: <?php echo $pointerEvents; ?>;">
                                    <label for="designate_subject" style="min-height: 45px;">Subject <span
                                            style="display: block; color: #999; font-size: 0.9em;">(Faculty Only -
                                            Optional)</span></label>
                                    <input type="text" id="designate_subject" name="designate_subject"
                                        placeholder="<?php echo $isFacultyProfile ? 'Select or type subject' : 'Available for Faculty_Members only'; ?>"
                                        autocomplete="off"
                                        style="text-transform: uppercase; cursor: <?php echo $cursorStyle; ?>;" <?php echo $disabledAttr; ?>>
                                    <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing
                                        to see existing subjects</small>
                                </div>
                                <div class="form-group custom-dropdown"
                                    style="opacity: <?php echo $opacityStyle; ?>; pointer-events: <?php echo $pointerEvents; ?>;">
                                    <label for="room-number" style="min-height: 45px;">Room Number <span
                                            style="display: block; color: #999; font-size: 0.9em;">(Faculty Only -
                                            Optional)</span></label>
                                    <input type="text" id="room-number" name="room-number"
                                        placeholder="<?php echo $isFacultyProfile ? 'Select or type room number' : 'Available for Faculty_Members only'; ?>"
                                        autocomplete="off"
                                        style="text-transform: uppercase; cursor: <?php echo $cursorStyle; ?>;" <?php echo $disabledAttr; ?>>
                                    <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing
                                        to see existing rooms</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="display: flex; gap: 10px; justify-content: flex-start;">
                                    <button type="button" id="add-schedule-btn"
                                        class="btn-modern btn-solid-success add-schedule-btn btn-gray-hover"
                                        style="min-width: 140px;" onclick="addSchedule()">Add
                                        Schedule</button>
                                    <button type="button" id="edit-schedule-btn"
                                        class="btn-modern btn-solid-warning edit-schedule-btn btn-gray-hover"
                                        style="min-width: 140px;" onclick="editSchedule()" disabled>Update Selected
                                        Schedule</button>
                                    <button type="button" class="btn-modern btn-solid-danger btn-cancel btn-gray-hover"
                                        style="min-width: 140px;" onclick="clearScheduleForm()">Cancel</button>
                                </div>
                            </div>

                            <div class="schedule-calendar-section">
                                <div class="schedule-header">
                                    <h3>Schedule</h3>
                                    <button type="button"
                                        class="btn-modern btn-solid-danger clear-schedules-btn btn-gray-hover"
                                        style="min-width: 200px;" onclick="clearAllSchedules()">
                                        Clear All Schedules
                                    </button>
                                </div>
                                <!-- Indicators removed per user request -->
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

                        <div class="form-actions d-flex justify-content-end gap-3 mt-4">
                            <button type="submit"
                                class="btn-modern btn-solid-success btn-save btn-gray-hover px-4"
                                style="min-width: 150px;">
                                <?php echo (isset($currentUser['role']) && strtolower($currentUser['role']) === 'admin') ? 'Save Schedule' : 'Submit Request'; ?>
                            </button>
                            <button type="button" class="btn-modern btn-solid-danger btn-cancel btn-gray-hover px-4"
                                style="min-width: 150px;" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>


    <!-- HELPER MODALS FOR EDIT SCHEDULE (Moved to end for z-index stacking) -->
    <div class="modal fade" id="scheduleNoWorkDayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-danger">No Working Day Selected</h5>
                <p id="scheduleNoWorkDayMsg">Please select at least one working day first!</p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4"
                        data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleMissingTimeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-danger">Missing Information</h5>
                <p id="scheduleMissingTimeMsg">Please select both start and end times!</p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4"
                        data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleInvalidTimeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-danger">Invalid Time Range</h5>
                <p id="scheduleInvalidTimeMsg">Start time must be before end time!</p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4"
                        data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleFacultyMissingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-danger">Required Fields</h5>
                <p id="scheduleFacultyMissingMsg">Faculty members must enter class, subject, and room number for
                    schedules!</p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4"
                        data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleAddedSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 rounded-4 shadow border-0">
                <div class="modal-body text-center">
                    <div class="success-icon-container">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-1 text-success">Schedule Added Successfully</h5>
                    <div id="scheduleAddedSuccessMsg"></div>
                    <div class="mt-4">
                        <button type="button" class="btn-modern btn-outline text-success border-success fw-bold px-5" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Request Conflict Modal -->
    <div class="modal fade" id="schedulePendingConflictModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-danger">Pending Request Exists</h5>
                <p id="schedulePendingConflictMsg">You already have a pending schedule request. Please wait for it to be
                    approved or rejected, or cancel it before submitting a new one.</p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-success border-success fw-bold px-4" data-bs-dismiss="modal">Understood</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Warning: Pending Request Exists -->
    <div class="modal fade" id="adminPendingRequestWarningModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;"
       >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-danger">Pending Request Exists</h5>
                <p>This account currently has a pending schedule request. You cannot edit their schedule directly until
                    the pending request is approved or rejected.</p>
                <style>
                    #adminPendingRequestWarningModal .btn {
                        width: auto !important;
                        min-width: 120px !important;
                        /* Give them a sensible minimum width instead of 100% */
                        flex: 0 0 auto !important;
                        margin: 0 !important;
                        padding: 8px 16px !important;
                    }
                </style>
                <div class="d-flex justify-content-center align-items-center gap-3 flex-wrap mt-3">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary fw-bold px-4 py-2 m-0" data-bs-dismiss="modal">Cancel</button>
                    <?php if (isset($pendingRequestId) && $pendingRequestId): ?>
                        <a href="review_schedule_request.php?id=<?php echo urlencode($pendingRequestId); ?>&referrer=staff_profile&emp_id=<?php echo urlencode($employee['employee_id']); ?>"
                            class="btn-modern btn-outline text-danger border-danger fw-bold px-4 py-2 m-0 text-decoration-none d-inline-flex align-items-center justify-content-center">View Request</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleUpdatedSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 rounded-4 shadow border-0">
                <div class="modal-body text-center">
                    <div class="success-icon-container">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-1 text-success">Schedule Updated Successfully</h5>
                    <div id="scheduleUpdatedSuccessMsg"></div>
                    <div class="mt-4">
                        <button type="button" class="btn-modern btn-outline text-success border-success fw-bold px-5" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleClearConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 rounded-4 shadow border-0">
                <div class="modal-body text-center">
                    <div class="success-icon-container" style="background: #fff5f5; color: #e53e3e;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-danger">Confirm Clear All</h5>
                    <p id="scheduleClearConfirmMsg" class="text-muted">Are you sure you want to clear all schedules?</p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
                        <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4 fw-bold" data-bs-dismiss="modal">No, Keep Them</button>
                        <button type="button" class="btn-modern btn-outline text-danger border-danger px-4 fw-bold" id="scheduleClearConfirmBtn">Yes, Clear All</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleDeleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 rounded-4 shadow border-0">
                <div class="modal-body text-center">
                    <div class="success-icon-container" style="background: #fff5f5; color: #e53e3e;">
                        <i class="bi bi-trash3-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-danger">Confirm Delete</h5>
                    <p id="scheduleDeleteConfirmMsg" class="text-muted">Are you sure you want to delete this schedule block?</p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap mt-4">
                        <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn-modern btn-outline text-danger border-danger px-4 fw-bold" id="scheduleDeleteConfirmBtn">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleClearedSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 rounded-4 shadow border-0">
                <div class="modal-body text-center">
                    <div class="success-icon-container">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-success">Schedules Cleared</h5>
                    <p id="scheduleClearedSuccessMsg" class="text-muted">All schedules have been cleared successfully!</p>
                    <div class="mt-4">
                        <button type="button" class="btn-modern btn-outline text-success border-success fw-bold px-5" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleSavedSuccessModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-success">
                    <?php echo (isset($currentUser['role']) && strtolower($currentUser['role']) === 'admin') ? 'Schedule Updated Successfully' : 'Request Submitted'; ?>
                </h5>
                <p>
                    <?php echo (isset($currentUser['role']) && strtolower($currentUser['role']) === 'admin') ? 'The schedule has been updated directly.' : 'Your request has been submitted.'; ?>
                </p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-success border-success fw-bold px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleNoDataModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-info">No Schedules</h5>
                <p id="scheduleNoDataMsg">No schedules to clear!</p>
                <div class="mt-3">
                    <button type="button" class="btn-modern btn-outline text-info border-info fw-bold px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Pending Request Modal -->
    <div class="modal fade" id="viewPendingRequestModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content shadow-lg border-0 rounded-4">
                <div class="modal-header bg-warning bg-opacity-10 border-bottom-0 pb-0 justify-content-center">
                    <h5 class="fw-bold mb-0 text-center" style="color: #664d03;"><i class="bi bi-hourglass-split me-2"></i>Pending Schedule Request</h5>
                </div>
                <div class="modal-body p-4 pt-3 text-center">
                    <p class="text-muted small mb-3">This is the schedule you requested. It is currently waiting for admin approval.</p>
                    <div class="table-responsive" style="overflow-x: auto; padding-bottom: 15px;">
                        <div id="pending-request-calendar-view" class="schedule-calendar-preview"
                            style="min-height: 200px;"></div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 justify-content-end gap-2">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4 fw-bold" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4 fw-bold"
                        onclick="cancelPendingRequest(<?php echo $pendingRequestId; ?>)">
                        <i class="bi bi-x-circle"></i> Cancel Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Variables for JS -->
    <script>
        window.employeeId = '<?php echo $employee_id; ?>';
        window.employeeIdEncoded = '<?php echo htmlspecialchars($employee['employee_id']); ?>';
        window.employeeInternalId = <?php echo $employee['id']; ?>;
        window.isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        window.employeeRole = <?php echo json_encode($employee['roles']); ?>;
        <?php
        $breakDedVal = 60;
        if (isset($conn)) {
            $bdRes = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'break_deduction_minutes'");
            if ($bdRes && $bdRow = $bdRes->fetch_assoc())
                $breakDedVal = (int) $bdRow['setting_value'];
        }
        ?>
        window.breakDeductionMinutes = <?php echo $breakDedVal; ?>;
        window.schedulesData = <?php echo json_encode($processedSchedules); ?>;
    </script>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/new_profile.js?v=<?php echo time(); ?>"></script>

    <!-- Sidebar Toggle Script (Inline for simplicity) -->
    <script>
        document.getElementById('menu-btn').addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('content').classList.toggle('shift');
        });



        // Remove Employee Logic (Simplified/Copied from staffinfo.php)
        const removeForm = document.getElementById('removeEmployeeForm');
        if (removeForm) {
            removeForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const pwd = document.getElementById('adminPasswordInput').value;
                // Reusing the fetch logic logic would be best, but for now just showing alert as placeholder or basic fetch
                // User asked for UI primarily. I'll rely on existing processes/remove_employee.php

                try {
                    const formData = new FormData();
                    formData.append('employee_id', window.employeeIdEncoded);
                    formData.append('admin_password', pwd);
                    formData.append('csrf_token', '<?php echo $_SESSION["csrf_token"] ?? "" ?>');

                    const res = await fetch('processes/remove_employee.php', { method: 'POST', body: formData });
                    const txt = await res.text();
                    let result;
                    try { result = JSON.parse(txt); } catch (e) { console.error('Invalid JSON', txt); throw new Error('Server error'); }

                    if (result.success) {
                        // Show success modal
                        const successModal = new bootstrap.Modal(document.getElementById('removeSuccessModal'));
                        successModal.show();
                        setTimeout(() => { window.location.href = 'staff.php'; }, 2000);
                    } else {
                        document.getElementById('passwordError').textContent = result.message;
                        document.getElementById('passwordError').style.display = 'block';
                    }
                } catch (err) {
                    document.getElementById('errorRemoveMessage').textContent = 'An error occurred while communicating with the server.';
                    new bootstrap.Modal(document.getElementById('errorRemoveModal')).show();
                }
            });
        }
    </script>

    <!-- Leave Confirmation Modal (Generic) -->
    <div class="modal fade" id="leaveConfirmModal2" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 modal-title" id="leaveConfirmTitle2">Confirm Action</h5>
                <p class="mb-4 modal-body-text" id="leaveConfirmMsg2">Are you sure you want to proceed?</p>
                <div class="d-flex justify-content-center gap-3">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary fw-bold" data-bs-dismiss="modal">No, Cancel</button>
                    <button type="button" class="btn-modern btn-outline text-success border-success fw-bold" id="btnConfirmAction2">Yes, Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Pending Request Confirm Modal -->
    <div class="modal fade" id="cancelPendingRequestConfirmModal" tabindex="-1" aria-hidden="true"
        style="z-index: 1060;" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center rounded-4 shadow border-0">
                <h5 class="fw-bold mb-3 text-danger">Confirm Cancel</h5>
                <p id="cancelPendingRequestConfirmMsg">Are you sure you want to cancel this pending schedule request?
                </p>
                <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
                    <button type="button" class="btn-modern btn-outline text-secondary border-secondary px-4 fw-bold" data-bs-dismiss="modal">No, Keep It</button>
                    <button type="button" class="btn-modern btn-outline text-danger border-danger px-4 fw-bold" id="cancelPendingRequestConfirmBtn">Yes, Cancel
                        Request</button>
                </div>
            </div>
        </div>
    </div>
    <!-- ========================= PHP: PREPARE DATA FOR EDIT SCHEDULE ========================= -->
    <?php
    $emp_id = $employee['id'] ?? null;
    $existingSchedules = [];
    if ($emp_id) {
        // Fetch schedules with assignments using the same logic as staffinfo.php
        $sql = "SELECT es.*, sp.day_of_week, sp.start_time, sp.end_time, sp.period_name,
                       ea.designate_class, ea.subject_code, ea.room_num
                FROM employee_schedules es
                JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                LEFT JOIN employee_assignments ea ON es.employee_id = ea.employee_id AND sp.id = ea.schedule_period_id
                WHERE es.employee_id = ? AND es.is_active = 1 AND sp.is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $emp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $scheduleMap = [];
        while ($row = $result->fetch_assoc()) {
            $key = $row['schedule_id'] . '_' . $row['start_time'] . '_' . $row['end_time'] . '_' . ($row['designate_class'] ?? '') . '_' . ($row['subject_code'] ?? '') . '_' . ($row['room_num'] ?? '');
            if (!isset($scheduleMap[$key])) {
                $scheduleMap[$key] = [
                    'days' => [],
                    'startTime' => $row['start_time'],
                    'endTime' => $row['end_time'],
                    'class' => $row['designate_class'] ?? $row['period_name'] ?? '',
                    'subject' => $row['subject_code'] ?? '',
                    'room_num' => $row['room_num'] ?? '',
                ];
            }
            // Map 0-6 to Day Names
            $daysOfWeek = [0 => 'Monday', 1 => 'Tuesday', 2 => 'Wednesday', 3 => 'Thursday', 4 => 'Friday', 5 => 'Saturday', 6 => 'Sunday'];
            $dayStr = $daysOfWeek[$row['day_of_week']] ?? '';
            if ($dayStr && !in_array($dayStr, $scheduleMap[$key]['days'])) {
                $scheduleMap[$key]['days'][] = $dayStr;
            }
        }
        $existingSchedules = array_values($scheduleMap);
        $stmt->close();
    }

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

    <!-- ========================= SCRIPTS ========================= -->

    <script src="../assets/js/edit_employee.js?v=<?php echo time(); ?>"></script>
    <script>
        // Global variables required by logic scripts
        const employeeIdForLeave = <?php echo json_encode($employee['id']); ?>;
        const employeeInternalId = <?php echo json_encode($employee['id']); ?>; // Alias
        const employeeCode = <?php echo json_encode($employee['employee_id']); ?>;
        const isAdmin = <?php echo json_encode($isAdmin); ?>;

        // Schedule Data for Edit Modal
        window.existingSchedules = <?php echo json_encode($existingSchedules); ?>;

        // Options for User Dropdowns
        window.existingClasses = <?php echo json_encode($existing_classes); ?>;
        window.existingSubjects = <?php echo json_encode($existing_subjects); ?>;
        window.existingRooms = <?php echo json_encode($existing_rooms); ?>;

        document.addEventListener('DOMContentLoaded', function () {
            // Initialize the edit schedule modal calendar when modal is shown
            const editScheduleModal = document.getElementById('editScheduleModal');
            if (editScheduleModal) {

                // Track whether the schedule was saved successfully
                window._editScheduleSaved = false;

                editScheduleModal.addEventListener('shown.bs.modal', function () {
                    console.log('Edit schedule modal opened, initializing calendar...');

                    // Snapshot the schedules BEFORE any user changes (for cancel/restore)
                    window._editScheduleSnapshot = JSON.parse(JSON.stringify(window.editAddedSchedules || []));
                    window._editScheduleSaved = false;

                    // The initializeCalendar function from edit_employee.js should be available
                    if (typeof initializeCalendar === 'function') {
                        initializeCalendar();
                    }
                    // Re-render schedules after calendar initialization
                    if (typeof renderSchedules === 'function') {
                        console.log('Re-rendering schedules. Total schedules:', window.editAddedSchedules?.length || 0);
                        renderSchedules();
                    }
                    // Always clear the form inputs when modal opens so it starts fresh
                    if (typeof clearScheduleForm === 'function') {
                        clearScheduleForm();
                    }
                });

                // When the modal is closed/dismissed without saving: restore the snapshot
                editScheduleModal.addEventListener('hidden.bs.modal', function () {
                    if (typeof clearScheduleForm === 'function') {
                        clearScheduleForm();
                    }
                    // Restore schedules to pre-open state if the user did NOT save
                    if (!window._editScheduleSaved && window._editScheduleSnapshot !== undefined) {
                        window.editAddedSchedules = window._editScheduleSnapshot;
                        // Sync the module-level variable via the global reference
                        if (typeof editAddedSchedules !== 'undefined') {
                            try { editAddedSchedules = window.editAddedSchedules; } catch(e) {}
                        }
                        window._editScheduleSnapshot = undefined;
                        console.log('Schedule changes discarded (cancel). Restored to:', window.editAddedSchedules.length, 'schedule(s).');
                    }
                });
            }
        });

        // DTR Export Redirection (from staffinfo.php)
        document.getElementById('exportDtrBtn')?.addEventListener('click', function () {
            const id = '<?php echo htmlspecialchars($employee['employee_id']); ?>';
            window.location.href = `../attendancerep/indirep.php?id=${id}`;
        });
    </script>
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
                    <form id="logoutForm" method="POST" action="../dashboard/logout.php" style="display:inline;">
                        <input type="hidden" name="confirm_logout" value="1">
                        <button type="submit" class="btn-modern btn-outline text-danger border-danger px-4">Yes, Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- <script src="../assets/js/staff_profile_logic.js?v=<?php echo time(); ?>"></script> -->
    <script>
        // Logout Modal Trigger
        function showLogoutModal() {
            var modal = new bootstrap.Modal(document.getElementById('logoutModal'));
            modal.show();
        }

        // Sidebar Toggle Logic - Direct Style Manipulation with Proper State Detection
        window.toggleSidebar = function () {
            const sidebar = document.getElementById("sidebar");
            const content = document.getElementById("content");

            if (!sidebar || !content) {
                console.error("Sidebar or Content element not found!");
                return;
            }

            // Dashboard.js uses 576px as the cutoff
            if (window.innerWidth <= 576) {
                // Mobile: toggle visibility
                const computedLeft = window.getComputedStyle(sidebar).left;
                const isVisible = computedLeft === '0px';

                if (isVisible) {
                    sidebar.style.left = '-250px';
                    const existing = document.getElementById('mobileBackdrop');
                    if (existing) existing.remove();
                    document.body.style.overflow = '';
                } else {
                    sidebar.style.left = '0px';
                    sidebar.style.zIndex = '1050';
                    document.body.style.overflow = 'hidden';

                    const backdrop = document.createElement('div');
                    backdrop.setAttribute('id', 'mobileBackdrop');
                    backdrop.style.position = 'fixed';
                    backdrop.style.top = '0';
                    backdrop.style.left = '0';
                    backdrop.style.width = '100vw';
                    backdrop.style.height = '100vh';
                    backdrop.style.background = 'rgba(0,0,0,0.5)';
                    backdrop.style.zIndex = '1040';
                    document.body.appendChild(backdrop);

                    backdrop.addEventListener('click', () => {
                        sidebar.style.left = '-250px';
                        document.body.style.overflow = '';
                        backdrop.remove();
                    });
                }
            } else {
                // Desktop/Tablet: toggle sidebar and content margin
                // Check the ACTUAL computed style, not just inline style
                const computedLeft = window.getComputedStyle(sidebar).left;
                const isVisible = computedLeft === '0px';

                if (isVisible) {
                    // Currently visible, hide it
                    sidebar.style.left = '-250px';
                    content.style.marginLeft = '0px';
                } else {
                    // Currently hidden, show it
                    sidebar.style.left = '0px';
                    content.style.marginLeft = '250px';
                }
            }
        };

        // Initialize state (optional)
        document.addEventListener('DOMContentLoaded', function () {
            // No auto-run needed, click event handles it.

            // Fix for stacked modals: When Delete/Helper Confirmation closes, ensure Edit Schedule modal keeps scrolling/focus
            const scheduleHelperModals = [
                'scheduleDeleteConfirmModal',
                'scheduleNoWorkDayModal',
                'scheduleMissingTimeModal',
                'scheduleInvalidTimeModal',
                'scheduleFacultyMissingModal',
                'scheduleAddedSuccessModal',
                'scheduleUpdatedSuccessModal',
                'scheduleClearConfirmModal',
                'scheduleClearedSuccessModal',
                'scheduleSavedSuccessModal',
                'scheduleNoDataModal'
            ];

            scheduleHelperModals.forEach(modalId => {
                const modalEl = document.getElementById(modalId);
                if (modalEl) {
                    // 1. When helper modal ID hides, check if #editScheduleModal is open -> restore scrolling
                    modalEl.addEventListener('hidden.bs.modal', function () {
                        const editScheduleModal = document.getElementById('editScheduleModal');
                        if (editScheduleModal && editScheduleModal.classList.contains('show')) {
                            document.body.classList.add('modal-open');
                            if (!document.querySelector('.modal-backdrop')) {
                                const backdrop = document.createElement('div');
                                backdrop.className = 'modal-backdrop fade show';
                                document.body.appendChild(backdrop);
                            }
                        }
                    });

                    // 2. When helper modal shows, force it ABOVE the edit modal (z-index 2050 from inline style works)
                    modalEl.addEventListener('show.bs.modal', function () {
                        // Set modal higher with !important to override staff.css
                        this.style.setProperty('z-index', '2050', 'important');
                    });
                }
            });
        });
    </script>
    <style>
        /* Mobile Nav Override if distinct from active */
        .sidebar.mobile-nav {
            left: 0 !important;
            margin-left: 0 !important;
            z-index: 1050;
        }

        body.lock-scroll {
            overflow: hidden;
        }

        /* Global Override for Shifted Content (Sidebar Closed) */
        /* Takes precedence over staff.css global .content.shift */
        #content.shift {
            margin-left: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        /* Sidebar Toggle Styles (Using IDs for Specificity) */
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

            /* #content margin defaults to 0 via staff.css, or base styles */
            #content {
                margin-left: 0 !important;
            }
        }
    </style>
</body>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Check for update success
        const updateStatus = '<?php echo $updateStatus; ?>';
        if (updateStatus === 'updated') {
            const successModal = new bootstrap.Modal(document.getElementById('saveSuccessModal'));
            successModal.show();
            // Clean up URL without reloading
            const url = new URL(window.location);
            url.searchParams.delete('status');
            url.searchParams.delete('t');
            window.history.replaceState({}, '', url);
        }

        // Check for update error
        const updateError = <?php echo json_encode($updateError); ?>;
        if (updateError) {
            const errorModalVal = new bootstrap.Modal(document.getElementById('errorRemoveModal'));
            const msgEl = document.getElementById('errorRemoveMessage');
            if (msgEl) msgEl.textContent = updateError;
            errorModalVal.show();
        }
    });

</script>

</html>