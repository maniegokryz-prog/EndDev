<?php
require_once 'db_connection.php';

echo "<h2>Top 10 Recent Notifications</h2>";
echo "<table border='1'><tr><th>ID</th><th>Message</th><th>Link</th><th>Created At</th></tr>";

$result = $conn->query("SELECT id, message, link, created_at FROM notifications ORDER BY id DESC LIMIT 10");

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['message']) . "</td>";
    echo "<td>" . htmlspecialchars($row['link'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
