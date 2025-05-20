<?php
// Message synchronization handler
session_start();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection
include('db.php');

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');

// Create temp directory if it doesn't exist
if (!file_exists(TEMP_DIR)) {
    @mkdir(TEMP_DIR, 0777, true);
    @chmod(TEMP_DIR, 0777);
}

// Get parameters
$ticketId = isset($_GET['ticketId']) ? trim($_GET['ticketId']) : '';
$lastCheck = isset($_GET['lastCheck']) ? $_GET['lastCheck'] : date('Y-m-d H:i:s', strtotime('-5 minutes'));
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : 'device_' . session_id();

if (empty($ticketId)) {
    echo json_encode(['error' => 'Missing ticket ID']);
    exit;
}

try {
    // First, get the actual KeyId if necessary
    if (!preg_match('/^#/', $ticketId)) {
        $stmt_keyid = $pdo->prepare("SELECT KeyId FROM xdfree01 WHERE id = :id");
        $stmt_keyid->bindParam(':id', $ticketId);
        $stmt_keyid->execute();
        $actualKeyId = $stmt_keyid->fetchColumn();
        
        if ($actualKeyId) {
            $ticketId = $actualKeyId;
        }
    }
    
    // Query for new messages
    $sql = "SELECT c.id, c.Message, c.type, c.Date as CommentTime, c.user 
            FROM comments_xdfree01_extrafields c
            WHERE c.XDFree01_KeyID = :ticketId 
            AND c.Date > :lastCheck 
            ORDER BY c.Date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':ticketId', $ticketId);
    $stmt->bindParam(':lastCheck', $lastCheck);
    $stmt->execute();
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current ticket status
    $sql_status = "SELECT Status FROM info_xdfree01_extrafields WHERE XDFree01_KeyID = :ticketId";
    $stmt_status = $pdo->prepare($sql_status);
    $stmt_status->bindParam(':ticketId', $ticketId);
    $stmt_status->execute();
    $currentStatus = $stmt_status->fetchColumn();
    
    // Build response
    $response = [
        'hasNewMessages' => count($messages) > 0,
        'messages' => $messages,
        'ticketId' => $ticketId,
        'currentStatus' => $currentStatus,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error',
        'hasNewMessages' => false
    ]);
}
?>
