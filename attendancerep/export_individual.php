<?php
require_once '../db_connection.php';

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
// We need to generate a list of (Month, Year, StartDate, EndDate) tuples
$periods = [];

if ($startDateParam && $endDateParam) {
    // Logic to split range into months
    $start = new DateTime($startDateParam);
    $end = new DateTime($endDateParam);
    $end->modify('+1 day'); // Include end date in interval

    $interval = DateInterval::createFromDateString('1 month');
    $period = new DatePeriod($start, $interval, $end);

    // This naive period loop might miss the last partial month if not careful, 
    // better to iterate by day or just determine unique Y-m pairs.
    
    // Robust approach: Iterate from start to end, track unique months.
    $current = clone $start;
    $endLimit = new DateTime($endDateParam); // Reset end limit
    
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
    // Single month
    $periods["$yearParam-$monthParam"] = [
        'year' => $yearParam,
        'month' => $monthParam,
        'days' => null // null means full month
    ];
} else {
    // Default to current month
    $m = date('n');
    $y = date('Y');
    $periods["$y-$m"] = [
        'year' => $y,
        'month' => $m,
        'days' => null
    ];
}

// Fetch attendance for all relevant periods
// Optimization: Fetch all data in one query if range is contiguous, 
// but for simplicity and correctness with the split-month requirement, 
// we can just fetch everything in the range or filter by month in lookups.

// Let's build a big list of periods to render.
$renderData = [];

