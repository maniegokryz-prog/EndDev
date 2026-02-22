<?php
require_once 'db_connection.php';
$conn->query("UPDATE employees SET sync_status = 0 WHERE id = 1");
echo json_encode(["status" => "done"]);
?>