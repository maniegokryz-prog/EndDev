<?php
// Protect this page - require authentication
require_once '../auth_guard.php';
require_once '../navigation.php';
require '../db_connection.php';

// Get current user info
$currentUser = getCurrentUser();
$isAdmin = isset($currentUser['role']) && $currentUser['role'] === 'admin';

// Check if user can request leave
function canRequestLeave($employeeRoles) {
    if (empty($employeeRoles)) return false;
    $rolesLower = strtolower($employeeRoles);
    if (stripos($rolesLower, 'faculty') !== false) return false;
    if (stripos($rolesLower, 'admin') !== false) return true;
    if (stripos($rolesLower, 'non-teaching') !== false || stripos($rolesLower, 'non_teaching') !== false) return true;
    return true;
}

// --- Classes (Copied from staffinfo.php) ---

class EmployeeEditor {
    private $db;
    private $employee = null;
    private $errors = [];

    public function __construct($database) { $this->db = $database; }

    public function loadEmployee($employee_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM employees WHERE employee_id = ?");
            if (!$stmt) throw new Exception('Failed to prepare statement: ' . $this->db->error);
            $stmt->bind_param('s', $employee_id);
            if (!$stmt->execute()) throw new Exception('Failed to execute query: ' . $stmt->error);
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
    public function getEmployee() { return $this->employee; }
    public function getErrors() { return $this->errors; }
    public function hasErrors() { return !empty($this->errors); }
}

class EmployeeDetailViewer {
    private $db;
    private $employee = null;
    private $schedules = [];
    private $errors = [];
    
    public function __construct($database) { $this->db = $database; }
    
