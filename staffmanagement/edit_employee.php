<?php
require '../db_connection.php';
class EmployeeEditor {
    private $db;
    private $employee = null;
    private $errors = [];

    public function __construct($database) {
        $this->db = $database;
    }

    public function loadEmployee($employee_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM employees WHERE employee_id = ?");
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $this->db->error);
            }
            
            $stmt->bind_param('s', $employee_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
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

    public function getEmployee() {
        return $this->employee;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }
}

// Get employee ID from URL parameter
$employee_id = $_GET['id'] ?? '';

if (empty($employee_id)) {
    header('Location: showRecord.php?error=no_id_to_edit');
    exit;
}

$editor = new EmployeeEditor($conn);
$loadSuccess = $editor->loadEmployee($employee_id);
$employee = $editor->getEmployee();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .form-actions {
            margin-top: 30px;
            text-align: right;
        }
        .form-actions button {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-save {
            background-color: #2196F3;
            color: white;
            transition: background 0.3s;
        }
        .btn-save:hover {
            background-color: #1976D2;
        }
        .btn-cancel {
            background-color: #f44336;
            color: white;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
            padding: 12px 25px;
            border-radius: 5px;
        }
        .error-box {
            color: #d32f2f;
            border: 2px solid #d32f2f;
            background: #ffebee;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }

    .edit-schedule-btn {
        background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(255, 152, 0, 0.3);
        width: 100%;
    }

    .edit-schedule-btn:hover:not(:disabled) {
        background: linear-gradient(135deg, #F57C00 0%, #E65100 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(255, 152, 0, 0.4);
    }

    .add-schedule-btn:hover {
        background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
        transform: translateY(-2px);

    }       
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Employee Details</h1>

        <?php if ($editor->hasErrors()): ?>
            <div class="error-box">
                <strong>Errors:</strong>
                <ul>
                    <?php foreach ($editor->getErrors() as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($loadSuccess && $employee): ?>
            <form action="processes/update_employee.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                
                <div class="form-group">
                    <label>Profile Picture</label>
                    <img id="profile-preview" 
                         src="<?php echo htmlspecialchars($employee['profile_photo'] ?? 'assets/profile_pic/user.png'); ?>" 
                         alt="Profile Preview" 
                         style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; display: block; margin-bottom: 10px;"
                         onerror="this.src='assets/profile_pic/user.png'">
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
                    <small>Select a new image to update the profile picture. Leave blank to keep the current one.</small>
                </div>

                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($employee['middle_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($employee['phone']); ?>">
                </div>

                <div class="form-group">
                    <label for="roles">Role</label>
                    <input type="text" id="roles" name="roles" value="<?php echo htmlspecialchars($employee['roles']); ?>">
                </div>

                <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>">
                </div>

                <div class="form-group">
                    <label for="position">Position</label>
                    <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($employee['position']); ?>">
                </div>

                <div class="form-group">
                    <label for="hire_date">Hire Date</label>
                    <input type="date" id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($employee['hire_date']); ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Active" <?php echo ($employee['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($employee['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

            <h2>Work Schedule</h2>
            <div class="schedule-section">
                <div class="form-group">
                    <label>Select Working Days:</label>
                    <p class="helper-text">Selected days appear dimmed</p>
                    <div class="day-buttons">
                        <button type="button" class="day-btn" data-day="Monday" onclick="toggleDay(this)">Mon</button>
                        <button type="button" class="day-btn" data-day="Tuesday" onclick="toggleDay(this)">Tue</button>
                        <button type="button" class="day-btn" data-day="Wednesday" onclick="toggleDay(this)">Wed</button>
                        <button type="button" class="day-btn" data-day="Thursday" onclick="toggleDay(this)">Thu</button>
                        <button type="button" class="day-btn" data-day="Friday" onclick="toggleDay(this)">Fri</button>
                        <button type="button" class="day-btn" data-day="Saturday" onclick="toggleDay(this)">Sat</button>
                        <button type="button" class="day-btn" data-day="Sunday" onclick="toggleDay(this)">Sun</button>
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
                <div class="form-row" id="faculty-fields">
                    <div class="form-group">
                        <label for="designate_class">Designate Class <span style="color: #999;">(Faculty Only)</span></label>
                        <input type="text" id="designate_class" name="designate_class" 
                               placeholder="Available for Faculty Members only" 
                               autocomplete="off" style="text-transform: uppercase;" disabled>
                        <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing to see existing classes</small>
                    </div>
                    <div class="form-group">
                        <label for="designate_subject">Subject <span style="color: #999;">(Faculty Only)</span></label>
                        <input type="text" id="designate_subject" name="designate_subject" 
                               placeholder="Available for Faculty Members only" 
                               autocomplete="off" style="text-transform: uppercase;" disabled>
                        <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing to see existing subjects</small>
                    </div>
                    <div class="form-group">
                        <label for="room-number">Room Number <span style="color: #999;">(Faculty Only)</span></label>
                        <input type="text" id="room-number" name="room-number" 
                               placeholder="Available for Faculty Members only" 
                               autocomplete="off" style="text-transform: uppercase;" disabled>
                        <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing to see existing rooms</small>
                     </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="display: flex; gap: 10px;">
                        <button type="button" class="add-schedule-btn" onclick="addSchedule()">Add Schedule</button>
                        <button type="button" id="edit-schedule-btn" class="edit-schedule-btn" onclick="editSchedule()" disabled>Update Selected Schedule</button>
                        <button type="button" class="btn-cancel" onclick="clearScheduleForm()">Cancel</button>
                    </div>
                </div>

                <!-- Weekly Schedule Calendar -->
                <div class="schedule-calendar-section">
                    <div class="schedule-header">
                        <h3>Schedule</h3>
                        <button type="button" class="clear-schedules-btn" onclick="clearAllSchedules()">
                            Clear All Schedules
                        </button>
                    </div>
                    <div class="calendar-wrapper">
                        <div class="schedule-calendar">
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
                            
                            <!-- Time slots and schedule cells -->
                            <div id="calendar-grid"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden input for schedule data -->
                <input type="hidden" name="schedule_data" id="schedule_data">
                
            </div>

             <div class="form-actions">
                    <a href="employee_detail.php?id=<?php echo htmlspecialchars($employee['employee_id']); ?>" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-save">Save Changes</button>
            </div>
            </form>
        <?php else: ?>
            <p>Could not load employee data. <a href="showRecord.php">Back to records</a>.</p>
        <?php endif; ?>
    </div>

    <?php
    // --- PHP: Query employee schedules and output as JSON (with assignments) ---
    $emp_id = $employee['id'] ?? null;
    $existingSchedules = [];
    if ($emp_id) {
        $sql = "SELECT es.*, sp.day_of_week, sp.start_time, sp.end_time, sp.period_name,
                       ea.designate_class, ea.subject_code, ea.room_num
                FROM employee_schedules es
                JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                LEFT JOIN employee_assignments ea ON es.employee_id = ea.employee_id AND sp.id = ea.schedule_period_id
                WHERE es.employee_id = ? AND es.is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $employee['id']);
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
            // IMPORTANT: Use 0-6 format (Monday=0, Sunday=6) to match Python's datetime.weekday()
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
    <script>
    // --- JS: Populate calendar with existing schedules ---
    window.existingSchedules = <?php echo json_encode($existingSchedules); ?>;
    </script>
    <script src="assets/js/edit_employee.js"></script>
    <script>
        // --- JS: Live preview for profile picture ---
        document.addEventListener('DOMContentLoaded', function() {
            const photoInput = document.getElementById('profile_photo');
            const previewImg = document.getElementById('profile-preview');
            if (photoInput && previewImg) {
                photoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        previewImg.src = URL.createObjectURL(file);
                    }
                });
            }
        });
    </script>
</body>
</html>
