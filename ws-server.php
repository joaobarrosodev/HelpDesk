<?php
// WebSocket server for HelpDesk chat
require 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class ChatServer implements MessageComponentInterface {
    protected $clients;
    protected $subscriptions = [];
    protected $debug = true;
    protected $deviceIds = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "HelpDesk WebSocket Server started\n";
        $this->logMessage("Server initialized");
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection
        $this->clients->attach($conn);
        
        // Initialize connection properties properly
        $conn->ticketSubscriptions = [];
        $conn->deviceId = null;
        
        $this->logMessage("New connection: {$conn->resourceId}");
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg);
            $this->logMessage("Message from {$from->resourceId}: " . $msg);

            if (!$data || !isset($data->action)) {
                $this->logMessage("Invalid message format");
                return;
            }

            // Store device ID if provided
            if (isset($data->deviceId)) {
                $from->deviceId = $data->deviceId;
                $this->logMessage("Client {$from->resourceId} identified as device: {$data->deviceId}");
            }

            if ($data->action === 'subscribe' && isset($data->ticketId)) {
                // Subscribe to ticket updates
                $ticketId = $data->ticketId;
                
                // Store subscription info
                if (!isset($this->subscriptions[$ticketId])) {
                    $this->subscriptions[$ticketId] = [];
                }
                
                // Add this connection to the ticket's subscribers
                $this->subscriptions[$ticketId][$from->resourceId] = $from;
                
                // Create a fresh array for ticketSubscriptions to avoid modification issues
                $ticketSubs = [];
                if (is_array($from->ticketSubscriptions)) {
                    $ticketSubs = $from->ticketSubscriptions;
                }
                
                // Add this ticket if not already subscribed
                if (!in_array($ticketId, $ticketSubs)) {
                    $ticketSubs[] = $ticketId;
                }
                
                // Assign the updated array
                $from->ticketSubscriptions = $ticketSubs;
                
                $this->logMessage("Client {$from->resourceId} subscribed to ticket {$ticketId}");
                
                // Send confirmation to client
                $from->send(json_encode([
                    'action' => 'subscribed',
                    'ticketId' => $ticketId,
                    'success' => true
                ]));
            }
            elseif ($data->action === 'newMessage' && isset($data->ticketId) && isset($data->message)) {
                // Broadcast message to all clients subscribed to this ticket
                $this->logMessage("Broadcasting message to ticket {$data->ticketId}");
                $this->broadcastToTicket($data->ticketId, $data, $from->deviceId ?? null);
            }
            elseif ($data->action === 'ping') {
                // Respond to ping with pong
                $from->send(json_encode([
                    'action' => 'pong', 
                    'time' => time(),
                    'deviceId' => $from->deviceId ?? null
                ]));
            }
        } catch (\Exception $e) {
            $this->logMessage("Error processing message: " . $e->getMessage());
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Clean up subscriptions for this connection
        if (isset($conn->ticketSubscriptions) && is_array($conn->ticketSubscriptions)) {
            foreach ($conn->ticketSubscriptions as $ticketId) {
                if (isset($this->subscriptions[$ticketId][$conn->resourceId])) {
                    unset($this->subscriptions[$ticketId][$conn->resourceId]);
                    
                    // Remove empty ticket arrays
                    if (empty($this->subscriptions[$ticketId])) {
                        unset($this->subscriptions[$ticketId]);
                    }
                }
            }
        }
        
        $this->clients->detach($conn);
        $this->logMessage("Connection {$conn->resourceId} disconnected");
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->logMessage("Error on connection {$conn->resourceId}: {$e->getMessage()}");
        $conn->close();
    }
    
    public function broadcastToTicket($ticketId, $data, $sourceDeviceId = null) {
        if (!isset($this->subscriptions[$ticketId]) || empty($this->subscriptions[$ticketId])) {
            $this->logMessage("No subscribers for ticket {$ticketId}");
            return;
        }
        
        $this->logMessage("Broadcasting to ticket {$ticketId}, clients: " . count($this->subscriptions[$ticketId]));
        
        $encoded = json_encode($data);
        foreach ($this->subscriptions[$ticketId] as $resourceId => $client) {
            try {
                // Skip sending back to the original device if specified
                $clientDeviceId = isset($client->deviceId) ? $client->deviceId : null;
                if ($sourceDeviceId && $clientDeviceId === $sourceDeviceId) {
                    $this->logMessage("Skipping sender device {$clientDeviceId}");
                    continue;
                }
                
                $this->logMessage("Sending message to client {$resourceId}");
                $client->send($encoded);
            } catch (\Exception $e) {
                $this->logMessage("Error sending to client {$resourceId}: " . $e->getMessage());
            }
        }
    }
    
    protected function logMessage($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] $message\n";
        
        // Log to file
        file_put_contents(
            __DIR__ . '/ws-server.log', 
            "$message\n", 
            FILE_APPEND
        );
    }
}

// Create temp directory if it doesn't exist
$tempDir = __DIR__ . '/temp';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// Create and run the WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    8080
);

echo "WebSocket server running at 0.0.0.0:8080\n";
$server->run();
