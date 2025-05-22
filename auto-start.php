<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');

// Create temp directory if it doesn't exist
if (!file_exists(TEMP_DIR)) {
    @mkdir(TEMP_DIR, 0777, true);
    @chmod(TEMP_DIR, 0777);
}

// Process any WebSocket messages
$messageFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . 'ws_message_*.json');
if (!empty($messageFiles)) {
    include_once __DIR__ . DIRECTORY_SEPARATOR . 'process_ws_messages.php';
    if (function_exists('processAllMessageFiles')) {
        processAllMessageFiles();
    }
}

// Clean up old files
cleanOldFiles();

// Only attempt auto-start if not in CLI and enabled
if (php_sapi_name() != 'cli') {
    $enableAutoStart = true;  // Set to false to disable auto-start
    
    if ($enableAutoStart && !isWsServerRunning()) {
        attemptServerAutoStart();
    }
}

// Function to check if WebSocket server is running
function isWsServerRunning() {
    $socket = @fsockopen('localhost', 8080, $errno, $errstr, 1);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}

// Function to clean up old files
function cleanOldFiles() {
    $tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'temp';
    $syncFiles = glob($tempDir . DIRECTORY_SEPARATOR . 'sync_*.txt');
    $messageFiles = glob($tempDir . DIRECTORY_SEPARATOR . 'ws_message_*.json');
    $timeLimit = 300; // 5 minutes
    
    $oldFiles = 0;
    
    foreach (array_merge($syncFiles, $messageFiles) as $file) {
        try {
            if (file_exists($file) && is_readable($file)) {
                $fileTime = @filemtime($file);
                if ($fileTime && (time() - $fileTime > $timeLimit)) {
                    @unlink($file);
                    $oldFiles++;
                }
            }
        } catch (Exception $e) {
            error_log("Error cleaning file: " . $e->getMessage());
        }
    }
    
    return $oldFiles;
}

// Function to attempt auto-starting the WebSocket server
function attemptServerAutoStart() {
    $tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'temp';
    $lockFile = $tempDir . DIRECTORY_SEPARATOR . 'ws_autostart.lock';
    
    // Don't attempt to start more than once every 60 seconds
    if (file_exists($lockFile) && (time() - @filemtime($lockFile) < 60)) {
        return false;
    }
    
    // Create/update lock file
    @touch($lockFile);
    
    // Log auto-start attempt
    $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'ws-autostart.log';
    $message = date('[Y-m-d H:i:s]') . ' Auto-start attempt from ' . 
        ($_SERVER['SCRIPT_NAME'] ?? 'unknown') . ' by user ' . 
        ($_SESSION['usuario_email'] ?? $_SESSION['admin_email'] ?? 'unknown') . "\n";
    @file_put_contents($logFile, $message, FILE_APPEND);
    
    // Start the server based on operating system
    $path = __DIR__;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $result = @pclose(popen('start /B "HelpDesk WS" cmd /c "cd /D ' . $path . ' && php ws-server.php > ws-server.log 2>&1"', 'r'));
    } else {
        $result = @exec('cd ' . $path . ' && nohup php ws-server.php > ws-server.log 2>&1 &');
    }
    
    // Wait briefly and check if server is running
    sleep(2);
    return isWsServerRunning();
}
?>