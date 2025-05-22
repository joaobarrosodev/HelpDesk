<?php
session_start();
include('conflogin.php');
// Set timezone to Portugal
date_default_timezone_set('Europe/Lisbon');

// Check if user is logged in (handle both user and admin sessions)
if (!isset($_SESSION['usuario_email']) && !isset($_SESSION['admin_email'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Determine if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Check for required parameters
if (!isset($_POST['keyid']) || !isset($_POST['id']) || !isset($_POST['message'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    } else {
        header('Location: meus_tickets.php?error=1');
    }
    exit;
}

// Extract parameters
$keyid = $_POST['keyid'];
$id = $_POST['id'];
$message = trim($_POST['message']);
$user = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : $_SESSION['usuario_email'];
$deviceId = isset($_POST['deviceId']) ? $_POST['deviceId'] : null;

// Determine message type (1 for user, 2 for admin)
$messageType = (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']) || isset($_SESSION['admin_email']) ? 2 : 1;

// Log request
file_put_contents(
    __DIR__ . '/message-insert.log',
    date('[Y-m-d H:i:s]') . " Message insertion request:\n" .
    "TicketID: $keyid\n" .
    "NumericID: $id\n" .
    "Message: $message\n" .
    "User: $user\n" .
    "Type: $messageType\n\n",
    FILE_APPEND
);

// Database insertion
try {
    // Include database configuration
    require_once __DIR__ . '/db.php';
    
    // Insert message into database
    $stmt = $pdo->prepare("INSERT INTO comments_xdfree01_extrafields (XDFree01_KeyID, Message, Date, user, type) 
                           VALUES (:keyid, :message, NOW(), :user, :type)");
    
    $stmt->bindParam(':keyid', $keyid);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':user', $user);
    $stmt->bindParam(':type', $messageType);
    
    $result = $stmt->execute();
    
    if ($result) {
        $insertId = $pdo->lastInsertId();
        file_put_contents(
            __DIR__ . '/message-insert.log',
            date('[Y-m-d H:i:s]') . " Message inserted successfully with ID: $insertId\n",
            FILE_APPEND
        );
        $dbSuccess = true;
    } else {
        file_put_contents(
            __DIR__ . '/message-insert.log',
            date('[Y-m-d H:i:s]') . " Database insertion failed: " . json_encode($stmt->errorInfo()) . "\n",
            FILE_APPEND
        );
        $dbSuccess = false;
    }
} catch (Exception $e) {
    file_put_contents(
        __DIR__ . '/message-insert.log',
        date('[Y-m-d H:i:s]') . " Exception during database insertion: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
    $dbSuccess = false;
}

// Prepare message data for WebSocket
$messageData = [
    'action' => 'sendMessage',
    'ticketId' => $keyid,
    'ticketNumericId' => $id,
    'message' => $message,
    'user' => $user,
    'type' => $messageType,
    'deviceId' => $deviceId,
    'timestamp' => date('Y-m-d H:i:s'),
    'messageId' => uniqid('msg_', true),
    'alreadySaved' => $dbSuccess
];

// Send to WebSocket server
$wsSuccess = sendToWebSocketServer($messageData);

// Respond to the client
if ($dbSuccess || $wsSuccess) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Message sent',
            'dbSaved' => $dbSuccess,
            'websocketSent' => $wsSuccess,
            'messageId' => $insertId ?? null
        ]);
    } else {
        header('Location: detalhes_ticket.php?keyid=' . urlencode($id));
    }
} else {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to send message'
        ]);
    } else {
        header('Location: meus_tickets.php?error=3');
    }
}

/**
 * Send message to WebSocket server
 */
function sendToWebSocketServer($messageData) {
    // Log WebSocket send attempt
    file_put_contents(
        __DIR__ . '/message-insert.log',
        date('[Y-m-d H:i:s]') . " Attempting to send message to WebSocket server\n",
        FILE_APPEND
    );

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
       
        file_put_contents(
            __DIR__ . '/message-insert.log',
            date('[Y-m-d H:i:s]') . " cURL response: HTTP $httpCode - $response\n",
            FILE_APPEND
        );
       
        if ($httpCode == 200) {
            return true;
        }
    }
   
    // Fallback: create a temp file for WebSocket to process
    $tempDir = __DIR__ . '/temp';
    if (!file_exists($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }
   
    $tempFile = $tempDir . '/ws_message_' . uniqid() . '.json';
    $wsData = [
        'action' => 'newMessage',
        'ticketId' => $messageData['ticketId'],
        'message' => [
            'Message' => $messageData['message'],
            'user' => $messageData['user'],
            'type' => $messageData['type'],
            'CommentTime' => $messageData['timestamp'],
            'deviceId' => $messageData['deviceId'],
            'messageId' => $messageData['messageId'],
            'alreadySaved' => $messageData['alreadySaved']
        ],
        'timestamp' => time()
    ];
    
    $result = @file_put_contents($tempFile, json_encode($wsData));
   
    file_put_contents(
        __DIR__ . '/message-insert.log',
        date('[Y-m-d H:i:s]') . " File creation result: " . ($result !== false ? "Success" : "Failure") . "\n",
        FILE_APPEND
    );
   
    if ($result !== false) {
        @chmod($tempFile, 0666);
        
        // Also create a sync file for immediate sync
        $syncId = uniqid();
        $cleanTicketId = str_replace('#', '', $messageData['ticketId']);
        $syncFile = $tempDir . "/sync_{$cleanTicketId}_{$syncId}.txt";
        
        $syncData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $wsData['message'],
            'ticketId' => $messageData['ticketId'],
            'created' => time()
        ];
        
        file_put_contents($syncFile, json_encode($syncData));
        chmod($syncFile, 0666);
        
        return true;
    }
   
    return false;
}
?>