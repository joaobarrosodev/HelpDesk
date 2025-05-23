<?php
session_start();

// Include database from parent directory
include_once('../db.php');

// Set timezone to Portugal
date_default_timezone_set('Europe/Lisbon');

// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Admin not logged in']);
    exit;
}

$user = $_SESSION['admin_email'];
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

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
$deviceId = isset($_POST['deviceId']) ? $_POST['deviceId'] : null;
$ws_origin = isset($_POST['ws_origin']) ? $_POST['ws_origin'] : '0';

// Log the received data for debugging
error_log("Admin inserir_mensagem - KeyID: $keyid, Message: $message, User: $user");

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Check for duplicate messages
    $checkSql = "SELECT id FROM comments_xdfree01_extrafields 
                WHERE XDFree01_KeyID = :keyid 
                AND Message = :message 
                AND user = :user 
                AND ABS(TIMESTAMPDIFF(SECOND, Date, NOW())) < 5";
    
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':keyid', $keyid);
    $checkStmt->bindParam(':message', $message);
    $checkStmt->bindParam(':user', $user);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $pdo->rollBack();
        error_log("Admin inserir_mensagem - Duplicate message detected");
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Message already saved',
                'duplicate' => true
            ]);
        } else {
            header('Location: detalhes_ticket.php?keyid=' . urlencode($keyid));
        }
        exit;
    }
    
    // Save message to database
    $sql = "INSERT INTO comments_xdfree01_extrafields 
            (XDFree01_KeyID, Message, type, Date, user) 
            VALUES (:keyid, :message, :type, NOW(), :user)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':keyid', $keyid);
    $stmt->bindParam(':message', $message);
    $stmt->bindValue(':type', 2); // Admin type
    $stmt->bindParam(':user', $user);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert message into database');
    }
    
    // Get the inserted message ID
    $messageId = $pdo->lastInsertId();
    error_log("Admin inserir_mensagem - Message saved with ID: $messageId");
    
    // Update ticket's last update time
    $updateSql = "UPDATE info_xdfree01_extrafields 
                  SET dateu = NOW() 
                  WHERE XDFree01_KeyID = :keyid";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->bindParam(':keyid', $keyid);
    $updateStmt->execute();
    
    // Commit transaction
    $pdo->commit();
    
    // Only send to WebSocket if this message wasn't originated from WebSocket
    if ($ws_origin !== '1') {
        // Prepare message data for WebSocket/sync
        $messageData = [
            'action' => 'sendMessage',
            'ticketId' => $keyid,
            'ticketNumericId' => $id,
            'message' => $message,
            'user' => $user,
            'type' => 2, // Admin type
            'deviceId' => $deviceId,
            'timestamp' => date('Y-m-d H:i:s'),
            'messageId' => 'db_' . $messageId,
            'alreadySaved' => true
        ];
        
        // Try to send to WebSocket server
        $wsSuccess = sendToWebSocketServer($messageData);
        error_log("Admin inserir_mensagem - WebSocket send result: " . ($wsSuccess ? 'success' : 'failed'));
    } else {
        $wsSuccess = true;
    }
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Message saved successfully',
            'messageId' => $messageId,
            'websocketSent' => $wsSuccess
        ]);
    } else {
        header('Location: detalhes_ticket.php?keyid=' . urlencode($keyid));
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Error in admin/inserir_mensagem.php: ' . $e->getMessage());
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save message: ' . $e->getMessage()
        ]);
    } else {
        header('Location: consultar_tickets.php?error=3');
    }
}

/**
 * Send message to WebSocket server via HTTP or temp file
 */
function sendToWebSocketServer($messageData) {
    // Try HTTP first
    $wsUrl = 'http://localhost:8080/send-message';
    
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return true;
        }
    }
    
    // Fallback: create a temp file for WebSocket to process
    $tempDir = dirname(__DIR__) . '/temp';
    if (!file_exists($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }
    
    // Create sync file for immediate synchronization
    $syncId = uniqid();
    $cleanTicketId = str_replace('#', '', $messageData['ticketId']);
    $syncFile = $tempDir . "/sync_{$cleanTicketId}_{$syncId}.txt";
    
    $syncData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => [
            'Message' => $messageData['message'],
            'user' => $messageData['user'],
            'type' => $messageData['type'],
            'CommentTime' => $messageData['timestamp'],
            'deviceId' => $messageData['deviceId'],
            'messageId' => $messageData['messageId']
        ],
        'ticketId' => $messageData['ticketId'],
        'created' => time()
    ];
    
    $result = @file_put_contents($syncFile, json_encode($syncData));
    
    if ($result !== false) {
        @chmod($syncFile, 0666);
        return true;
    }
    
    return false;
}
?>