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

// Prepare file download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="All_Records_Backup_' . date('Ymd_His') . '.xls"');
header('Cache-Control: max-age=0');

echo '<?xml version="1.0"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
echo 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
echo 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
echo 'xmlns:html="http://www.w3.org/TR/REC-html40">';
echo '<Styles>';
echo '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Bottom"/><Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/></Style>';
echo '<Style ss:ID="sTitle"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="14" ss:Color="#000000" ss:Bold="1"/></Style>';
echo '<Style ss:ID="sHeader"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000" ss:Bold="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders><Interior ss:Color="#F0F0F0" ss:Pattern="Solid"/></Style>';
echo '<Style ss:ID="sDataCenter"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '<Style ss:ID="sDataCenterBold"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000" ss:Bold="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>';
echo '</Styles>';

$hasAnyWorksheet = false;

foreach ($employees as $row) {
    $fullName = strtoupper($row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? ''));

    $employee = [
        'internal_id' => $row['id'],
        'employee_id' => $row['employee_id'],
        'name' => $fullName,
        'role' => $row['roles'] ?? 'N/A',
    ];

    // Fetch all attendance for this employee
    $sql = "SELECT attendance_date, time_in, time_out, actual_hours, status, notes FROM daily_attendance 
            WHERE employee_id = ? AND status != 'visit' ORDER BY attendance_date ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee['internal_id']);
    $stmt->execute();
    $res = $stmt->get_result();

    $attendanceRecords = [];
    $schedule = getEmployeeSchedule($conn, $employee['internal_id']);

    while ($r = $res->fetch_assoc()) {
        if (!empty($r['time_in']) && !empty($r['time_out'])) {
            $r['actual_hours'] = calculateActualHoursWithClamping($r['time_in'], $r['time_out'], $schedule, $r['attendance_date'], $employee['role']);
        }
        $attendanceRecords[$r['attendance_date']] = $r;
    }
    $stmt->close();

    // Always render a worksheet even if there are no records so they are accounted for
    renderXMLSpreadsheetHistoryWorksheet($employee, $attendanceRecords);
    $hasAnyWorksheet = true;
}

if (!$hasAnyWorksheet) {
    // Excel requires at least one worksheet
    echo '<Worksheet ss:Name="No Data"><Table><Row><Cell><Data ss:Type="String">No records found</Data></Cell></Row></Table></Worksheet>';
}

echo '</Workbook>';
?>