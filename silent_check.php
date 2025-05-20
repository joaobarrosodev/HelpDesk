<?php
session_start();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');

// Get and validate the ticket ID
$ticketId = isset($_GET['ticketId']) ? trim($_GET['ticketId']) : '';
$lastCheck = isset($_GET['lastCheck']) ? $_GET['lastCheck'] : '';
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : '';

// Check if ticket ID is empty
if (empty($ticketId)) {
    echo json_encode(['error' => 'Missing ticket ID']);
    exit;
}

// Set up response structure
$result = [
    'hasNewMessages' => false,
    'messages' => [],
    'timestamp' => date('Y-m-d H:i:s'),
    'ticketId' => $ticketId
];

// Try to get the real KeyId from database if needed
include('db.php');
try {
    if (!preg_match('/^#/', $ticketId)) {
        $stmt = $pdo->prepare("SELECT KeyId FROM xdfree01 WHERE id = :id");
        $stmt->bindParam(':id', $ticketId);
        $stmt->execute();
        $actualKeyId = $stmt->fetchColumn();
        
        if ($actualKeyId) {
            $ticketId = $actualKeyId;
            $result['ticketId'] = $actualKeyId;
        }
    }
    
    // Check for new messages from database
    if (!empty($lastCheck)) {
        $sql = "SELECT c.id, c.Message, c.type, c.Date as CommentTime, c.user 
                FROM comments_xdfree01_extrafields c 
                WHERE c.XDFree01_KeyID = :ticketId 
                AND c.Date > :lastCheck 
                ORDER BY c.Date ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':ticketId', $ticketId);
        $stmt->bindParam(':lastCheck', $lastCheck);
        $stmt->execute();
        
        $dbMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($dbMessages) > 0) {
            $result['messages'] = $dbMessages;
            $result['hasNewMessages'] = true;
        }
    }
    
    // Get current ticket status
    $sql_status = "SELECT Status FROM info_xdfree01_extrafields WHERE XDFree01_KeyID = :keyid";
    $stmt_status = $pdo->prepare($sql_status);
    $stmt_status->bindParam(':keyid', $ticketId);
    $stmt_status->execute();
    $currentStatus = $stmt_status->fetchColumn();
    
    $result['currentStatus'] = $currentStatus;
    
} catch (Exception $e) {
    // Silent error handling
}

echo json_encode($result);
?>
