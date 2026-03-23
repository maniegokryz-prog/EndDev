<?php
require_once '../db_connection.php';
require_once 'dtr_utils.php';

$employeeId = $_GET['id'] ?? '121212'; // Default to Lord D Castro

echo "<h1>Debug Schedule for Employee ID: $employeeId</h1>";

// Get Internal ID
$stmt = $conn->prepare("SELECT id, first_name, last_name, roles FROM employees WHERE employee_id = ?");
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $internalId = $row['id'];
    echo "Found Employee: " . $row['first_name'] . " " . $row['last_name'] . " (Internal ID: $internalId)<br>";
    
    // Fetch Schedule
    $schedule = getEmployeeSchedule($conn, $internalId);
    
    echo "<h2>Schedule Dump</h2>";
    echo "<pre>";
    print_r($schedule);
    echo "</pre>";

    echo "<h2>Test Date: 2026-02-16 (Monday)</h2>";
    $date = '2026-02-16';
    $phpDow = date('w', strtotime($date));
    $dbDow = ($phpDow == 0) ? 6 : $phpDow - 1;
    echo "PHP DOW: $phpDow<br>";
    echo "DB DOW (Calc): $dbDow<br>";
    
    if (isset($schedule[$dbDow])) {
        echo "Schedule FOUND for Monday.<br>";
        
        // Test Calc
        $tIn = '08:00:00';
        $tOut = '15:00:00'; // 3:00 PM
        echo "Test Calc: In $tIn, Out $tOut<br>";
        $hours = calculateActualHoursWithClamping($tIn, $tOut, $schedule, $date, $row['roles'], null, null);
        echo "Calculated Hours: $hours (Expected 3.5 if clamped to 3.5h schedule)<br>";
    } else {
        echo "Schedule NOT FOUND for Monday.<br>";
    }

} else {
    echo "Employee not found.";
}
?>
