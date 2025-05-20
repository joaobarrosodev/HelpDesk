<?php
/**
 * Simple initialization script with no WebSocket server auto-start
 * Uses file-based synchronization instead
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');
define('SYNC_FILE_AGE_LIMIT', 300); // 5 minutes in seconds

// Create temp directory if it doesn't exist
if (!file_exists(TEMP_DIR)) {
    $created = @mkdir(TEMP_DIR, 0777, true);
    if ($created) {
        @chmod(TEMP_DIR, 0777);
    }
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
$syncFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . 'sync_*.txt');
$oldFiles = 0;

// Process message files with proper error handling
if (is_array($messageFiles)) {
    foreach ($messageFiles as $file) {
        try {
            if (file_exists($file) && is_readable($file)) {
                $fileTime = @filemtime($file);
                if ($fileTime && (time() - $fileTime > SYNC_FILE_AGE_LIMIT)) {
                    @unlink($file);
                    $oldFiles++;
                }
            }
        } catch (Exception $e) {
            error_log("Error processing file: " . $e->getMessage());
        }
    }
}

// Process sync files with proper error handling
if (is_array($syncFiles)) {
    foreach ($syncFiles as $file) {
        try {
            if (file_exists($file) && is_readable($file)) {
                $fileTime = @filemtime($file);
                if ($fileTime && (time() - $fileTime > SYNC_FILE_AGE_LIMIT)) {
                    @unlink($file);
                    $oldFiles++;
                }
            }
        } catch (Exception $e) {
            error_log("Error processing file: " . $e->getMessage());
        }
    }
}

// Log cleanup information if debugging is enabled
if ($oldFiles > 0 && defined('DEBUG') && DEBUG) {
    error_log("Cleaned up $oldFiles old files from temp directory");
}

/**
 * Auto-start WebSocket server if not running
 * This script checks if the WebSocket server is running and attempts to start it if needed
 */

// Only attempt auto-start if enabled
$enableAutoStart = true;  // Set to false to disable auto-start

// Don't run this in CLI mode (to prevent recursion when the server itself runs)
if (php_sapi_name() == 'cli') {
    return;
}

// Check if server is running
function isWsServerRunning() {
    $socket = @fsockopen('localhost', 8080, $errno, $errstr, 1);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}

// Don't continue if auto-start is disabled or server is already running
if (!$enableAutoStart || isWsServerRunning()) {
    return;
}

// Create temp directory if it doesn't exist
$tempDir = dirname(__FILE__) . '/temp';
if (!file_exists($tempDir)) {
    @mkdir($tempDir, 0777, true);
}

// Don't attempt to start more than once every 60 seconds
$lockFile = $tempDir . '/ws_autostart.lock';
if (file_exists($lockFile) && (time() - @filemtime($lockFile) < 60)) {
    return;
}

// Create/update lock file
@touch($lockFile);

// Try to start the WebSocket server in the background
$path = dirname(__FILE__);

// Different commands for Windows vs Unix
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    pclose(popen('start /B "HelpDesk WS" cmd /c "cd /D ' . $path . ' && php ws-server.php > ws-server.log 2>&1"', 'r'));
} else {
    exec('cd ' . $path . ' && nohup php ws-server.php > ws-server.log 2>&1 &');
}

// Log the auto-start attempt
$logFile = $path . '/ws-autostart.log';
$message = date('[Y-m-d H:i:s]') . ' Auto-start attempt from ' . 
    ($_SERVER['SCRIPT_NAME'] ?? 'unknown') . ' by user ' . 
    ($_SESSION['usuario_email'] ?? 'unknown') . "\n";
@file_put_contents($logFile, $message, FILE_APPEND);
?>
