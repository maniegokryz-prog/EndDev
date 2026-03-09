<?php
require_once 'db_connection.php';

echo "<h2>Repairing Notification Links</h2>";

// 1. Fix links that are exactly '/staffmanagement/staffinfo.php' (missing ID)
$sql1 = "UPDATE notifications n 
         INNER JOIN employees e ON n.employee_id = e.id 
         SET n.link = CONCAT('/staffmanagement/staffinfo.php?id=', e.employee_id) 
         WHERE n.link = '/staffmanagement/staffinfo.php' 
         OR n.link = '../staffmanagement/staffinfo.php'";

if ($conn->query($sql1)) {
    echo "Fixed " . $conn->affected_rows . " broken links (missing ID).<br>";
} else {
    echo "Error fixing broken links: " . $conn->error . "<br>";
}

// 2. Fix NULL links for leave_request, leave_approved, leave_rejected types
$sql2 = "UPDATE notifications n 
         INNER JOIN employees e ON n.employee_id = e.id 
         SET n.link = CONCAT('/staffmanagement/staffinfo.php?id=', e.employee_id) 
         WHERE (n.link IS NULL OR n.link = '') 
         AND n.type IN ('leave_request', 'leave_approved', 'leave_rejected', 'admin_request')";

if ($conn->query($sql2)) {
    echo "Fixed " . $conn->affected_rows . " NULL links.<br>";
} else {
    echo "Error fixing NULL links: " . $conn->error . "<br>";
}

echo "Done.";
?>
