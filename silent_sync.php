<?php
session_start();
header('Content-Type: application/json');

// Security check without logging
if (!isset($_SESSION['usuario_email'])) {
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

// Process sync files
if (is_array($syncFiles)) {
    foreach ($syncFiles as $file) {
        if (file_exists($file) && is_readable($file)) {
            // Only consider files that have been created in the last 60 seconds
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
                // Clean up old sync files
                @unlink($file);
            }
        }
    }
}

// No debug information added
echo json_encode($result);
?>
