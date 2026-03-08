<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "attendance_admin";
$password = "Confirmp@ssword123";

$conn = new mysqli($servername, $username, $password);

$result = $conn->query("SHOW PROCESSLIST");
while ($row = $result->fetch_assoc()) {
    $info = isset($row['Info']) ? $row['Info'] : '';
    if ($info && $row['Command'] != 'Sleep' && $row['Id'] != $conn->thread_id) {
        if (strpos($info, 'ALTER TABLE') !== false || strpos($info, 'CREATE INDEX') !== false || strpos($info, 'SHOW COLUMNS') !== false) {
             echo "KILLing query " . $row['Id'] . "<br>";
             $conn->query("KILL " . $row['Id']);
        }
    }
    print_r($row);
    echo "<br>\n";
}
?>
