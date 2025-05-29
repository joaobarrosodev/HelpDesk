<?php
/**
 * WebSocket Health Check Script
 * 
 * This script checks if the WebSocket server is running and returns a JSON response.
 * Used by the client to determine if the WebSocket connection should be re-established.
 */

// Set content type to JSON
header('Content-Type: application/json');

// Check if silent mode is requested (no logging)
$silent = isset($_GET['silent']) && $_GET['silent'] == '1';

// Server information
$wsHost = 'localhost';
$wsPort = 8080;
$startupThreshold = 60; // Seconds to consider the server as "starting"

// Check for startup flag file
$startupFlagFile = __DIR__ . '/temp/ws-server-starting.flag';
$serverStarting = false;

if (file_exists($startupFlagFile)) {
    $flagTime = @filemtime($startupFlagFile);
    $serverStarting = ($flagTime && (time() - $flagTime < $startupThreshold));
}

// Check if the WebSocket server is running
function isServerRunning($host, $port) {
    $socket = @fsockopen($host, $port, $errno, $errstr, 1);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}

$isRunning = isServerRunning($wsHost, $wsPort);

// Build response
$response = [
    'status' => $isRunning ? 'ok' : ($serverStarting ? 'starting' : 'offline'),
    'timestamp' => date('Y-m-d H:i:s'),
    'host' => $wsHost,
    'port' => $wsPort
];

// Add detailed status for non-silent requests
if (!$silent) {
    $response['details'] = [
        'server_starting' => $serverStarting,
        'startup_flag_file' => file_exists($startupFlagFile),
        'flag_file_age' => $flagTime ? (time() - $flagTime) : null,
        'pid_file_exists' => file_exists(__DIR__ . '/temp/ws-server.pid'),
        'error_log_exists' => file_exists(__DIR__ . '/ws-server.log'),
        'auto_start_enabled' => true
    ];
    
    // Check if the server is in auto-restart mode
    $lockFile = __DIR__ . '/temp/ws_autostart.lock';
    if (file_exists($lockFile)) {
        $response['details']['auto_restart_pending'] = true;
        $response['details']['auto_restart_lock_age'] = time() - @filemtime($lockFile);
    }
    
    // Log this check
    if (!$isRunning && !$serverStarting) {
        @file_put_contents(
            __DIR__ . '/ws-healthcheck.log',
            date('[Y-m-d H:i:s]') . ' WebSocket server is offline. Health check from ' . 
                ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n",
            FILE_APPEND
        );
    }
}

// If server is not running and not in silent mode, attempt to auto-start
if (!$isRunning && !$serverStarting && !$silent) {
    // Include auto-start script to try to restart the server
    include_once __DIR__ . '/auto-start.php';
    
    // Check again after attempted start
    $isRunning = isServerRunning($wsHost, $wsPort);
    $response['status'] = $isRunning ? 'restarted' : 'restart_failed';
    $response['auto_start_attempted'] = true;
}

// Return response
echo json_encode($response);