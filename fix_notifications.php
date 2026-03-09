<?php
require 'c:\inetpub\wwwroot\EndDev\db_connection.php';

// Update all notifications sent to employees about schedule changes
$stmt = $conn->prepare("SELECT n.id, n.employee_id as target_user_id, e.roles, e.employee_id as string_id FROM notifications n JOIN employees e ON n.employee_id = e.id WHERE n.type = 'schedule_change' AND n.target = 'employee'");
$stmt->execute();
$result = $stmt->get_result();

$updated = 0;
while ($row = $result->fetch_assoc()) {
    if (stripos(strtolower($row['roles']), 'admin') !== false) {
        // staff_profile.php expects the custom string 'employee_id' (e.g. 111111 or EMP-01) not the DB auto-increment ID
        $link = "/staffmanagement/staff_profile.php?id=" . $row['string_id'];
    } else {
        $link = "#";
    }
    
    $updateStmt = $conn->prepare("UPDATE notifications SET link = ? WHERE id = ?");
    $updateStmt->bind_param("si", $link, $row['id']);
    if ($updateStmt->execute()) {
        $updated++;
    }
}

echo "Successfully scrubbed and updated $updated historical schedule change notifications.";
?>
