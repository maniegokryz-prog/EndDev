<?php
require '../db_connection.php';

echo "<h2>Session Debug Information</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n\n";
echo "Session Status: " . session_status() . "\n\n";
echo "All Session Variables:\n";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";
echo "<h3>Required for Archive Access:</h3>";
echo "<ul>";
echo "<li>logged_in: " . (isset($_SESSION['logged_in']) ? ($_SESSION['logged_in'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "</li>";
echo "<li>user_type: " . (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'NOT SET') . "</li>";
echo "<li>Is Admin: " . (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin' ? 'YES' : 'NO') . "</li>";
echo "</ul>";

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "<p style='color: red;'><strong>❌ Not logged in - Please log in first</strong></p>";
} elseif ($_SESSION['user_type'] !== 'admin') {
    echo "<p style='color: orange;'><strong>⚠️ Logged in but not an admin - Archive requires admin access</strong></p>";
} else {
    echo "<p style='color: green;'><strong>✅ Access granted - You can access the archive</strong></p>";
}
?>
