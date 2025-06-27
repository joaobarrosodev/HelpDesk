<?php
// silent_sync.php - Handle polling for new ticket messages
// Ensure clean output by capturing everything
ob_start();

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response array
$response = [
    'hasNewMessages' => false,
    'messages' => [],
    'timestamp' => date('Y-m-d H:i:s'),
    'error' => null
];

try {
    // Start session and include required files
    session_start();
    include('conflogin.php');
    include('db.php');
    
    // Log request for debugging
    error_log("silent_sync.php accessed - Params: " . print_r($_GET, true));
    
    // Check for required parameters
    if (!isset($_GET['ticketId']) || !isset($_GET['deviceId']) || !isset($_GET['lastCheck'])) {
        throw new Exception('Missing required parameters');
    }
    
    $ticketId = $_GET['ticketId'];
    $deviceId = $_GET['deviceId'];
    $lastCheck = $_GET['lastCheck'];
    
    // Query to fetch new messages since last check
    $sql = "SELECT c.Message, c.type, c.Date as CommentTime, c.user, c.Id 
            FROM comments_xdfree01_extrafields c
            WHERE c.XDFree01_KeyID = :ticketId 
            AND c.Date > :lastCheck
            ORDER BY c.Date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':ticketId', $ticketId);
    $stmt->bindParam(':lastCheck', $lastCheck);
    $stmt->execute();
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update response
    $response['hasNewMessages'] = !empty($messages);
    $response['messages'] = $messages;
    $response['timestamp'] = date('Y-m-d H:i:s');
    
    // Log results for debugging
    error_log("silent_sync.php - Found " . count($messages) . " new messages");

} catch (Exception $e) {
    error_log("silent_sync.php error: " . $e->getMessage());
    $response['error'] = 'Error: ' . $e->getMessage();
} 

// Clean any output buffer to prevent contamination
ob_end_clean();

// Send clean JSON response
echo json_encode($response);
exit;
?>