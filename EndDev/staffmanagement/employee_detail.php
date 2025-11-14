<?php
require '../db_connection.php';

class EmployeeDetailViewer {
    private $db;
    private $employee = null;
    private $schedules = [];
    private $errors = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function loadEmployeeDetails($employee_id) {
        try {
            // Get employee basic information
            $stmt = $this->db->prepare("
                SELECT id, employee_id, first_name, middle_name, last_name, 
                       email, phone, roles, department, position, hire_date, 
                       status, created_at, updated_at, profile_photo
                FROM employees 
                WHERE employee_id = ?
            ");
            
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $this->db->error);
            }
            
            $stmt->bind_param('s', $employee_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute employee query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if (!$result) {
                throw new Exception('Failed to get result set');
            }
            
            $this->employee = $result->fetch_assoc();
            
            $stmt->close();
            
            if (!$this->employee) {
                $this->errors[] = "Employee not found with ID: " . htmlspecialchars($employee_id);
                return false;
            }
            
            // Sanitize employee data
            $this->employee = $this->sanitizeData($this->employee);
            
            // Get employee schedules and assignments
            $this->loadEmployeeSchedules($this->employee['id']);
            
            $this->logActivity("Employee details viewed", "Employee ID: " . $employee_id);
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
            $this->logError("Employee Details Load Failed", $e->getMessage());
            return false;
        }
    }
    
