<?php
// This file handles WebSocket server actions
session_start();

// Basic security check
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include the auto-start file so we can use its functions
include('auto-start.php');

// Get the requested action
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'start':
        // Try to start the WebSocket server
        if (startWebSocketServer()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'WebSocket server started successfully.'
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Failed to start WebSocket server. Check the log for details.'
            ]);
        }
        break;
        
    case 'reset':
        // Reset the attempt counter
        $_SESSION['ws_start_attempts'] = 0;
        $_SESSION['ws_reset_time'] = time();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Attempt counter reset successfully.'
        ]);
        break;
        
    case 'log':
        // Output the WebSocket log file content
        $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'ws-auto-start.log';
        
        header('Content-Type: text/plain');
        
        if (file_exists($logFile)) {
            // Get the last 100 lines
            $lines = file($logFile);
            $lines = array_slice($lines, -100);
            echo implode('', $lines);
        } else {
            echo "Log file does not exist.";
        }
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Invalid action',
            'valid_actions' => ['start', 'reset', 'log']
        ]);
}
?>
