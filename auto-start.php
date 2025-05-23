<?php
// Check if WebSocket server is running
function isWebSocketRunning() {
    $connection = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 1);
    if ($connection) {
        fclose($connection);
        return true;
    }
    return false;
}

// Try to start WebSocket server if not running
if (!isWebSocketRunning()) {
    $lockFile = __DIR__ . '/temp/ws_autostart.lock';
    $tempDir = __DIR__ . '/temp';
    
    // Create temp directory if it doesn't exist
    if (!file_exists($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }
    
    // Check if another process is already trying to start the server
    if (file_exists($lockFile)) {
        $lockTime = filemtime($lockFile);
        // If lock file is older than 60 seconds, remove it
        if (time() - $lockTime > 60) {
            @unlink($lockFile);
        } else {
            // Another process is handling it
            return;
        }
    }
    
    // Create lock file
    file_put_contents($lockFile, time());
    
    // Try to start the WebSocket server
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows
        $command = 'start /B php ' . escapeshellarg(__DIR__ . '/ws-server.php') . ' > ' . 
                   escapeshellarg(__DIR__ . '/logs/websocket_' . date('Y-m-d') . '.log') . ' 2>&1';
        pclose(popen($command, 'r'));
    } else {
        // Linux/Mac
        $command = 'nohup php ' . escapeshellarg(__DIR__ . '/ws-server.php') . ' > ' . 
                   escapeshellarg(__DIR__ . '/logs/websocket_' . date('Y-m-d') . '.log') . ' 2>&1 &';
        exec($command);
    }
    
    // Wait a bit for server to start
    sleep(2);
    
    // Remove lock file
    @unlink($lockFile);
    
    // Log the start attempt
    $logFile = __DIR__ . '/ws-autostart.log';
    $logMessage = date('[Y-m-d H:i:s]') . " - WebSocket server start attempted\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
?>