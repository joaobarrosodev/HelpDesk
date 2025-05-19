<?php
/**
 * WebSocket Manager - Functions to interact with the WebSocket server
 */

/**
 * Broadcasts a message to all clients connected to a specific ticket
 *
 * @param string $ticketId The ID of the ticket
 * @param array $messageData The message data to broadcast
 * @return bool Success status
 */
function broadcastMessageToWebSocket($ticketId, $messageData) {
    // WebSocket server address
    $host = "localhost";
    $port = 8080;
    
    // Create a socket connection
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!$socket) {
        error_log('Unable to create socket: ' . socket_strerror(socket_last_error()));
        return false;
    }

    // Set socket options
    socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 1, 'usec' => 0));
    socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 1, 'usec' => 0));

    // Try to connect to the WebSocket server
    $result = @socket_connect($socket, $host, $port);
    if (!$result) {
        error_log('WebSocket server not running on ' . $host . ':' . $port);
        socket_close($socket);
        return false;
    }

    // Prepare the WebSocket handshake
    $key = base64_encode(openssl_random_pseudo_bytes(16));
    $headers = "GET / HTTP/1.1\r\n" .
               "Host: " . $host . ":" . $port . "\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Key: " . $key . "\r\n" .
               "Sec-WebSocket-Version: 13\r\n\r\n";

    // Send the headers
    socket_write($socket, $headers, strlen($headers));

    // Read the response
    $response = socket_read($socket, 2048);
    if (!$response) {
        error_log('No response from WebSocket server');
        socket_close($socket);
        return false;
    }

    // Create message payload
    $payload = json_encode([
        'action' => 'newMessage',
        'ticketId' => $ticketId,
        'message' => $messageData
    ]);
    
    // Frame the payload according to WebSocket protocol
    $frameHead = [];
    $payloadLength = strlen($payload);
    
    $frameHead[0] = 129; // FIN + text frame (0x81)
    
    if ($payloadLength <= 125) {
        $frameHead[1] = $payloadLength;
    } else if ($payloadLength <= 65535) {
        $frameHead[1] = 126;
        $frameHead[2] = ($payloadLength >> 8) & 255;
        $frameHead[3] = $payloadLength & 255;
    } else {
        $frameHead[1] = 127;
        for ($i = 0; $i < 8; $i++) { // Fixed for loop syntax here
            $frameHead[$i + 2] = ($payloadLength >> ((7 - $i) * 8)) & 255;
        }
    }
    
    // Convert frameHead to string
    $header = '';
    foreach ($frameHead as $byte) {
        $header .= chr($byte);
    }
    
    // Send the framed message
    $framedMessage = $header . $payload;
    socket_write($socket, $framedMessage, strlen($framedMessage));
    
    // Close the socket
    socket_close($socket);
    
    return true;
}
