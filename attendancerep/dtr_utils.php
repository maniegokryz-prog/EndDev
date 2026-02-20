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
    // Header Info
    echo '<table border="1">';
    echo '<tr><td colspan="5" style="font-weight: bold; font-size: 14pt; text-align: center;">ATTENDANCE HISTORY</td></tr>';
    echo '<tr><td colspan="5" style="font-weight: bold; text-align: center;">' . htmlspecialchars($employee['name']) . '</td></tr>';
    echo '<tr><td colspan="5" style="text-align: center;">' . htmlspecialchars($employee['role']) . '</td></tr>';
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


// Helper to fetch schedule
function getEmployeeSchedule($conn, $employeeInternalId) {
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


function calculateActualHoursWithClamping($timeInStr, $timeOutStr, $schedule, $dateStr, $employeeRole = '') {
    if (empty($timeInStr) || empty($timeOutStr) || empty($schedule)) {
        return 0;
    }

    $dow = date('w', strtotime($dateStr));
    // Verify mapped schedule for day (0=Sun, 6=Sat) exists?
    // Mapped: 0=Monday? No, PHP 'w': 0=Sun, 1=Mon...
    // DB typically: 0=Mon, 6=Sun in Python. Let's check Python logic
    // In Python: `day_of_week = log_datetime.weekday()` (0=Mon).
    // In PHP `date('w')`: 0=Sunday, 1=Monday.
    // We need to map PHP dow to DB dow.
    // DB dow: 0=Mon, 1=Tue... 6=Sun.
    $phpDow = (int)date('w', strtotime($dateStr));
    $dbDow = ($phpDow == 0) ? 6 : $phpDow - 1;

    if (!isset($schedule[$dbDow])) {
        // No schedule logic applied, return raw diff?
        $tIn = strtotime($timeInStr);
        $tOut = strtotime($timeOutStr);
        return round(($tOut - $tIn) / 60, 2);
    }

    $periods = $schedule[$dbDow];
    // Sort periods by start time to ensure correct First/Last logic
    usort($periods, function($a, $b) { 
        return strtotime($a['start']) - strtotime($b['start']); 
    });

    $firstPeriodStart = $periods[0]['start'];
    $lastPeriodEnd = end($periods)['end'];

    $dateOnly = date('Y-m-d', strtotime($dateStr));
    
    // Create timestamps for Day Bounds
    $schedStartTs = strtotime("$dateOnly $firstPeriodStart");
    $schedEndTs = strtotime("$dateOnly $lastPeriodEnd");
    
    $tInTs = strtotime("$dateOnly " . date('H:i:s', strtotime($timeInStr)));
    $tOutTs = strtotime("$dateOnly " . date('H:i:s', strtotime($timeOutStr)));

    // 1. Determine Effective Global Start (Apply Lateness Rule)
    $calcStartTs = $tInTs;
    if ($tInTs < $schedStartTs) {
        // Early In: Clamp to First Schedule Start
        $calcStartTs = $schedStartTs;
    } elseif ($tInTs > $schedStartTs) {
        // Late In: Round UP to next full hour
        $checkH = (int)date('H', $tInTs);
        $checkM = (int)date('i', $tInTs);
        $checkS = (int)date('s', $tInTs);
        
        if ($checkM == 0 && $checkS == 0) {
            $calcStartTs = $tInTs;
        } else {
            // Round up to next hour
            $calcStartTs = mktime($checkH + 1, 0, 0, date('n', $tInTs), date('j', $tInTs), date('Y', $tInTs));
        }
    }

    // 2. Determine Effective Global End (Apply Early Out / Overtime Rule)
    $calcEndTs = $tOutTs;
    if ($tOutTs > $schedEndTs) {
        // Late Out: Clamp to Last Schedule End
        $calcEndTs = $schedEndTs;
    }

    // 3. Sum Intersections with Each Period
    $totalSeconds = 0;
    
    // If effective start is after effective end (e.g. extremely late), return 0
    if ($calcStartTs >= $calcEndTs) {
        return 0;
    }

    foreach ($periods as $period) {
        $pStartTs = strtotime("$dateOnly " . $period['start']);
        $pEndTs = strtotime("$dateOnly " . $period['end']);
        
        // Calculate Intersection [calcStart, calcEnd] INT [pStart, pEnd]
        $intStart = max($calcStartTs, $pStartTs);
        $intEnd = min($calcEndTs, $pEndTs);
        
        $duration = $intEnd - $intStart;
        if ($duration > 0) {
            $totalSeconds += $duration;
        }
    }

    // Apply Break Deduction for Admin and Non-Teaching Personnel
    // 5 hours = 300 minutes
    $totalMinutes = $totalSeconds / 60;
    


    // Apply Break Deduction for Admin and Non-Teaching Personnel
    // Fetch setting from DB (cached static)
    static $deductionMinutes = null;
    global $conn; // Ensure connection is available

    if ($deductionMinutes === null) {
        if (isset($conn)) {
             $result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'break_deduction_minutes'");
             if ($result && $row = $result->fetch_assoc()) {
                 $deductionMinutes = (int)$row['setting_value'];
             } else {
                 $deductionMinutes = 60; // Default if not found
             }
        } else {
             $deductionMinutes = 60; // Default fallback
        }
    }

    $role = strtolower($employeeRole);
    if (strpos($role, 'admin') !== false || strpos($role, 'non-teaching') !== false || strpos($role, 'non_teaching') !== false) {
        // If worked > 5 hours (300 mins), apply deduction
        if ($totalMinutes >= 300) {
            $totalMinutes = max(0, $totalMinutes - $deductionMinutes);
        }
    }

    return round($totalMinutes, 2);
}

