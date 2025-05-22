<?php
// Simple script to create the temp directory
header('Content-Type: application/json');

$tempDir = __DIR__ . DIRECTORY_SEPARATOR . 'temp';
$result = ['success' => false, 'message' => ''];

if (!file_exists($tempDir)) {
    $created = @mkdir($tempDir, 0777, true);
    if ($created) {
        @chmod($tempDir, 0777);
        $result = ['success' => true, 'message' => 'Temp directory created'];
    } else {
        $result = ['success' => false, 'message' => 'Failed to create temp directory'];
    }
} else {
    $result = ['success' => true, 'message' => 'Temp directory already exists'];
}

echo json_encode($result);