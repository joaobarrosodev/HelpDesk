<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include('../db.php');

// Detailed logging for debugging
error_log("=== ADMIN inserir_mensagem.php called ===");
error_log("POST data: " . print_r($_POST, true));

// Check if AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$deviceId = isset($_POST['deviceId']) ? $_POST['deviceId'] : 'admin_unknown';

if ($isAjax) {
    header('Content-Type: application/json');
}

// Verify admin access
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_admin']) || !$_SESSION['usuario_admin']) {
    error_log("Error: Unauthorized access attempt to admin message handler");
    if ($isAjax) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin access required.']);
    } else {
        header('Location: login.php');
    }
    exit;
}

// Verify required fields
if (!isset($_POST['keyid']) || !isset($_POST['message']) || empty($_POST['message'])) {
    error_log("Error: Missing required fields");
    if ($isAjax) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
    } else {
        echo "Missing required form data";
    }
    exit;
}

try {
    // Get form data
    $keyid = $_POST['keyid'];
    $id = isset($_POST['id']) ? $_POST['id'] : '';
    $message = trim($_POST['message']);
    $user = $_SESSION['usuario_email'];
    $date = date('Y-m-d H:i:s');
    
    // Type 0 = admin message
    $userType = 0;
    
    error_log("Admin message - keyid: $keyid, id: $id, message length: " . strlen($message));
    
    // Get the actual KeyId if needed
    if (!preg_match('/^#/', $keyid)) {
        // First try direct KeyId match
        $verify_stmt = $pdo->prepare("SELECT KeyId FROM xdfree01 WHERE KeyId = :keyid");
        $verify_stmt->bindParam(':keyid', $keyid);
        $verify_stmt->execute();
        $foundKeyId = $verify_stmt->fetchColumn();
        
        if ($foundKeyId) {
            error_log("Found KeyId directly: $foundKeyId");
        } 
        // If not found, try by ID
        else if (!empty($id)) {
            error_log("KeyId not found directly, trying with id=$id");
            $stmt_keyid = $pdo->prepare("SELECT KeyId FROM xdfree01 WHERE id = :id");
            $stmt_keyid->bindParam(':id', $id);
            $stmt_keyid->execute();
            $actualKeyId = $stmt_keyid->fetchColumn();
            
            if ($actualKeyId) {
                $keyid = $actualKeyId;
                error_log("Found KeyId using id: $keyid");
            } else {
                throw new Exception("Cannot find valid KeyId for ticket");
            }
        }
    }
    
    // Insert message
    $sql = "INSERT INTO comments_xdfree01_extrafields (XDFree01_KeyID, Message, type, Date, user) 
            VALUES (:keyid, :message, :type, :date, :user)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':keyid', $keyid);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':type', $userType);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':user', $user);
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Error inserting admin message: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Failed to insert message into database");
    }
    
    $messageId = $pdo->lastInsertId();
    error_log("Admin message inserted with ID: $messageId");
    
    // Update ticket status
    $sql_check = "SELECT Status FROM info_xdfree01_extrafields WHERE XDFree01_KeyID = :keyid";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':keyid', $keyid);
    $stmt_check->execute();
    $status = $stmt_check->fetchColumn();
    
    error_log("Current ticket status: $status");
    
    if ($status == 'Aguarda Resposta') {
        $novo_status = 'Aguarda Resposta Cliente';
        $sql_update = "UPDATE info_xdfree01_extrafields 
                      SET Status = :novo_status 
                      WHERE XDFree01_KeyID = :keyid";
        
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->bindParam(':novo_status', $novo_status);
        $stmt_update->bindParam(':keyid', $keyid);
        $stmt_update->execute();
        error_log("Status updated to: $novo_status");
    }
    
    // Create a sync file for clients to detect the new message
    $tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';
    
    // Create temp directory if it doesn't exist
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
        chmod($tempDir, 0777);
    }
    
    // Create sync file
    $syncFileName = "sync_{$keyid}_" . time() . "_" . mt_rand(1000, 9999) . ".txt";
    $syncFilePath = $tempDir . DIRECTORY_SEPARATOR . $syncFileName;
    
    $syncData = [
        'message' => [
            'id' => $messageId,
            'Message' => $message,
            'user' => $user,
            'type' => $userType,
            'CommentTime' => $date,
            'messageId' => 'admin_' . $messageId . '_' . time()
        ],
        'ticketId' => $keyid,
        'timestamp' => time()
    ];
      file_put_contents($syncFilePath, json_encode($syncData));
    chmod($syncFilePath, 0666);
    error_log("Created sync file: $syncFileName");
    
    // Return success response
    if ($isAjax) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'messageId' => $messageId,
            'messageTime' => $date,
            'user' => $user,
            'type' => $userType
        ]);
    } else {
        // Redirect back to ticket details
        $redirect_id = !empty($id) ? $id : $keyid;
        error_log("Redirecting to detalhes_ticket.php with keyid=$redirect_id");
        header("Location: detalhes_ticket.php?keyid={$redirect_id}");
    }
    
} catch (Exception $e) {
    error_log("Error in admin/inserir_mensagem.php: " . $e->getMessage());
    
    if ($isAjax) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } else {
        echo "Error: " . $e->getMessage();
    }
}

error_log("=== ADMIN inserir_mensagem.php completed ===");
?>
