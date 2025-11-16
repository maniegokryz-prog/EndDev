<?php
/**
 * IONOS API Endpoint - Place this file on your IONOS server
 * File location: https://yourdomain.com/api/sync_endpoint.php
 * 
 * This receives data from localhost and inserts it into IONOS database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow requests from localhost
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simple API key authentication (change this to a secure random key)
define('API_KEY', 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo');

// Check API key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// Connect to IONOS database
require_once '../db_connection.php'; // Adjust path as needed

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$table = $_POST['table'] ?? '';
$data = $_POST['data'] ?? '';
$whereCondition = $_POST['where'] ?? '';

// Decode JSON data
if (is_string($data)) {
    $data = json_decode($data, true);
}

switch ($action) {
    case 'insert':
        syncInsert($conn, $table, $data);
        break;
    
    case 'update':
        syncUpdate($conn, $table, $data, $whereCondition);
        break;
    
    case 'delete':
        syncDelete($conn, $table, $whereCondition);
        break;
    
    case 'batch':
        syncBatch($conn, $_POST['operations'] ?? []);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function syncInsert($conn, $table, $data) {
    if (empty($table) || empty($data)) {
        echo json_encode(['success' => false, 'error' => 'Missing table or data']);
        return;
    }
    
    $columns = array_keys($data);
    $values = array_values($data);
    
    $columnNames = implode(', ', array_map(function($col) {
        return "`$col`";
    }, $columns));
    
    $placeholders = implode(', ', array_fill(0, count($values), '?'));
    
    $sql = "INSERT INTO `$table` ($columnNames) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        return;
    }
    
    $types = getBindTypes($values);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data inserted', 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $stmt->error]);
    }
}

function syncUpdate($conn, $table, $data, $whereCondition) {
    if (empty($table) || empty($data) || empty($whereCondition)) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }
    
    $setClauses = [];
    $values = [];
    
    foreach ($data as $column => $value) {
        $setClauses[] = "`$column` = ?";
        $values[] = $value;
    }
    
    $setString = implode(', ', $setClauses);
    $sql = "UPDATE `$table` SET $setString WHERE $whereCondition";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        return;
    }
    
    $types = getBindTypes($values);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data updated', 'affected_rows' => $stmt->affected_rows]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Execute failed: ' . $stmt->error]);
    }
}

function syncDelete($conn, $table, $whereCondition) {
    if (empty($table) || empty($whereCondition)) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }
    
    $sql = "DELETE FROM `$table` WHERE $whereCondition";
    
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Data deleted', 'affected_rows' => $conn->affected_rows]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Delete failed: ' . $conn->error]);
    }
}

function syncBatch($conn, $operations) {
    $results = [];
    
    foreach ($operations as $op) {
        $table = $op['table'] ?? '';
        $action = $op['action'] ?? '';
        $data = $op['data'] ?? [];
        $where = $op['where'] ?? '';
        
        // Process each operation
        // Add implementation here if needed
    }
    
    echo json_encode(['success' => true, 'results' => $results]);
}

function getBindTypes($values) {
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
?>
