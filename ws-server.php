<?php
require 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;

// Define CLI_MODE to handle both CLI and HTTP requests
define('CLI_MODE', php_sapi_name() === 'cli');

// Include database connection
require_once __DIR__ . '/db.php';

class ChatServer implements MessageComponentInterface {
    protected $clients;
    protected $subscriptions;
    protected $pdo;
    protected $tempDir;
    
    public function __construct($pdo) {
        $this->clients = new \SplObjectStorage;
        $this->subscriptions = [];
        $this->pdo = $pdo;
        $this->tempDir = __DIR__ . '/temp';
        
        echo "HelpDesk Chat Server Started\n";
        echo "Listening on port 8080\n";
        echo date('[Y-m-d H:i:s]') . " Server initialized\n";
        
        // Create temp directory if needed
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
        
        // Check for temp messages periodically
        Loop::addPeriodicTimer(1, [$this, 'processTempMessages']);
        
        // Clean old files every 5 minutes
        Loop::addPeriodicTimer(300, [$this, 'cleanOldFiles']);
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->resourceId = uniqid('client_', true);
        $conn->deviceId = null;
        $conn->ticketSubscriptions = [];
        
        echo date('[Y-m-d H:i:s]') . " New connection: {$conn->resourceId}\n";
        
        // Send connection confirmation
        $conn->send(json_encode([
            'action' => 'connected',
            'clientId' => $conn->resourceId,
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['action'])) {
                return;
            }
            
            echo date('[Y-m-d H:i:s]') . " Received {$data['action']} from {$from->resourceId}\n";
            
            // Store device ID if provided
            if (isset($data['deviceId'])) {
                $from->deviceId = $data['deviceId'];
            }
            
            switch ($data['action']) {
                case 'subscribe':
                    $this->handleSubscribe($from, $data);
                    break;
                    
                case 'newMessage':
                    $this->handleNewMessage($from, $data);
                    break;
                    
                case 'sendMessage':
                    $this->handleSendMessage($from, $data);
                    break;
                    
                case 'ping':
                    $from->send(json_encode(['action' => 'pong', 'timestamp' => time()]));
                    break;
            }
        } catch (Exception $e) {
            echo date('[Y-m-d H:i:s]') . " Error: " . $e->getMessage() . "\n";
        }
    }
    
    protected function handleSubscribe(ConnectionInterface $conn, $data) {
        if (!isset($data['ticketId'])) {
            return;
        }
        
        $ticketId = $data['ticketId'];
        
        // Remove from previous subscriptions
        foreach ($this->subscriptions as $tid => &$clients) {
            $this->subscriptions[$tid] = array_filter($clients, function($client) use ($conn) {
                return $client !== $conn;
            });
        }
        
        // Add to new subscription
        if (!isset($this->subscriptions[$ticketId])) {
            $this->subscriptions[$ticketId] = [];
        }
        
        $this->subscriptions[$ticketId][] = $conn;
        $conn->ticketSubscriptions[] = $ticketId;
        
        echo date('[Y-m-d H:i:s]') . " Client {$conn->resourceId} subscribed to ticket {$ticketId}\n";
        
        // Send confirmation
        $conn->send(json_encode([
            'action' => 'subscribed',
            'ticketId' => $ticketId,
            'success' => true
        ]));
    }
    
    protected function handleNewMessage(ConnectionInterface $from, $data) {
        if (!isset($data['ticketId']) || !isset($data['message'])) {
            return;
        }
        
        $ticketId = $data['ticketId'];
        $message = $data['message'];
        
        // Add device info to prevent echo
        if ($from->deviceId) {
            $message['sourceDeviceId'] = $from->deviceId;
        }
        
        // Broadcast to all subscribed clients except sender
        if (isset($this->subscriptions[$ticketId])) {
            foreach ($this->subscriptions[$ticketId] as $client) {
                if ($client !== $from && $client->deviceId !== $from->deviceId) {
                    $client->send(json_encode([
                        'action' => 'newMessage',
                        'ticketId' => $ticketId,
                        'message' => $message
                    ]));
                }
            }
        }
        
        echo date('[Y-m-d H:i:s]') . " Broadcasted message to ticket {$ticketId}\n";
    }
    
