<?php

function getDTRStyles($isExcel = false)
{
    if ($isExcel) {
        return '<style>
            .text-center { text-align: center; }
            .bold { font-weight: bold; }
            .border { border: 1px solid #000; }
        </style>';
    }

    return '<style>
        @media print {
            @page {
                size: letter portrait; /* approx 8.5x11in */
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
            border: 1px solid #000080;
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
                border: 1px solid #000080;
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
        
        .dtr-side-by-side {
            display: flex;
            justify-content: center;
            gap: 20px;
            /* page-break-after: always; Removed to avoid double page breaks with manual PHP breaks */
        }
        
        @media print {
            .dtr-side-by-side {
                gap: 0.5in; /* Print gap */
                width: 100%;
                justify-content: center;
                margin: 0;
            }
            .dtr-side-by-side .dtr-wrapper {
                margin: 0;
                /* Optional: scale down slightly if margins are tight */
            }
        }

        .footer { margin-top: 10px; font-size: 10px; color: #000080; }
        .signature { 
            border-top: 1px solid #000080; 
            width: 90%; 
            margin: 20px auto 0 auto; 
            text-align: center; 
            padding-top: 2px;
        }
    </style>';
}

function renderDTRForm($employee, $data, $isExcel = false)
{
    $year = $data['year'];
    $month = $data['month'];
    $monthName = $data['monthName'];
    $attendance = $data['attendance'];

    // Calculate totals
    $totalHours = 0;
    $totalMinutes = 0;

    // Wrapper
    if (!$isExcel)
        echo '<div class="dtr-wrapper">';

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

        $amIn = '';
        $amOut = '';
        $pmIn = '';
        $pmOut = '';
        $hrs = '';
        $mins = '';

        // Check if date exists in valid days (if range filter applied)
        $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $isValidDay = true;
        if (isset($data['validDays']) && $data['validDays'] !== null) {
            $isValidDay = in_array($currentDate, $data['validDays']);
        }

        if ($r && $isValidDay) {
            // Logic: Place time based on actual AM/PM value

            // TIME IN
            if (!empty($r['time_in'])) {
                $inTs = strtotime($r['time_in']);
                $inStr = date('g:i', $inTs); // 12-hour format without leading zero
                if (date('a', $inTs) === 'am') {
                    $amIn = $inStr;
                } else {
                    $pmIn = $inStr; // Late arrival in PM
                }
            }

            // TIME OUT
            if (!empty($r['time_out'])) {
                $outTs = strtotime($r['time_out']);
                $outStr = date('g:i', $outTs);
                if (date('a', $outTs) === 'am') {
                    $amOut = $outStr; // Early departure in AM
                } else {
                    $pmOut = $outStr;
                }
            }

            if ($r['actual_hours']) {
                $totalM = (float) $r['actual_hours'];
                $h = floor($totalM / 60);
                $m = (int) ($totalM) % 60;

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
                <tr><td colspan="7" class="text-center">OIC - Office of the College President</td></tr>
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
                    <div class="dtr-oic-name" contenteditable="true" style="font-weight: bold; text-decoration: underline; font-size: 12px; outline: none; cursor: text;" oninput="syncDTRField(this, \'dtr-oic-name\')">Mrs. VICTORIA M. SISON, MAEd</div>
                    <div class="dtr-oic-title" contenteditable="true" style="font-size: 10px; outline: none; cursor: text;" oninput="syncDTRField(this, \'dtr-oic-title\')">OIC - Office of the College President</div>
                </div>
              </div>';
        echo '</div>'; // End wrapper

        // Add sync script only once (check if already added)
        if (!defined('DTR_SYNC_SCRIPT_ADDED')) {
            define('DTR_SYNC_SCRIPT_ADDED', true);
            echo '<script>
                function syncDTRField(source, className) {
                    const value = source.innerText;
                    const fields = document.querySelectorAll("." + className);
                    fields.forEach(field => {
                        if (field !== source) {
                            field.innerText = value;
                        }
                    });
                }
            </script>';
        }
    }
}

function renderExcelHistoryTable($employee, $attendanceRecords)
{
    // Determine the date range
    $dateRangeStr = "";
    if (!empty($attendanceRecords)) {
        $dates = array_keys($attendanceRecords);
        sort($dates);
        $startDate = date('F j, Y', strtotime($dates[0]));
        $endDate = date('F j, Y', strtotime($dates[count($dates) - 1]));
        if ($startDate === $endDate) {
            $dateRangeStr = $startDate;
        } else {
            $dateRangeStr = $startDate . " - " . $endDate;
        }
    }

    // Header Info
    echo '<table border="1">';
    echo '<tr><td colspan="5" style="font-weight: bold; font-size: 14pt; text-align: center;">BULACAN POLYTECHNIC COLLEGE</td></tr>';
    echo '<tr><td colspan="5" style="font-weight: bold; font-size: 14pt; text-align: center;">ATTENDANCE HISTORY</td></tr>';
    echo '<tr><td colspan="5" style="font-weight: bold; text-align: center;">' . htmlspecialchars($employee['name']) . '</td></tr>';
    echo '<tr><td colspan="5" style="text-align: center;">' . htmlspecialchars($employee['role']) . '</td></tr>';
    if ($dateRangeStr) {
        echo '<tr><td colspan="5" style="text-align: center;">' . $dateRangeStr . '</td></tr>';
    }
    echo '<tr><td colspan="5" style="height: 10px;"></td></tr>'; // Spacer

    // Table Header
    echo '<tr style="background-color: #f0f0f0; font-weight: bold;">
            <th style="width: 150px; text-align: center;">Date</th>
            <th style="width: 100px; text-align: center;">Time In</th>
            <th style="width: 100px; text-align: center;">Time Out</th>
            <th style="width: 100px; text-align: center;">Total Hours</th>
            <th style="width: 150px; text-align: center;">Notes / Status</th>
          </tr>';

    // Data Rows
    if (empty($attendanceRecords)) {
        echo '<tr><td colspan="5" style="text-align: center;">No records found.</td></tr>';
    } else {
        foreach ($attendanceRecords as $date => $data) {
            $dateStr = $data['attendance_date'];
            $timeIn = $data['time_in'] ? date('h:i A', strtotime($data['time_in'])) : '';
            $timeOut = $data['time_out'] ? date('h:i A', strtotime($data['time_out'])) : '';

            // Hours
            $hours = '';
            if ($data['actual_hours']) {
                $h = floor($data['actual_hours'] / 60);
                $m = (int) ($data['actual_hours']) % 60;
                $hours = sprintf('%dh %dm', $h, $m);
            }

            // Notes / Status
            $status = ucfirst($data['status']);
            if ($data['status'] === 'manual') {
                $notes = 'Manual Entry';
            } elseif ($data['status'] === 'visit') {
                $notes = 'Visit';
            } else {
                $notes = 'Biometric Scan'; // Default assumption
            }
            if (!empty($data['notes'])) {
                $notes .= ' - ' . $data['notes'];
            }

            echo '<tr>
                    <td style="text-align: center;">' . date('F j, Y', strtotime($dateStr)) . '</td>
                    <td style="text-align: center;">' . $timeIn . '</td>
                    <td style="text-align: center;">' . $timeOut . '</td>
                    <td style="text-align: center;">' . $hours . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($notes) . '</td>
                  </tr>';
        }
    }
    echo '</table>';
}

function renderXMLSpreadsheetHistoryWorksheet($employee, $attendanceRecords)
{
    // Determine sheet name (Excel limits to 31 chars, certain characters invalid)
    $sheetName = substr(str_replace(['\\', '/', '?', '*', '[', ']'], '', $employee['name']), 0, 31);

    echo '<Worksheet ss:Name="' . htmlspecialchars($sheetName) . '">';
    echo '<Table ss:ExpandedColumnCount="5" ss:ExpandedRowCount="' . (count($attendanceRecords) + 6) . '" x:FullColumns="1" x:FullRows="1">';

    // Column Widths
    echo '<Column ss:Index="1" ss:Width="110"/>';
    echo '<Column ss:Index="2" ss:Width="75"/>';
    echo '<Column ss:Index="3" ss:Width="75"/>';
    echo '<Column ss:Index="4" ss:Width="75"/>';
    echo '<Column ss:Index="5" ss:Width="150"/>';

    // Determine the date range
    $dateRangeStr = "";
    if (!empty($attendanceRecords)) {
        $dates = array_keys($attendanceRecords);
        sort($dates);
        $startDate = date('F j, Y', strtotime($dates[0]));
        $endDate = date('F j, Y', strtotime($dates[count($dates) - 1]));
        if ($startDate === $endDate) {
            $dateRangeStr = $startDate;
        } else {
            $dateRangeStr = $startDate . " - " . $endDate;
        }
    }

    // Header Info
    echo '<Row><Cell ss:MergeAcross="4" ss:StyleID="sTitle"><Data ss:Type="String">BULACAN POLYTECHNIC COLLEGE</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="4" ss:StyleID="sTitle"><Data ss:Type="String">ATTENDANCE HISTORY</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="4" ss:StyleID="sDataCenterBold"><Data ss:Type="String">' . htmlspecialchars($employee['name']) . '</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="4" ss:StyleID="sDataCenter"><Data ss:Type="String">' . htmlspecialchars($employee['role']) . '</Data></Cell></Row>';
    if ($dateRangeStr) {
        echo '<Row><Cell ss:MergeAcross="4" ss:StyleID="sDataCenter"><Data ss:Type="String">' . $dateRangeStr . '</Data></Cell></Row>';
    }
    echo '<Row><Cell ss:MergeAcross="4"><Data ss:Type="String"></Data></Cell></Row>';

    // Table Header Row
    echo '<Row>';
    echo '<Cell ss:StyleID="sHeader"><Data ss:Type="String">Date</Data></Cell>';
    echo '<Cell ss:StyleID="sHeader"><Data ss:Type="String">Time In</Data></Cell>';
    echo '<Cell ss:StyleID="sHeader"><Data ss:Type="String">Time Out</Data></Cell>';
    echo '<Cell ss:StyleID="sHeader"><Data ss:Type="String">Total Hours</Data></Cell>';
    echo '<Cell ss:StyleID="sHeader"><Data ss:Type="String">Notes / Status</Data></Cell>';
    echo '</Row>';

    // Data Rows
    if (empty($attendanceRecords)) {
        echo '<Row><Cell ss:MergeAcross="4" ss:StyleID="sDataCenter"><Data ss:Type="String">No records found.</Data></Cell></Row>';
    } else {
        foreach ($attendanceRecords as $date => $data) {
            $dateStr = $data['attendance_date'];
            $timeIn = (!empty($data['time_in']) && $data['time_in'] !== '00:00:00') ? date('h:i A', strtotime($data['time_in'])) : '';
            $timeOut = (!empty($data['time_out']) && $data['time_out'] !== '00:00:00') ? date('h:i A', strtotime($data['time_out'])) : '';

            // Hours
            $hours = '';
            if (!empty($data['actual_hours'])) {
                $h = floor($data['actual_hours'] / 60);
                $m = (int) ($data['actual_hours']) % 60;
                $hours = sprintf('%dh %dm', $h, $m);
            }

            // Notes / Status
            $status = ucfirst($data['status']);
            if ($data['status'] === 'manual') {
                $notes = 'Manual Entry';
            } elseif ($data['status'] === 'visit') {
                $notes = 'Visit';
            } else {
                $notes = 'Biometric Scan'; // Default assumption
            }
            if (!empty($data['notes'])) {
                $notes .= ' - ' . $data['notes'];
            }

            echo '<Row>';
            echo '<Cell ss:StyleID="sDataCenter"><Data ss:Type="String">' . date('F j, Y', strtotime($dateStr)) . '</Data></Cell>';
            echo '<Cell ss:StyleID="sDataCenter"><Data ss:Type="String">' . $timeIn . '</Data></Cell>';
            echo '<Cell ss:StyleID="sDataCenter"><Data ss:Type="String">' . $timeOut . '</Data></Cell>';
            echo '<Cell ss:StyleID="sDataCenter"><Data ss:Type="String">' . $hours . '</Data></Cell>';
            echo '<Cell ss:StyleID="sDataCenter"><Data ss:Type="String">' . htmlspecialchars($notes) . '</Data></Cell>';
            echo '</Row>';
        }
    }

    echo '</Table>';
    echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">';
    echo '<PageSetup><Header x:Margin="0.3"/><Footer x:Margin="0.3"/><PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/></PageSetup>';
    echo '<FitToPage/><Print><FitHeight>0</FitHeight><ValidPrinterInfo/><HorizontalResolution>600</HorizontalResolution><VerticalResolution>600</VerticalResolution></Print>';
    echo '<Selected/><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios>';
    echo '</WorksheetOptions>';
    echo '</Worksheet>';
}


// Helper to fetch schedule
function getEmployeeSchedule($conn, $employeeInternalId)
{
    // Get active schedule periods
    // We fetch ALL active periods for simplicity, keyed by day_of_week
    $sql = "SELECT sp.day_of_week, sp.start_time, sp.end_time
            FROM employee_schedules es
            JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
            WHERE es.employee_id = ? AND es.is_active = 1 AND sp.is_active = 1
            ORDER BY sp.day_of_week, sp.start_time";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employeeInternalId);
    $stmt->execute();
    $result = $stmt->get_result();

    $schedule = [];
    while ($row = $result->fetch_assoc()) {
        $dow = $row['day_of_week'];
        if (!isset($schedule[$dow])) {
            $schedule[$dow] = [];
        }
        $schedule[$dow][] = [
            'start' => $row['start_time'],
            'end' => $row['end_time']
        ];
    }
    $stmt->close();
    return $schedule;
}


function calculateActualHoursWithClamping($timeInStr, $timeOutStr, $schedule, $dateStr, $employeeRole = '')
{
    if (empty($timeInStr) || empty($timeOutStr)) {
        return 0;
    }

    $phpDow = (int) date('w', strtotime($dateStr));
    $dbDow = ($phpDow == 0) ? 6 : $phpDow - 1;

    $totalMinutes = 0;

    if (empty($schedule) || !isset($schedule[$dbDow])) {
        // No schedule found — compute raw elapsed minutes (no clamping)
        $tIn  = strtotime($timeInStr);
        $tOut = strtotime($timeOutStr);
        $totalMinutes = ($tOut - $tIn) / 60;
    } else {
        $periods = $schedule[$dbDow];
        usort($periods, function ($a, $b) {
            return strtotime($a['start']) - strtotime($b['start']);
        });

        $firstPeriodStart = $periods[0]['start'];
        $lastPeriodEnd    = end($periods)['end'];

        $dateOnly = date('Y-m-d', strtotime($dateStr));

        $schedStartTs = strtotime("$dateOnly $firstPeriodStart");
        $schedEndTs   = strtotime("$dateOnly $lastPeriodEnd");
        $tInTs  = strtotime("$dateOnly " . date('H:i:s', strtotime($timeInStr)));
        $tOutTs = strtotime("$dateOnly " . date('H:i:s', strtotime($timeOutStr)));

        $calcStartTs = max($tInTs, $schedStartTs);
        $calcEndTs   = min($tOutTs, $schedEndTs);

        if ($calcStartTs >= $calcEndTs) {
            return 0;
        }

        $totalSeconds = 0;
        foreach ($periods as $period) {
            $pStartTs = strtotime("$dateOnly " . $period['start']);
            $pEndTs   = strtotime("$dateOnly " . $period['end']);
            $intStart = max($calcStartTs, $pStartTs);
            $intEnd   = min($calcEndTs, $pEndTs);
            $duration = $intEnd - $intStart;
            if ($duration > 0) {
                $totalSeconds += $duration;
            }
        }

        $totalMinutes = $totalSeconds / 60;
    }

    // ── Break Deduction ─────────────────────────────────────────────────────
    // Load setting once per request (cached via static variable)
    static $deductionMinutes = null;
    if ($deductionMinutes === null) {
        global $conn;
        $dbConn = isset($conn) ? $conn : null;
        if ($dbConn) {
            $res = $dbConn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'break_deduction_minutes'");
            $deductionMinutes = ($res && $r2 = $res->fetch_assoc()) ? (int) $r2['setting_value'] : 60;
        } else {
            $deductionMinutes = 60;
        }
    }

    $roleLower = strtolower(str_replace(['-', '_', '.'], ' ', $employeeRole));

    // Faculty_Member / Teaching roles → NO deduction
    $hasFaculty     = strpos($roleLower, 'faculty')      !== false;
    $hasTeaching    = strpos($roleLower, 'teaching')     !== false;
    $hasNonTeaching = strpos($roleLower, 'non teaching') !== false;
    $isFacultyExempt = $hasFaculty || ($hasTeaching && !$hasNonTeaching);

    if (!$isFacultyExempt && $deductionMinutes > 0) {
        // Applies to admin, staff, non-teaching etc. if worked >= 5 hours
        if ($totalMinutes >= 300) {
            $totalMinutes = max(0, $totalMinutes - $deductionMinutes);
        }
    }

    return round($totalMinutes, 2);
}

// ======================================================================================
// NATIVE XLSX EXPORT GENERATOR (PHP_XLSXWriter)
// ======================================================================================

function exportNativeXLSXHistoryWorkbook($allExcelData, $filename)
{
    if (!class_exists('ZipArchive')) {
        die('
            <div style="font-family: Arial, sans-serif; text-align: center; margin-top: 50px; color: #333;">
                <h2 style="color: #d9534f;">Excel Export Failed</h2>
                <p>The PHP <strong>ZipArchive</strong> extension is missing or disabled on the server.</p>
                <p>Please enable the <code>php_zip</code> extension in your <code>php.ini</code> configuration file and restart your web server / IIS to use the Native XLSX Export feature.</p>
            </div>
        ');
    }

    require_once __DIR__ . '/xlsxwriter.class.php';

    // Output headers for Native XLSX
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    $writer = new XLSXWriter();
    $writer->setAuthor('EndDev Attendance System');

    // Define styles mapping similar to previous export
    $styleTitle = [
        'font' => 'Calibri',
        'font-size' => 14,
        'font-style' => 'bold',
        'halign' => 'center',
        'valign' => 'center',
    ];

    $styleDataCenterBold = [
        'font' => 'Calibri',
        'font-size' => 11,
        'font-style' => 'bold',
        'halign' => 'center',
        'valign' => 'center',
    ];

    $styleDataCenter = [
        'font' => 'Calibri',
        'font-size' => 11,
        'halign' => 'center',
        'valign' => 'center',
    ];

    $styleHeader = [
        'font' => 'Calibri',
        'font-size' => 11,
        'font-style' => 'bold',
        'halign' => 'center',
        'valign' => 'center',
        'fill' => '#F0F0F0',
        'border' => 'left,right,top,bottom',
        'border-style' => 'thin'
    ];

    $styleDataCell = [
        'font' => 'Calibri',
        'font-size' => 11,
        'halign' => 'center',
        'valign' => 'center',
        'border' => 'left,right,top,bottom',
        'border-style' => 'thin'
    ];

    // Col widths: Date(18), TimeIn(12), TimeOut(12), Hours(12), Status(25)
    $col_options = ['widths' => [18, 12, 12, 12, 25]];

    foreach ($allExcelData as $employeeGroup) {
        $employee = $employeeGroup['employee'];
        $attendanceRecords = $employeeGroup['records'];

        // Determine sheet name (Excel limits to 31 chars, certain characters invalid)
        $sheetName = substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $employee['name']), 0, 31);
        if (empty(trim($sheetName))) {
            $sheetName = 'Sheet_' . rand(1000, 9999);
        }

        // Determine the date range
        $dateRangeStr = "";
        if (!empty($attendanceRecords)) {
            $dates = array_keys($attendanceRecords);
            sort($dates);
            $startDate = date('F j, Y', strtotime($dates[0]));
            $endDate = date('F j, Y', strtotime($dates[count($dates) - 1]));
            if ($startDate === $endDate) {
                $dateRangeStr = $startDate;
            } else {
                $dateRangeStr = $startDate . " - " . $endDate;
            }
        }

        // Add headers (merged cells: 0-indexed [row_start, col_start, row_end, col_end])
        $writer->writeSheetRow($sheetName, ["BULACAN POLYTECHNIC COLLEGE"], $styleTitle);
        $writer->writeSheetRow($sheetName, ["ATTENDANCE HISTORY"], $styleTitle);
        $writer->writeSheetRow($sheetName, [$employee['name']], $styleDataCenterBold);
        $writer->writeSheetRow($sheetName, [$employee['role']], $styleDataCenter);
        if ($dateRangeStr) {
            $writer->writeSheetRow($sheetName, [$dateRangeStr], $styleDataCenter);
        }
        $writer->writeSheetRow($sheetName, [""], $styleDataCenter); // Spacer

        // Merge the top rows across 5 columns
        $writer->markMergedCell($sheetName, 0, 0, 0, 4);
        $writer->markMergedCell($sheetName, 1, 0, 1, 4);
        $writer->markMergedCell($sheetName, 2, 0, 2, 4);
        $writer->markMergedCell($sheetName, 3, 0, 3, 4);
        if ($dateRangeStr) {
            $writer->markMergedCell($sheetName, 4, 0, 4, 4);
            $writer->markMergedCell($sheetName, 5, 0, 5, 4); // Spacer merge
        } else {
            $writer->markMergedCell($sheetName, 4, 0, 4, 4); // Spacer merge if no date range
        }


        // Table Header Setup using suppress_row because we write a styled row manually next
        $writer->writeSheetHeader($sheetName, [
            'Date' => 'string',
            'Time In' => 'string',
            'Time Out' => 'string',
            'Total Hours' => 'string',
            'Notes / Status' => 'string',
        ], array_merge($col_options, ['suppress_row' => true]));

        // Write Styled Table Header
        $writer->writeSheetRow($sheetName, ['Date', 'Time In', 'Time Out', 'Total Hours', 'Notes / Status'], $styleHeader);

        if (empty($attendanceRecords)) {
            $writer->writeSheetRow($sheetName, ['No records found.', '', '', '', ''], $styleDataCell);
            $rowNum = $dateRangeStr ? 7 : 6;
            $writer->markMergedCell($sheetName, $rowNum, 0, $rowNum, 4);
        } else {
            foreach ($attendanceRecords as $date => $data) {
                $dateStr = $data['attendance_date'];
                $timeIn = (!empty($data['time_in']) && $data['time_in'] !== '00:00:00') ? date('h:i A', strtotime($data['time_in'])) : '-';
                $timeOut = (!empty($data['time_out']) && $data['time_out'] !== '00:00:00') ? date('h:i A', strtotime($data['time_out'])) : '-';

                // Hours
                $hours = '-';
                if (!empty($data['actual_hours'])) {
                    $h = floor($data['actual_hours'] / 60);
                    $m = (int) ($data['actual_hours']) % 60;
                    $hours = sprintf('%dh %dm', $h, $m);
                }

                // Notes / Status
                $status = ucfirst($data['status']);
                if ($data['status'] === 'manual') {
                    $notes = 'Manual Entry';
                } elseif ($data['status'] === 'visit') {
                    $notes = 'Visit';
                } else {
                    $notes = 'Biometric Scan';
                }
                if (!empty($data['notes'])) {
                    $notes .= ' - ' . $data['notes'];
                }

                $writer->writeSheetRow($sheetName, [
                    date('F j, Y', strtotime($dateStr)),
                    $timeIn,
                    $timeOut,
                    $hours,
                    $notes
                ], $styleDataCell);
            }
        }
    }

    $writer->writeToStdOut();
    exit;
}
?>