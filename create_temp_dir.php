<?php
/**
 * Simple helper to create temp directory for WebSocket messages
 */

header('Content-Type: application/json');

$tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'temp';
$result = ['success' => false];

if (!file_exists($tempDir)) {
    $created = @mkdir($tempDir, 0777, true);
    $result['created'] = $created;
    if ($created) {
        @chmod($tempDir, 0777);
        $result['success'] = true;
        $result['message'] = 'Temp directory created successfully';
    } else {
        $result['error'] = 'Failed to create temp directory';
        $result['path'] = $tempDir;
    }
} else {
    $result['success'] = true;
    $result['message'] = 'Temp directory already exists';
    
    // Check if directory is writable
    $result['writable'] = is_writable($tempDir);
    
    // Try to create a test file
    $testFile = $tempDir . DIRECTORY_SEPARATOR . 'test_' . time() . '.txt';
    $writeTest = @file_put_contents($testFile, 'Test');
    $result['writeTest'] = ($writeTest !== false);
    
    if ($writeTest !== false) {
        // Clean up test file
        @unlink($testFile);
    }
}

echo json_encode($result);
