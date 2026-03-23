<?php
// Test direct connection to Hostinger MySQL
$hHost = '154.197.99.252';
$hUser = 'dbu58088';
$hPass = 'Confirmp@ssword123';
$hDb   = 'dbs14970485';
$hPort = 3306;

$cloud = @new mysqli($hHost, $hUser, $hPass, $hDb, $hPort);
if ($cloud->connect_error) {
    echo "❌ Connection FAILED: " . $cloud->connect_error . "<br>";
    echo "Note: Hostinger may require external MySQL access to be enabled in their control panel.";
} else {
    echo "✅ Connected to Hostinger MySQL!<br><br>";
    
    // Try to upsert a test setting
    $stmt = $cloud->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('test_sync', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $testVal = 'sync_ok_' . date('H:i:s');
    $stmt->bind_param('s', $testVal);
    if ($stmt->execute()) {
        echo "✅ Wrote test_sync = <b>$testVal</b> to Hostinger.<br>";
        echo "Check Hostinger's system_settings table to verify!";
    } else {
        echo "❌ Write failed: " . $stmt->error;
    }
    $cloud->close();
}
?>
