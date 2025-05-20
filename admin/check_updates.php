<?php
session_start();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_admin']) || !$_SESSION['usuario_admin']) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include('../db.php');

// Get and validate the ticket ID
$ticketId = isset($_GET['ticketId']) ? trim($_GET['ticketId']) : '';
$lastCheck = isset($_GET['lastCheck']) ? $_GET['lastCheck'] : date('Y-m-d H:i:s', strtotime('-5 minutes'));

// Check if ticket ID is empty
if (empty($ticketId)) {
    echo json_encode(['error' => 'Missing ticket ID']);
    exit;
}

try {
    // Get the actual KeyId value from the database for reliable comparison
    $stmt_keyid = $pdo->prepare("SELECT KeyId FROM xdfree01 WHERE id = :id");
    $stmt_keyid->bindParam(':id', $ticketId);
    $stmt_keyid->execute();
    $actualKeyId = $stmt_keyid->fetchColumn();
    
    if (!$actualKeyId) {
        echo json_encode(['error' => 'Invalid ticket ID']);
        exit;
    }
    
    // Query for new messages since the last check
    $sql = "SELECT c.id, c.Message, c.type, c.Date as CommentTime, c.user 
            FROM comments_xdfree01_extrafields c
            WHERE c.XDFree01_KeyID = :keyid 
            AND c.Date > :lastCheck 
            ORDER BY c.Date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':keyid', $actualKeyId);
    $stmt->bindParam(':lastCheck', $lastCheck);
    $stmt->execute();
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if ticket status has been updated
    $sql_status = "SELECT Status FROM info_xdfree01_extrafields WHERE XDFree01_KeyID = :keyid";
    $stmt_status = $pdo->prepare($sql_status);
    $stmt_status->bindParam(':keyid', $actualKeyId);
    $stmt_status->execute();
    $currentStatus = $stmt_status->fetchColumn();
    
    $response = [
        'hasNewMessages' => count($messages) > 0,
        'messages' => $messages,
        'ticketId' => $actualKeyId,
        'timestamp' => date('Y-m-d H:i:s'),
        'currentStatus' => $currentStatus,
        'hasUpdates' => count($messages) > 0
    ];
    
    // Create a sync file for client-side to detect
    if (count($messages) > 0) {
        // Define temp directory
        $tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';
        
        // Create temp directory if it doesn't exist
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        // Create a sync file for each message
        foreach ($messages as $message) {
            if ($message['type'] == 2) { // Only create sync files for admin messages (type 2)
                $syncFileName = "sync_{$actualKeyId}_" . time() . "_" . mt_rand(1000, 9999) . ".txt";
                $syncFilePath = $tempDir . DIRECTORY_SEPARATOR . $syncFileName;
                
                // Add device ID and source info to message
                $message['sourceDeviceId'] = 'admin_' . session_id();
                $message['messageId'] = 'admin_' . $message['id'] . '_' . time();
                
                // Create sync file content
                $syncContent = json_encode([
                    'message' => $message,
                    'ticketId' => $actualKeyId,
                    'timestamp' => time()
                ]);
                
                file_put_contents($syncFilePath, $syncContent);
                chmod($syncFilePath, 0666); // Make sure it's readable by the web server
            }
        }
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Admin check_updates.php error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database error',
        'hasUpdates' => false
    ]);
}
?>
