<?php
require 'db_connection.php';

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
    // If there's an error, continue with empty array
    error_log("Error fetching roles: " . $e->getMessage());
}

// Get existing departments from database
$existing_departments = [];
try {
    $result = $conn->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_departments[] = $row['department'];
        }
    }
} catch (Exception $e) {
    // If there's an error, continue with empty array
    error_log("Error fetching departments: " . $e->getMessage());
}

// Get existing classes from database
$existing_classes = [];
try {
    $result = $conn->query("SELECT DISTINCT designate_class FROM employee_assignments WHERE designate_class IS NOT NULL AND designate_class != '' ORDER BY designate_class");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_classes[] = $row['designate_class'];
        }
    }
} catch (Exception $e) {
    // If there's an error, continue with empty array
    error_log("Error fetching classes: " . $e->getMessage());
}

// Get existing subjects from database
$existing_subjects = [];
try {
    $result = $conn->query("SELECT DISTINCT subject_code FROM employee_assignments WHERE subject_code IS NOT NULL AND subject_code != '' ORDER BY subject_code");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_subjects[] = $row['subject_code'];
        }
    }
} catch (Exception $e) {
    // If there's an error, continue with empty array
    error_log("Error fetching subjects: " . $e->getMessage());
}

// Get existing room numbers from database
$existing_rooms = [];
try {
    $result = $conn->query("SELECT DISTINCT room_num FROM employee_assignments WHERE room_num IS NOT NULL AND room_num != '' ORDER BY room_num");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_rooms[] = $row['room_num'];
        }
    }
} catch (Exception $e) {
    // If there's an error, continue with empty array
    error_log("Error fetching room numbers: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Recognition Attendance System</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- FaceAPI.js - Try local first, fallback to CDN -->
    <script src="assets/js/tf.min.js" 
            onerror="this.onerror=null; this.src='https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@1.7.4/dist/tf.min.js'"></script>
    <script src="assets/js/face-api.min.js" 
            onerror="this.onerror=null; this.src='https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js'"></script>
</head>
<body>
    <div class="container">
        <h1>Employee Registration</h1>
        
        <form action="processes/add_employee.php" method="POST">
            <!-- Employee Information -->
            <div class="form-row">
                <div class="form-group">
                    <label for="employee_id">Employee ID:</label>
                    <input type="text" id="employee_id" name="employee_id" required>
                </div>
                <div class="form-group">
                    <label for="add_password">Add Password:</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="add_password" name="add_password" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="roles">Role:</label>
                    <input type="text" id="roles" name="roles" 
                           placeholder="Select from dropdown or type new role" required 
                           autocomplete="off">
                    <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing to see existing roles</small>
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
                    <input type="tel" id="phone" name="phone" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="department">Department:</label>
                    <input type="text" id="department" name="department" 
                           placeholder="Select from dropdown or type new department" required 
                           autocomplete="off">
                    <small style="color: #666; font-size: 0.8em;">Click dropdown arrow or start typing to see existing departments</small>
                </div>
                <div class="form-group">
                    <label for="position">Position:</label>
                    <input type="text" id="position" name="position" required>
                </div>
                <div class="form-group">
                    <label for="hire_date">Hire Date:</label>
                    <input type="date" id="hire_date" name="hire_date" required>
                </div>
            </div>

            <!-- Add Schedule Section -->
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
                    <div class="form-group">
                        <button type="button" class="add-schedule-btn" onclick="addSchedule()">Add Schedule</button>
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
                
            </div>

            <!-- Face Capture Section -->
            <h2>Face Registration</h2>
            <div class="camera-section">
                <div class="camera-container">
                    <video id="video" autoplay></video>
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
                <h4 id="current-angle">Step 1 of 5: Face Forward (Looking straight at camera)</h4>
                <p id="angle-instruction">Look directly at the camera with a neutral expression</p>
                <button type="button" id="capture-btn">Capture Photo</button>
                <button type="button" id="skip-btn">Skip This Angle</button>
            </div>

            <div id="captured-photos">
                <h4>Captured Photos:</h4>
                <div id="photo-thumbnails"></div>
            </div>

            <!-- Hidden Fields -->
            <input type="hidden" name="face_photos" id="face_photos" value="">
            <input type="hidden" name="schedule_data" id="schedule_data" value="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <!-- Submit -->
            <input type="submit" value="Add Employee" id="submit-btn" disabled>
        </form>

        <a href="showRecord.php">View Employee Records</a>
    </div>
    <!-- JavaScript Modules with cache busting -->
    <script src="assets/js/face-detection.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/camera-controller.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/face-registration-app.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/add_employee.js?v=<?php echo time(); ?>"></script>
</body>
</html>