foreach ($periods as $key => $period) {
    $y = $period['year'];
    $m = $period['month'];
    
    // Fetch data for this specific month/year for this employee
    $sql = "SELECT attendance_date, time_in, time_out, actual_hours FROM daily_attendance 
            WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?";
    
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
    
    // Filter days if we have a specific list (from Date Range)
    // Actually, Form 48 usually shows the WHOLE month (1-31), 
    // but implies attendance only on worked days.
    // If a range is selected (e.g. Jan 15 - Feb 15), 
    // split it into Jan DTR (showing entries for 15-31) and Feb DTR (1-15).
    // The standard form prints 1-31 regardless.
    
    $renderData[] = [
        'year' => $y,
        'month' => $m,
        'monthName' => date('F', mktime(0, 0, 0, $m, 10)),
        'attendance' => $attendanceMap,
        'validDays' => $period['days'] // Array of Y-m-d strings or null
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
    $dateStr = date('Y-m-d');
    
    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>DTR - ' . htmlspecialchars($employee['name']) . '</title>';
    echo '<style>
        @media print {
            @page {
                size: letter portrait; /* or auto? User asked for 3x8.5 size */
                margin: 0.5in;
            }
            body { margin: 0; padding: 0; }
            .page-break { page-break-before: always; }
            .no-print { display: none; }
        }
        
        body { 
            font-family: "Arial", sans-serif;
            font-size: 11px;
            color: #000080; /* Navy Blue Text */
            background-color: #f0f0f0;
        }
        
        .dtr-wrapper {
            background-color: white;
            padding: 20px;
            margin: 20px auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 3.5in; /* Constrain width as requested approx 3-3.5in */
            min-height: 8.5in;
        }

        /* Screen only wrapper to center it */
        @media screen {
            .dtr-wrapper { margin-bottom: 20px; }
        }

        @media print {
            .dtr-wrapper {
                box-shadow: none;
                margin: 0;
                width: 3.5in; /* Enforce width in print */
            }
        }
        
        .header { text-align: center; margin-bottom: 5px; }
        .header h3 { margin: 0; font-size: 12px; font-weight: bold; color: #000080; }
        .header .form-no { font-size: 9px; font-style: italic; text-align: left; color: #000080; }
        .header .title { font-size: 14px; font-weight: 900; margin: 5px 0; color: #000080; }
        
        .info-row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .line-bottom { border-bottom: 1px solid #000080; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-top: 5px;
            border: 2px solid #000080;
        }
        
        th, td {
            border: 1px solid #000080;
            text-align: center;
            padding: 1px;
            height: 14px;
        }
        
        .col-day { width: 10%; }
        
        .footer { margin-top: 10px; font-size: 10px; color: #000080; }
        .signature { 
            border-top: 1px solid #000080; 
            width: 90%; 
            margin: 20px auto 0 auto; 
            text-align: center; 
            padding-top: 2px;
        }
    </style>';
    echo '</head>';
    echo '<body>';
    
    // Print Control
    echo '<div class="no-print" style="text-align: center; padding: 10px; background: #333; color: white;">
            <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">PRINT DTR</button>
            <br><small>Use Scale in Print Settings if needed.</small>
          </div>';
    
    foreach ($renderData as $index => $data) {
        if ($index > 0) echo '<div class="page-break"></div>';
        renderDTRForm($employee, $data);
    }
    
    echo '</body></html>';
}

function exportToExcel($employee, $renderData) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="DTR_' . $employee['employee_id'] . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    // Excel styles
    echo '<style>
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .border { border: 1px solid #000; }
    </style>';
    echo '</head><body>';
    
    foreach ($renderData as $data) {
        renderDTRForm($employee, $data, true);
        echo '<br><br>';
    }
    
    echo '</body></html>';
}

function renderDTRForm($employee, $data, $isExcel = false) {
    $year = $data['year'];
    $month = $data['month'];
    $monthName = $data['monthName'];
    $attendance = $data['attendance'];
    
    // Calculate totals
    $totalHours = 0;
    $totalMinutes = 0;
    
    // Wrapper
    if (!$isExcel) echo '<div class="dtr-wrapper">';
    
    // Header
    if ($isExcel) {
        echo '<table><tr><td colspan="7" class="text-center bold">BULACAN POLYTECHNIC COLLEGE</td></tr>';
        echo '<tr><td colspan="7">Civil Service Form No. 48</td></tr>';
        echo '<tr><td colspan="7" class="text-center bold" style="font-size: 14pt;">DAILY TIME RECORD</td></tr>';
        echo '<tr><td colspan="7" class="text-center">-----o0o-----</td></tr>';
        echo '<tr><td colspan="7" class="text-center bold" style="border-bottom: 1px solid black;">' . $employee['name'] . '</td></tr>';
        echo '<tr><td colspan="7" class="text-center">(Name)</td></tr>';
        echo '<tr><td colspan="3">For the month of:</td><td colspan="4" class="bold">' . $monthName . ' ' . $year . '</td></tr>';
    } else {
        echo '<div class="header">
                <h3>BULACAN POLYTECHNIC COLLEGE</h3>
                <br>
                <div class="form-no">Civil Service Form No. 48</div>
                <div class="title">DAILY TIME RECORD</div>
                <div style="font-size: 10px;">-----o0o-----</div>
              </div>';
              
        echo '<div style="text-align: center; border-bottom: 2px solid #000080; font-weight: bold; font-size: 13px; margin-bottom: 2px;">' . $employee['name'] . '</div>';
        echo '<div style="text-align: center; font-size: 10px; margin-bottom: 10px;">(Name)</div>';
        
        echo '<div class="info-row">
                <span>For the month of</span>
                <span class="line-bottom" style="width: 60%; font-weight: bold; text-align: center;">' . $monthName . ' ' . $year . '</span>
              </div>';
              
        echo '<div class="info-row" style="margin-bottom: 5px;">
                <span style="font-style: italic; width: 40%;">Official hours for arrival and departure</span>
                <div style="width: 58%;">
                    <div style="display: flex; justify-content: space-between;">
                        <span>Regular days</span>
                        <span class="line-bottom" style="width: 50%;"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Saturdays</span>
                        <span class="line-bottom" style="width: 50%;"></span>
                    </div>
                </div>
              </div>';
    }
    
    // Table Header
    echo '<table ' . ($isExcel ? 'border="1"' : '') . '>';
    echo '<thead>
            <tr>
                <th rowspan="2" class="col-day">Day</th>
                <th colspan="2">A.M.</th>
                <th colspan="2">P.M.</th>
                <th colspan="2">Total</th>
            </tr>
            <tr>
                <th>Arrival</th>
                <th>Departure</th>
                <th>Arrival</th>
                <th>Departure</th>
                <th>Hours</th>
                <th>Minutes</th>
            </tr>
          </thead>';
    echo '<tbody>';
    
    for ($d = 1; $d <= 31; $d++) {
        $r = $attendance[$d] ?? null;
        
        $amIn = ''; $amOut = '';
        $pmIn = ''; $pmOut = '';
        $hrs = ''; $mins = '';
        
        // Check if date exists in valid days (if range filter applied)
        $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $isValidDay = true;
        if ($data['validDays'] !== null) {
            $isValidDay = in_array($currentDate, $data['validDays']);
        }
        
        if ($r && $isValidDay) {
            $timeInStr = $r['time_in'] ? date('G:i', strtotime($r['time_in'])) : null;
            $timeOutStr = $r['time_out'] ? date('G:i', strtotime($r['time_out'])) : null;
            
            // Simple logic: In -> AM Arrival, Out -> PM Departure
            // As per Plan.
            if ($r['time_in']) $amIn = date('h:i', strtotime($r['time_in']));
            if ($r['time_out']) $pmOut = date('h:i', strtotime($r['time_out']));
            
            if ($r['actual_hours']) {
                $totalM = (float)$r['actual_hours'];
                $h = floor($totalM / 60);
                $m = $totalM % 60;
                
                $hrs = $h > 0 ? $h : '';
                $mins = $m > 0 ? $m : '';
                
                $totalHours += $h;
                $totalMinutes += $m;
            }
        }
        
        echo '<tr>
                <td>' . $d . '</td>
                <td>' . $amIn . '</td>
                <td>' . $amOut . '</td>
                <td>' . $pmIn . '</td>
                <td>' . $pmOut . '</td>
                <td>' . $hrs . '</td>
                <td>' . $mins . '</td>
              </tr>';
    }
    
    // Logic for Total row
    $addedHours = floor($totalMinutes / 60);
    $finalMintues = $totalMinutes % 60;
    $finalHours = $totalHours + $addedHours;
    
    echo '<tr>
            <td colspan="5" style="text-align: right; font-weight: bold; padding-right: 5px;">Total</td>
            <td style="font-weight: bold;">' . ($finalHours > 0 ? $finalHours : '') . '</td>
            <td style="font-weight: bold;">' . ($finalMintues > 0 ? $finalMintues : '') . '</td>
          </tr>';
          
    echo '</tbody></table>';
    
    // Footer
    if ($isExcel) {
        echo '<table>
                <tr><td colspan="7">I certify on my honor that the above is a true and correct report...</td></tr>
                <tr><td colspan="7" class="text-center" style="border-top: 1px solid black;">(Name and Signature)</td></tr>
                <tr><td colspan="7" class="text-center">VERIFIED as to the prescribed office hours</td></tr>
                <tr><td colspan="7" class="text-center bold text-decoration-underline">Mrs. VICTORIA M. SISON, MAEd</td></tr>
                <tr><td colspan="7" class="text-center">CIC - Office of the College President</td></tr>
              </table>';
    } else {
        echo '<div class="footer">
                <p style="font-style: italic; margin: 10px 0; text-align: justify;">
                    I certify on my honor that the above is a true and correct report of the hours of work performed, 
                    record of which was made daily at the time of arrival and departure from office.
                </p>
                <div class="signature">(Name and Signature)</div>
                <div style="margin: 15px 0 25px 0;">VERIFIED as to the prescribed office hours</div>
                <div style="text-align: center;">
                    <div style="font-weight: bold; text-decoration: underline; font-size: 12px;">Mrs. VICTORIA M. SISON, MAEd</div>
                    <div style="font-size: 10px;">CIC - Office of the College President</div>
                </div>
              </div>';
        echo '</div>'; // End wrapper
    }
}
?>
