<?php
require 'db_connection.php';

echo "=== employee_schedules table structure ===\n";
$result = $conn->query('SHOW COLUMNS FROM employee_schedules');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}

echo "\n=== Recent employee_schedules records ===\n";
$result = $conn->query("SELECT COUNT(*) as count FROM employee_schedules WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$count = $result->fetch_assoc()['count'];
echo "Count (last 24 hours): $count\n";

if ($count > 0) {
    $result = $conn->query("SELECT es.id, e.employee_id, s.schedule_name, es.created_at FROM employee_schedules es JOIN employees e ON es.employee_id=e.id JOIN schedules s ON es.schedule_id=s.id WHERE es.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 3");
    while($row = $result->fetch_assoc()) {
        echo "  - ID: {$row['id']}, Employee: {$row['employee_id']}, Schedule: {$row['schedule_name']}, Created: {$row['created_at']}\n";
    }
}
?>
