<?php
/**
 * Simple script to create temp directory for WebSocket messages
 */
header('Content-Type: application/json');

// Define temp directory path
$tempDir = __DIR__ . '/temp';

// Create directory if it doesn't exist
if (!file_exists($tempDir)) {
    $created = mkdir($tempDir, 0777, true);
    if ($created) {
        // Try to set permissions (may not work on Windows)
        @chmod($tempDir, 0777);
        echo json_encode([
            'success' => true, 
            'message' => 'Temp directory created successfully', 
            'path' => $tempDir
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to create temp directory',
            'path' => $tempDir
        ]);
    }
} else {
    // Directory already exists
    echo json_encode([
        'success' => true, 
        'message' => 'Temp directory already exists',
        'path' => $tempDir
    ]);
}

// Clean up old files while we're here
$oldFiles = array_merge(
    glob($tempDir . '/sync_*_*.txt'),
    glob($tempDir . '/ws_message_*.json')
);

$removedCount = 0;
foreach ($oldFiles as $file) {
    // Remove files older than 5 minutes
    if (time() - filemtime($file) > 300) {
        @unlink($file);
        $removedCount++;
    }
}

if ($removedCount > 0) {
    error_log("Removed $removedCount old files from temp directory");
}
