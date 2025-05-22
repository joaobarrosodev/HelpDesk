<?php
/**
 * Database Logger
 * This file provides functions to log database operations
 */

function db_log($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logData = "[$timestamp] $message";
    
    if ($data !== null) {
        $logData .= " - " . json_encode($data);
    }
    
    file_put_contents(
        __DIR__ . '/db-operations.log',
        $logData . PHP_EOL,
        FILE_APPEND
    );
}