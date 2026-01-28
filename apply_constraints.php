<?php
require_once 'db_connection.php';

$sqlFile = 'add_unique_constraints.sql';
if (!file_exists($sqlFile)) {
    die("Error: SQL file not found.");
}

$sqlContent = file_get_contents($sqlFile);
// Split by semicolon to run multiple queries, avoiding empty lines
$queries = explode(';', $sqlContent);

echo "Running SQL updates from $sqlFile...\n";

foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        // Skip SHOW statements
        if (stripos($query, 'SHOW INDEX') === 0)
            continue;

        echo "Executing: " . substr($query, 0, 50) . "...\n";
        if ($conn->query($query)) {
            echo "SUCCESS.\n";
        } else {
            // Ignore "Duplicate key" errors if they happen
            if ($conn->errno == 1061 || $conn->errno == 1062) {
                echo "SKIPPED (Already exists).\n";
            } else {
                echo "ERROR: " . $conn->error . "\n";
            }
        }
    }
}
echo "Done.\n";
$conn->close();
?>