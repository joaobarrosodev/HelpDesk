<?php
session_start();
header('Content-Type: application/json');

// Security check without logging
if (!isset($_SESSION['usuario_email']) && !isset($_SESSION['admin_email'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');
define('DEBUG', false); // Disable debugging

// Create temp directory if it doesn't exist
if (!file_exists(TEMP_DIR)) {
    @mkdir(TEMP_DIR, 0777, true);
    @chmod(TEMP_DIR, 0777);
}

// Get and validate the ticket ID
$ticketId = isset($_GET['ticketId']) ? trim($_GET['ticketId']) : '';
$ticketId = str_replace('%23', '#', $ticketId);

// Check if ticket ID is empty
if (empty($ticketId)) {
    echo json_encode(['error' => 'Missing ticket ID']);
    exit;
}

// Clean the ticket ID for internal use
$cleanTicketId = str_replace('#', '', $ticketId);

// Get other parameters
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : '';
$lastCheck = isset($_GET['lastCheck']) ? $_GET['lastCheck'] : '';

$result = [
    'hasNewMessages' => false,
    'messages' => [],
    'sourceFiles' => [],
    'timestamp' => date('Y-m-d H:i:s'),
    'deviceId' => $deviceId,
    'ticketId' => $ticketId
];

// Track processed files to prevent re-processing
$processedFiles = [];

// Now check for sync files
$syncFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . "sync_{$cleanTicketId}_*.txt");

// Add alternate format (with hash)
$ticketIdWithHash = '#' . $cleanTicketId;
$moreFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . "sync_{$ticketIdWithHash}_*.txt");
if (is_array($moreFiles)) {
    $syncFiles = array_merge($syncFiles, $moreFiles);
}

// Also check with original format if it's different from both clean and hash formats
if ($ticketId !== $cleanTicketId && $ticketId !== $ticketIdWithHash) {
    $originalFormatFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . "sync_{$ticketId}_*.txt");
    if (is_array($originalFormatFiles)) {
        $syncFiles = array_merge($syncFiles, $originalFormatFiles);
    }
}

// Remove duplicates and sort by creation time
if (is_array($syncFiles)) {
    $syncFiles = array_unique($syncFiles);
    
    // Sort by file modification time (oldest first)
    usort($syncFiles, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
}

// Process sync files
if (is_array($syncFiles)) {
    foreach ($syncFiles as $file) {
        if (file_exists($file) && is_readable($file)) {
            // Only consider files that have been created in the last 300 seconds (5 minutes)
            $fileTime = @filemtime($file);
            if ($fileTime && (time() - $fileTime < 300)) {
                $content = @file_get_contents($file);
                if ($content) {
                    $data = json_decode($content, true);
                    if ($data && isset($data['message'])) {
                        $message = $data['message'];
                        
                        // SIMPLIFIED: Only skip if same device sent this message
                        $sourceDeviceId = isset($message['sourceDeviceId']) ? $message['sourceDeviceId'] : null;
                        
                        // Only skip if this exact device sent this message
                        if ($deviceId && $deviceId === $sourceDeviceId) {
                            continue;
                        }
                        
                        // Check if we already processed this message (by messageId)
                        $messageId = isset($message['messageId']) ? $message['messageId'] : null;
                        if ($messageId && in_array($messageId, $processedFiles)) {
                            continue;
                        }
                        
                        // Filter by timestamp if lastCheck is provided - but allow some overlap
                        if ($lastCheck && isset($message['CommentTime'])) {
                            $messageTime = strtotime($message['CommentTime']);
                            $lastCheckTime = strtotime($lastCheck);
                            
                            // Allow 5 second overlap to prevent missing messages
                            if ($messageTime < ($lastCheckTime - 5)) {
                                continue;
                            }
                        }
                        
                        $result['messages'][] = $message;
                        $result['hasNewMessages'] = true;
                        
                        if ($messageId) {
                            $processedFiles[] = $messageId;
                        }
                    }
                }
                
                // CHANGED: Don't delete sync file immediately, let it exist for a bit
                // Only clean up after file is older than 2 minutes
                if (time() - $fileTime > 120) {
                    @unlink($file);
                }
                
            } else {
                // Clean up old sync files (older than 5 minutes)
                @unlink($file);
            }
        }
    }
}

// No debug information added
echo json_encode($result);