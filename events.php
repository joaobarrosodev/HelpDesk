<?php
session_start();
include('conflogin.php');
include('db.php');

// Required headers for Server-Sent Events
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // For NGINX

// Verify the user is authenticated
if (!isset($_SESSION['usuario_email'])) {
    echo "event: error\n";
    echo "data: " . json_encode(['message' => 'Unauthorized']) . "\n\n";
    exit;
}

// Get ticket_id and last timestamp
if (!isset($_GET['keyid']) || !isset($_GET['timestamp'])) {
    echo "event: error\n";
    echo "data: " . json_encode(['message' => 'Missing parameters']) . "\n\n";
    exit;
}

$keyid = $_GET['keyid'];
$timestamp = $_GET['timestamp'];

// Initial response
echo "event: connected\n";
echo "data: " . json_encode(['ticket_id' => $keyid]) . "\n\n";
ob_flush();
flush();

// Keep checking for new messages and send them as events
while (true) {
    // Check for new messages
    $sql = "SELECT Message, type, Date as CommentTime, user
           FROM comments_xdfree01_extrafields
           WHERE XDFree01_KeyID = :keyid
           AND Date > :timestamp
           ORDER BY Date ASC";
           
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':keyid', $keyid);
    $stmt->bindParam(':timestamp', $timestamp);
    $stmt->execute();
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lastTimestamp = $timestamp;  // Default to the original timestamp
    
    if (!empty($messages)) {
        $lastMessage = end($messages);
        $lastTimestamp = $lastMessage['CommentTime'];
        
        // Send messages as events
        echo "event: messages\n";
        echo "data: " . json_encode([
            'messages' => $messages,
            'lastTimestamp' => $lastTimestamp
        ]) . "\n\n";
        
        ob_flush();
        flush();
        
        // Update timestamp to only get new messages in the next check
        $timestamp = $lastTimestamp;
    }
    
    // Reduced wait time for more frequent checks since there's no notification sound
    usleep(500000); // 0.5 seconds instead of 1 second
    
    // Send a ping event to keep the connection alive
    echo "event: ping\n";
    echo "data: " . json_encode(['time' => time()]) . "\n\n";
    ob_flush();
    flush();
}
?>
