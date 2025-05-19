<?php
session_start();

// Make sure we're authorized
if (!isset($_SESSION['usuario_email'])) {
    $_SESSION['usuario_email'] = 'test@example.com'; // Set a temporary session for testing
}

// Create initial output without any echo statements
ob_start();

// Clear the buffer and set JSON header
ob_end_clean();
header('Content-Type: application/json');

// Define constants needed for check_sync.php
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');
define('DEBUG', true);

// Get ticket ID from query string if available
$ticketId = isset($_GET['ticketId']) ? $_GET['ticketId'] : '#011';
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : 'testdevice';
$lastCheck = isset($_GET['lastCheck']) ? $_GET['lastCheck'] : date('Y-m-d H:i:s');

// Process ticket ID similar to check_sync.php
$ticketId = str_replace('%23', '#', $ticketId);
$cleanTicketId = str_replace('#', '', $ticketId);
$ticketIdWithHash = '#' . $cleanTicketId;

// Define test response - simplified version with NO debugging information
$response = [
    'hasNewMessages' => false,
    'messages' => [],
    'sourceFiles' => [],
    'timestamp' => date('Y-m-d H:i:s'),
    'deviceId' => $deviceId,
    'ticketId' => $ticketId
    // No debug information at all
];

// Output clean JSON without any console logging
echo json_encode($response);
?>
