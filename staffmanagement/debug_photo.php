<?php
// Debug profile photo value
echo "<div style='background:white; color:red; padding:10px; z-index:9999; position:fixed; top:0; left:50%;'>";
echo "ID: " . htmlspecialchars($employee_id) . "<br>";
echo "DB Photo RAW: [" . ($employee['profile_photo'] ?? 'NULL') . "]<br>";
echo "Calculated Path: [" . $profilePhoto . "]<br>";
echo "SidebarCurrentUser Photo: [" . ($currentUser['profile_photo'] ?? 'NULL') . "]<br>";
echo "</div>";
?>
