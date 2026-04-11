<?php
/**
 * Manual Attendance Management API
 * Handles adding manual attendance records for employees
 */

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require '../../db_connection.php';
require_once '../../attendancerep/dtr_utils.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Removed custom error log to prevent missing directory warnings
// ini_set('error_log', '../../logs/manual_attendance_errors.log');

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'add_manual':
            addManualAttendance($conn);
            break;

        case 'update_timeout':
            updateTimeOut($conn);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    error_log("Manual Attendance Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

if (isset($conn)) {
    $conn->close();
}

/**
 * Add manual attendance records
 */
/**
 * Add manual attendance records
 */
function addManualAttendance($conn)
{
    // Get JSON data from request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $employee_id = $data['employee_id'] ?? 0;
    $records = $data['records'] ?? [];

    if (!$employee_id) {
        throw new Exception('Employee ID is required');
    }

    if (empty($records)) {
        throw new Exception('No attendance records provided');
    }

    // Validate employee exists
    $sql = "SELECT id, first_name, last_name, roles FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    $employeeRole = $employee['roles'] ?? '';

    if (!$employee) {
        throw new Exception('Employee not found');
    }

    $conn->begin_transaction();

    try {
        $success_count = 0;
        $errors = [];

        foreach ($records as $index => $record) {
            $date = $record['date'] ?? '';
            $time_in = $record['time_in'] ?? '';
            $time_out = $record['time_out'] ?? null; // Allow null/empty
            $break_out = $record['break_out'] ?? null;
            $break_in = $record['break_in'] ?? null;

            // Validate required fields (Time In is required, Time Out is optional)
            if (empty($date) || empty($time_in)) {
                $errors[] = "Record " . ($index + 1) . ": Missing date or time in";
                continue;
            }

            // Treat empty string time_out as null
            if ($time_out !== null && trim($time_out) === '') {
                $time_out = null;
            }
            if ($break_out !== null && trim($break_out) === '') {
                $break_out = null;
            }
            if ($break_in !== null && trim($break_in) === '') {
                $break_in = null;
            }

            // Validate date format
            $dateObj = DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj) {
                $errors[] = "Record " . ($index + 1) . ": Invalid date format";
                continue;
            }

            // Validate time in format (HH:MM or HH:MM:SS)
            if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time_in)) {
                $errors[] = "Record " . ($index + 1) . ": Invalid time in format '$time_in'";
                continue;
            }

            $timeInObj = new DateTime($date . ' ' . $time_in);
            $timeOutObj = null;

            // Validate time out format if provided
            if ($time_out) {
                if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time_out)) {
                    $errors[] = "Record " . ($index + 1) . ": Invalid time out format '$time_out'";
                    continue;
                }

                $timeOutObj = new DateTime($date . ' ' . $time_out);

                // Check if time_out is after time_in
                if ($timeOutObj <= $timeInObj) {
                    $errors[] = "Record " . ($index + 1) . ": Time out must be after time in";
                    continue;
                }
            }

            // Check if employee has a schedule for this date and get all schedule periods
            $dayOfWeek = $dateObj->format('w'); // 0 (Sunday) to 6 (Saturday)
            // Convert PHP's day format (0=Sunday) to database format (0=Monday, 6=Sunday)
            $dayOfWeekDb = ($dayOfWeek == 0) ? 6 : ($dayOfWeek - 1);

            // Check if employee has an approved offset schedule for this date
            $sqlOffset = "SELECT r.id as request_id, r.status as req_status, r.original_schedule_id, r.original_day_of_week 
                          FROM offset_schedule_requests r 
                          WHERE r.employee_id = ? AND r.requested_date = ? AND r.status IN ('approved', 'completed')";
            $stmtOffset = $conn->prepare($sqlOffset);
            if (!$stmtOffset) {
                error_log("Prepare offset failed: " . $conn->error);
            }
            $stmtOffset->bind_param("is", $employee_id, $date);
            $stmtOffset->execute();
            $offset_result = $stmtOffset->get_result();
            $offset_data = $offset_result->fetch_assoc();

            $is_offset_day = false;
            $offset_req_id = null;
            $offset_req_status = null;

            if ($offset_data) {
                // Fetch the detailed periods for the offset schedule for the SPECIFIC day they requested
                $source_day_of_week = $offset_data['original_day_of_week'];
                $sqlOffsetPeriods = "SELECT start_time, end_time FROM schedule_periods WHERE schedule_id = ? AND is_active = 1 AND day_of_week = ? ORDER BY start_time ASC";
                $stmtOffsetPeriods = $conn->prepare($sqlOffsetPeriods);
                $stmtOffsetPeriods->bind_param("ii", $offset_data['original_schedule_id'], $source_day_of_week);
                $stmtOffsetPeriods->execute();
                $res_periods = $stmtOffsetPeriods->get_result()->fetch_all(MYSQLI_ASSOC);

                if (empty($res_periods)) {
                    $errors[] = "Record " . ($index + 1) . ": Associated mirrored schedule has no active time periods to offset.";
                    continue;
                } else {
                    $schedule_periods = $res_periods;
                }

                $is_offset_day = true;
                $offset_req_id = $offset_data['request_id'];
                $offset_req_status = $offset_data['req_status'];
            } else {
                $sql = "SELECT sp.start_time, sp.end_time
                        FROM employee_schedules es
                        JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                        WHERE es.employee_id = ? 
                          AND es.is_active = 1
                          AND sp.day_of_week = ?
                          AND sp.is_active = 1
                          AND (es.end_date IS NULL OR es.end_date >= ?)";
                $schedule_stmt = $conn->prepare($sql);
                $schedule_stmt->bind_param("iis", $employee_id, $dayOfWeekDb, $date);
                $schedule_stmt->execute();
                $schedule_result = $schedule_stmt->get_result();
                $schedule_periods = $schedule_result->fetch_all(MYSQLI_ASSOC);

                // --- INJECT MAKEUP CLASSES ---
                $sqlMakeup = "SELECT start_time, end_time FROM makeup_class_requests WHERE employee_id = ? AND requested_date = ? AND status = 'approved'";
                $stmtMakeup = $conn->prepare($sqlMakeup);
                $stmtMakeup->bind_param("is", $employee_id, $date);
                $stmtMakeup->execute();
                $makeup_res = $stmtMakeup->get_result()->fetch_all(MYSQLI_ASSOC);
                if (!empty($makeup_res)) {
                    $schedule_periods = array_merge($schedule_periods, $makeup_res);
                }
                
                // Sort by start time to make calculations accurate
                usort($schedule_periods, function($a, $b) {
                    return strtotime($a['start_time']) - strtotime($b['start_time']);
                });
            }

            if (empty($schedule_periods)) {
                $errors[] = "Record " . ($index + 1) . " (" . $dateObj->format('M d, Y') . "): No schedule found for this day";
                continue;
            }

            // Calculate scheduled_hours (sum of all periods in minutes, stored as decimal)
            $scheduled_minutes = 0;
            $first_period_start = null;
            $last_period_end = null;

            foreach ($schedule_periods as $period) {
                $start_parts = explode(':', $period['start_time']);
                $end_parts = explode(':', $period['end_time']);

                $start_minutes = ($start_parts[0] * 60) + $start_parts[1];
                $end_minutes = ($end_parts[0] * 60) + $end_parts[1];

                $scheduled_minutes += ($end_minutes - $start_minutes);

                // Track first and last periods for late/overtime calculation
                if ($first_period_start === null) {
                    $first_period_start = $period['start_time'];
                }
                $last_period_end = $period['end_time'];
            }

            // NEW VALIDATION: Ensure manual attendance is applicable to the employee's schedule
            if ($first_period_start && $last_period_end) {
                $schedule_start_dt = new DateTime($date . ' ' . $first_period_start);
                $schedule_end_dt = new DateTime($date . ' ' . $last_period_end);

                // Reject if time_in starts AFTER the schedule ends
                if ($timeInObj > $schedule_end_dt) {
                    $errors[] = "Record " . ($index + 1) . " (" . $dateObj->format('M d, Y') . "): " .
                        "Time in (" . $timeInObj->format('g:i A') . ") starts after the schedule ends. " .
                        "Employee's schedule for this day ends at " . $schedule_end_dt->format('g:i A');
                    continue;
                }

                // Reject if time_out ends BEFORE the schedule starts (Only if time_out is provided)
                if ($timeOutObj && $timeOutObj < $schedule_start_dt) {
                    $errors[] = "Record " . ($index + 1) . " (" . $dateObj->format('M d, Y') . "): " .
                        "Time out (" . $timeOutObj->format('g:i A') . ") ends before the schedule starts. " .
                        "Employee's schedule for this day starts at " . $schedule_start_dt->format('g:i A');
                    continue;
                }
            }

            // Convert scheduled_minutes to decimal (for storage in scheduled_hours field)
            $scheduled_hours = round($scheduled_minutes, 2);

            // Calculate actual hours worked (in minutes, stored as decimal)
            $actual_hours = 0;
            if ($timeOutObj) {
                // Ensure time limits and breaks are respected accurately
                $formatted_schedule = [];
                foreach ($schedule_periods as $p) {
                    $formatted_schedule[] = [
                        'start' => $p['start_time'],
                        'end' => $p['end_time']
                    ];
                }
                $scheduleToPass = [$dayOfWeekDb => $formatted_schedule];
                $actual_hours = calculateActualHoursWithClamping($time_in, $time_out, $scheduleToPass, $date, $employeeRole, $break_out, $break_in, $employee_id);

                // Debug logging to a custom file
                file_put_contents(
                    __DIR__ . '/debug_manual_calc.txt',
                    date('Y-m-d H:i:s') . " - Calc: In=" . $timeInObj->format('Y-m-d H:i:s') .
                    ", Out=" . $timeOutObj->format('Y-m-d H:i:s') .
                    ", Val=$actual_hours\n",
                    FILE_APPEND
                );
            }

            // Calculate late minutes (based on first period start time)
            $late_minutes = 0;
            if ($first_period_start) {
                $start_parts = explode(':', $first_period_start);
                $scheduled_start = new DateTime($date . ' ' . $first_period_start);

                if ($timeInObj > $scheduled_start) {
                    $late_interval = $scheduled_start->diff($timeInObj);
                    $late_minutes = ($late_interval->h * 60) + $late_interval->i;
                }
            }

            // Calculate early departure or overtime (based on last period end time)
            // Only applicable if time_out is provided
            $early_departure_minutes = 0;
            $overtime_minutes = 0;

            if ($last_period_end && $timeOutObj) {
                $scheduled_end = new DateTime($date . ' ' . $last_period_end);

                // Adjust scheduled end if it looks like overnight (e.g. shift ends at 06:00 next day)
                // This is complex without shift configuration, but we rely on simple comparison
                // If scheduled end is < scheduled start, it's overnight.
                // But here we rely on the Date.

                if ($timeOutObj < $scheduled_end) {
                    // Left early (undertime)
                    $early_interval = $timeOutObj->diff($scheduled_end);
                    $early_departure_minutes = ($early_interval->h * 60) + $early_interval->i;
                } else if ($timeOutObj > $scheduled_end) {
                    // Overtime
                    $overtime_interval = $scheduled_end->diff($timeOutObj);
                    $overtime_minutes = ($overtime_interval->h * 60) + $overtime_interval->i;
                }
            }

            // Check if record already exists for this date
            $sql = "SELECT id FROM daily_attendance WHERE employee_id = ? AND attendance_date = ?";
            $check_stmt = $conn->prepare($sql);
            $check_stmt->bind_param("is", $employee_id, $date);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();

            $status_base = ($timeOutObj) ? 'manual' : 'manual incomplete';

            // Build composite status
            if (strpos($status_base, 'manual') !== false) {
                if ($late_minutes > 0) {
                    $status_base .= ' late';
                }
                if ($early_departure_minutes > 0) {
                    $status_base .= ' undertime';
                }
            }
            $status = $status_base;

            if ($existing) {
                // Update existing record
                $sql = "UPDATE daily_attendance 
                        SET time_in = ?, 
                            time_out = ?, 
                            break_out = ?,
                            break_in = ?,
                            scheduled_hours = ?,
                            actual_hours = ?,
                            late_minutes = ?,
                            early_departure_minutes = ?,
                            overtime_minutes = ?,
                            status = ?,
                            calculated_at = NOW()
                        WHERE employee_id = ? AND attendance_date = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    $errors[] = "Record " . ($index + 1) . ": Failed to prepare UPDATE statement - " . $conn->error;
                    continue;
                }
                $stmt->bind_param(
                    "ssssddiiisis",
                    $time_in,
                    $time_out,
                    $break_out,
                    $break_in,
                    $scheduled_hours,
                    $actual_hours,
                    $late_minutes,
                    $early_departure_minutes,
                    $overtime_minutes,
                    $status,
                    $employee_id,
                    $date
                );
            } else {
                // Insert new record
                $sql = "INSERT INTO daily_attendance 
                        (employee_id, attendance_date, time_in, time_out, break_out, break_in, scheduled_hours, actual_hours, 
                         late_minutes, early_departure_minutes, overtime_minutes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    $errors[] = "Record " . ($index + 1) . ": Failed to prepare INSERT statement - " . $conn->error;
                    continue;
                }
                $stmt->bind_param(
                    "isssssddiiis",
                    $employee_id,
                    $date,
                    $time_in,
                    $time_out,
                    $break_out,
                    $break_in,
                    $scheduled_hours,
                    $actual_hours,
                    $late_minutes,
                    $early_departure_minutes,
                    $overtime_minutes,
                    $status
                );
            }

            if ($stmt->execute()) {
                $success_count++;

                // If it is an offset day and we have actual_hours (time out provided), credit the Time Bank
                if ($is_offset_day && $timeOutObj && $actual_hours > 0 && $offset_req_status === 'approved') {
                    // Check if already credited
                    $checkLedger = $conn->prepare("SELECT id FROM time_bank_ledger WHERE source_id = ? AND transaction_type = 'earned'");
                    $checkLedger->bind_param("i", $offset_req_id);
                    $checkLedger->execute();
                    if ($checkLedger->get_result()->num_rows == 0) {
                        $worked_hours = round($actual_hours / 60, 2);

                        $ledgerStmt = $conn->prepare("INSERT INTO time_bank_ledger (employee_id, transaction_type, hours, source_id, description, reference_date) VALUES (?, 'earned', ?, ?, 'Completed Offset Schedule', ?)");
                        $ledgerStmt->bind_param("idis", $employee_id, $worked_hours, $offset_req_id, $date);
                        $ledgerStmt->execute();

                        $updateReq = $conn->prepare("UPDATE offset_schedule_requests SET status = 'completed' WHERE id = ?");
                        $updateReq->bind_param("i", $offset_req_id);
                        $updateReq->execute();
                        $offset_req_status = 'completed'; // Prevent re-triggering in same request
                    }
                }

                // Sync to cloud database
                require_once __DIR__ . '/../../db_cloud_sync.php';
                $action = $existing ? 'update' : 'insert';
                $whereClause = $existing ? "employee_id = $employee_id AND attendance_date = '$date'" : '';

                syncToCloud('daily_attendance', [
                    'employee_id' => $employee_id,
                    'attendance_date' => $date,
                    'time_in' => $time_in,
                    'time_out' => $time_out,
                    'break_out' => $break_out,
                    'break_in' => $break_in,
                    'scheduled_hours' => $scheduled_hours,
                    'actual_hours' => $actual_hours,
                    'late_minutes' => $late_minutes,
                    'early_departure_minutes' => $early_departure_minutes,
                    'overtime_minutes' => $overtime_minutes,
                    'status' => $status
                ], $action, $whereClause);
            } else {
                $errors[] = "Record " . ($index + 1) . ": Database error - " . $stmt->error;
                error_log("Manual Attendance SQL Error: " . $stmt->error . " | SQL: " . $sql);
            }
        }

        if ($success_count > 0) {
            $conn->commit();

            $response = [
                'success' => true,
                'message' => "$success_count attendance record(s) added successfully",
                'records_processed' => count($records),
                'records_added' => $success_count
            ];

            if (!empty($errors)) {
                $response['warnings'] = $errors;
            }

            echo json_encode($response);
        } else {
            throw new Exception('No records were added. Errors: ' . implode('; ', $errors));
        }

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Update time out for an incomplete attendance record
 */
