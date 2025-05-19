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
?>
