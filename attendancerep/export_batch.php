<?php
require_once '../db_connection.php';
require_once 'dtr_utils.php';

// Get parameters from POST
$exportType = $_POST['export_type'] ?? 'excel';
$employeeIdsJson = $_POST['employee_ids'] ?? '[]';
$employeeIds = json_decode($employeeIdsJson, true);
$startDateParam = $_POST['start_date'] ?? null;
$endDateParam = $_POST['end_date'] ?? null;

if (empty($employeeIds) || !is_array($employeeIds)) {
    die("No employees selected or invalid data.");
}

// 1. Determine Date Periods (Common for all employees)
$periods = [];
if ($startDateParam && $endDateParam) {
    try {
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
    } catch (Exception $e) {
        die("Invalid date format.");
    }
} else {
    // Default to current month if no range selected
    $m = date('n');
    $y = date('Y');
    $periods["$y-$m"] = [
        'year' => $y,
        'month' => $m,
        'days' => null
    ];
}

ob_start();

if ($exportType === 'excel') {
    // Excel Header
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Batch_DTR_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo getDTRStyles(true);
    echo '</head><body>';
} else {
    // PDF (HTML) Header
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Batch DTR - ' . date('Y-m-d') . '</title>';
    echo getDTRStyles(false);
    echo '</head>';
    echo '<body>';

    // Print Control
    echo '<div class="no-print" style="text-align: center; padding: 10px; background: #333; color: white;">
            <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">PRINT BATCH DTR</button>
            <br><small>This will print all selected DTRs. Use headers/footers settings in browser to remove URL/Time.</small>
          </div>';
}

$isFirstPage = true;

// Process Each Employee
foreach ($employeeIds as $empId) {
    // Fetch Employee Info
    $stmt = $conn->prepare("SELECT id, employee_id, first_name, middle_name, last_name, roles FROM employees WHERE id = ?");
    $stmt->bind_param("i", $empId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        continue;
    }

    $row = $result->fetch_assoc();
    $fullName = strtoupper($row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? ''));

    $employee = [
        'internal_id' => $row['id'],
        'employee_id' => $row['employee_id'],
        'name' => $fullName,
        'role' => $row['roles'] ?? 'N/A',
    ];
    $stmt->close();

    // Process Periods for this employee
    $employeeRenderData = [];
    foreach ($periods as $period) {
        $y = $period['year'];
        $m = $period['month'];

        // Fetch Attendance
        $sql = "SELECT attendance_date, time_in, time_out, actual_hours, status, notes FROM daily_attendance 
                WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ? AND status != 'visit'";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $employee['internal_id'], $m, $y);
        $stmt->execute();
        $res = $stmt->get_result();

        $attendanceMap = [];
        while ($r = $res->fetch_assoc()) {
            $d = (int) date('j', strtotime($r['attendance_date']));
            $attendanceMap[$d] = $r;
        }
        $stmt->close();

        $employeeRenderData[] = [
            'year' => $y,
            'month' => $m,
            'monthName' => date('F', mktime(0, 0, 0, $m, 10)),
            'attendance' => $attendanceMap,
            'validDays' => $period['days']
        ];
    }

    // Output Logic
    if ($exportType === 'excel') {
        // Flatten for Excel History Table
        $allRecords = [];
        foreach ($employeeRenderData as $monthData) {
            if (!empty($monthData['attendance'])) {
                foreach ($monthData['attendance'] as $day => $record) {
                    $allRecords[$record['attendance_date']] = $record;
                }
            }
        }
        ksort($allRecords);

        renderExcelHistoryTable($employee, $allRecords);
        echo '<br><br>'; // Spacing between employees
    } else {
        // PDF / Print - Chunk by 2
        $chunks = array_chunk($employeeRenderData, 2);

        foreach ($chunks as $chunk) {
            if (!$isFirstPage) {
                echo '<div class="page-break"></div>';
            }

            if (count($chunk) === 2) {
                echo '<div class="dtr-side-by-side">';
                renderDTRForm($employee, $chunk[0], false);
                renderDTRForm($employee, $chunk[1], false);
                echo '</div>';
            } else {
                renderDTRForm($employee, $chunk[0], false);
            }
            $isFirstPage = false;
        }
    }
}

echo '</body></html>';
ob_end_flush();
?>