<?php
/**
 * WebSocket HTTP Bridge
 * This script provides an HTTP interface to interact with the WebSocket server
 */

// Set headers
header('Content-Type: application/json');

// Secret key for authentication (should match the one in ws-manager.php)
$secretKey = 'ws_secret_key';

// Get the raw POST data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

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
    $syncFile = $tempDir . "/sync_{$data['ticketId']}_{$syncId}.txt";
    file_put_contents($syncFile, json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $data['message']
    ]));
    chmod($syncFile, 0666);
    
    // Try to process WebSocket messages immediately
    include_once('process_ws_messages.php');
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'messageFile' => basename($messageFile),
        'syncFile' => basename($syncFile)
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
