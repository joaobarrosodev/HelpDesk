<?php
session_start();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['usuario_email'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');
define('DEBUG', false);

// Create temp directory if it doesn't exist
if (!file_exists(TEMP_DIR)) {
    @mkdir(TEMP_DIR, 0777, true);
    @chmod(TEMP_DIR, 0777);
}

// Get parameters
$ticketId = isset($_GET['ticketId']) ? trim($_GET['ticketId']) : null;
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : null;
$lastCheck = isset($_GET['lastCheck']) ? $_GET['lastCheck'] : null;

if (!$ticketId) {
    echo json_encode(['error' => 'Missing ticket ID']);
    exit;
}

$result = [
    'hasNewMessages' => false,
    'messages' => [],
    'sourceFiles' => [],
    'timestamp' => date('Y-m-d H:i:s'),
    'deviceId' => $deviceId,
    'ticketId' => $ticketId
];

// Process any WebSocket message files first
$messageFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . 'ws_message_*.json');
if (!empty($messageFiles)) {
    if (DEBUG) error_log("Found " . count($messageFiles) . " pending WebSocket messages to process");
    include_once __DIR__ . DIRECTORY_SEPARATOR . 'process_ws_messages.php';
    if (function_exists('processAllMessageFiles')) {
        processAllMessageFiles();
    }
}

// Now check for sync files
$cleanTicketId = str_replace('#', '', $ticketId);
$syncFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . "sync_{$cleanTicketId}_*.txt");

// Add alternate format (with hash)
if (strpos($ticketId, '#') === 0) {
    $moreFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . "sync_{$ticketId}_*.txt");
    if (is_array($moreFiles)) {
        $syncFiles = array_merge($syncFiles, $moreFiles);
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
                            $result['sourceFiles'][] = $file;
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

// Add debug information
if (DEBUG) {
    $result['debug'] = [
        'syncFilesCount' => is_array($syncFiles) ? count($syncFiles) : 0,
        'syncPattern' => TEMP_DIR . DIRECTORY_SEPARATOR . "sync_{$cleanTicketId}_*.txt", 
        'syncFilesFound' => is_array($syncFiles) ? array_map('basename', $syncFiles) : [],
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
}

echo json_encode($result);
