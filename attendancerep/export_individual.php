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
$stmt = $conn->prepare("SELECT id, employee_id, first_name, middle_name, last_name, roles, hire_date FROM employees WHERE employee_id = ?");
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Employee not found");
}

$row = $result->fetch_assoc();
// Format Name: Last Name, First Name M.I. or Middle Name
$fullName = mb_strtoupper($row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? ''));

$employee = [
    'internal_id' => $row['id'],
    'employee_id' => $row['employee_id'],
    'name' => $fullName,
    'role' => $row['roles'] ?? 'N/A',
];
$stmt->close();

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
    
    $sql = "SELECT attendance_date, time_in, time_out, actual_hours, status, notes FROM daily_attendance 
            WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ? AND status != 'visit'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $employee['internal_id'], $m, $y);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $attendanceMap = [];
    while ($r = $res->fetch_assoc()) {
        $d = (int)date('j', strtotime($r['attendance_date']));
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

function exportToPDF($employee, $renderData) {
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
    
    // Process in chunks of 2 for side-by-side layout
    $chunks = array_chunk($renderData, 2);
    $isFirstPage = true;

    foreach ($chunks as $chunk) {
        if (!$isFirstPage) echo '<div class="page-break"></div>';
        
        if (count($chunk) === 2) {
            echo '<div class="dtr-side-by-side">';
            renderDTRForm($employee, $chunk[0], false);
            renderDTRForm($employee, $chunk[1], false);
            echo '</div>';
        } else {
            // Single DTR (center it or just render)
            // If it's just one, we can still use the wrapper to keep styling consistent if desired, 
            // or just render it. Let's just render standard.
            renderDTRForm($employee, $chunk[0], false);
        }
        $isFirstPage = false;
    }
    
    echo '</body></html>';
}

function exportToExcel($employee, $renderData) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Attendance_History_' . $employee['employee_id'] . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>
            body { font-family: Arial, sans-serif; font-size: 10pt; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #000; padding: 5px; }
          </style>';
    echo '</head><body>';
    
    // Flatten the month-based chunks into a single list of records
    $allRecords = [];
    foreach ($renderData as $monthData) {
        if (!empty($monthData['attendance'])) {
            // $monthData['attendance'] is keyed by DAY (1..31)
            // We want to sort by date.
            foreach ($monthData['attendance'] as $day => $record) {
                // Key it by full date for sorting if needed, or just append
                $allRecords[$record['attendance_date']] = $record;
            }
        }
    }
    // Sort by date
    ksort($allRecords);

    renderExcelHistoryTable($employee, $allRecords);
    
    echo '</body></html>';
}
?>
