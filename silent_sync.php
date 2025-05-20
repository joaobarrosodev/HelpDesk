<?php
session_start();
header('Content-Type: application/json');

// Security check without logging
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');

// Create temp directory if it doesn't exist
if (!file_exists(TEMP_DIR)) {
    @mkdir(TEMP_DIR, 0777, true);
    @chmod(TEMP_DIR, 0777);
}

// Get and validate the ticket ID
$ticketId = isset($_GET['ticketId']) ? trim($_GET['ticketId']) : '';
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : '';
$lastCheck = isset($_GET['lastCheck']) ? $_GET['lastCheck'] : '';

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
    'deviceId' => $deviceId,
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
} catch (Exception $e) {
    // Silent error handling
}

// Look for sync files
$possibleFormats = [
    $ticketId,                      // Original format
    str_replace('#', '', $ticketId), // Without hash
    '#' . str_replace('#', '', $ticketId) // With hash
];

$syncFiles = [];
foreach ($possibleFormats as $format) {
    $pattern = TEMP_DIR . DIRECTORY_SEPARATOR . "sync_{$format}_*.txt";
    $files = glob($pattern);
    if (is_array($files)) {
        $syncFiles = array_merge($syncFiles, $files);
    }
}

// Process sync files
if (is_array($syncFiles)) {
    foreach ($syncFiles as $file) {
        if (file_exists($file) && is_readable($file)) {
            // Only consider recent files
            $fileTime = @filemtime($file);
            if ($fileTime && (time() - $fileTime < 60)) {
                $content = @file_get_contents($file);
                if ($content) {
                    $data = json_decode($content, true);
                    if ($data && isset($data['message'])) {
                        // Don't include messages from this device
                        $sourceDeviceId = isset($data['message']['sourceDeviceId']) ? $data['message']['sourceDeviceId'] : null;
                        if (!$deviceId || $deviceId !== $sourceDeviceId) {
                            $result['messages'][] = $data['message'];
                            $result['hasNewMessages'] = true;
                        }
                    }
                }
            } else {
                // Clean up old files
                @unlink($file);
            }
        }
    }
}

// Also check the database for new messages as a fallback
if (!$result['hasNewMessages'] && !empty($lastCheck)) {
    try {
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
            foreach ($dbMessages as $message) {
                $message['messageId'] = 'db_' . $message['id'] . '_' . time();
                $result['messages'][] = $message;
                $result['hasNewMessages'] = true;
            }
        }
    } catch (Exception $e) {
        // Silent error handling
    }
}

// Check if ticket status has changed
try {
    $sql_status = "SELECT Status FROM info_xdfree01_extrafields WHERE XDFree01_KeyID = :keyid";
    $stmt_status = $pdo->prepare($sql_status);
    $stmt_status->bindParam(':keyid', $ticketId);
    $stmt_status->execute();
    $currentStatus = $stmt_status->fetchColumn();
    
    $result['currentStatus'] = $currentStatus;
} catch (Exception $e) {
    // Silent error
}

echo json_encode($result);
?>
