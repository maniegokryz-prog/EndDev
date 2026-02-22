<?php
/**
 * Cloud Database Synchronization Module (Background Engine Trigger)
 * This file captures legacy synchronous cloud sync requests and routes them 
 * to the new Background Sync Engine by flagging the local records with `sync_status = 0`.
 */

class CloudSync
{
    public static function sync($table, $data, $action = 'insert', $whereCondition = '')
    {
        // If it's an insert, the schema default `sync_status = 0` already handles it.
        if ($action !== 'update') {
            return true;
        }

        global $conn;
        $db = $conn;

        if (!$db) {
            // Include connection script relative to this file to ensure proper credentials (Hostinger vs Local)
            if (file_exists(__DIR__ . '/db_connection.php')) {
                require __DIR__ . '/db_connection.php';
                $db = $conn; // $conn will be set by db_connection.php
            }
        }

        if (empty($whereCondition) || !is_object($db))
            return false;

        $check = $db->query("SHOW COLUMNS FROM `$table` LIKE 'sync_status'");
        if ($check && $check->num_rows > 0) {
            $db->query("UPDATE `$table` SET `sync_status` = 0 WHERE $whereCondition");
        }
        return true;
    }
}

function syncToCloud($table, $data, $action = 'insert', $whereCondition = '')
{
    return CloudSync::sync($table, $data, $action, $whereCondition);
}

function syncToCloudWithLookup($table, $data)
{
    // This was mostly used for inserts, which default to 0 anyway.
    return true;
}

function syncDeleteWithLookup($table, $data)
{
    // Currently delete syncing isn't broadly supported by the dashboard logic.
    return true;
}
?>