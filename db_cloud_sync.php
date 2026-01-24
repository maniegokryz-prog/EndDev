<?php
/**
 * Cloud Database Synchronization Module
 * This file handles syncing local database changes to IONOS cloud database
 * 
 * Usage:
 * 1. Include this file: require_once 'db_cloud_sync.php';
 * 2. Call syncToCloud($table, $data, $action) after any INSERT/UPDATE/DELETE
 * 3. Or use CloudSync::sync() method
 */

class CloudSync {
    private static $cloudConn = null;
    private static $syncEnabled = true;
    private static $logFile = null;
    
    /**
     * Cloud database configuration
     * UPDATE THESE WITH YOUR IONOS DATABASE CREDENTIALS
     * 
     * METHOD 1: Direct Connection (only works when deployed to IONOS)
     * Set 'method' => 'direct' and use localhost
     * 
     * METHOD 2: REST API (works from localhost)
     * Set 'method' => 'api' and configure your API endpoint
     */
    private static $cloudConfig = [
        'method' => 'api',  // 'direct' or 'api'
        
        // Direct connection (when on IONOS server)
        'host' => 'localhost',
        'username' => 'dbu58088',
        'password' => 'Confirmp@ssword123',
        'database' => 'dbs14970485',
        'port' => 3306,
        
        // API connection (works from anywhere)
        'api_url' => 'http://bpcfaceid.com/api/sync_endpoint.php',  // Change this!
        'api_key' => 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo'     // Must match sync_endpoint.php
    ];
    
    /**
     * Get log file path
     */
    private static function getLogFile() {
        if (self::$logFile === null) {
            self::$logFile = __DIR__ . '/logs/cloud_sync.log';
        }
        return self::$logFile;
    }
    
    /**
     * Connect to cloud database
     */
    private static function connectCloud() {
        if (self::$cloudConn !== null) {
            return self::$cloudConn;
        }
        
        // If using API method, return a dummy connection (API calls don't need MySQL connection)
        if (self::$cloudConfig['method'] === 'api') {
            self::$cloudConn = 'API_MODE'; // Special marker for API mode
            self::log("ℹ️ Using API sync mode");
            return self::$cloudConn;
        }
        
        // Direct connection method
        $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost', 
            ['localhost', '127.0.0.1', '::1', 'localhost:80']);
        
        if ($isLocalhost) {
            self::log("ℹ️ Running on localhost - Direct connection only works when deployed to IONOS. Use 'api' method for localhost sync.");
            return null;
        }
        
        try {
            self::$cloudConn = new mysqli(
                self::$cloudConfig['host'],
                self::$cloudConfig['username'],
                self::$cloudConfig['password'],
                self::$cloudConfig['database'],
                self::$cloudConfig['port']
            );
            
            if (self::$cloudConn->connect_error) {
                throw new Exception("Cloud DB connection failed: " . self::$cloudConn->connect_error);
            }
            
            self::$cloudConn->set_charset("utf8mb4");
            self::log("✅ Connected to cloud database (direct)");
            return self::$cloudConn;
            
        } catch (Exception $e) {
            self::log("❌ Cloud connection error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Sync data to cloud database
     * 
     * @param string $table Table name
     * @param array $data Data to sync (associative array)
     * @param string $action 'insert', 'update', or 'delete'
     * @param string $whereCondition WHERE clause for update/delete (e.g., "id = 5")
     * @return bool Success status
     */
    public static function sync($table, $data, $action = 'insert', $whereCondition = '') {
        if (!self::$syncEnabled) {
            return true; // Sync disabled
        }
        
        $cloud = self::connectCloud();
        if ($cloud === null) {
            self::log("⚠️ Sync skipped - cloud connection failed");
            return false;
        }
        
        try {
            switch (strtolower($action)) {
                case 'insert':
                    return self::insertToCloud($cloud, $table, $data);
                    
                case 'update':
                    return self::updateToCloud($cloud, $table, $data, $whereCondition);
                    
                case 'delete':
                    return self::deleteFromCloud($cloud, $table, $whereCondition);
                    
                default:
                    self::log("❌ Invalid action: $action");
                    return false;
            }
        } catch (Exception $e) {
            self::log("❌ Sync error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert data to cloud
     */
    private static function insertToCloud($cloud, $table, $data) {
        // API mode
        if ($cloud === 'API_MODE') {
            return self::apiRequest('insert', $table, $data);
        }
        
        // Direct connection mode
        $columns = array_keys($data);
        $values = array_values($data);
        
        $columnNames = implode(', ', array_map(function($col) {
            return "`$col`";
        }, $columns));
        
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        
        $sql = "INSERT INTO `$table` ($columnNames) VALUES ($placeholders)";
        $stmt = $cloud->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $cloud->error);
        }
        
        $types = self::getBindTypes($values);
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            self::log("✅ INSERT to cloud: $table");
            return true;
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    }
    
    /**
     * Update data in cloud
     */
    private static function updateToCloud($cloud, $table, $data, $whereCondition) {
        if (empty($whereCondition)) {
            throw new Exception("WHERE condition required for UPDATE");
        }
        
        // API mode
        if ($cloud === 'API_MODE') {
            return self::apiRequest('update', $table, $data, $whereCondition);
        }
        
        // Direct connection mode
        $setClauses = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $setClauses[] = "`$column` = ?";
            $values[] = $value;
        }
        
        $setString = implode(', ', $setClauses);
        $sql = "UPDATE `$table` SET $setString WHERE $whereCondition";
        
        $stmt = $cloud->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $cloud->error);
        }
        
        $types = self::getBindTypes($values);
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            self::log("✅ UPDATE to cloud: $table WHERE $whereCondition");
            return true;
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    }
    
