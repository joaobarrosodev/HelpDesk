<?php
/**
 * WebSocket and Chat System Health Check
 * This script checks the health of the WebSocket server and the database connection
 * It can be called via AJAX or CLI (command line)
 */

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');
define('LOG_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'logs');
define('HEALTH_LOG', LOG_DIR . DIRECTORY_SEPARATOR . 'health.log');

// Create directories if they don't exist
if (!file_exists(TEMP_DIR)) {
    @mkdir(TEMP_DIR, 0777, true);
}

if (!file_exists(LOG_DIR)) {
    @mkdir(LOG_DIR, 0777, true);
}

// Initialize response
$response = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check 1: WebSocket server
function isWsServerRunning() {
    $socket = @fsockopen('localhost', 8080, $errno, $errstr, 1);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}

$wsRunning = isWsServerRunning();
$response['checks']['websocket'] = [
    'status' => $wsRunning ? 'ok' : 'error',
    'message' => $wsRunning ? 'WebSocket server is running' : 'WebSocket server is not running'
];

if (!$wsRunning) {
    $response['status'] = 'warning';
    
    // Try to start the server if not running
    $startFlag = TEMP_DIR . DIRECTORY_SEPARATOR . 'ws-server-starting.flag';
    $flagTime = file_exists($startFlag) ? @filemtime($startFlag) : 0;
    
    // Don't try to start if we've attempted recently (last 60 seconds)
    if (!$flagTime || (time() - $flagTime) > 60) {
        // Create the startup flag file
        @file_put_contents($startFlag, date('Y-m-d H:i:s'));
        @chmod($startFlag, 0666);
        
        // Try to start the server
        $path = dirname(__FILE__);
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @pclose(popen('start /B "HelpDesk WS" cmd /c "cd /D ' . $path . ' && php ws-server.php > ws-server.log 2>&1"', 'r'));
            $response['checks']['websocket']['message'] .= '. Attempting to start...';
            $response['checks']['websocket']['actionTaken'] = 'restart_attempted';
        } else {
            @exec('cd ' . $path . ' && nohup php ws-server.php > ws-server.log 2>&1 &');
            $response['checks']['websocket']['message'] .= '. Attempting to start...';
            $response['checks']['websocket']['actionTaken'] = 'restart_attempted';
        }
    } else {
        $response['checks']['websocket']['message'] .= '. Recent restart attempt detected, waiting...';
        $response['checks']['websocket']['actionTaken'] = 'waiting';
    }
}

// Check 2: Database connection
try {
    require_once('db.php');
    
    // Simple query to test connection
    $stmt = $pdo->query("SELECT 1");
    $dbConnected = ($stmt !== false);
    
    $response['checks']['database'] = [
        'status' => $dbConnected ? 'ok' : 'error',
        'message' => $dbConnected ? 'Database connection is working' : 'Database connection failed'
    ];
    
    if (!$dbConnected && $response['status'] !== 'error') {
        $response['status'] = 'error';
    }
    
    // Check message table
    if ($dbConnected) {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'comments_xdfree01_extrafields'");
        $tableExists = $tableCheck->rowCount() > 0;
        
        $response['checks']['message_table'] = [
            'status' => $tableExists ? 'ok' : 'error',
            'message' => $tableExists ? 'Message table exists' : 'Message table does not exist'
        ];
        
        if (!$tableExists && $response['status'] !== 'error') {
            $response['status'] = 'error';
        }
    }
} catch (Exception $e) {
    $response['checks']['database'] = [
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ];
    $response['status'] = 'error';
}

// Check 3: Temp directory
$tempDirExists = file_exists(TEMP_DIR);
$tempDirWritable = $tempDirExists && is_writable(TEMP_DIR);
$response['checks']['temp_dir'] = [
    'status' => $tempDirWritable ? 'ok' : 'error',
    'message' => $tempDirWritable ? 'Temp directory is writable' : 
                  ($tempDirExists ? 'Temp directory exists but is not writable' : 'Temp directory does not exist')
];

if (!$tempDirWritable && $response['status'] !== 'error') {
    $response['status'] = 'warning';
}

// Check 4: File permissions
$syncFilesCount = 0;
$oldSyncFilesCount = 0;

if ($tempDirExists) {
    $syncFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . 'sync_*.txt');
    $syncFilesCount = is_array($syncFiles) ? count($syncFiles) : 0;
    
    // Count old sync files (older than 5 minutes)
    if ($syncFilesCount > 0) {
        foreach ($syncFiles as $file) {
            if (time() - @filemtime($file) > 300) {
                $oldSyncFilesCount++;
            }
        }
    }
    
    $response['checks']['sync_files'] = [
        'status' => ($oldSyncFilesCount > 10) ? 'warning' : 'ok',
        'message' => $syncFilesCount . ' sync files found (' . $oldSyncFilesCount . ' old files)'
    ];
    
    if ($oldSyncFilesCount > 10 && $response['status'] === 'ok') {
        $response['status'] = 'warning';
    }
    
    // Auto cleanup if too many old files
    if ($oldSyncFilesCount > 20) {
        $cleaned = 0;
        foreach ($syncFiles as $file) {
            if (time() - @filemtime($file) > 300) {
                @unlink($file);
                $cleaned++;
            }
        }
        $response['checks']['sync_files']['actionTaken'] = 'cleanup';
        $response['checks']['sync_files']['cleaned'] = $cleaned;
    }
}

// Log the health check results
$logEntry = date('Y-m-d H:i:s') . ' - Status: ' . $response['status'] . "\n";
foreach ($response['checks'] as $check => $data) {
    $logEntry .= "  - $check: " . $data['status'] . ' - ' . $data['message'] . "\n";
}
$logEntry .= "\n";

@file_put_contents(HEALTH_LOG, $logEntry, FILE_APPEND);

// Return response based on request type
if (php_sapi_name() === 'cli') {
    // CLI output
    echo "WebSocket Health Check - " . date('Y-m-d H:i:s') . "\n";
    echo "Overall Status: " . strtoupper($response['status']) . "\n\n";
    
    foreach ($response['checks'] as $check => $data) {
        echo strtoupper($data['status']) . " - $check: " . $data['message'] . "\n";
        if (isset($data['actionTaken'])) {
            echo "  Action: " . $data['actionTaken'] . "\n";
        }
    }
} else {
    // Web/AJAX output
    header('Content-Type: application/json');
    echo json_encode($response);
}
