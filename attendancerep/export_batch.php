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

if ($exportType !== 'excel') {
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
$allExcelData = [];

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
        $sql = "SELECT attendance_date, time_in, break_out, break_in, time_out, actual_hours, status, notes FROM daily_attendance 
                WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ? AND status != 'visit'";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $employee['internal_id'], $m, $y);
        $stmt->execute();
        $res = $stmt->get_result();

        $schedule = getEmployeeSchedule($conn, $employee['internal_id']);

        $attendanceMap = [];
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['time_in']) && !empty($r['time_out'])) {
                $r['actual_hours'] = calculateActualHoursWithClamping($r['time_in'], $r['time_out'], $schedule, $r['attendance_date'], $employee['role']);
            }
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

        // Accumulate data for XLSX later
        $allExcelData[] = [
            'employee' => $employee,
            'records' => $allRecords
        ];
    } else {
        // PDF / Print - Render 2 copies of the SAME month per page
        foreach ($employeeRenderData as $monthData) {
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
    }
}

if ($exportType === 'excel') {
    ob_end_clean(); // Discard any empty space or outputs printed before this
    exportNativeXLSXHistoryWorkbook($allExcelData, 'Batch_DTR_' . date('Ymd_His') . '.xlsx');
} else {
    echo '</body>';
    echo '</html>';
}
ob_end_flush();
?>