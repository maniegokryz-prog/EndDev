<?php
// HARDCORE DEBUGGING MODE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h3>Debug Trace Started</h3>";

echo "1. Including auth_guard.php...<br>";
try {
    require_once '../auth_guard.php';
    echo "✅ auth_guard.php loaded. Session ID: " . session_id() . "<br>";
} catch (Throwable $e) {
    die("❌ Error loading auth_guard: " . $e->getMessage());
}

echo "2. Including navigation.php...<br>";
try {
    require_once '../navigation.php';
    echo "✅ navigation.php loaded.<br>";
} catch (Throwable $e) {
    die("❌ Error loading navigation: " . $e->getMessage());
}

echo "3. Including db_connection.php...<br>";
try {
    require_once '../db_connection.php';
    echo "✅ db_connection.php loaded.<br>";
    if (isset($conn)) {
         echo "✅ \$conn object exists.<br>";
         if ($conn->connect_error) {
             die("❌ DB Connection Error: " . $conn->connect_error);
         }
         echo "✅ DB Connection Status: Connected to " . $conn->host_info . "<br>";
    } else {
         die("❌ \$conn object is missing after include.<br>");
    }
} catch (Throwable $e) {
    die("❌ Error loading db_connection: " . $e->getMessage());
}

// Get current user info
echo "4. Getting Current User...<br>";
$currentUser = getCurrentUser();
echo "✅ User: " . print_r($currentUser, true) . "<br>";

$id = $_GET['id'] ?? 'MA22013612'; // Default ID for testing if missing
echo "5. Testing Employee Fetch with ID: $id<br>";

// Rest of the script logic (Simplified for debug)
if ($id) {
    echo "Executing Query...<br>";
    $stmt = $conn->prepare("SELECT employee_id, first_name, middle_name, last_name, roles, hire_date, profile_photo FROM employees WHERE employee_id = ?");
    if (!$stmt) die("❌ Prepare Failed: " . $conn->error);
    
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "✅ Employee Found: " . $row['first_name'] . "<br>";
    } else {
        echo "⚠️ Employee NOT Found.<br>";
    }
    $stmt->close();
}

echo "<h3>🎉 Script Reached HTML Section (Success)</h3>";

// Original HTML Include (Commented out to isolate PHP errors first)
// require 'indirep.php'; 
?>
