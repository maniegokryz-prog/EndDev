<?php
// Debug schedule check
header('Content-Type: text/html');
require '../../db_connection.php';

$employee_id = 7; // Hardcoded for 'Justine'
$date = '2026-02-26';

echo "<h3>Debug Schedule for Emp ID $employee_id on $date</h3>";

$dateObj = DateTime::createFromFormat('Y-m-d', $date);
$dayOfWeek = $dateObj->format('w'); // 0 (Sunday) to 6 (Saturday)
$dayOfWeekDb = ($dayOfWeek == 0) ? 6 : ($dayOfWeek - 1);
$dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

echo "Date: $date<br>";
echo "PHP DayOfWeek (w): $dayOfWeek (0=Sun, 6=Sat)<br>";
echo "DB DayOfWeek (calculated): $dayOfWeekDb (" . $dayNames[$dayOfWeekDb] . ")<br>";

// 1. Check if ANY active schedule exists for this employee
echo "<h4>1. Active Employee Schedules:</h4>";
$sql = "SELECT * FROM employee_schedules WHERE employee_id = $employee_id";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()) {
    echo "Sched ID: " . $row['schedule_id'] . " | Include Date? " . ($row['end_date'] ? $row['end_date'] : 'NULL (Forever)') . " | Is Active? " . $row['is_active'] . "<br>";
    
    // Check periods for this schedule
    $subSql = "SELECT * FROM schedule_periods WHERE schedule_id = " . $row['schedule_id'];
    $subRes = $conn->query($subSql);
    echo "<blockquote>Periods:<br>";
    while($p = $subRes->fetch_assoc()) {
        echo "Day: " . $p['day_of_week'] . " Start: " . $p['start_time'] . " End: " . $p['end_time'] . "<br>";
    }
    echo "</blockquote>";
}

// 2. Run the exact query used in get_employee_schedule.php
echo "<h4>2. Exact Query Test:</h4>";
$sql = "SELECT sp.start_time, sp.end_time
        FROM employee_schedules es
        JOIN schedule_periods sp ON es.schedule_id = sp.schedule_id
        WHERE es.employee_id = ? 
          AND es.is_active = 1
          AND sp.day_of_week = ?
          AND (es.end_date IS NULL OR es.end_date >= ?)
        ORDER BY sp.start_time ASC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $employee_id, $dayOfWeekDb, $date);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

if (empty($rows)) {
    echo "<b>Query returned NO rows.</b>";
} else {
    echo "Query returned " . count($rows) . " rows.<br>";
    print_r($rows);
}
?>
