<?php
require 'db_connection.php';

// Fetch all "A Faculty Member..." notifications
$res = $conn->query("SELECT id, employee_id, message FROM notifications WHERE type = 'makeup_request' AND message LIKE 'A Faculty Member%'");

while ($row = $res->fetch_assoc()) {
    $empStmt = $conn->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
    $empStmt->bind_param("i", $row['employee_id']);
    $empStmt->execute();
    $empData = $empStmt->get_result()->fetch_assoc();
    
    if ($empData) {
        $emp_name = trim(($empData['first_name'] ?? '') . ' ' . ($empData['last_name'] ?? ''));
        if ($emp_name) {
            // Replace "A Faculty Member" with the actual name
            $newMsg = str_replace("A Faculty Member", $emp_name, $row['message']);
            $upd = $conn->prepare("UPDATE notifications SET message = ? WHERE id = ?");
            $upd->bind_param("si", $newMsg, $row['id']);
            $upd->execute();
            echo "Updated notif {$row['id']} to {$newMsg}\n";
        }
    }
}
echo "Done";
?>
