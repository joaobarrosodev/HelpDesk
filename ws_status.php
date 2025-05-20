<?php
// This file provides WebSocket server status
session_start();

// Basic security check
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// WebSocket port
define('WS_PORT', 8080);

// Check if WebSocket server is running
function isWebSocketServerRunning() {
    $host = '127.0.0.1';
    $port = WS_PORT;
    
    // Try to open a connection to the port
    $fp = @fsockopen($host, $port, $errno, $errstr, 2);
    $result = $fp !== false;
    if ($fp) {
        fclose($fp);
    }
    
    return $result;
}

// Get server status
$serverRunning = isWebSocketServerRunning();

echo json_encode([
    'running' => $serverRunning,
    'port' => WS_PORT,
    'checked_at' => date('Y-m-d H:i:s')
]);
?>
