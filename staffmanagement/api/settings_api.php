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
            $days = (int) $row['setting_value'];
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
        $days = (int) $days;
        if ($days < 0)
            $days = 0;

        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'leave_notice_period_days'");
        $stmt->bind_param("s", $days);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Settings updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Update failed: ' . $conn->error]);
        }
        exit;
    }

    if ($action === 'get_break_settings') {
        $sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'break_deduction_minutes'";
        $result = $conn->query($sql);

        $minutes = 60; // Default
        if ($result && $row = $result->fetch_assoc()) {
            $minutes = (int) $row['setting_value'];
        }

        echo json_encode(['success' => true, 'break_deduction_minutes' => $minutes]);
        exit;
    }

    if ($action === 'update_break_settings') {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $minutes = $_POST['break_deduction_minutes'] ?? 60;
        $minutes = (int) $minutes;
        if ($minutes < 0) {
            $minutes = 0; // Ensure non-negative
        }

        // Use ON DUPLICATE KEY UPDATE logic if key might not exist?
        // But init script handled it. Let's stick to update, or better: INSERT ... ON DUPLICATE
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('break_deduction_minutes', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param("s", $minutes);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Break settings updated']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Update failed: ' . $conn->error]);
        }
        exit;
    }

    if ($action === 'get_grace_period') {
        $sql = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('grace_period_minutes', 'deduct_late_time')";
        $result = $conn->query($sql);

        $minutes = 0; // Default
        $deduct = 1; // Default to true (ON)

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['setting_key'] === 'grace_period_minutes') {
                    $minutes = (int) $row['setting_value'];
                } elseif ($row['setting_key'] === 'deduct_late_time') {
                    $deduct = (int) $row['setting_value'];
                }
            }
        }

        echo json_encode(['success' => true, 'grace_period_minutes' => $minutes, 'deduct_late_time' => $deduct]);
        exit;
    }

    if ($action === 'update_grace_period') {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $minutes = $_POST['grace_period_minutes'] ?? 0;
        $minutes = (int) $minutes;
        if ($minutes < 0) {
            $minutes = 0; // Ensure non-negative
        }

        $deduct = isset($_POST['deduct_late_time']) ? (int)$_POST['deduct_late_time'] : 1;

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('grace_period_minutes', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->bind_param("s", $minutes);
            $stmt->execute();

            $stmt2 = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('deduct_late_time', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt2->bind_param("s", $deduct);
            $stmt2->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Grace period updated']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'Update failed: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>