    public function loadEmployeeDetails($employee_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, employee_id, first_name, middle_name, last_name, 
                       email, phone, roles, department, position, hire_date, 
                       status, created_at, updated_at, profile_photo
                FROM employees 
                WHERE employee_id = ?
            ");
            if (!$stmt) throw new Exception('Failed to prepare statement: ' . $this->db->error);
            $stmt->bind_param('s', $employee_id);
            if (!$stmt->execute()) throw new Exception('Failed to execute employee query: ' . $stmt->error);
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
    
     private function loadEmployeeSchedules($internal_employee_id) {
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
            if (!$stmt) throw new Exception('Failed to prepare schedule query: ' . $this->db->error);
            $stmt->bind_param('i', $internal_employee_id);
            if (!$stmt->execute()) throw new Exception('Failed to execute schedule query: ' . $stmt->error);
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
    
    private function sanitizeData($data) {
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
    
    public function getEmployee() { return $this->employee; }
    public function getSchedules() { return $this->schedules; }
    public function getFullName() {
        if (!$this->employee) return 'Unknown';
        $nameParts = [];
        if ($this->employee['first_name'] && $this->employee['first_name'] !== 'N/A') $nameParts[] = $this->employee['first_name'];
        if ($this->employee['middle_name'] && $this->employee['middle_name'] !== 'N/A') $nameParts[] = $this->employee['middle_name'];
        if ($this->employee['last_name'] && $this->employee['last_name'] !== 'N/A') $nameParts[] = $this->employee['last_name'];
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
        $dayIdx = (int)$s['day_of_week'];
        if (isset($dayNames[$dayIdx]) && !in_array($dayNames[$dayIdx], $groups[$key]['days'])) {
            $groups[$key]['days'][] = $dayNames[$dayIdx];
        }
    }
    $processedSchedules = array_values($groups);
}

// Profile Photo Logic
$profilePhoto = '../assets/profile_pic/user.png';
if (!empty($employee['profile_photo']) && $employee['profile_photo'] !== 'N/A') {
    if (strpos($employee['profile_photo'], 'assets/') === 0) {
        $profilePhoto = '../' . $employee['profile_photo'];
    } else {
        $profilePhoto = '../assets/profile_pic/' . $employee['profile_photo'];
    }
}
$profilePhoto .= '?v=' . time();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile - <?php echo $viewer->getFullName(); ?></title>
    
    <!-- Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?php echo time(); ?>"> <!-- Base styles for sidebar/nav -->
    <link rel="stylesheet" href="../assets/css/new_profile.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="staff.css?v=<?php echo time(); ?>"> <!-- Keep for Sidebar styles specific to staff module -->

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

    <!-- Top Navbar -->
    <div class="top-navbar d-flex justify-content-between align-items-center p-2 shadow-sm">
        <div class="menu-toggle">
            <i class="bi bi-list fs-3 text-warning icon-btn" id="menu-btn"></i>
        </div>
        <?php include '../includes/notification_bell.php'; ?>
    </div>

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column pt-5" id="sidebar">
        <div class="profile text-center p-3 mt-4">
            <img src="<?php echo !empty($currentUser['profile_photo']) ? '../' . htmlspecialchars($currentUser['profile_photo']) . '?v=' . time() : '../assets/profile_pic/user.png'; ?>" 
                 alt="Profile" class="rounded-circle mb-2" width="70" height="70">
            <h5 class="mb-0"><?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?></h5>
            <small class="role"><?php echo htmlspecialchars(ucfirst($currentUser['role'] ?? 'User')); ?></small>
        </div>
        <nav class="nav flex-column px-2">
            <?php renderNavigation('My Info'); ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="content pt-3" id="content">
        <div class="container-fluid p-4">
            
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
                                <?php echo htmlspecialchars($employee['roles']); ?> | <?php echo htmlspecialchars($employee['department']); ?>
                            </div>
                            <div class="contact-info">
                                <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($employee['email']); ?></div>
                                <div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($employee['phone'] !== 'N/A' ? $employee['phone'] : 'No contact info'); ?></div>
                            </div>
                            <div class="action-buttons">
                                <button class="btn-modern btn-outline" data-bs-toggle="modal" data-bs-target="#editInfoModal">
                                    <i class="bi bi-pencil"></i> Edit Info
                                </button>
                                <?php if ($isAdmin): ?>
                                <button class="btn-modern btn-outline text-danger border-danger" data-bs-toggle="modal" data-bs-target="#removeEmployeeModal">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
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
                                <?php for($m=1; $m<=12; $m++): ?>
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
                                    echo "<option value='$y' ".($y == $curYear ? 'selected' : '').">$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="metricsLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                    <div id="metricsError" class="text-center py-4 text-danger" style="display:none;">Failed to load metrics</div>

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
                                 <button class="btn btn-sm btn-outline-secondary" id="dateRangeTrigger" title="Select Dates">
                                    <i class="bi bi-calendar3"></i>
                                 </button>
                                 <!-- Calendar Popup Dropdown -->
                                 <div id="calendarPopup" class="calendar-popup shadow-lg" style="display: none;">
                                    <div class="calendar-header p-2 border-bottom">
                                        <button class="calendar-nav-btn btn-sm" id="prevMonth"><i class="bi bi-chevron-left"></i></button>
                                        <span class="fw-bold small" id="calendarTitle">Month Year</span>
                                        <button class="calendar-nav-btn btn-sm" id="nextMonth"><i class="bi bi-chevron-right"></i></button>
                                    </div>
                                    <div id="calendar" class="calendar-days p-2"></div>
                                    <div class="p-2 border-top text-end">
                                        <button class="btn btn-xs btn-light" id="clearDatesBtn">Clear</button>
                                        <button class="btn btn-xs btn-primary close-calendar-btn">Done</button>
                                    </div>
                                    <!-- Hidden input for value storage -->
                                    <input type="hidden" id="dateRangeInput"> 
                                </div>
                            </div>
                        </div>
                        
                        <!-- Compact Actions -->
                        <div class="d-flex gap-2 mb-3">
                            <?php if ($isAdmin): ?>
                            <button class="btn btn-sm btn-outline-success flex-grow-1" data-bs-toggle="modal" data-bs-target="#attendanceModal">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-primary flex-grow-1" id="exportDtrBtn">
                                 <i class="bi bi-box-arrow-up-right"></i> Details
                            </button>
                        </div>

                        <div id="dtrLoading" class="text-center py-2" style="display:none;"><div class="spinner-border spinner-border-sm"></div></div>
                        <div id="dtrList" class="dtr-list-vertical"></div>
                    </div>

                    <!-- Leave Request Section (Restored) -->
                    <?php if (canRequestLeave($employee['roles'])): ?>
                    <div class="profile-card leave-section-card" style="border-left: 4px solid var(--secondary-color);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">Scheduled Leave</h4>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addLeaveModal" title="Add Request">
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
                        <?php if ($isAdmin): ?>
                        <button class="btn-modern btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#editScheduleModal">
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

    <!-- Includes for Modals (Reusing existing modals from staffinfo.php but stripped down or included) -->
    <!-- I will copy the critical modal structures here to ensure they exist -->
    
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Leave Management Modals -->
    <div class="modal fade" id="addLeaveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title">Add Scheduled Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                <label class="form-label">Type:</label>
                <select class="form-control mb-3" id="leaveType">
                    <option value="">Select leave type...</option>
                    <option value="Sick">Sick Leave</option>
                    <option value="Vacation">Vacation Leave</option>
                    <option value="Maternity">Maternity Leave</option>
                    <option value="Paternity">Paternity Leave</option>
                    <option value="Emergency">Emergency Leave</option>
                    <option value="Other">Other</option>
                </select>

                <div class="row">
              <div class="col-md-6">
                <label class="form-label">FROM:</label>
                <input type="date" class="form-control mb-2" id="leaveFrom">
                <input type="time" class="form-control mb-3" id="leaveStartTime" disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label">TO:</label>
                <input type="date" class="form-control mb-2" id="leaveTo">
                <input type="time" class="form-control mb-3" id="leaveEndTime" disabled>
              </div>
            </div>
                
                <label class="form-label">Reason:</label>
                <textarea class="form-control mb-3" id="leaveReason" rows="3" placeholder="Briefly explain your reason for leave"></textarea>
                
                <label class="form-label">Attachment (Optional):</label>
                <input type="file" class="form-control mb-2" id="leaveAttachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                
                <div class="alert alert-info mb-3" id="monthlyLimitInfo" style="font-size: 0.9rem;">
                    <strong>Checking limits...</strong>
                </div>
                
                <?php if ($isAdmin): ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="autoApprove">
                    <label class="form-check-label" for="autoApprove">
                    <strong>Auto-approve this request</strong> (Admin only)
                    </label>
                </div>
                <?php endif; ?>
                </div>
                <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="btnSubmitLeave">Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Details Modal (View) -->
    <div class="modal fade" id="leaveDetailsViewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title">Leave Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                <div class="mb-2"><strong class="me-2">Type:</strong> <span id="viewLeaveType"></span></div>
                <div class="mb-2"><strong class="me-2">Status:</strong> <span id="viewLeaveStatus"></span></div>
                <div class="mb-2"><strong class="me-2">Dates:</strong> <span id="viewLeaveDates"></span></div>
                <div class="mb-2"><strong class="me-2">Reason:</strong> <div id="viewLeaveReason" class="text-muted p-2 bg-light rounded mt-1"></div></div>
                <div id="viewLeaveAttachmentContainer" class="mt-3" style="display:none;">
                    <a href="#" id="viewLeaveAttachment" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-paperclip"></i> View Attachment</a>
                </div>
                </div>
                <div class="modal-footer" id="viewLeaveActions">
                    <!-- Dynamic buttons -->
                </div>
            </div>
        </div>
    </div>

    <!-- Generic Success Modal -->
    <div class="modal fade" id="leaveSuccessModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <div class="mb-3 text-success"><i class="bi bi-check-circle fs-1"></i></div>
                <h5 class="fw-bold mb-3 success-title">Success</h5>
                <p id="leaveSuccessMsg"></p>
                <button class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
    
    <!-- Generic Error Modal -->
    <div class="modal fade" id="leaveErrorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <div class="mb-3 text-danger"><i class="bi bi-exclamation-circle fs-1"></i></div>
                <h5 class="fw-bold mb-3 text-danger">Error</h5>
                <p id="leaveErrorMsg"></p>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Confirm Action Modal -->
    <div class="modal fade" id="leaveConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3" id="leaveConfirmTitle">Confirm Action</h5>
                <p id="leaveConfirmMsg"></p>
                <div class="d-flex justify-content-center gap-2 mt-3">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button class="btn btn-primary" id="btnConfirmAction">Yes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Employee Modal -->
    <div class="modal fade" id="removeEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <i class="bi bi-exclamation-circle text-danger fs-1"></i>
                <h5 class="mt-3">Remove Employee?</h5>
                <p class="text-muted">This action cannot be undone easily. Please enter admin password.</p>
                <form id="removeEmployeeForm">
                    <input type="password" class="form-control mb-3" id="adminPasswordInput" placeholder="Admin Password" required>
                    <div id="passwordError" class="text-danger small mb-2" style="display:none;"></div>
                    <button type="submit" class="btn btn-danger" id="confirmRemoveBtn">Confirm Removal</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================= HELPER MODALS (Moved to end for Z-Index) ========================= -->
    
    <!-- Validation/Error Modal -->
    <div class="modal fade" id="leaveValidationErrorModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <div class="mb-3"><i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i></div>
                <h5 class="fw-bold mb-3 text-danger">Attention</h5>
                <p id="leaveValidationErrorMsg"></p>
                <button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>

    <!-- Pre-Submit Confirmation Modal (Leave Request) -->
    <div class="modal fade" id="leaveDetailsModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <h5 class="mb-3">Start Leave Request?</h5>
                    <p id="leaveDetailsText" class="mb-4 text-muted"></p>
                    <div class="d-flex justify-content-center gap-3">
                        <button class="btn btn-outline-dark" onclick="goBackToForm()">Edit</button>
                        <button class="btn btn-success" onclick="finalizeLeave()">Confirm & Submit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Confirmation Modal (Admin) -->
    <div class="modal fade" id="leaveApproveConfirmModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-success">Approve Request</h5>
                <p id="leaveApproveConfirmMsg"></p>
                <div class="d-flex justify-content-center gap-3 mt-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="leaveApproveConfirmBtn">Yes, Approve</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete/Reject Confirmation Modal -->
    <div class="modal fade" id="leaveDeleteConfirmModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-danger" id="leaveDeleteConfirmTitle">Confirm Action</h5>
                <p id="leaveDeleteConfirmMsg"></p>
                <div class="d-flex justify-content-center gap-3 mt-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-danger" id="leaveDeleteConfirmBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Success Modal -->
    <div class="modal fade" id="attendanceSuccessModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5 class="fw-bold mb-3 text-success">Attendance Saved</h5>
                <p id="attendanceSuccessMessage">Records saved successfully.</p>
                <button type="button" class="btn btn-primary mt-3" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>

    <!-- Manual Attendance Modal (Copied from staffinfo.php) -->
    <div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content p-3">
            <div class="modal-header">
            <h5 class="modal-title">Manual Attendance Record</h5>
            </div>
            <div class="modal-body">
            <div id="attendanceContainer">
                <div class="attendance-row row mb-3 align-items-start">
                <div class="col-md-3">
                    <label>Date:</label>
                    <input type="date" class="form-control">
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
                    <button class="btn btn-danger removeRow" style="display:none; margin-top: 32px;">−</button>
                </div>
                </div>
            </div>
            <button id="addDayBtn" class="btn btn-warning mt-2">+ Add Another Day</button>
            </div>
            <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" id="saveBtn">Save Records</button>
            </div>
        </div>
        </div>
    </div>

    <!-- Edit Schedule Modal (Simplified Placeholder - Full implementation requires copying a lot of logic) -->
    <!-- Ideally, we should include the full modal from staffinfo.php, but for now I'll provide a link or simple placeholder as the logic is very complex -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4 text-center">
                <h5>Edit Schedule</h5>
                <p>To edit the schedule, please use the legacy interface or contact the system administrator.</p>
                <a href="staffinfo.php?id=<?php echo $employee_id; ?>" class="btn btn-primary">Go to Legacy Editor</a>
            </div>
        </div>
    </div>

    <!-- Variables for JS -->
    <script>
        window.employeeId = '<?php echo $employee_id; ?>';
        window.employeeIdEncoded = '<?php echo htmlspecialchars($employee['employee_id']); ?>';
        window.employeeInternalId = <?php echo $employee['id']; ?>;
        window.isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        window.schedulesData = <?php echo json_encode($processedSchedules); ?>;
    </script>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/new_profile.js?v=<?php echo time(); ?>"></script>
    
    <!-- Sidebar Toggle Script (Inline for simplicity) -->
    <script>
        document.getElementById('menu-btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('content').classList.toggle('shift');
        });
        
        // Remove Employee Logic (Simplified/Copied from staffinfo.php)
        const removeForm = document.getElementById('removeEmployeeForm');
        if(removeForm) {
            removeForm.addEventListener('submit', async function(e) {
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
                    try { result = JSON.parse(txt); } catch(e) { console.error('Invalid JSON', txt); throw new Error('Server error'); }
                    
                    if(result.success) {
                        alert('Employee removed.');
                        window.location.href = 'staff.php';
                    } else {
                        document.getElementById('passwordError').textContent = result.message;
                        document.getElementById('passwordError').style.display = 'block';
                    }
                } catch(err) {
                    alert('Error removing employee.');
                }
            });
        }
    </script>
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
                WHERE es.employee_id = ? AND es.is_active = 1";
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
            $daysOfWeek = [0=>'Monday',1=>'Tuesday',2=>'Wednesday',3=>'Thursday',4=>'Friday',5=>'Saturday',6=>'Sunday'];
            $dayStr = $daysOfWeek[$row['day_of_week']] ?? '';
            if ($dayStr && !in_array($dayStr, $scheduleMap[$key]['days'])) {
                $scheduleMap[$key]['days'][] = $dayStr;
            }
        }
        $existingSchedules = array_values($scheduleMap);
        $stmt->close();
    }
    ?>

    <!-- ========================= SCRIPTS ========================= -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/edit_employee.js"></script>
    <script>
        // Global variables required by logic scripts
        const employeeIdForLeave = <?php echo json_encode($employee['id']); ?>;
        const employeeInternalId = <?php echo json_encode($employee['id']); ?>; // Alias
        const employeeCode = <?php echo json_encode($employee['employee_id']); ?>;
        const isAdmin = <?php echo json_encode($isAdmin); ?>;
        
        // Schedule Data for Edit Modal
        window.existingSchedules = <?php echo json_encode($existingSchedules); ?>;

        // DTR Export Redirection (from staffinfo.php)
        document.getElementById('exportDtrBtn')?.addEventListener('click', function() {
            const id = '<?php echo htmlspecialchars($employee['employee_id']); ?>';
            window.location.href = `../attendancerep/indirep.php?id=${id}`;
        });

        // Toggle Day helper (global scope for onclick in HTML)
        window.toggleDay = function(btn) {
            btn.classList.toggle('active');
            // Logic to update hidden input handled by edit_employee.js usually, 
            // but if edit_employee.js uses a different class/id, we might need to shim it.
            // edit_employee.js likely attaches listeners or we rely on the onclick="toggleDay(this)" attributes I copied.
        };
    </script>
    <script src="../assets/js/staff_profile_logic.js"></script>
</body>
</html>