    protected function handleSendMessage(ConnectionInterface $from, $data) {
        if (!isset($data['ticketId']) || !isset($data['message'])) {
            echo date('[Y-m-d H:i:s]') . " Missing required fields for sendMessage\n";
            return;
        }
        
        try {
            // Save message to database
            $saved = $this->saveMessageToDatabase($data);
            
            if ($saved) {
                echo date('[Y-m-d H:i:s]') . " Message saved to database for ticket {$data['ticketId']}\n";
                
                // Create message object for broadcasting
                $messageObj = [
                    'Message' => $data['message'],
                    'user' => $data['user'] ?? 'Unknown',
                    'type' => $data['type'] ?? 1,
                    'CommentTime' => date('Y-m-d H:i:s'),
                    'deviceId' => $data['deviceId'] ?? null,
                    'messageId' => $data['messageId'] ?? uniqid('msg_', true),
                    'sourceDeviceId' => $from->deviceId
                ];
                
                // Broadcast to all subscribed clients
                if (isset($this->subscriptions[$data['ticketId']])) {
                    foreach ($this->subscriptions[$data['ticketId']] as $client) {
                        // Don't send back to the same device
                        if (!$from->deviceId || $client->deviceId !== $from->deviceId) {
                            $client->send(json_encode([
                                'action' => 'newMessage',
                                'ticketId' => $data['ticketId'],
                                'message' => $messageObj
                            ]));
                        }
                    }
                }
                
                // Also create sync file for fallback
                $this->createSyncFile($data['ticketId'], $messageObj);
                
                // Send success response to sender
                $from->send(json_encode([
                    'action' => 'messageSaved',
                    'success' => true,
                    'messageId' => $messageObj['messageId']
                ]));
            } else {
                $from->send(json_encode([
                    'action' => 'messageSaved',
                    'success' => false,
                    'error' => 'Failed to save message'
                ]));
            }
        } catch (Exception $e) {
            echo date('[Y-m-d H:i:s]') . " Error saving message: " . $e->getMessage() . "\n";
            $from->send(json_encode([
                'action' => 'messageSaved',
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }
    
    protected function saveMessageToDatabase($data) {
        try {
            // Extract message details
            $ticketId = $data['ticketId'];
            $message = $data['message'];
            $user = $data['user'];
            $userType = isset($data['type']) ? $data['type'] : 1;
            
            // Check for duplicates
            $checkSql = "SELECT id FROM comments_xdfree01_extrafields 
                        WHERE XDFree01_KeyID = :keyid 
                        AND Message = :message 
                        AND user = :user 
                        AND ABS(TIMESTAMPDIFF(SECOND, Date, NOW())) < 5";
            
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->bindParam(':keyid', $ticketId);
            $checkStmt->bindParam(':message', $message);
            $checkStmt->bindParam(':user', $user);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                echo date('[Y-m-d H:i:s]') . " Duplicate message detected, skipping\n";
                return true;
            }
            
            // Insert message
            $sql = "INSERT INTO comments_xdfree01_extrafields 
                    (XDFree01_KeyID, Message, type, Date, user) 
                    VALUES (:keyid, :message, :type, NOW(), :user)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':keyid', $ticketId);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':type', $userType);
            $stmt->bindParam(':user', $user);
            
            $result = $stmt->execute();
            
            if ($result) {
                // Update ticket's last update time
                $updateSql = "UPDATE info_xdfree01_extrafields 
                             SET dateu = NOW() 
                             WHERE XDFree01_KeyID = :keyid";
                $updateStmt = $this->pdo->prepare($updateSql);
                $updateStmt->bindParam(':keyid', $ticketId);
                $updateStmt->execute();
            }
            
            return $result;
        } catch (Exception $e) {
            echo date('[Y-m-d H:i:s]') . " Database error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    protected function createSyncFile($ticketId, $messageData) {
        $syncId = uniqid();
        $cleanTicketId = str_replace('#', '', $ticketId);
        $syncFile = $this->tempDir . "/sync_{$cleanTicketId}_{$syncId}.txt";
        
        $syncData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $messageData,
            'ticketId' => $ticketId,
            'created' => time()
        ];
        
        @file_put_contents($syncFile, json_encode($syncData));
        @chmod($syncFile, 0666);
    }
    
    public function processTempMessages() {
        $files = glob($this->tempDir . '/ws_send_*.json');
        foreach ($files as $file) {
            try {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['action']) && $data['action'] === 'sendMessage') {
                    // Save to database
                    $saved = $this->saveMessageToDatabase($data);
                    
                    if ($saved) {
                        // Create message object
                        $messageObj = [
                            'Message' => $data['message'],
                            'user' => $data['user'] ?? 'Unknown',
                            'type' => $data['type'] ?? 1,
                            'CommentTime' => date('Y-m-d H:i:s'),
                            'deviceId' => $data['deviceId'] ?? null,
                            'messageId' => $data['messageId'] ?? uniqid('msg_', true)
                        ];
                        
                        // Broadcast to subscribers
                        if (isset($this->subscriptions[$data['ticketId']])) {
                            foreach ($this->subscriptions[$data['ticketId']] as $client) {
                                $client->send(json_encode([
                                    'action' => 'newMessage',
                                    'ticketId' => $data['ticketId'],
                                    'message' => $messageObj
                                ]));
                            }
                        }
                        
                        // Create sync file
                        $this->createSyncFile($data['ticketId'], $messageObj);
                    }
                }
                
                // Delete processed file
                unlink($file);
            } catch (Exception $e) {
                echo date('[Y-m-d H:i:s]') . " Error processing temp file: " . $e->getMessage() . "\n";
            }
        }
    }
    
    public function cleanOldFiles() {
        // Clean sync files older than 5 minutes
        $files = glob($this->tempDir . '/sync_*.txt');
        foreach ($files as $file) {
            if (filemtime($file) < time() - 300) {
                @unlink($file);
            }
        }
        
        // Clean message files older than 2 minutes
        $files = glob($this->tempDir . '/ws_*.json');
        foreach ($files as $file) {
            if (filemtime($file) < time() - 120) {
                @unlink($file);
            }
        }
        
        echo date('[Y-m-d H:i:s]') . " Cleaned old temp files\n";
    }
    
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remove from all subscriptions
        foreach ($this->subscriptions as $ticketId => &$clients) {
            $this->subscriptions[$ticketId] = array_filter($clients, function($client) use ($conn) {
                return $client !== $conn;
            });
        }
        
        echo date('[Y-m-d H:i:s]') . " Connection closed: {$conn->resourceId}\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo date('[Y-m-d H:i:s]') . " Error: " . $e->getMessage() . "\n";
        $conn->close();
    }
}

