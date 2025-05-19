<?php
/**
 * Auto-start WebSocket server script
 * This file checks if the WebSocket server is running and attempts to start it if not
 */

// Function to check if WebSocket server is running
function isWebSocketServerRunning() {
    $connection = @fsockopen('localhost', 8080);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    return false;
}

// Try to start the WebSocket server if it's not running
if (!isWebSocketServerRunning()) {
    // Path to the WebSocket server script
    $wsServerScript = __DIR__ . '/ws-server.php';
    
    // Check if we're on Windows or Linux/Unix
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows - use start command to run in background
        $cmd = sprintf(
            'start /B php "%s" > "%s\ws-server-output.log" 2>&1',
            $wsServerScript,
            __DIR__
        );
        
        // Execute the command without waiting
        pclose(popen($cmd, 'r'));
        
        // Log that we tried to start the server
        error_log("Tried to start WebSocket server: $cmd");
    } else {
        // Linux/Unix - use nohup to run in background
        $cmd = sprintf(
            'nohup php "%s" > "%s/ws-server-output.log" 2>&1 &',
            $wsServerScript,
            __DIR__
        );
        
        // Execute the command
        exec($cmd);
        
        // Log that we tried to start the server
        error_log("Tried to start WebSocket server: $cmd");
    }
    
    // Give the server a moment to start
    sleep(1);
}
?>
