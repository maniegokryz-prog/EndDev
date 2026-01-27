<?php
$conn = new mysqli("localhost", "root", "Confirmp@ssword123", "database_records");
if ($conn->connect_error) die("Conn failed");

echo "Checking ID 7:<br>";
$res = $conn->query("SELECT * FROM employees WHERE id = 7");
if($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo "Found: " . json_encode($row);
} else {
    echo "ID 7 NOT FOUND";
}

echo "<br><br>Checking ID 2:<br>";
$res = $conn->query("SELECT * FROM employees WHERE id = 2");
if($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo "Found: " . json_encode($row);
} else {
    echo "ID 2 NOT FOUND";
}
?>
