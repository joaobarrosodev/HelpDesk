<?php
/**
 * Simple WebSocket Manager - Functions to handle message synchronization
 */

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');
define('DEBUG', false);

/**
 * Creates a message file that will be processed and forwarded to users
 * viewing the same ticket
 *
 * @param string $ticketId The ticket ID
 * @param array $messageData The message data
 * @return bool Success status
 */
function broadcastMessageToWebSocket($ticketId, $messageData) {
    // Create temp directory if it doesn't exist
    if (!file_exists(TEMP_DIR)) {
        $created = @mkdir(TEMP_DIR, 0777, true);
        if ($created) {
            @chmod(TEMP_DIR, 0777);
        } else if (DEBUG) {
            error_log("Could not create temp directory: " . TEMP_DIR);
        }
    }
    
    // Ensure we have a ticket ID
    if (empty($ticketId)) {
        if (DEBUG) error_log("Cannot broadcast message: Missing ticket ID");
        return false;
    }
    
    // Generate a unique ID for this message
    if (!isset($messageData['messageId'])) {
        $messageData['messageId'] = uniqid('msg_');
    }
    
    // Format the payload
    $payload = json_encode([
        'action' => 'newMessage',
        'ticketId' => $ticketId,
        'message' => $messageData,
        'timestamp' => time()
    ]);
    
    // Write to file for processing
    $wsFilename = 'ws_message_' . uniqid() . '.json';
    $wsFile = TEMP_DIR . DIRECTORY_SEPARATOR . $wsFilename;
    
    $result = @file_put_contents($wsFile, $payload);
    
    if ($result === false) {
        if (DEBUG) error_log("Failed to create WebSocket message file: $wsFilename");
        $syncResult = createSyncFile($ticketId, $messageData);
        return $syncResult;
    }
    
    @chmod($wsFile, 0666);
    if (DEBUG) error_log("Created WebSocket message file: $wsFilename");
    
    // Also create sync file as backup (more reliable)
    $syncResult = createSyncFile($ticketId, $messageData);
    
    return true;
}

/**
 * Creates a sync file directly (bypassing WebSocket)
 *
 * @param string $ticketId The ticket ID
 * @param array $messageData The message data
 * @return bool Success status
 */
function createSyncFile($ticketId, $messageData) {
    // Create temp directory if it doesn't exist
    if (!file_exists(TEMP_DIR)) {
        $created = @mkdir(TEMP_DIR, 0777, true);
        if ($created) {
            @chmod(TEMP_DIR, 0777);
        } else if (DEBUG) {
            error_log("Could not create temp directory: " . TEMP_DIR);
            return false;
        }
    }
    
    $syncId = uniqid();
    $cleanTicketId = str_replace('#', '', $ticketId);
    $syncFile = TEMP_DIR . DIRECTORY_SEPARATOR . "sync_{$cleanTicketId}_{$syncId}.txt";
    
    $syncData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $messageData
    ];
    
    $result = @file_put_contents($syncFile, json_encode($syncData));
    if ($result === false) {
        if (DEBUG) error_log("Failed to create sync file");
        return false;
    }
    
    @chmod($syncFile, 0666);
    if (DEBUG) error_log("Created sync file: " . basename($syncFile));
    
    return true;
}

/**
 * Send a direct HTTP request to the WebSocket server using cURL
 * 
 * @param string $ticketId Ticket ID
 * @param array $messageData Message data
 * @return bool Success status
 */
function sendDirectHttpRequest($ticketId, $messageData) {
    // Skip if cURL is not available
    if (!function_exists('curl_init')) {
        error_log('cURL is not available. Cannot send direct HTTP request.');
        return false;
    }
    
    try {
        // Create a message data payload that can be sent via HTTP
        $data = [
            'action' => 'httpMessage',
            'ticketId' => $ticketId,
            'message' => $messageData,
            'timestamp' => time(),
            'secret' => 'ws_secret_key' // Add a secret key for security
        ];
        
        // Initialize cURL
        $ch = curl_init('http://localhost/infoexe/HelpDesk/ws-http-bridge.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 seconds timeout
        
        // Send the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            error_log("Direct HTTP request successful. Response: " . $response);
            return isset($result['success']) && $result['success'] === true;
        } else {
            error_log("Direct HTTP request failed with code $httpCode: $response");
            return false;
        }
    } catch (Exception $e) {
        error_log('Exception in direct HTTP request: ' . $e->getMessage());
        return false;
    }
}
