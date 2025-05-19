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
    protected $clientsByTicket = []; // Fixed the syntax error here

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "HelpDesk WebSocket Server started\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->ticketSubscriptions = [];
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg);
        echo "Message from {$from->resourceId}: " . json_encode($data) . "\n";

        if (!$data || !isset($data->action)) {
            return;
        }

        if ($data->action === 'subscribe' && isset($data->ticketId)) {
            // Subscribe to ticket updates
            $ticketId = $data->ticketId;
            
            if (!isset($this->clientsByTicket[$ticketId])) {
                $this->clientsByTicket[$ticketId] = [];
            }
            
            $this->clientsByTicket[$ticketId][$from->resourceId] = $from;
            $from->ticketSubscriptions[] = $ticketId;
            
            echo "Client {$from->resourceId} subscribed to ticket {$ticketId}\n";
        }
        elseif ($data->action === 'newMessage' && isset($data->ticketId) && isset($data->message)) {
            // Broadcast message to all clients subscribed to this ticket
            $this->broadcastToTicket($data->ticketId, $data, $from);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Remove client from all ticket subscriptions
        if (isset($conn->ticketSubscriptions)) {
            foreach ($conn->ticketSubscriptions as $ticketId) {
                if (isset($this->clientsByTicket[$ticketId][$conn->resourceId])) {
                    unset($this->clientsByTicket[$ticketId][$conn->resourceId]);
                    
                    // Clean up empty ticket arrays
                    if (empty($this->clientsByTicket[$ticketId])) {
                        unset($this->clientsByTicket[$ticketId]);
                    }
                }
            }
        }
        
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
    
    public function broadcastToTicket($ticketId, $data, ConnectionInterface $exclude = null) {
        if (!isset($this->clientsByTicket[$ticketId])) {
            return;
        }
        
        echo "Broadcasting to ticket {$ticketId}, clients: " . count($this->clientsByTicket[$ticketId]) . "\n";
        
        foreach ($this->clientsByTicket[$ticketId] as $client) {
            if ($exclude === null || $client !== $exclude) {
                $client->send(json_encode($data));
            }
        }
    }
}

// Create and run the WebSocket server on port 8080
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
