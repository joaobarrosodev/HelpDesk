<?php
session_start();
include('conflogin.php');
// Set timezone to Portugal
date_default_timezone_set('Europe/Lisbon');
// Check if user is logged in
if (!isset($_SESSION['admin_email'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Admin not logged in']);
    exit;
}
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
// Check for required parameters
if (!isset($_POST['keyid']) || !isset($_POST['id']) || !isset($_POST['message'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    } else {
        header('Location: consultar_tickets.php?error=1');
    }
    exit;
}
$keyid = $_POST['keyid'];
$id = $_POST['id'];
$message = trim($_POST['message']);
$user = $_SESSION['admin_email'];
$deviceId = isset($_POST['deviceId']) ? $_POST['deviceId'] : null;
// Prepare message data for WebSocket
$messageData = [
    'action' => 'sendMessage',
    'ticketId' => $keyid,
    'ticketNumericId' => $id,
    'message' => $message,
    'user' => $user,
    'type' => 2, // Admin type
    'deviceId' => $deviceId,
    'timestamp' => date('Y-m-d H:i:s'),
    'messageId' => uniqid('admin_msg_', true)
];
// Send to WebSocket server via HTTP bridge
$success = sendToWebSocketServer($messageData);
if ($success) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Message sent via WebSocket',
            'websocketSent' => true
        ]);
    } else {
        header('Location: detalhes_ticket.php?keyid=' . urlencode($id));
    }
} else {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to send message to WebSocket server'
        ]);
    } else {
        header('Location: consultar_tickets.php?error=3');
    }
}
/**
 * Send message to WebSocket server via HTTP
 */
function sendToWebSocketServer($messageData) {
    $wsUrl = 'http://localhost:8080/send-message';
   
    // Try cURL first
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $wsUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer helpdesk_secret_key'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
       
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
       
        if ($httpCode == 200) {
            return true;
        }
    }
   
    // Fallback: create a temp file for WebSocket to process
    $tempDir = __DIR__ . '/../temp';
    if (!file_exists($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }
   
    $tempFile = $tempDir . '/ws_send_' . uniqid() . '.json';
    $result = @file_put_contents($tempFile, json_encode($messageData));
   
    if ($result !== false) {
        @chmod($tempFile, 0666);
        return true;
    }
   
    return false;
}
?>