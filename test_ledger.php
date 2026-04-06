<?php
require 'db_connection.php';
$res = $conn->query("SELECT * FROM time_bank_ledger");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "Total Rows: " . $res->num_rows . "\n";
echo "Error if any: " . $conn->error . "\n";

// check offset requests
$res_off = $conn->query("SELECT * FROM offset_schedule_requests");
while ($row = $res_off->fetch_assoc()) {
    print_r($row);
}
?>
