<?php
require_once 'db_connection.php';

echo "Checking Schema Constraints...\n\n";

$tables = ['attendance_logs', 'schedule_periods', 'employee_schedules'];
$constraints = [
    'attendance_logs' => 'unique_attendance_log',
    'schedule_periods' => 'unique_schedule_period',
    'employee_schedules' => 'unique_employee_schedule'
];

foreach ($tables as $table) {
    echo "Checking table '$table'...\n";
    $result = $conn->query("SHOW INDEX FROM $table");
    $hasKey = false;
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['Key_name'] === $constraints[$table]) {
                $hasKey = true;
                break;
            }
        }
    }

    if ($hasKey) {
        echo "[MATCH] Constraint '{$constraints[$table]}' exists.\n";
    } else {
        echo "[MISSING] Constraint '{$constraints[$table]}' is MISSING.\n";
    }
}

echo "\nDone.";
?>