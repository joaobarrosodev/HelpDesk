<?php
/**
 * Simple WebSocket message processor for HelpDesk chat
 * 
 * This file processes JSON message files found in the temp directory
 * and synchronizes them using sync files approach instead of WebSockets
 * when WebSockets aren't available
 */

// Don't timeout while processing messages
set_time_limit(30);

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');
define('DEBUG', false);

// Create temp directory if it doesn't exist
if (!file_exists(TEMP_DIR)) {
    @mkdir(TEMP_DIR, 0777, true);
    @chmod(TEMP_DIR, 0777);
}

/**
 * Processes a WebSocket message file and creates a sync file for it
 */
function processMessageFile($file) {
    if (!file_exists($file) || !is_readable($file)) {
        return false;
    }
    
    try {
        $content = @file_get_contents($file);
        if ($content === false) {
            if (DEBUG) error_log("Could not read file: $file");
            return false;
        }
        
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['action']) || $data['action'] !== 'newMessage') {
            if (DEBUG) error_log("Invalid message format in file: $file");
            return false;
        }
        
        // Extract the necessary information
        $ticketId = $data['ticketId'] ?? null;
        $message = $data['message'] ?? null;
        
        if (!$ticketId || !$message) {
            if (DEBUG) error_log("Missing ticketId or message in file: $file");
            return false;
        }
        
        // Generate a sync file for this message - this is the fallback mechanism
        $syncId = uniqid();
        $cleanTicketId = str_replace('#', '', $ticketId);
        $syncFile = TEMP_DIR . DIRECTORY_SEPARATOR . "sync_{$cleanTicketId}_{$syncId}.txt";
        
        // Add messageId if it doesn't exist
        if (!isset($message['messageId'])) {
            $message['messageId'] = 'auto_' . uniqid();
        }
        
        // Store the message in a sync file
        $syncData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
        
        $result = @file_put_contents($syncFile, json_encode($syncData));
        if ($result === false) {
            if (DEBUG) error_log("Could not write to sync file: $syncFile");
            return false;
        }
        
        @chmod($syncFile, 0666);
        
        if (DEBUG) error_log("Created sync file: $syncFile for message in $file");
        
        // Delete the original WebSocket message file
        @unlink($file);
        
        return true;
    } catch (Exception $e) {
        if (DEBUG) error_log("Error processing message file $file: " . $e->getMessage());
        return false;
    }
}

/**
 * Process all message files in the temp directory
 */
function processAllMessageFiles() {
    $processed = 0;
    $failed = 0;
    $messageFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . 'ws_message_*.json');
    
    if (is_array($messageFiles)) {
        foreach ($messageFiles as $file) {
            if (file_exists($file) && is_readable($file)) {
                if (time() - @filemtime($file) > 60) {
                    // Remove files older than 1 minute
                    @unlink($file);
                    continue;
                }
                
                $result = processMessageFile($file);
                if ($result) {
                    $processed++;
                } else {
                    $failed++;
                }
            }
        }
    }
    
    return [
        'processed' => $processed,
        'failed' => $failed,
        'total' => is_array($messageFiles) ? count($messageFiles) : 0
    ];
}

// If this script is called directly, process all message files
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    $result = processAllMessageFiles();
    echo json_encode($result);
}
