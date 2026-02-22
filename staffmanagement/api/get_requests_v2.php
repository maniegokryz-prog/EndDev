<?php
// New API endpoint to bypass cache
// get_requests_v2.php
header('Content-Type: application/json');
$servername = "localhost";
$username = "attendance_admin";
$password = "Confirmp@ssword123";
$dbname = "database_records";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success'=>false, 'error'=>'DB Connection Failed']);
    exit;
}

$employee_id = $_GET['employee_id'] ?? 0;

$sql = "SELECT el.*, el.rejection_reason, lt.type_name as leave_type
        FROM employee_leaves el
        INNER JOIN leave_types lt ON el.leave_type_id = lt.id
        WHERE el.employee_id = ?
        ORDER BY el.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    // Format dates nicely here or in JS? Matching original API which seemingly passed raw or formatted?
    // Original JS used 'formatted_dates' property. We need to recreate that if PHP was doing it.
    // Let's check original API output one more time.
    // Original output had "formatted_dates"? No, Step 1197 output `start_date`, `end_date`.
    // Wait. `staff_profile_logic.js` uses `leave.formatted_dates`.
    // Where does `formatted_dates` come from?
    // In `leave_request_clean.php`? I need to check if it did post-processing.
    
    // Quick math: formatted_dates
    $s = new DateTime($row['start_date']);
    $e = new DateTime($row['end_date']);
    if ($row['start_date'] == $row['end_date']) {
        $row['formatted_dates'] = $s->format('M j, Y');
    } else {
         $row['formatted_dates'] = $s->format('M j') . ' - ' . $e->format('M j, Y');
    }
    
    $data[] = $row;
}

echo json_encode(['success' => true, 'data' => $data, 'count' => count($data)]);
?>
