<?php
// api/settings_api.php
// Ensure no output before JSON
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Define global flag to prevent db_connection from setting standard headers/error handling if needed
$GLOBALS['error_reporting_configured'] = true;

try {
    require_once __DIR__ . '/../../db_connection.php';

    // Ensure session is started (handled by db_connection.php mostly, but verify)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Clean buffer just in case include caused output
    ob_clean();

    if ($action === 'get_leave_settings') {
        $sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'leave_notice_period_days'";
        $result = $conn->query($sql);
        
        $days = 0;
        if ($result && $row = $result->fetch_assoc()) {
            $days = (int)$row['setting_value'];
        }
        
        echo json_encode(['success' => true, 'notice_period_days' => $days]);
        exit;
    }

    if ($action === 'update_leave_settings') {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $days = $_POST['notice_period_days'] ?? 0;
        $days = (int)$days;
        if ($days < 0) $days = 0;
        
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'leave_notice_period_days'");
        $stmt->bind_param("s", $days);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Settings updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Update failed: ' . $conn->error]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
