<?php
session_start();
include('conflogin.php'); // Ensure user is authenticated

// Simple WebDAV URLs to try
$webdav_urls = [
    'https://infocloud.ddns.net:5001/docs/',
];

$username = 'Nas';
$password = '/*2025IE+';

if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    die('File parameter missing');
}

$filename = $_GET['file'];

// Try each URL until one works
foreach ($webdav_urls as $base_url) {
    $file_url = $base_url . $filename;
    
    $ch = curl_init($file_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $file_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $file_content !== false && strlen($file_content) > 0) {
        // File found! Send it to browser
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($file_content));
        
        echo $file_content;
        exit;
    }
}

// If we get here, file wasn't found in any location
echo "<h3>File Not Found</h3>";
echo "<p>File: $filename</p>";
echo "<p>Tried these locations:</p><ul>";
foreach ($webdav_urls as $url) {
    echo "<li>$url$filename</li>";
}
echo "</ul>";
?>
