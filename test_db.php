<?php
require 'db_connection.php';
$res = $conn->query("SELECT * FROM system_settings");
while ($r = $res->fetch_assoc()) {
    echo $r['setting_key'] . " = " . $r['setting_value'] . "<br>\n";
}
?>
