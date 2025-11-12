<?php
require_once '../db_connection.php';

// Get parameters
$employeeId = $_GET['id'] ?? null;
$exportType = $_GET['export'] ?? 'excel';
$month = $_GET['month'] ?? null;
$year = $_GET['year'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

if (!$employeeId) {
    die("Employee ID is required");
}

// Fetch employee data
$stmt = $conn->prepare("SELECT employee_id, first_name, middle_name, last_name, roles, hire_date FROM employees WHERE employee_id = ?");
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Employee not found");
}

$row = $result->fetch_assoc();
$fullName = trim($row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . $row['last_name']);

$employee = [
    'employee_id' => $row['employee_id'],
    'name' => $fullName,
    'role' => $row['roles'] ?? 'N/A',
    'hire_date' => $row['hire_date']
];
$stmt->close();

// Get employee's internal ID
$stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Employee internal ID not found");
}

$empRow = $result->fetch_assoc();
$employeeInternalId = $empRow['id'];
$stmt->close();

// Build attendance query with filters
$query = "SELECT 
            attendance_date, 
            time_in, 
            time_out, 
            scheduled_hours, 
            actual_hours, 
            late_minutes,
            early_departure_minutes,
            overtime_minutes,
            status 
          FROM daily_attendance 
          WHERE employee_id = ?";

$params = [$employeeInternalId];
$types = "i";

// Apply filters
if ($startDate && $endDate) {
    $query .= " AND date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
} elseif ($month && $year) {
    $query .= " AND MONTH(date) = ? AND YEAR(date) = ?";
    $params[] = $month;
    $params[] = $year;
    $types .= "ii";
} elseif ($year) {
    $query .= " AND YEAR(date) = ?";
    $params[] = $year;
    $types .= "i";
}

$query .= " ORDER BY date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$attendanceResult = $stmt->get_result();

$attendanceData = [];
while ($row = $attendanceResult->fetch_assoc()) {
    $attendanceData[] = $row;
}
$stmt->close();

// Export based on type
if ($exportType === 'excel') {
    exportToExcel($employee, $attendanceData);
} elseif ($exportType === 'pdf') {
    exportToPDF($employee, $attendanceData);
}

function exportToExcel($employee, $data) {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="attendance_report_' . $employee['employee_id'] . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    echo 'th { background-color: #103932; color: white; }';
    echo '.header { background-color: #f8f9fa; padding: 20px; margin-bottom: 20px; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Header information
    echo '<div class="header">';
    echo '<h2>Individual Attendance Report</h2>';
    echo '<p><strong>Employee ID:</strong> ' . htmlspecialchars($employee['employee_id']) . '</p>';
    echo '<p><strong>Name:</strong> ' . htmlspecialchars($employee['name']) . '</p>';
    echo '<p><strong>Role:</strong> ' . htmlspecialchars($employee['role']) . '</p>';
    echo '<p><strong>Report Generated:</strong> ' . date('F d, Y h:i A') . '</p>';
    echo '</div>';
    
    // Table
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Date</th>';
    echo '<th>Time In</th>';
    echo '<th>Time Out</th>';
    echo '<th>Scheduled Hours</th>';
    echo '<th>Total Hours</th>';
    echo '<th>Status</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    if (count($data) > 0) {
        foreach ($data as $row) {
            // Determine status label
            $status = strtolower(trim($row['status']));
            $statusLabel = 'Unknown';
            
            if ($status === 'complete') {
                $statusLabel = 'Present';
            } elseif ($status === 'incomplete') {
                $statusLabel = 'Incomplete';
            } elseif ($status === 'absent') {
                $statusLabel = 'Absent';
            }
            
            // Convert minutes to hours
            $scheduledHours = $row['scheduled_hours'] ? round($row['scheduled_hours'] / 60, 1) . ' hrs' : '-';
            $actualHours = $row['actual_hours'] ? round($row['actual_hours'] / 60, 1) . ' hrs' : '-';
            
            echo '<tr>';
            echo '<td>' . date('F d, Y', strtotime($row['attendance_date'])) . '</td>';
            echo '<td>' . ($row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-') . '</td>';
            echo '<td>' . ($row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-') . '</td>';
            echo '<td>' . $scheduledHours . '</td>';
            echo '<td>' . $actualHours . '</td>';
            echo '<td>' . htmlspecialchars($statusLabel) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6" style="text-align: center;">No attendance records found</td></tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

function exportToPDF($employee, $data) {
    // For PDF, we'll use HTML that can be converted to PDF
    // You can integrate libraries like TCPDF or mPDF for better PDF generation
    
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Attendance Report</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo 'table { border-collapse: collapse; width: 100%; margin-top: 20px; }';
    echo 'th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }';
    echo 'th { background-color: #103932; color: white; }';
    echo '.header { background-color: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 8px; }';
    echo '.badge-present { background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; }';
    echo '.badge-absent { background-color: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; }';
    echo '.badge-incomplete { background-color: #fd7e14; color: white; padding: 4px 8px; border-radius: 4px; }';
    echo '@media print { button { display: none; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Print button
    echo '<button onclick="window.print()" style="padding: 10px 20px; background-color: #103932; color: white; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 20px;">Print PDF</button>';
    
    // Header
    echo '<div class="header">';
    echo '<h1>Individual Attendance Report</h1>';
    echo '<p><strong>Employee ID:</strong> ' . htmlspecialchars($employee['employee_id']) . '</p>';
    echo '<p><strong>Name:</strong> ' . htmlspecialchars($employee['name']) . '</p>';
    echo '<p><strong>Role:</strong> ' . htmlspecialchars($employee['role']) . '</p>';
    echo '<p><strong>Report Generated:</strong> ' . date('F d, Y h:i A') . '</p>';
    echo '</div>';
    
    // Table
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Date</th>';
    echo '<th>Time In</th>';
    echo '<th>Time Out</th>';
    echo '<th>Scheduled Hours</th>';
    echo '<th>Total Hours</th>';
    echo '<th>Status</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    if (count($data) > 0) {
        foreach ($data as $row) {
            // Determine status and badge class
            $status = strtolower(trim($row['status']));
            $badgeClass = 'badge-present';
            $statusLabel = 'Unknown';
            
            if ($status === 'complete') {
                $badgeClass = 'badge-present';
                $statusLabel = 'Present';
            } elseif ($status === 'incomplete') {
                $badgeClass = 'badge-incomplete';
                $statusLabel = 'Incomplete';
            } elseif ($status === 'absent') {
                $badgeClass = 'badge-absent';
                $statusLabel = 'Absent';
            }
            
            // Convert minutes to hours
            $scheduledHours = $row['scheduled_hours'] ? round($row['scheduled_hours'] / 60, 1) . ' hrs' : '-';
            $actualHours = $row['actual_hours'] ? round($row['actual_hours'] / 60, 1) . ' hrs' : '-';
            
            echo '<tr>';
            echo '<td>' . date('F d, Y', strtotime($row['attendance_date'])) . '</td>';
            echo '<td>' . ($row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-') . '</td>';
            echo '<td>' . ($row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-') . '</td>';
            echo '<td>' . $scheduledHours . '</td>';
            echo '<td>' . $actualHours . '</td>';
            echo '<td><span class="' . $badgeClass . '">' . htmlspecialchars($statusLabel) . '</span></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6" style="text-align: center;">No attendance records found</td></tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '<script>';
    echo 'setTimeout(function() { window.print(); }, 500);';
    echo '</script>';
    
    echo '</body>';
    echo '</html>';
    exit;
}
?>
