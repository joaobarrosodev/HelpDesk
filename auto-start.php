<?php
/**
 * This script checks if the WebSocket server is running and starts it if needed.
 * It should be included at the top of critical pages like detalhes_ticket.php.
 */

function isWebSocketServerRunning() {
    $host = "localhost";
    $port = 8080;
    
    $socket = @fsockopen($host, $port, $errno, $errstr, 1);
    
    if ($socket) {
        fclose($socket);
        return true;
    }
    
    return false;
}

// Check if WebSocket server is running
if (!isWebSocketServerRunning()) {
    // Server not running, try to start it
    $path = dirname(__FILE__);
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        pclose(popen('start /B "HelpDesk WS" cmd /c "cd /D ' . $path . ' && php ws-server.php > ws-server.log 2>&1"', 'r'));
        
        // Give it a moment to start
        sleep(1);
        
        // Create a file indicating we've tried to start the server
        file_put_contents($path . '/ws-server-startup.txt', date('Y-m-d H:i:s') . " - Attempted to start WebSocket server\n", FILE_APPEND);
    } else {
        // Linux/Unix/Mac
        exec('cd ' . $path . ' && nohup php ws-server.php > ws-server.log 2>&1 &');
    }
}
?>
