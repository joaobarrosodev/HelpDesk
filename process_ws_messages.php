<?php
/**
 * Process WebSocket message files
 */
function processAllMessageFiles() {
    $tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'temp';
    $messageFiles = glob($tempDir . DIRECTORY_SEPARATOR . 'ws_message_*.json');
    $processedCount = 0;
    
    if (!is_array($messageFiles)) {
        return 0;
    }
    
    foreach ($messageFiles as $file) {
        try {
            if (file_exists($file) && is_readable($file)) {
                $content = @file_get_contents($file);
                if ($content) {
                    $data = json_decode($content, true);
                    if ($data && isset($data['ticketId']) && isset($data['message'])) {
                        $ticketId = $data['ticketId'];
                        $message = $data['message'];
                        
                        // Create a sync file for this message
                        createSyncFile($ticketId, $message);
                        
                        // Delete the message file
                        @unlink($file);
                        $processedCount++;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error processing message file: " . $e->getMessage());
        }
    }
    
    return $processedCount;
}

/**
 * Create a sync file for a message
 */
function createSyncFile($ticketId, $messageData) {
    $tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'temp';
    
    // Ensure ticketId is clean
    $cleanTicketId = str_replace('#', '', $ticketId);
    
    // Create unique ID for sync file
    $syncId = uniqid();
    $syncFile = $tempDir . DIRECTORY_SEPARATOR . "sync_{$cleanTicketId}_{$syncId}.txt";
    
    // Prepare sync data
    $syncData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $messageData,
        'ticketId' => $ticketId,
        'created' => time()
    ];
    
    // Write sync file
    $result = @file_put_contents($syncFile, json_encode($syncData));
    if ($result === false) {
        error_log("Failed to create sync file for ticket $ticketId");
        return false;
    }
    
    @chmod($syncFile, 0666);
    return true;
}
?>