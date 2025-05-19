<?php
session_start();
include('conflogin.php');
include('db.php');
include('ws-manager.php'); // Include WebSocket manager functions

// Check if user is logged in
if (!isset($_SESSION['usuario_email'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

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

$keyid = $_POST['keyid'];
$id = $_POST['id'];
$message = trim($_POST['message']);
$user = $_SESSION['usuario_email'];
$date = date('Y-m-d H:i:s');

// Get device ID if provided
$deviceId = isset($_POST['deviceId']) ? $_POST['deviceId'] : null;

// Determine message type (1 for user, 2 for admin/support)
$messageType = (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']) ? 2 : 1;

try {
    // Insert the message into the database
    $stmt = $pdo->prepare("INSERT INTO comments_xdfree01_extrafields (XDFree01_KeyID, Message, Date, user, type) 
                          VALUES (:keyid, :message, :date, :user, :type)");
    
    $stmt->bindParam(':keyid', $keyid);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':user', $user);
    $stmt->bindParam(':type', $messageType);
    
    $stmt->execute();
    $messageId = $pdo->lastInsertId();
    
    // Prepare the message data for WebSocket
    $messageData = [
        'Message' => $message,
        'user' => $user,
        'type' => $messageType,
        'CommentTime' => $date,
        'deviceId' => $deviceId,
        'messageId' => 'db_' . $messageId
    ];
    
    // Update status to "Em análise" for new tickets or if status is "Pendente"
    $updateStatus = false;
    
    // Get current status
    $stmtStatus = $pdo->prepare("SELECT Status FROM info_xdfree01_extrafields WHERE XDFree01_KeyID = :keyid");
    $stmtStatus->bindParam(':keyid', $keyid);
    $stmtStatus->execute();
    $currentStatus = $stmtStatus->fetchColumn();
    
    // Update status if necessary
    if ($currentStatus == 'Pendente' && $messageType == 2) {
        $newStatus = 'Em análise';
        $updateStatus = true;
    }
    else if ($currentStatus == 'Em análise' && $messageType == 1) {
        $newStatus = 'Aguarda resposta';
        $updateStatus = true;
    }
    
    if ($updateStatus) {
        $stmtUpdateStatus = $pdo->prepare("UPDATE info_xdfree01_extrafields SET Status = :status WHERE XDFree01_KeyID = :keyid");
        $stmtUpdateStatus->bindParam(':status', $newStatus);
        $stmtUpdateStatus->bindParam(':keyid', $keyid);
        $stmtUpdateStatus->execute();
    }
    
    // Broadcast the message via WebSocket
    broadcastMessageToWebSocket($keyid, $messageData);
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'messageId' => $messageId,
            'websocketBroadcast' => true,
            'statusUpdated' => $updateStatus,
            'newStatus' => $updateStatus ? $newStatus : null
        ]);
    } else {
        header('Location: detalhes_ticket.php?keyid=' . urlencode($id));
    }
    
} catch (PDOException $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    } else {
        header('Location: meus_tickets.php?error=2');
    }
}
?>
