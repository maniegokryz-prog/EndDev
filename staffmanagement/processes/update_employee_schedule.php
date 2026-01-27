<?php
require '../../db_connection.php';
require_once __DIR__ . '/../../db_cloud_sync.php';

class EmployeeScheduleUpdater {
    private $db;
    private $errors = [];
    private $validatedData = [];

    public function __construct($database) {
        $this->db = $database;
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendErrorResponse('Method not allowed.', 405);
            return;
        }

        try {
            $this->logActivity('Employee schedule update request started');

            // Collect schedule-related data
            $this->validatedData['employee_id_string'] = $_POST['employee_id'] ?? '';
            $this->validatedData['first_name'] = $_POST['first_name'] ?? '';
            $this->validatedData['last_name'] = $_POST['last_name'] ?? '';
            $this->validatedData['schedule_data'] = $_POST['schedule_data'] ?? '[]';

            if (empty($this->validatedData['employee_id_string'])) {
                throw new Exception("Employee ID is missing.");
            }

            $this->db->begin_transaction();

            // Get internal employee ID
            $employee = $this->getEmployeeByStringId($this->validatedData['employee_id_string']);
            if (!$employee) {
                throw new Exception("Employee with ID '{$this->validatedData['employee_id_string']}' not found.");
            }
            $employeeId = $employee['id'];

            // Update schedule
            $this->updateEmployeeSchedule($employeeId);

            $this->db->commit();

            $this->logActivity('Employee schedule updated successfully', 'Employee ID: ' . $this->validatedData['employee_id_string']);
            
            // Return success response
            echo json_encode([
                'success' => true, 
                'message' => 'Schedule updated successfully',
                'employee_id' => $this->validatedData['employee_id_string']
            ]);
            exit;

        } catch (Exception $e) {
            $this->db->rollback();
            $this->logError('Schedule Update Failed', $e->getMessage());
            
            // Return error response
            echo json_encode([
                'success' => false,
                'message' => 'Schedule update failed: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    private function getEmployeeByStringId($employeeIdString) {
        $stmt = $this->db->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $stmt->bind_param('s', $employeeIdString);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function updateEmployeeSchedule($employeeId) {
        // Find the current active schedule_id for the employee
        $stmt = $this->db->prepare("SELECT schedule_id FROM employee_schedules WHERE employee_id = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentSchedule = $result->fetch_assoc();

        // If an old schedule exists, remove only its periods and assignments, not the schedule itself
        if ($currentSchedule && $currentSchedule['schedule_id']) {
            $oldScheduleId = $currentSchedule['schedule_id'];
            
            // Get schedule name for cloud sync
            $stmt = $this->db->prepare("SELECT schedule_name FROM schedules WHERE id = ?");
            $stmt->bind_param('i', $oldScheduleId);
            $stmt->execute();
            $result = $stmt->get_result();
            $scheduleNameForDelete = $result->fetch_assoc()['schedule_name'] ?? null;
            
            // Delete old periods and assignments for this schedule
            $stmt = $this->db->prepare("SELECT id, day_of_week, start_time, end_time FROM schedule_periods WHERE schedule_id = ?");
            $stmt->bind_param('i', $oldScheduleId);
            $stmt->execute();
            $periodsResult = $stmt->get_result();
            $periodIds = [];
            $periodDetails = [];
            while ($row = $periodsResult->fetch_assoc()) {
                $periodIds[] = $row['id'];
                $periodDetails[] = [
                    'day_of_week' => $row['day_of_week'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time']
                ];
            }
            
            // Delete assignments for these periods and sync to cloud
            foreach ($periodIds as $index => $pid) {
                $stmt = $this->db->prepare("DELETE FROM employee_assignments WHERE schedule_period_id = ?");
                $stmt->bind_param('i', $pid);
                $stmt->execute();
                
                // Sync deletion to cloud - set is_active = 0 (soft delete)
                if ($scheduleNameForDelete) {
                    syncToCloudWithLookup('employee_assignments', [
                        'employee_id_string' => $this->validatedData['employee_id_string'],
                        'schedule_name' => $scheduleNameForDelete,
                        'day_of_week' => $periodDetails[$index]['day_of_week'],
                        'start_time' => $periodDetails[$index]['start_time'],
                        'end_time' => $periodDetails[$index]['end_time'],
                        'is_active' => 0,
                        '_delete_mode' => true  // Signal to update instead of insert
                    ]);
                }
            }
            
            // Delete periods and sync to cloud
            $stmt = $this->db->prepare("DELETE FROM schedule_periods WHERE schedule_id = ?");
            $stmt->bind_param('i', $oldScheduleId);
            $stmt->execute();
            
            // Sync period deletions to cloud - delete each period individually
            foreach ($periodDetails as $period) {
                if ($scheduleNameForDelete) {
                    // Build WHERE clause using schedule_id lookup
                    $scheduleIdQuery = "(SELECT id FROM schedules WHERE schedule_name = '{$scheduleNameForDelete}')";
                    syncToCloud('schedule_periods', [], 'delete', 
                        "schedule_id = {$scheduleIdQuery} " .
                        "AND day_of_week = {$period['day_of_week']} " .
                        "AND start_time = '{$period['start_time']}' " .
                        "AND end_time = '{$period['end_time']}'"
                    );
                }
            }
            
            $this->logActivity('Old schedule periods and assignments deleted', "Schedule ID: {$oldScheduleId}");
        }

        // Decode the new schedule data
        $scheduleData = json_decode($this->validatedData['schedule_data'], true);
        if (empty($scheduleData)) {
            $this->logActivity('No schedule data provided for update', "Employee ID: {$employeeId}");
            return; // No new schedule to create
        }

        // Get or create schedule name
        $scheduleName = null;
        
        // If no schedule exists, create a new schedule and link it
        if (!$currentSchedule || !$currentSchedule['schedule_id']) {
            $scheduleName = "Schedule_" . $this->validatedData['employee_id_string'] . "_" . date('Ymd_His');
            $description = "Schedule for " . $this->validatedData['first_name'] . " " . $this->validatedData['last_name'];
            
            $stmt = $this->db->prepare("INSERT INTO schedules (schedule_name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $scheduleName, $description);
            $stmt->execute();
            $oldScheduleId = $this->db->insert_id;
            
            // Sync schedule to cloud
            syncToCloud('schedules', [
                'id' => $oldScheduleId,
                'schedule_name' => $scheduleName,
                'description' => $description
            ], 'insert');
            
            $stmt = $this->db->prepare("INSERT INTO employee_schedules (employee_id, schedule_id, effective_date, is_active) VALUES (?, ?, ?, 1)");
            $effectiveDate = date('Y-m-d');
            $stmt->bind_param('iis', $employeeId, $oldScheduleId, $effectiveDate);
            $stmt->execute();
            $empScheduleId = $this->db->insert_id;
            
            // Sync employee schedule to cloud with ID lookup
            syncToCloudWithLookup('employee_schedules', [
                'employee_id_string' => $this->validatedData['employee_id_string'],
                'schedule_name' => $scheduleName,
                'effective_date' => $effectiveDate,
                'is_active' => 1
            ]);
            
            $this->logActivity('New schedule created and linked', "Schedule ID: {$oldScheduleId}");
        } else {
            // Get existing schedule name
            $stmt = $this->db->prepare("SELECT schedule_name FROM schedules WHERE id = ?");
            $stmt->bind_param('i', $oldScheduleId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $scheduleName = $row['schedule_name'];
            }
        }

        // Insert new periods and assignments
        // IMPORTANT: Use 0-6 format (Monday=0, Sunday=6) to match Python's datetime.weekday()
        $dayMappings = [
            'Monday' => 0, 
            'Tuesday' => 1, 
            'Wednesday' => 2, 
            'Thursday' => 3, 
            'Friday' => 4, 
            'Saturday' => 5, 
            'Sunday' => 6
        ];

        foreach ($scheduleData as $scheduleBlock) {
            // Determine if this is a faculty schedule (has class and subject info)
            $isFacultySchedule = (
                isset($scheduleBlock['class']) && $scheduleBlock['class'] !== 'N/A' && 
                isset($scheduleBlock['subject']) && $scheduleBlock['subject'] !== 'General' &&
                $scheduleBlock['subject'] !== 'N/A'
            );
            
            foreach ($scheduleBlock['days'] as $dayName) {
                $dayOfWeek = $dayMappings[$dayName] ?? null;
                if ($dayOfWeek === null) {
                    $this->logError('Schedule Update', "Invalid day name: {$dayName}");
                    continue;
                }

                // Set period name based on schedule type
                $periodName = $isFacultySchedule 
                    ? ($scheduleBlock['subject'] . " - " . $scheduleBlock['class']) 
                    : 'Work Shift';
                
                // Insert schedule period
                $stmt = $this->db->prepare("
                    INSERT INTO schedule_periods 
                    (schedule_id, day_of_week, period_name, start_time, end_time) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('iisss', 
                    $oldScheduleId, 
                    $dayOfWeek, 
                    $periodName, 
                    $scheduleBlock['startTime'], 
                    $scheduleBlock['endTime']
                );
                $stmt->execute();
                $periodId = $this->db->insert_id;

                if (!$periodId) {
                    $this->logError('Schedule Update', "Failed to create schedule_period for day {$dayName}");
                    continue;
                }

                // Sync schedule period to cloud
                syncToCloud('schedule_periods', [
                    'id' => $periodId,
                    'schedule_id' => $oldScheduleId,
                    'day_of_week' => $dayOfWeek,
                    'period_name' => $periodName,
                    'start_time' => $scheduleBlock['startTime'],
                    'end_time' => $scheduleBlock['endTime'],
                    'is_active' => 1
                ], 'insert');

                // If faculty schedule, insert assignment details
                if ($isFacultySchedule) {
                    $stmt = $this->db->prepare("
                        INSERT INTO employee_assignments 
                        (employee_id, schedule_period_id, subject_code, designate_class, room_num) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $roomNum = $scheduleBlock['room_num'] ?? '';
                    
                    $stmt->bind_param('iisss', 
                        $employeeId, 
                        $periodId, 
                        $scheduleBlock['subject'], 
                        $scheduleBlock['class'], 
                        $roomNum
                    );
                    $stmt->execute();
                    $assignmentId = $this->db->insert_id;
                    
                    if (!$stmt->affected_rows) {
                        $this->logError('Schedule Update', "Failed to create assignment for period {$periodId}");
                    } else {
                        // Sync employee assignment to cloud with ID lookup
                        syncToCloudWithLookup('employee_assignments', [
                            'employee_id_string' => $this->validatedData['employee_id_string'],
                            'schedule_name' => $scheduleName,
                            'day_of_week' => $dayOfWeek,
                            'start_time' => $scheduleBlock['startTime'],
                            'end_time' => $scheduleBlock['endTime'],
                            'subject_code' => $scheduleBlock['subject'],
                            'designate_class' => $scheduleBlock['class'],
                            'room_num' => $roomNum,
                            'is_active' => 1
                        ]);
                    }
                }
            }
        }
        
        $this->logActivity('New schedule periods and assignments created', "Schedule ID: {$oldScheduleId}, Blocks: " . count($scheduleData));
        
        // Create notification for employee about schedule change
        $this->createScheduleChangeNotification($employeeId);
    }
    
    private function createScheduleChangeNotification($employeeId) {
        try {
            // Check if notifications table exists
            $check_table = $this->db->query("SHOW TABLES LIKE 'notifications'");
            if ($check_table->num_rows == 0) {
                $this->logActivity('Notification skipped', 'notifications table does not exist');
                return;
            }
            
            // Get employee details
            $stmt = $this->db->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
            $stmt->bind_param("i", $employeeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $employee = $result->fetch_assoc();
            
            if ($employee) {
                $emp_name = $employee['first_name'] . ' ' . $employee['last_name'];
                $message = $emp_name . ", There are some changes to your schedule";
                $link = "/EndDev/staffmanagement/staff_profile.php";
                
                // Check if link column exists
                $check_column = $this->db->query("SHOW COLUMNS FROM notifications LIKE 'link'");
                if ($check_column->num_rows > 0) {
                    $stmt = $this->db->prepare("INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'schedule_change', ?, ?, 'employee', 0)");
                    $stmt->bind_param("iss", $employeeId, $message, $link);
                } else {
                    $stmt = $this->db->prepare("INSERT INTO notifications (employee_id, type, message, target, is_read) VALUES (?, 'schedule_change', ?, 'employee', 0)");
                    $stmt->bind_param("is", $employeeId, $message);
                }
                $stmt->execute();
                
                $this->logActivity('Schedule change notification created', "Employee ID: {$employeeId}");
            }
        } catch (Exception $e) {
            $this->logError('Notification Creation Failed', $e->getMessage());
        }
    }

    private function logActivity($activity, $reference = '') {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [ACTIVITY] " . $activity;
        if ($reference) $log_entry .= " - " . $reference;
        $log_entry .= PHP_EOL;
        
        $log_dir = __DIR__ . '/../logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'schedule_updates.log', $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function logError($context, $message) {
        $log_entry = "[" . date('Y-m-d H:i:s') . "] [ERROR] Context: " . $context . " - Message: " . $message . PHP_EOL;
        
        $log_dir = __DIR__ . '/../logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_dir . 'schedule_updates.log', $log_entry, FILE_APPEND | LOCK_EX);
    }

    private function sendErrorResponse($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message]);
    }
}

// Initialize and handle the request
$updater = new EmployeeScheduleUpdater($conn);
$updater->handleRequest();
?>
