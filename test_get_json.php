<?php
require 'c:\inetpub\wwwroot\EndDev\db_connection.php';
$stmt = $conn->query("SELECT * FROM schedule_requests ORDER BY id DESC LIMIT 1");
if ($stmt) {
    echo "Found request:\n";
    $row = $stmt->fetch_assoc();
    print_r($row);
} else {
    echo "Query failed: " . $conn->error;
}
?>
