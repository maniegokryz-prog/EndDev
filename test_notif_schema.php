<?php
require_once 'db_connection.php';
$res = $conn->query('SHOW COLUMNS FROM notifications');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
