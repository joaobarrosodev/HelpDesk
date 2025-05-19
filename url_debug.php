<?php
header('Content-Type: application/json');

$result = [
    'get_params' => $_GET,
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not available',
    'query_string' => $_SERVER['QUERY_STRING'] ?? 'not available',
    'raw_inputs' => file_get_contents('php://input'),
    'server_vars' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'not available',
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not available',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'not available'
    ]
];

echo json_encode($result, JSON_PRETTY_PRINT);
?>
