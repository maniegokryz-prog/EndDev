<?php
require_once 'db_connection.php';

echo "<h2>Notifications Table Debug</h2>";

$res = $conn->query("SELECT id, type, target, employee_id, deleted_by, actioned_by FROM notifications ORDER BY created_at DESC LIMIT 20");

if (!$res) {
    echo "Error: " . $conn->error;
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Type</th><th>Target</th><th>Emp ID</th><th>Deleted By</th><th>Actioned By</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['type']}</td>";
        echo "<td>{$row['target']}</td>";
        echo "<td>{$row['employee_id']}</td>";
        echo "<td>" . htmlspecialchars($row['deleted_by'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['actioned_by'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>
