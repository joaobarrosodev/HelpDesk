<?php
session_start();
include('conflogin.php'); // Ensure user is authenticated

// Simple WebDAV URLs to try for uploads - try multiple paths
$webdav_urls = [
    'https://infocloud.ddns.net:5001/uploads/',
    'https://infocloud.ddns.net:5001/remote.php/webdav/uploads/',
    'https://infocloud.ddns.net:5001/remote.php/webdav/uploads/',
];

$username = 'Nas';
$password = '/*2025IE+';

if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    die('File parameter missing');
}

$filename = $_GET['file'];

// Debug mode - show which URLs we're trying
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

if ($debug) {
    echo "<h3>Debug Mode: Testing URLs for file: " . htmlspecialchars($filename) . "</h3>";
}

// Try each URL until one works
foreach ($webdav_urls as $base_url) {
    $file_url = $base_url . $filename;
    
    if ($debug) {
        echo "<p>Trying: <strong>" . htmlspecialchars($file_url) . "</strong></p>";
    }
    
    $ch = curl_init($file_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($debug) {
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, fopen('php://output', 'w'));
    }
    
    $file_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($debug) {
        echo "<p>HTTP Code: <strong>$http_code</strong></p>";
        echo "<p>Content Type: <strong>$content_type</strong></p>";
        echo "<p>Content Length: <strong>" . strlen($file_content) . "</strong></p>";
        if ($curl_error) {
            echo "<p>cURL Error: <strong>$curl_error</strong></p>";
        }
        echo "<hr>";
    }
    
    if ($http_code === 200 && $file_content !== false && strlen($file_content) > 0) {
        if ($debug) {
            echo "<p style='color: green;'><strong>SUCCESS! File found at: $file_url</strong></p>";
            exit;
        }
        
        // Determine content type based on file extension if not provided
        if (!$content_type || $content_type === 'application/octet-stream') {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $content_type = 'image/jpeg';
                    break;
                case 'png':
                    $content_type = 'image/png';
                    break;
                case 'gif':
                    $content_type = 'image/gif';
                    break;
                case 'pdf':
                    $content_type = 'application/pdf';
                    break;
                default:
                    $content_type = 'application/octet-stream';
            }
        }
        
        // File found! Send it to browser
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($file_content));
        
        echo $file_content;
        exit;
    }
}

// If we get here, file wasn't found in any location
echo "<h3>File Not Found</h3>";
echo "<p>File: " . htmlspecialchars($filename) . "</p>";
echo "<p>Tried these locations:</p><ul>";
foreach ($webdav_urls as $url) {
    echo "<li>" . htmlspecialchars($url . $filename) . "</li>";
}
echo "</ul>";
echo "<p><a href='?file=" . urlencode($filename) . "&debug=1'>Enable Debug Mode</a></p>";
?>
