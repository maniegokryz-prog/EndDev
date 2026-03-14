<?php
require_once 'db_connection.php';

$sql = "SHOW CREATE TABLE schedule_requests";
$result = $conn->query($sql);
if ($result) {
    print_r($result->fetch_assoc());
} else {
    echo "Error: " . $conn->error;
}
?>
