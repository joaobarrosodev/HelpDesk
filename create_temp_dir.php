<?php
header('Content-Type: application/json');

$tempDir = __DIR__ . '/temp';
$logsDir = __DIR__ . '/logs';

$result = [
    'success' => true,
    'messages' => []
];

// Create temp directory
if (!file_exists($tempDir)) {
    if (@mkdir($tempDir, 0777, true)) {
        $result['messages'][] = 'Temp directory created successfully';
        @chmod($tempDir, 0777);
    } else {
        $result['success'] = false;
        $result['messages'][] = 'Failed to create temp directory';
    }
} else {
    $result['messages'][] = 'Temp directory already exists';
}

// Create logs directory
if (!file_exists($logsDir)) {
    if (@mkdir($logsDir, 0777, true)) {
        $result['messages'][] = 'Logs directory created successfully';
        @chmod($logsDir, 0777);
    } else {
        $result['success'] = false;
        $result['messages'][] = 'Failed to create logs directory';
    }
} else {
    $result['messages'][] = 'Logs directory already exists';
}

// Clean old temp files (older than 1 hour)
if (is_dir($tempDir)) {
    $files = glob($tempDir . '/*');
    $cleaned = 0;
    foreach ($files as $file) {
        if (is_file($file) && time() - filemtime($file) > 3600) {
            if (@unlink($file)) {
                $cleaned++;
            }
        }
    }
    if ($cleaned > 0) {
        $result['messages'][] = "Cleaned $cleaned old temp files";
    }
}

echo json_encode($result);
?>
