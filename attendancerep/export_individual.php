<?php
require_once '../db_connection.php';
require_once 'dtr_utils.php';

// Get parameters
$employeeId = $_GET['id'] ?? null;
$exportType = $_GET['export'] ?? 'excel'; // 'excel' or 'pdf'
$monthParam = $_GET['month'] ?? null;
$yearParam = $_GET['year'] ?? null;
$startDateParam = $_GET['start_date'] ?? null;
$endDateParam = $_GET['end_date'] ?? null;

if (!$employeeId) {
    die("Employee ID is required");
}

// Fetch employee data
$stmt = $conn->prepare("SELECT id, employee_id, first_name, middle_name, last_name, suffix, roles, hire_date FROM employees WHERE employee_id = ?");
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Employee not found");
}

$row = $result->fetch_assoc();
// Format Name: Last Name, First Name M.I. or Middle Name
$suffix = trim($row['suffix'] ?? '');
$fullName = strtoupper(trim(preg_replace('/\s+/', ' ', $row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ($suffix ? ', ' . $suffix : ''))));

$employee = [
    'internal_id' => $row['id'],
    'employee_id' => $row['employee_id'],
    'name' => $fullName,
    'role' => $row['roles'] ?? 'N/A',
];
$stmt->close();

// Fetch employee schedule once
$schedule = getEmployeeSchedule($conn, $employee['internal_id']);

// Determine date ranges to process
$periods = [];

if ($startDateParam && $endDateParam) {
    $start = new DateTime($startDateParam);
    $end = new DateTime($endDateParam);
    $endLimit = new DateTime($endDateParam);

    $current = clone $start;
    while ($current <= $endLimit) {
        $y = $current->format('Y');
        $m = $current->format('n');
        $key = "$y-$m";

        if (!isset($periods[$key])) {
            $periods[$key] = [
                'year' => $y,
                'month' => $m,
                'days' => []
            ];
        }
        $periods[$key]['days'][] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }
} elseif ($monthParam && $yearParam) {
    $periods["$yearParam-$monthParam"] = [
        'year' => $yearParam,
        'month' => $monthParam,
        'days' => null
    ];
} else {
    $m = date('n');
    $y = date('Y');
    $periods["$y-$m"] = [
        'year' => $y,
        'month' => $m,
        'days' => null
    ];
}

$renderData = [];
foreach ($periods as $key => $period) {
    $y = $period['year'];
    $m = $period['month'];

    $sql = "SELECT attendance_date, time_in, break_out, break_in, time_out, actual_hours, status, notes FROM daily_attendance 
            WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ? AND status != 'visit'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $employee['internal_id'], $m, $y);
    $stmt->execute();
    $res = $stmt->get_result();

    $attendanceMap = [];
    while ($r = $res->fetch_assoc()) {
        // RECALCULATE ACTUAL HOURS DYNAMICALLY
        // This ensures the "Last Hour" / Clamping rules apply even to historic data
        if (!isset($r['actual_hours']) || $r['actual_hours'] === null) {
            $r['actual_hours'] = calculateActualHoursWithClamping($r['time_in'], $r['time_out'], $schedule, $r['attendance_date'], $employee['role'], $r['break_out'], $r['break_in'], $employee['internal_id']);
        }

        $d = (int) date('j', strtotime($r['attendance_date']));
        $attendanceMap[$d] = $r;
    }
    $stmt->close();

    $renderData[] = [
        'year' => $y,
        'month' => $m,
        'monthName' => date('F', mktime(0, 0, 0, $m, 10)),
        'attendance' => $attendanceMap,
        'validDays' => $period['days']
    ];
}

if ($exportType === 'excel') {
    exportToExcel($employee, $renderData);
} else {
    exportToPDF($employee, $renderData);
}

// --------------------------------------------------------------------------------
// OUTPUT FUNCTIONS
// --------------------------------------------------------------------------------

function exportToPDF($employee, $renderData)
{
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>DTR - ' . htmlspecialchars($employee['name']) . '</title>';
    echo getDTRStyles(false); // Using util function
    echo '</head>';
    echo '<body>';

    // Print Control
    echo '<div class="no-print" style="text-align: center; padding: 10px; background: #333; color: white;">
            <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">PRINT DTR</button>
            <br><small>Use Scale in Print Settings if needed.</small>
          </div>';

    // Process each month individually
    $isFirstPage = true;

    foreach ($renderData as $monthData) {
        if (!$isFirstPage) {
            echo '<div class="page-break"></div>';
        }

        echo '<div class="dtr-side-by-side">';
        // Render 2 copies of the SAME month
        renderDTRForm($employee, $monthData, false);
        renderDTRForm($employee, $monthData, false);
        echo '</div>';

        $isFirstPage = false;
    }

    echo '</body></html>';
}

function exportToExcel($employee, $renderData)
{
    // Flatten the month-based chunks into a single list of records
    $allRecords = [];
    foreach ($renderData as $monthData) {
        if (!empty($monthData['attendance'])) {
            foreach ($monthData['attendance'] as $day => $record) {
                $allRecords[$record['attendance_date']] = $record;
            }
        }
    }
    // Sort by date
    ksort($allRecords);

    $allExcelData = [
        [
            'employee' => $employee,
            'records' => $allRecords
        ]
    ];

    // ob_end_clean() to ensure no accidental whitespace breaks the ZIP/XLSX file output
    if (ob_get_length())
        ob_end_clean();

    exportNativeXLSXHistoryWorkbook($allExcelData, 'Attendance_History_' . $employee['employee_id'] . '.xlsx');
}
?>