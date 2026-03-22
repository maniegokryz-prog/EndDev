<?php
require 'db_connection.php';
$res = $conn->query('SELECT employee_id, first_name, last_name, roles FROM employees LIMIT 20');
while($row = $res->fetch_assoc()) {
    echo $row['employee_id'] . ': ' . $row['first_name'] . ' ' . $row['last_name'] . ' -> ' . $row['roles'] . PHP_EOL;
}
?>
