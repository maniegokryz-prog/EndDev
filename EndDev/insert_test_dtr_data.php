<?php
/**
 * Insert Test Data for DTR Testing
 * Run this once to populate daily_attendance with test data
 */

require 'db_connection.php';

echo "<!DOCTYPE html><html><head><title>Insert Test DTR Data</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "</head><body class='p-5'>";
echo "<div class='container'><h2>Insert Test DTR Data</h2>";

// Check if we already have data
$check = $conn->query("SELECT COUNT(*) as count FROM daily_attendance");
$row = $check->fetch_assoc();

if ($row['count'] > 0) {
    echo "<div class='alert alert-warning'>⚠️ Database already has {$row['count']} record(s). Clear them first or skip.</div>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='clear' class='btn btn-danger'>Clear Existing Data</button> ";
    echo "<button type='submit' name='insert' class='btn btn-primary'>Insert Anyway</button>";
    echo "</form>";
} else {
    echo "<div class='alert alert-info'>✓ No existing data. Ready to insert test records.</div>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='insert' class='btn btn-success'>Insert Test Data</button>";
    echo "</form>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['clear'])) {
        $conn->query("DELETE FROM daily_attendance");
        echo "<div class='alert alert-success mt-3'>✅ Cleared all records. <a href=''>Refresh page</a></div>";
    }
    
    if (isset($_POST['insert'])) {
        // Get first employee
        $empResult = $conn->query("SELECT id, employee_id FROM employees ORDER BY id LIMIT 1");
        
        if ($empResult && $empResult->num_rows > 0) {
            $employee = $empResult->fetch_assoc();
            $empId = $employee['id'];
            $empCode = $employee['employee_id'];
            
            echo "<div class='alert alert-info'>Using Employee: ID={$empId}, Code={$empCode}</div>";
            
            // Generate 30 days of test data
            $inserted = 0;
            $errors = [];
            
            for ($i = 0; $i < 30; $i++) {
                $date = date('Y-m-d', strtotime("-$i days"));
                
                // Random scenarios
                $scenario = rand(1, 5);
                
                switch ($scenario) {
                    case 1: // On time, complete
                        $timeIn = '08:00:00';
                        $timeOut = '17:00:00';
                        $scheduledHours = 480; // 8 hours in minutes
                        $actualHours = 480;
                        $lateMinutes = 0;
                        $earlyDeparture = 0;
                        $overtime = 0;
                        $status = 'complete';
                        break;
                        
                    case 2: // Late arrival
                        $timeIn = '08:30:00';
                        $timeOut = '17:00:00';
                        $scheduledHours = 480;
                        $actualHours = 450;
                        $lateMinutes = 30;
                        $earlyDeparture = 0;
                        $overtime = 0;
                        $status = 'complete';
                        break;
                        
                    case 3: // Early departure
                        $timeIn = '08:00:00';
                        $timeOut = '16:00:00';
                        $scheduledHours = 480;
                        $actualHours = 420;
                        $lateMinutes = 0;
                        $earlyDeparture = 60;
                        $overtime = 0;
                        $status = 'complete';
                        break;
                        
                    case 4: // Overtime
                        $timeIn = '08:00:00';
                        $timeOut = '18:00:00';
                        $scheduledHours = 480;
                        $actualHours = 540;
                        $lateMinutes = 0;
                        $earlyDeparture = 0;
                        $overtime = 60;
                        $status = 'complete';
                        break;
                        
                    case 5: // Incomplete (no time out)
                        $timeIn = '08:00:00';
                        $timeOut = null;
                        $scheduledHours = 480;
                        $actualHours = 0;
                        $lateMinutes = 0;
                        $earlyDeparture = 0;
                        $overtime = 0;
                        $status = 'incomplete';
                        break;
                }
                
                $sql = "INSERT INTO daily_attendance 
                        (employee_id, attendance_date, time_in, time_out, scheduled_hours, 
                         actual_hours, late_minutes, early_departure_minutes, overtime_minutes, 
                         break_time_minutes, status, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 60, ?, 'Test data')
                        ON DUPLICATE KEY UPDATE
                        time_in = VALUES(time_in),
                        time_out = VALUES(time_out),
                        scheduled_hours = VALUES(scheduled_hours),
                        actual_hours = VALUES(actual_hours),
                        late_minutes = VALUES(late_minutes),
                        early_departure_minutes = VALUES(early_departure_minutes),
                        overtime_minutes = VALUES(overtime_minutes),
                        status = VALUES(status)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('isssddiiis', 
                    $empId, $date, $timeIn, $timeOut, $scheduledHours,
                    $actualHours, $lateMinutes, $earlyDeparture, $overtime, $status
                );
                
                if ($stmt->execute()) {
                    $inserted++;
                } else {
                    $errors[] = "Date $date: " . $stmt->error;
                }
                
                $stmt->close();
            }
            
            echo "<div class='alert alert-success mt-3'>";
            echo "✅ <strong>Successfully inserted {$inserted} test records!</strong><br>";
            echo "Employee ID: {$empId} ({$empCode})<br>";
            echo "Date Range: " . date('Y-m-d', strtotime('-29 days')) . " to " . date('Y-m-d');
            echo "</div>";
            
            if (!empty($errors)) {
                echo "<div class='alert alert-warning mt-3'>";
                echo "<strong>Errors encountered:</strong><ul>";
                foreach ($errors as $error) {
                    echo "<li>$error</li>";
                }
                echo "</ul></div>";
            }
            
            echo "<div class='mt-4'>";
            echo "<h4>Test Data Summary:</h4>";
            echo "<ul class='list-group'>";
            echo "<li class='list-group-item'>Scenario 1 (~6 records): On Time, Complete</li>";
            echo "<li class='list-group-item'>Scenario 2 (~6 records): Late Arrival (30 min)</li>";
            echo "<li class='list-group-item'>Scenario 3 (~6 records): Early Departure (60 min)</li>";
            echo "<li class='list-group-item'>Scenario 4 (~6 records): Overtime (60 min)</li>";
            echo "<li class='list-group-item'>Scenario 5 (~6 records): Incomplete (no time out)</li>";
            echo "</ul>";
            echo "</div>";
            
            echo "<div class='mt-4'>";
            echo "<a href='test_dtr_api.php' class='btn btn-primary'>Test DTR API</a> ";
            echo "<a href='staffmanagement/staffinfo.php?id={$empId}' class='btn btn-success'>View Employee Profile</a> ";
            echo "<a href='' class='btn btn-secondary'>Refresh Page</a>";
            echo "</div>";
            
        } else {
            echo "<div class='alert alert-danger mt-3'>❌ No employees found in database. Please add employees first.</div>";
        }
    }
}

echo "</div></body></html>";

$conn->close();
?>
