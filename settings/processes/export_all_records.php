<?php
require_once '../../db_connection.php';
require_once '../../attendancerep/dtr_utils.php';

// Disable error display to prevent breaking XML output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication and admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Unauthorized access.");
}

// Fetch all employees
$stmt = $conn->prepare("SELECT id, employee_id, first_name, middle_name, last_name, roles FROM employees");
$stmt->execute();
$result = $stmt->get_result();

$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
$stmt->close();

if (empty($employees)) {
    die("No employees found.");
}

$allExcelData = [];

foreach ($employees as $row) {
    $fullName = strtoupper($row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? ''));

    $employee = [
        'internal_id' => $row['id'],
        'employee_id' => $row['employee_id'],
        'name' => $fullName,
        'role' => $row['roles'] ?? 'N/A',
    ];

    // Fetch all attendance for this employee
    $sql = "SELECT attendance_date, time_in, time_out, break_out, break_in, actual_hours, status, notes FROM daily_attendance 
            WHERE employee_id = ? AND status != 'visit' ORDER BY attendance_date ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee['internal_id']);
    $stmt->execute();
    $res = $stmt->get_result();

    $attendanceRecords = [];
    $schedule = getEmployeeSchedule($conn, $employee['internal_id']);

    while ($r = $res->fetch_assoc()) {
        if (!empty($r['time_in']) && !empty($r['time_out'])) {
            $r['actual_hours'] = calculateActualHoursWithClamping($r['time_in'], $r['time_out'], $schedule, $r['attendance_date'], $employee['role'], $r['break_out'], $r['break_in']);
        }
        $attendanceRecords[$r['attendance_date']] = $r;
    }
    $stmt->close();

    $allExcelData[] = [
        'employee' => $employee,
        'records' => $attendanceRecords
    ];
}

// ob_end_clean() to ensure no accidental whitespace breaks the ZIP/XLSX file output
if (ob_get_length())
    ob_end_clean();

exportNativeXLSXHistoryWorkbook($allExcelData, 'All_Records_Backup_' . date('Ymd_His') . '.xlsx');
?>