// Create necessary directories
$tempDir = __DIR__ . '/temp';
$logsDir = __DIR__ . '/logs';

if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}
if (!file_exists($logsDir)) {
    mkdir($logsDir, 0777, true);
}

// Handle HTTP requests when not in CLI mode
if (!CLI_MODE) {
    header('Content-Type: application/json');
    
    // Simple HTTP endpoint to receive messages
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
        (strpos($_SERVER['REQUEST_URI'], '/send-message') !== false)) {
        
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if ($data && isset($data['action']) && $data['action'] === 'sendMessage') {
            // Create temp file for processing
            $tempFile = $tempDir . '/ws_send_' . uniqid() . '.json';
            file_put_contents($tempFile, json_encode($data));
            chmod($tempFile, 0666);
            
            echo json_encode(['success' => true, 'queued' => true]);
        } else {
            echo json_encode(['error' => 'Invalid request']);
        }
    } else {
        echo json_encode(['error' => 'WebSocket server HTTP endpoint']);
    }
    exit;
}

// CLI mode - run the WebSocket server
try {
    // Write PID file
    file_put_contents($tempDir . '/ws-server.pid', getmypid());
    
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new ChatServer($pdo)
            )
        ),
        8080
    );
    
    echo "WebSocket Server running on ws://localhost:8080\n";
    echo "Press Ctrl+C to stop\n\n";
    
    $server->run();
} catch (Exception $e) {
    echo "Failed to start server: " . $e->getMessage() . "\n";
    exit(1);
}
?>