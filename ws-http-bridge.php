<?php
/**
 * WebSocket HTTP Bridge
 * This script provides an HTTP interface to interact with the WebSocket server
 */
// Set headers
header('Content-Type: application/json');

// Secret key for authentication
$secretKey = 'ws_secret_key';

// Get the raw POST data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Log all incoming requests
file_put_contents(
    __DIR__ . '/ws-bridge-requests.log',
    date('[Y-m-d H:i:s]') . ' Received request: ' . $rawData . "\n",
    FILE_APPEND
);

// Basic validation
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

// Check authentication
if (!isset($data['secret']) || $data['secret'] !== $secretKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check required fields
if (!isset($data['action']) || !isset($data['ticketId'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Process based on action
if ($data['action'] === 'httpMessage' && isset($data['message'])) {
    // Load database connection
    require_once __DIR__ . '/db.php';
    
    // Create a temp file to be processed by the WebSocket server
    $tempDir = __DIR__ . '/temp';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
   
    $messageFile = $tempDir . '/ws_message_' . uniqid() . '.json';
    $payload = json_encode([
        'action' => 'newMessage',
        'ticketId' => $data['ticketId'],
        'message' => $data['message'],
        'timestamp' => time()
    ]);
   
    file_put_contents($messageFile, $payload);
    chmod($messageFile, 0666);
   
    // Also create a sync file for immediate client-side sync
    $syncId = uniqid();
    $cleanTicketId = str_replace('#', '', $data['ticketId']);
    $syncFile = $tempDir . "/sync_{$cleanTicketId}_{$syncId}.txt";
    
    $syncData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $data['message'],
        'ticketId' => $data['ticketId'],
        'created' => time()
    ];
    
    file_put_contents($syncFile, json_encode($syncData));
    chmod($syncFile, 0666);
    
    // Try to save directly to database
    try {
        $message = $data['message'];
        $ticketId = $data['ticketId'];
        
        // Extract message data
        $messageText = isset($message['Message']) ? $message['Message'] : '';
        $user = isset($message['user']) ? $message['user'] : '';
        $type = isset($message['type']) ? intval($message['type']) : 1;
        $date = isset($message['CommentTime']) ? $message['CommentTime'] : date('Y-m-d H:i:s');
        
        // Log what we're about to insert
        file_put_contents(
            __DIR__ . '/ws-bridge-db.log',
            date('[Y-m-d H:i:s]') . " Attempting to save message to database:\n" .
            "TicketID: $ticketId\n" .
            "Message: $messageText\n" .
            "User: $user\n" .
            "Type: $type\n" .
            "Date: $date\n\n",
            FILE_APPEND
        );
        
        // Skip if any required field is missing
        if (empty($messageText) || empty($user) || empty($ticketId)) {
            file_put_contents(
                __DIR__ . '/ws-bridge-db.log',
                date('[Y-m-d H:i:s]') . " Missing required data for saving message\n",
                FILE_APPEND
            );
        } else {
            // Insert the message into the database
            $stmt = $pdo->prepare("INSERT INTO comments_xdfree01_extrafields (XDFree01_KeyID, Message, Date, user, type) 
                                  VALUES (:keyid, :message, :date, :user, :type)");
            
            $stmt->bindParam(':keyid', $ticketId);
            $stmt->bindParam(':message', $messageText);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':user', $user);
            $stmt->bindParam(':type', $type);
            
            $result = $stmt->execute();
            
            if ($result) {
                $insertId = $pdo->lastInsertId();
                file_put_contents(
                    __DIR__ . '/ws-bridge-db.log',
                    date('[Y-m-d H:i:s]') . " SUCCESS: Message saved to database with ID $insertId\n",
                    FILE_APPEND
                );
                $dbSaved = true;
            } else {
                file_put_contents(
                    __DIR__ . '/ws-bridge-db.log',
                    date('[Y-m-d H:i:s]') . " ERROR: Failed to save message to database: " . json_encode($stmt->errorInfo()) . "\n",
                    FILE_APPEND
                );
                $dbSaved = false;
            }
        }
    } catch (Exception $e) {
        file_put_contents(
            __DIR__ . '/ws-bridge-db.log',
            date('[Y-m-d H:i:s]') . " EXCEPTION: " . $e->getMessage() . "\n",
            FILE_APPEND
        );
        $dbSaved = false;
    }
    
    // Try to process WebSocket messages immediately
    if (file_exists(__DIR__ . '/process_ws_messages.php')) {
        include_once(__DIR__ . '/process_ws_messages.php');
        if (function_exists('processAllMessageFiles')) {
            processAllMessageFiles();
        }
    }
   
    // Return success response
    echo json_encode([
        'success' => true,
        'messageFile' => basename($messageFile),
        'syncFile' => basename($syncFile),
        'dbSaved' => $dbSaved ?? false
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}