<?php
session_start();
include('conflogin.php');
include('db.php');

// Check if user is logged in
if (!isset($_SESSION['usuario_email'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Log received data for debugging
error_log("inserir_mensagem.php called by user: " . $_SESSION['usuario_email']);
error_log("POST data: " . print_r($_POST, true));

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$deviceId = isset($_POST['deviceId']) ? $_POST['deviceId'] : 'unknown';

// Check for required parameters
if (!isset($_POST['keyid']) || !isset($_POST['message']) || empty($_POST['message'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    } else {
        header('Location: meus_tickets.php?error=1');
    }
    exit;
}

try {
    $keyid = $_POST['keyid'];
    $id = isset($_POST['id']) ? $_POST['id'] : '';
    $message = trim($_POST['message']);
    $user = $_SESSION['usuario_email'];
    $date = date('Y-m-d H:i:s');
    
    // Message type: 1 for client, 0 for admin
    $messageType = 1; // Client message
    if (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']) {
        $messageType = 0; // Admin message
    }
    
    error_log("Message type determined: $messageType (0=admin, 1=client)");
    
    // Get the actual KeyId if needed
    if (!preg_match('/^#/', $keyid)) {
        $stmt_keyid = $pdo->prepare("SELECT KeyId FROM xdfree01 WHERE id = :id");
        $stmt_keyid->bindParam(':id', empty($id) ? $keyid : $id);
        $stmt_keyid->execute();
        $actualKeyId = $stmt_keyid->fetchColumn();
        
        if ($actualKeyId) {
            $keyid = $actualKeyId;
            error_log("Using actual KeyId: $keyid");
        }
    }
    
    // Insert the message into database
    $stmt = $pdo->prepare("INSERT INTO comments_xdfree01_extrafields (XDFree01_KeyID, Message, Date, user, type) 
                          VALUES (:keyid, :message, :date, :user, :type)");
    
    $stmt->bindParam(':keyid', $keyid);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':user', $user);
    $stmt->bindParam(':type', $messageType);
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Error inserting message: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Failed to insert message");
    }
    
    $messageId = $pdo->lastInsertId();
    error_log("Message inserted with ID: $messageId");
    
    // Update ticket status if needed
    if ($messageType == 1) {
        // Client is responding - status to "Aguarda Resposta"
        $statusList = ['Aguarda Resposta Cliente', 'Em Análise', 'Em Resolução'];
        $statusList = "'" . implode("','", $statusList) . "'";
        
        $stmt = $pdo->prepare("UPDATE info_xdfree01_extrafields 
                              SET Status = 'Aguarda Resposta' 
                              WHERE XDFree01_KeyID = :keyid 
                              AND Status IN ($statusList)");
        $stmt->bindParam(':keyid', $keyid);
        $stmt->execute();
        error_log("Status updated for client message");
    } else {
        // Admin is responding - status to "Aguarda Resposta Cliente"
        $stmt = $pdo->prepare("UPDATE info_xdfree01_extrafields 
                              SET Status = 'Aguarda Resposta Cliente' 
                              WHERE XDFree01_KeyID = :keyid AND Status = 'Aguarda Resposta'");
        $stmt->bindParam(':keyid', $keyid);
        $stmt->execute();
        error_log("Status updated for admin message");
    }
    
    // Create notification file for real-time update
    $tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'temp';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
        chmod($tempDir, 0777);
    }
    
    $syncFileName = "sync_{$keyid}_" . time() . "_" . mt_rand(1000, 9999) . ".txt";
    $syncFilePath = $tempDir . DIRECTORY_SEPARATOR . $syncFileName;
    
    $syncData = [
        'message' => [
            'id' => $messageId,
            'Message' => $message,
            'user' => $user,
            'type' => $messageType, 
            'CommentTime' => $date,
            'messageId' => $messageId . '_' . time()
        ],
        'ticketId' => $keyid,
        'timestamp' => time()
    ];
    
    file_put_contents($syncFilePath, json_encode($syncData));
    chmod($syncFilePath, 0666);
    error_log("Created sync file: $syncFileName");    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'messageId' => $messageId,
            'messageTime' => $date,
            'user' => $user,
            'type' => $messageType
        ]);
    } else {
        // Redirect back to ticket details
        header("Location: detalhes_ticket.php?keyid=" . urlencode($id));
    }
    
} catch (Exception $e) {
    error_log("Exception in inserir_mensagem.php: " . $e->getMessage());
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } else {
        header('Location: meus_tickets.php?error=2');
    }
}
?>