    private function loadEmployeeSchedules($internal_employee_id) {
        try {
            $query = "
                SELECT 
                    s.schedule_name,
                    s.description as schedule_description,
                    sp.day_of_week,
                    sp.period_name,
                    sp.start_time,
                    sp.end_time,
                    ea.subject_code,
                    ea.designate_class,
                    ea.room_num,
                    es.is_active
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
            
            if (!$stmt) {
                throw new Exception('Failed to prepare schedule query: ' . $this->db->error);
            }
            
            $stmt->bind_param('i', $internal_employee_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute schedule query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if (!$result) {
                throw new Exception('Failed to get result set');
            }
            
            $this->schedules = [];
            while ($row = $result->fetch_assoc()) {
                $this->schedules[] = $this->sanitizeData($row);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $this->errors[] = "Error loading schedules: " . $e->getMessage();
            $this->logError("Schedule Load Failed", $e->getMessage());
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
    
    public function getEmployee() {
        return $this->employee;
    }
    
    public function getSchedules() {
        return $this->schedules;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    public function getFullName() {
        if (!$this->employee) return 'Unknown';
        
        $nameParts = [];
        if ($this->employee['first_name'] && $this->employee['first_name'] !== 'N/A') {
            $nameParts[] = $this->employee['first_name'];
        }
        if ($this->employee['middle_name'] && $this->employee['middle_name'] !== 'N/A') {
            $nameParts[] = $this->employee['middle_name'];
        }
        if ($this->employee['last_name'] && $this->employee['last_name'] !== 'N/A') {
            $nameParts[] = $this->employee['last_name'];
        }
        
        return implode(' ', $nameParts);
    }
    
    public function getDayName($day_of_week) {
        // IMPORTANT: Use 0-6 format (Monday=0, Sunday=6) to match Python's datetime.weekday()
        $days = [
            0 => 'Monday',
            1 => 'Tuesday', 
            2 => 'Wednesday',
            3 => 'Thursday',
            4 => 'Friday',
            5 => 'Saturday',
            6 => 'Sunday'
        ];
        
        return $days[$day_of_week] ?? 'Unknown';
    }
    
    public function formatTime($time) {
        if (!$time || $time === 'N/A') return $time;
        
        try {
            $datetime = DateTime::createFromFormat('H:i:s', $time);
            if (!$datetime) {
                $datetime = DateTime::createFromFormat('H:i', $time);
            }
            
            if ($datetime) {
                return $datetime->format('g:i A');
            }
        } catch (Exception $e) {
            // Return original if formatting fails
        }
        
        return $time;
    }
    
    public function formatDateTime($datetimeStr) {
        if (!$datetimeStr || $datetimeStr === 'N/A') {
            return $datetimeStr;
        }
        try {
            $date = new DateTime($datetimeStr);
            return $date->format('M d, Y, g:i A');
        } catch (Exception $e) {
            // Return original string if formatting fails
            return $datetimeStr;
        }
    }

    private function logActivity($activity, $reference = '') {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [ACTIVITY] " . $activity;
        if ($reference) $log_entry .= " - " . $reference;
        $log_entry .= PHP_EOL;
        
        $log_dir = __DIR__ . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function logError($context, $message) {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [ERROR] Context: " . $context . " - Message: " . $message . PHP_EOL;
        
        $log_dir = __DIR__ . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'system.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Get employee ID from URL parameter
$employee_id = $_GET['id'] ?? '';

if (empty($employee_id)) {
    header('Location: showRecord.php?error=no_id');
    exit;
}

// Initialize the viewer
$viewer = new EmployeeDetailViewer($conn);
$loadSuccess = $viewer->loadEmployeeDetails($employee_id);
$employee = $viewer->getEmployee();
$schedules = $viewer->getSchedules();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details - <?php echo $viewer->getFullName(); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1, h2 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .nav-links {
            margin: 20px 0;
        }
        .nav-links a {
            display: inline-block;
            margin-right: 15px;
            padding: 10px 20px;
            background: #103932;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .nav-links a:hover {
            background: #CBA135;
        }
        .error-box {
            color: #d32f2f;
            border: 2px solid #d32f2f;
            background: #ffebee;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .info-section {
            margin: 30px 0;
        }
        .profile-picture-container {
            text-align: center;
            margin: 20px 0;
        }
        .profile-picture {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #4dec7dff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
    <h1>Employee Details</h1>

    <!-- Navigation -->
    <div class="nav-links">
        <a href="showRecord.php">← Back to Employee Records</a>
        <a href="edit_employee.php?id=<?php echo htmlspecialchars($employee_id); ?>">Edit Employee</a>
        <a href="index.php">Add New Employee</a>
    </div>

    <!-- Error Display -->
    <?php if ($viewer->hasErrors()): ?>
        <div class="error-box">
            <strong>Errors:</strong>
            <ul>
                <?php foreach ($viewer->getErrors() as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($loadSuccess && $employee): ?>
        
        <!-- Profile Picture -->
        <div class="profile-picture-container">
            <img src="<?php echo htmlspecialchars($employee['profile_photo'] ?? 'assets/profile_pic/user.png') . '?v=' . time(); ?>" 
                 alt="Profile Picture" 
                 class="profile-picture"
                 onerror="this.src='assets/profile_pic/user.png'">
        </div>
        
        <!-- Employee Information -->
        <div class="info-section">
        <h2>Personal Information</h2>
        <table>
            <tr>
                <th>Field</th>
                <th>Value</th>
            </tr>
            <tr>
                <td><strong>Employee ID</strong></td>
                <td><?php echo $employee['employee_id']; ?></td>
            </tr>
            <tr>
                <td><strong>Full Name</strong></td>
                <td><?php echo $viewer->getFullName(); ?></td>
            </tr>
            <tr>
                <td><strong>Email</strong></td>
                <td><?php echo $employee['email']; ?></td>
            </tr>
            <tr>
                <td><strong>Phone</strong></td>
                <td><?php echo $employee['phone']; ?></td>
            </tr>
            <tr>
                <td><strong>Role</strong></td>
                <td><?php echo $employee['roles']; ?></td>
            </tr>
            <tr>
                <td><strong>Department</strong></td>
                <td><?php echo $employee['department']; ?></td>
            </tr>
            <tr>
                <td><strong>Position</strong></td>
                <td><?php echo $employee['position']; ?></td>
            </tr>
            <tr>
                <td><strong>Hire Date</strong></td>
                <td><?php echo $employee['hire_date']; ?></td>
            </tr>
            <tr>
                <td><strong>Status</strong></td>
                <td><?php echo $employee['status']; ?></td>
            </tr>
            <tr>
                <td><strong>Created At</strong></td>
                <td><?php echo $viewer->formatDateTime($employee['created_at']); ?></td>
            </tr>
            <tr>
                <td><strong>Updated At</strong></td>
                <td><?php echo $viewer->formatDateTime($employee['updated_at']); ?></td>
            </tr>
        </table>
        </div>

        <!-- Visual Schedule Calendar -->
        <div class="info-section">
        <h2>Weekly Schedule</h2>
        <?php if (!empty($schedules)): ?>
            <div class="schedule-calendar-section">
                <div class="calendar-wrapper">
                    <div class="schedule-calendar" id="employee-schedule-calendar">
                        <!-- Time slots header -->
                        <div class="time-header"></div>
                        
                        <!-- Day headers -->
                        <div class="day-header" data-day="Monday">Mon</div>
                        <div class="day-header" data-day="Tuesday">Tue</div>
                        <div class="day-header" data-day="Wednesday">Wed</div>
                        <div class="day-header" data-day="Thursday">Thu</div>
                        <div class="day-header" data-day="Friday">Fri</div>
                        <div class="day-header" data-day="Saturday">Sat</div>
                        <div class="day-header" data-day="Sunday">Sun</div>
                        
                        <!-- Calendar grid will be populated by JavaScript -->
                    </div>
                </div>
            </div>
            
            <script>
                // Schedule data from PHP - convert to format matching index.php
                const employeeSchedulesRaw = <?php echo json_encode($schedules); ?>;
                
                console.log('Raw employee schedules from database:', employeeSchedulesRaw);
                
                // Predefined color palette matching index.php
                const scheduleColors = [
                    '#4a7c59', '#8b4a6b', '#b85450', '#5b9bd5', '#ffc000',
                    '#c55a11', '#7030a0', '#0070c0', '#00b050', '#ff6b6b'
                ];
                
                // Convert database schedules to the format used in index.php
                function convertSchedulesToDisplayFormat() {
                    // IMPORTANT: day_of_week is 0-6 (Monday=0, Sunday=6) to match Python's datetime.weekday()
                    const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    const scheduleGroups = {};
                    
                    employeeSchedulesRaw.forEach(schedule => {
                        // Create a unique key for grouping schedules with same time/subject/class
                        const key = `${schedule.start_time}-${schedule.end_time}-${schedule.subject_code}-${schedule.designate_class}`;
                        
                        if (!scheduleGroups[key]) {
                            scheduleGroups[key] = {
                                startTime: schedule.start_time.substring(0, 5), // Convert HH:MM:SS to HH:MM
                                endTime: schedule.end_time.substring(0, 5),
                                subject: schedule.subject_code,
                                class: schedule.designate_class,
                                room_num: schedule.room_num,
                                days: [],
                                color: scheduleColors[Object.keys(scheduleGroups).length % scheduleColors.length]
                            };
                        }
                        
                        // day_of_week is already 0-6, so use directly as array index
                        const dayName = dayNames[parseInt(schedule.day_of_week)];
                        if (!scheduleGroups[key].days.includes(dayName)) {
                            scheduleGroups[key].days.push(dayName);
                        }
                    });
                    
                    return Object.values(scheduleGroups);
                }
                
                const addedSchedules = convertSchedulesToDisplayFormat();
                console.log('Converted schedules for display:', addedSchedules);
                
                // Copy exact functions from add_employee.js
                function parseTime(timeString) {
                    const [hours, minutes] = timeString.split(':').map(Number);
                    return hours * 60 + minutes;
                }
                
                function formatTimeSlot(minutes) {
                    const hours = Math.floor(minutes / 60);
                    const mins = minutes % 60;
                    return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
                }
                
                function formatTime(timeSlot) {
                    const [hours, minutes] = timeSlot.split(':').map(Number);
                    const period = hours >= 12 ? 'PM' : 'AM';
                    const displayHours = hours > 12 ? hours - 12 : (hours === 0 ? 12 : hours);
                    return `${displayHours}:${minutes.toString().padStart(2, '0')}${period}`;
                }
                
                function generateTimeSlots(startTime, endTime, intervalMinutes) {
                    const slots = [];
                    const start = parseTime(startTime);
                    const end = parseTime(endTime);
                    
                    let current = start;
                    while (current < end) {
                        slots.push(formatTimeSlot(current));
                        current += intervalMinutes;
                    }
                    
                    return slots;
                }
                
                function getRandomScheduleColor() {
                    return scheduleColors[Math.floor(Math.random() * scheduleColors.length)];
                }
                
                function initializeCalendar() {
                    const calendar = document.getElementById('employee-schedule-calendar');
                    if (!calendar) {
                        console.error('Calendar element not found!');
                        return;
                    }
                    
                    const timeSlots = generateTimeSlots('07:00', '24:00', 30);
                    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    
                    // Clear existing grid content (keep headers)
                    const existingCells = calendar.querySelectorAll('.time-slot, .calendar-cell');
                    existingCells.forEach(cell => cell.remove());
                    
                    // Set up grid rows (header + time slots)
                    calendar.style.gridTemplateRows = `40px repeat(${timeSlots.length}, 40px)`;
                    
                    // Create time slots and calendar cells
                    timeSlots.forEach((timeSlot, timeIndex) => {
                        // Time slot label
                        const timeLabel = document.createElement('div');
                        timeLabel.className = 'time-slot';
                        timeLabel.textContent = formatTime(timeSlot);
                        timeLabel.style.gridColumn = '1';
                        timeLabel.style.gridRow = `${timeIndex + 2}`;
                        calendar.appendChild(timeLabel);
                        
                        // Calendar cells for each day
                        days.forEach((day, dayIndex) => {
                            const cell = document.createElement('div');
                            cell.className = 'calendar-cell';
                            cell.dataset.day = day;
                            cell.dataset.timeSlot = timeSlot;
                            cell.dataset.timeIndex = timeIndex;
                            cell.style.gridColumn = `${dayIndex + 2}`;
                            cell.style.gridRow = `${timeIndex + 2}`;
                            calendar.appendChild(cell);
                        });
                    });
                    
                    console.log('Calendar grid created with', timeSlots.length, 'time slots');
                    
                    // Render schedules
                    renderSchedules();
                }
                
                function renderSchedules() {
                    // Clear existing schedule blocks
                    document.querySelectorAll('.schedule-block').forEach(block => block.remove());
                    
                    console.log('Rendering', addedSchedules.length, 'schedule(s)');
                    
                    // Re-render all schedules
                    addedSchedules.forEach((schedule, index) => {
                        renderScheduleBlock(schedule, index);
                    });
                }
                
                function renderScheduleBlock(schedule, scheduleIndex) {
                    const startTimeMinutes = parseTime(schedule.startTime);
                    const endTimeMinutes = parseTime(schedule.endTime);
                    const baseTimeMinutes = 420; // 7:00 AM in minutes
                    const slotDuration = 30; // 30-minute slots
                    const slotHeight = 40; // 40px per slot
                    
                    // Calculate slot positions
                    const startSlotIndex = Math.floor((startTimeMinutes - baseTimeMinutes) / slotDuration);
                    const endSlotIndex = Math.ceil((endTimeMinutes - baseTimeMinutes) / slotDuration);
                    const slotsSpanned = endSlotIndex - startSlotIndex;
                    
                    console.log(`Schedule ${scheduleIndex}: ${schedule.startTime}-${schedule.endTime}, slots ${startSlotIndex}-${endSlotIndex}, span ${slotsSpanned}`);
                    
                    schedule.days.forEach(day => {
                        const dayIndex = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].indexOf(day);
                        
                        if (startSlotIndex >= 0 && endSlotIndex <= 34) { // Within 7AM-12AM range
                            // Find the target cell
                            const targetCell = document.querySelector(`[data-day="${day}"][data-time-index="${startSlotIndex}"]`);
                            
                            if (targetCell) {
                                const scheduleBlock = document.createElement('div');
                                const isFacultySchedule = schedule.class !== 'N/A' && schedule.subject !== 'GENERAL' && schedule.room_num !== 'TBD';
                                scheduleBlock.className = isFacultySchedule ? 'schedule-block faculty-schedule' : 'schedule-block non-faculty-schedule';
                                
                                // Add unique identifier
                                scheduleBlock.dataset.scheduleId = scheduleIndex;
                                scheduleBlock.dataset.day = day;
                                scheduleBlock.dataset.startTime = schedule.startTime;
                                scheduleBlock.dataset.endTime = schedule.endTime;
                                
                                // Apply the schedule's assigned color
                                scheduleBlock.style.background = schedule.color || getRandomScheduleColor();
                                
                                // Calculate exact height
                                const exactHeight = slotsSpanned * slotHeight;
                                scheduleBlock.style.height = `${exactHeight}px`;
                                
                                // Generate content based on available information
                                let scheduleContent = '';
                                
                                if (isFacultySchedule) {
                                    // Faculty schedule with full information
                                    scheduleContent = `
                                        <div class="class-subject">${schedule.class}<br>${schedule.subject}</div>
                                        <div class="room-info">Room: ${schedule.room_num}</div>
                                        <div class="time-range">${formatTime(schedule.startTime)} - ${formatTime(schedule.endTime)}</div>
                                    `;
                                } else {
                                    // Non-faculty or minimal schedule - only show time
                                    scheduleContent = `
                                        <div class="time-range-only">${formatTime(schedule.startTime)} - ${formatTime(schedule.endTime)}</div>
                                        <div class="schedule-type">Work Schedule</div>
                                    `;
                                }
                                
                                scheduleBlock.innerHTML = `
                                    <div class="schedule-info">
                                        ${scheduleContent}
                                    </div>
                                `;
                                
                                targetCell.appendChild(scheduleBlock);
                                console.log(`Added schedule block to ${day} at slot ${startSlotIndex}`);
                            } else {
                                console.warn(`Could not find target cell for ${day} at slot ${startSlotIndex}`);
                            }
                        } else {
                            console.warn(`Schedule time out of range: ${schedule.startTime}-${schedule.endTime}`);
                        }
                    });
                }
                
                // Initialize calendar when page loads
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('DOM loaded, initializing calendar...');
                    initializeCalendar();
                });
            </script>
        <?php else: ?>
            <p>No schedule assigned to this employee.</p>
        <?php endif; ?>
        </div>

    <?php else: ?>
        <p>Employee not found or failed to load employee details.</p>
        <div class="nav-links">
            <a href="showRecord.php">← Back to Employee Records</a>
        </div>
    <?php endif; ?>

    </div>
</body>
</html>