    /**
     * Delete data from cloud
     */
    private static function deleteFromCloud($cloud, $table, $whereCondition) {
        if (empty($whereCondition)) {
            throw new Exception("WHERE condition required for DELETE");
        }
        
        // API mode
        if ($cloud === 'API_MODE') {
            return self::apiRequest('delete', $table, [], $whereCondition);
        }
        
        // Direct connection mode
        $sql = "DELETE FROM `$table` WHERE $whereCondition";
        
        if ($cloud->query($sql)) {
            self::log("✅ DELETE from cloud: $table WHERE $whereCondition");
            return true;
        } else {
            throw new Exception("Delete failed: " . $cloud->error);
        }
    }
    
    /**
     * Get bind parameter types for prepared statement
     */
    private static function getBindTypes($values) {
        $types = '';
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }
    
    /**
     * Make API request to cloud endpoint
     */
    private static function apiRequest($action, $table, $data = [], $whereCondition = '') {
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            self::log("⚠️ cURL not available - skipping cloud sync");
            return true; // Return true to not block the operation
        }
        
        $apiUrl = self::$cloudConfig['api_url'];
        $apiKey = self::$cloudConfig['api_key'];
        
        if (empty($apiUrl) || empty($apiKey)) {
            self::log("❌ API sync not configured. Set api_url and api_key in cloudConfig");
            return false;
        }
        
        $postData = [
            'action' => $action,
            'table' => $table,
            'data' => json_encode($data),
            'where' => $whereCondition
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_HTTPHEADER => [
                'X-API-KEY: ' . $apiKey,
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_TIMEOUT => 5,  // Reduced from 30 to 5 seconds
            CURLOPT_CONNECTTIMEOUT => 3,  // Connection timeout
            CURLOPT_SSL_VERIFYPEER => false,  // Disable SSL verification for localhost testing
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            self::log("❌ API request failed: $error");
            return false;
        }
        
        if ($httpCode !== 200) {
            self::log("❌ API returned HTTP $httpCode: $response");
            return false;
        }
        
        $result = json_decode($response, true);
        
        if ($result && isset($result['success']) && $result['success']) {
            self::log("✅ API sync $action to $table");
            return true;
        } else {
            $errorMsg = $result['error'] ?? 'Unknown error';
            self::log("❌ API sync failed: $errorMsg");
            return false;
        }
    }
    
    /**
     * Enable/disable sync
     */
    public static function setSyncEnabled($enabled) {
        self::$syncEnabled = $enabled;
        self::log($enabled ? "✅ Cloud sync enabled" : "⚠️ Cloud sync disabled");
    }
    
    /**
     * Test cloud connection
     */
    public static function testConnection() {
        $cloud = self::connectCloud();
        if ($cloud) {
            self::log("✅ Cloud connection test successful");
            return true;
        } else {
            self::log("❌ Cloud connection test failed");
            return false;
        }
    }
    