function updateTimeOut($conn)
{
    try {
        // Get JSON data from request
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data) {
            throw new Exception('Invalid JSON data received');
        }

        // Validate required fields
        $record_id = $data['record_id'] ?? null;
        $employee_id = $data['employee_id'] ?? null;
        $date = $data['date'] ?? null;
        $time_out = $data['time_out'] ?? null;
        $break_out = $data['break_out'] ?? null;
        $break_in = $data['break_in'] ?? null;

        if ($time_out !== null && trim($time_out) === '')
            $time_out = null;
        if ($break_out !== null && trim($break_out) === '')
            $break_out = null;
        if ($break_in !== null && trim($break_in) === '')
            $break_in = null;

        if (!$record_id || !$employee_id || !$date) {
            throw new Exception('Missing required fields: record_id, employee_id, date');
        }

        $conn->begin_transaction();

        // First, verify the record exists and is incomplete or manual (needs completion)
        // AND handle the case where we're just updating break times for an already complete record
        $check_sql = "SELECT id, time_in, status FROM daily_attendance 
                     WHERE id = ? AND employee_id = ? AND attendance_date = ?";
        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {
            throw new Exception('Failed to prepare verification query: ' . $conn->error);
        }

        $check_stmt->bind_param('iis', $record_id, $employee_id, $date);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Record not found or not eligible for update');
        }

        $record = $result->fetch_assoc();
        $time_in = $record['time_in'];

        if (!$time_in) {
            throw new Exception('Cannot add time out without time in');
        }

        // Fetch employee role for actual_hours calculation
        $emp_sql = "SELECT roles FROM employees WHERE id = ?";
        $emp_stmt = $conn->prepare($emp_sql);
        $emp_stmt->bind_param('i', $employee_id);
        $emp_stmt->execute();
        $emp_data = $emp_stmt->get_result()->fetch_assoc();
        $employeeRole = $emp_data['roles'] ?? '';

        // Get employee's schedule for this date to calculate hours
        // Convert date to day of week (0=Monday, 6=Sunday to match database format)
        $dateObj = new DateTime($date);
        $dayOfWeek = $dateObj->format('w'); // 0 (Sunday) to 6 (Saturday)
        $dayOfWeekDb = ($dayOfWeek == 0) ? 6 : ($dayOfWeek - 1); // Convert to 0=Monday format

        // Check for offset schedule
        $sqlOffset = "SELECT r.id as request_id, r.status as req_status, r.original_schedule_id, r.original_day_of_week
                      FROM offset_schedule_requests r 
                      WHERE r.employee_id = ? AND r.requested_date = ? AND r.status IN ('approved', 'completed')";
        $stmtOffset = $conn->prepare($sqlOffset);
        $stmtOffset->bind_param("is", $employee_id, $date);
        $stmtOffset->execute();
        $offset_result = $stmtOffset->get_result();
        $offset_data = $offset_result->fetch_assoc();

        $is_offset_day = false;
        $offset_req_id = null;
        $offset_req_status = null;

        if ($offset_data) {
            $source_day_of_week = $offset_data['original_day_of_week'];
            $sqlOffsetPeriods = "SELECT start_time, end_time, TIMESTAMPDIFF(MINUTE, start_time, end_time) as scheduled_minutes FROM schedule_periods WHERE schedule_id = ? AND is_active = 1 AND day_of_week = ? ORDER BY start_time ASC";
            $stmtOffsetPeriods = $conn->prepare($sqlOffsetPeriods);
            $stmtOffsetPeriods->bind_param("ii", $offset_data['original_schedule_id'], $source_day_of_week);
            $stmtOffsetPeriods->execute();
            $res_periods = $stmtOffsetPeriods->get_result()->fetch_all(MYSQLI_ASSOC);

            if (empty($res_periods)) {
                throw new Exception('Associated mirrored schedule has no active time periods to offset.');
            } else {
                $schedule_data = $res_periods;
            }

            $is_offset_day = true;
            $offset_req_id = $offset_data['request_id'];
            $offset_req_status = $offset_data['req_status'];
        } else {
            $schedule_sql = "SELECT 
                                sp.start_time, sp.end_time, 
                                TIMESTAMPDIFF(MINUTE, sp.start_time, sp.end_time) as scheduled_minutes
                             FROM employee_schedules es
                             JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
                             WHERE es.employee_id = ? 
                             AND es.is_active = 1
                             AND sp.day_of_week = ?
                             AND sp.is_active = 1
                             AND (es.end_date IS NULL OR es.end_date >= ?)";

            $schedule_stmt = $conn->prepare($schedule_sql);
            $schedule_stmt->bind_param('iis', $employee_id, $dayOfWeekDb, $date);
            $schedule_stmt->execute();
            $schedule_result = $schedule_stmt->get_result();
            $schedule_data = $schedule_result->fetch_all(MYSQLI_ASSOC);
            
            // --- INJECT MAKEUP CLASSES ---
            $sqlMakeup = "SELECT start_time, end_time, TIMESTAMPDIFF(MINUTE, start_time, end_time) as scheduled_minutes 
                          FROM makeup_class_requests WHERE employee_id = ? AND requested_date = ? AND status = 'approved'";
            $stmtMakeup = $conn->prepare($sqlMakeup);
            $stmtMakeup->bind_param("is", $employee_id, $date);
            $stmtMakeup->execute();
            $makeup_res = $stmtMakeup->get_result()->fetch_all(MYSQLI_ASSOC);
            if (!empty($makeup_res)) {
                $schedule_data = array_merge($schedule_data, $makeup_res);
            }
            
            usort($schedule_data, function($a, $b) {
                return strtotime($a['start_time']) - strtotime($b['start_time']);
            });
        }

        $scheduled_minutes = 0;
        $formatted_schedule = [];
        foreach ($schedule_data as $p) {
            $start_parts = explode(':', $p['start_time']);
            $end_parts = explode(':', $p['end_time']);
            $scheduled_minutes += (($end_parts[0] * 60 + $end_parts[1]) - ($start_parts[0] * 60 + $start_parts[1]));

            $formatted_schedule[] = [
                'start' => $p['start_time'],
                'end' => $p['end_time']
            ];
        }

        // Calculate actual hours worked (in minutes)
        $scheduleToPass = [$dayOfWeekDb => $formatted_schedule];
        $actual_minutes = calculateActualHoursWithClamping($time_in, $time_out, $scheduleToPass, $date, $employeeRole, $break_out, $break_in, $employee_id);

        $time_in_dt = new DateTime($date . ' ' . $time_in);
        $time_out_dt = new DateTime($date . ' ' . $time_out);
        if ($time_out_dt < $time_in_dt) {
            $time_out_dt->modify('+1 day');
        }

        // Calculate late minutes (compare time_in with first schedule start_time)
        $late_minutes = 0;
        if (!empty($schedule_data)) {
            $schedule_start_dt = new DateTime($date . ' ' . $schedule_data[0]['start_time']);
            if ($time_in_dt > $schedule_start_dt) {
                $late_minutes = ($time_in_dt->getTimestamp() - $schedule_start_dt->getTimestamp()) / 60;
            }
        }

        // Calculate early departure (compare time_out with last schedule end_time)
        $early_departure_minutes = 0;
        if (!empty($schedule_data)) {
            $last_index = count($schedule_data) - 1;
            $schedule_end_dt = new DateTime($date . ' ' . $schedule_data[$last_index]['end_time']);
            if ($time_out_dt < $schedule_end_dt) {
                $early_departure_minutes = ($schedule_end_dt->getTimestamp() - $time_out_dt->getTimestamp()) / 60;
            }
        }

        // Calculate overtime
        $overtime_minutes = max(0, $actual_minutes - $scheduled_minutes);

        // Build composite status
        $status_base = 'manual'; // User manually timed out, so it becomes manual
        if ($late_minutes > 0) {
            $status_base .= ' late';
        }
        if ($early_departure_minutes > 0) {
            $status_base .= ' undertime';
        }

        // Update the record
        $update_sql = "UPDATE daily_attendance 
                      SET time_out = ?, 
                          break_out = ?,
                          break_in = ?,
                          actual_hours = ?, 
                          late_minutes = ?, 
                          early_departure_minutes = ?, 
                          overtime_minutes = ?,
                          status = ?
                      WHERE id = ?";

        $update_stmt = $conn->prepare($update_sql);

        if (!$update_stmt) {
            throw new Exception('Failed to prepare update query: ' . $conn->error);
        }

        $update_stmt->bind_param(
            'sssddiiii',
            $time_out,
            $break_out,
            $break_in,
            $actual_minutes,
            $late_minutes,
            $early_departure_minutes,
            $overtime_minutes,
            $status_base,
            $record_id
        );

        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update record: ' . $update_stmt->error);
        }

        // Output Time Bank ledger logic for updateTimeOut
        if ($is_offset_day && $actual_minutes > 0 && $offset_req_status === 'approved') {
            $checkLedger = $conn->prepare("SELECT id FROM time_bank_ledger WHERE source_id = ? AND transaction_type = 'earned'");
            $checkLedger->bind_param("i", $offset_req_id);
            $checkLedger->execute();
            if ($checkLedger->get_result()->num_rows == 0) {
                $worked_hours = round($actual_minutes / 60, 2);
                $ledgerStmt = $conn->prepare("INSERT INTO time_bank_ledger (employee_id, transaction_type, hours, source_id, description, reference_date) VALUES (?, 'earned', ?, ?, 'Completed Offset Schedule', ?)");
                $ledgerStmt->bind_param("idis", $employee_id, $worked_hours, $offset_req_id, $date);
                $ledgerStmt->execute();

                $updateReq = $conn->prepare("UPDATE offset_schedule_requests SET status = 'completed' WHERE id = ?");
                $updateReq->bind_param("i", $offset_req_id);
                $updateReq->execute();
            }
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Time out updated successfully',
            'data' => [
                'record_id' => $record_id,
                'time_out' => $time_out,
                'actual_hours' => round($actual_minutes / 60, 1),
                'late_minutes' => $late_minutes,
                'early_departure_minutes' => $early_departure_minutes,
                'overtime_minutes' => $overtime_minutes,
                'status' => $status_base
            ]
        ]);

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        error_log("Update TimeOut Error: " . $e->getMessage());
        throw $e;
    }
}
?>