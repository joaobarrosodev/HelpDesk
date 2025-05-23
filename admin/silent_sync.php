<?php
session_start();
include('../db.php');

// Set JSON header
header('Content-Type: application/json');

// Disable any error output
error_reporting(0);
ini_set('display_errors', 0);

// Check for required parameters
if (!isset($_GET['ticketId']) || !isset($_GET['lastCheck'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$ticketId = $_GET['ticketId'];
$lastCheck = $_GET['lastCheck'];
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : null;

try {
    // Query for new messages since last check
    $sql = "SELECT c.Message, c.type, c.Date as CommentTime, c.user, c.id as messageId
            FROM comments_xdfree01_extrafields c
            WHERE c.XDFree01_KeyID = :ticketId
            AND c.Date > :lastCheck
            ORDER BY c.Date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':ticketId', $ticketId);
    $stmt->bindParam(':lastCheck', $lastCheck);
    $stmt->execute();
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if there are new messages
    if (count($messages) > 0) {
        // Add unique message IDs for deduplication
        foreach ($messages as &$message) {
            $message['messageId'] = 'db_' . $message['messageId'];
        }
        
        echo json_encode([
            'hasNewMessages' => true,
            'messages' => $messages,
            'count' => count($messages)
        ]);
    } else {
        echo json_encode([
            'hasNewMessages' => false,
            'messages' => [],
            'count' => 0
        ]);
    }
    
} catch (Exception $e) {
    error_log('Error in admin/silent_sync.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>