    /**
     * Log sync activity
     */
    private static function log($message) {
        $logFile = self::getLogFile();
        $logDir = dirname($logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Get sync log
     */
    public static function getLog($lines = 50) {
        $logFile = self::getLogFile();
        if (!file_exists($logFile)) {
            return "No sync log found.";
        }
        
        $logContent = file($logFile);
        return implode('', array_slice($logContent, -$lines));
    }
    
    /**
     * Close cloud connection
     */
    public static function closeConnection() {
        if (self::$cloudConn !== null) {
            self::$cloudConn->close();
            self::$cloudConn = null;
            self::log("🔒 Cloud connection closed");
        }
    }
}

/**
 * Helper function for quick sync
 */
function syncToCloud($table, $data, $action = 'insert', $whereCondition = '') {
    return CloudSync::sync($table, $data, $action, $whereCondition);
}

/**
 * Helper function for syncing with ID lookup
 * Used for tables that have foreign key references that need mapping
 */
function syncToCloudWithLookup($table, $data) {
    // Simple logging setup
    $logFile = __DIR__ . '/logs/cloud_sync.log';
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    // Check if cURL is available
    if (!function_exists('curl_init')) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ⚠️ cURL not available - skipping cloud sync with lookup\n", FILE_APPEND);
        return true; // Return true to not block the operation
    }
    
    $apiUrl = 'http://bpcfaceid.com/api/sync_endpoint.php';
    $apiKey = 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo';
    
    // Simple logging
    $logFile = __DIR__ . '/logs/cloud_sync.log';
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    try {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-API-KEY: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'action' => 'sync_with_lookup',
            'table' => $table,
            'data' => json_encode($data)
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Lookup sync error for $table: $error\n", FILE_APPEND);
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        if ($httpCode === 200 && isset($result['success']) && $result['success']) {
            $msg = $result['message'] ?? 'OK';
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ✅ Lookup sync success for $table: $msg\n", FILE_APPEND);
            return true;
        } else {
            $error = $result['error'] ?? 'Unknown error';
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Lookup sync failed for $table: $error\n", FILE_APPEND);
            return false;
        }
        
    } catch (Exception $e) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Exception during lookup sync: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Delete from cloud database with foreign key lookup
 * Used for tables that reference other tables by string IDs
 * 
 * @param string $table Table name (employee_assignments, schedule_periods)
 * @param array $data Lookup data (employee_id_string, schedule_name, day_of_week, start_time, end_time)
 * @return bool Success status
 */
function syncDeleteWithLookup($table, $data) {
    // Simple logging setup
    $logFile = __DIR__ . '/logs/cloud_sync.log';
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    // Check if cURL is available
    if (!function_exists('curl_init')) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ⚠️ cURL not available - skipping cloud delete with lookup\n", FILE_APPEND);
        return true; // Return true to not block the operation
    }
    
    $apiUrl = 'http://bpcfaceid.com/api/sync_endpoint.php';
    $apiKey = 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo';
    
    // Simple logging
    $logFile = __DIR__ . '/logs/cloud_sync.log';
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    try {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-API-KEY: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'action' => 'delete_with_lookup',
            'table' => $table,
            'data' => json_encode($data)
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Delete lookup error for $table: $error\n", FILE_APPEND);
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        if ($httpCode === 200 && isset($result['success']) && $result['success']) {
            $msg = $result['message'] ?? 'OK';
            $affected = $result['affected_rows'] ?? 0;
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ✅ Delete lookup success for $table: $msg ($affected rows)\n", FILE_APPEND);
            return true;
        } else {
            $error = $result['error'] ?? 'Unknown error';
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Delete lookup failed for $table: $error\n", FILE_APPEND);
            return false;
        }
        
    } catch (Exception $e) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Exception during delete lookup: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

// Example usage:
/*
// After inserting an employee
$employeeData = [
    'employee_id' => 'EMP001',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john@example.com'
];
syncToCloud('employees', $employeeData, 'insert');

// After updating an employee
$updateData = ['email' => 'newemail@example.com'];
syncToCloud('employees', $updateData, 'update', "employee_id = 'EMP001'");

// After deleting
syncToCloud('employees', [], 'delete', "employee_id = 'EMP001'");
*/
?>
