<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connection.php';

echo "Testing Reject Logic...<br>";

$requestId = 3; // Using Shem Kyle Erick's pending request ID based on previous screenshots

// Test connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Check if request exists
$stmt = $conn->prepare("SELECT * FROM schedule_requests WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

if (!$request) {
    die("Request ID $requestId not found or already processed.");
}

echo "Request found for Employee ID: " . $request['employee_id'] . "<br>";

// 2. Try the UPDATE query
$updateQuery = "UPDATE schedule_requests SET status = 'rejected', sync_status = 0 WHERE id = ?";
$stmt = $conn->prepare($updateQuery);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $requestId);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

echo "Update successful!<br>";

// 3. Try the Notification query
try {
    $employeeId = $request['employee_id'];
    $message = "Your schedule edit request has been rejected by the admin.";
    
    $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($check_table->num_rows > 0) {
        $stmt = $conn->prepare("SELECT employee_id as string_id, roles FROM employees WHERE id = ?");
        $stmt->bind_param("i", $employeeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        
        $link = "#";
        if ($employee && stripos(strtolower($employee['roles']), 'admin') !== false) {
            $link = "/EndDev/staffmanagement/staff_profile.php?id=" . $employee['string_id'];
        }
        
        $check_column = $conn->query("SHOW COLUMNS FROM notifications LIKE 'link'");
        if ($check_column->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO notifications (employee_id, type, message, link, target, is_read) VALUES (?, 'schedule_change', ?, ?, 'employee', 0)");
            $stmt->bind_param("iss", $employeeId, $message, $link);
        } else {
            $stmt = $conn->prepare("INSERT INTO notifications (employee_id, type, message, target, is_read) VALUES (?, 'schedule_change', ?, 'employee', 0)");
            $stmt->bind_param("is", $employeeId, $message);
        }
        if (!$stmt->execute()) {
             die("Notification Insert failed: " . $stmt->error);
        }
        echo "Notification added successfully.<br>";
    } else {
        echo "Notifications table does not exist.<br>";
    }
} catch (Exception $e) {
    die("Notification Exception: " . $e->getMessage());
}

echo "<br>All tests passed. Rejection logic should work.";
?>
