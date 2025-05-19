<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Check if this is being run from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line");
}

// Include Composer's autoloader
require __DIR__ . '/vendor/autoload.php';

// This class handles WebSocket connections
class ChatServer implements MessageComponentInterface {
    protected $clients;
    protected $clientsByTicket = [];
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "WebSocket server started\n";
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg);
        echo "Message received from {$from->resourceId}: {$msg}\n";
        
        if ($data->type === 'register') {
            // Register client for a specific ticket
            $ticketId = $data->ticketId;
            $userId = $data->userId;
            
            if (!isset($this->clientsByTicket[$ticketId])) {
                $this->clientsByTicket[$ticketId] = [];
            }
            
            $this->clientsByTicket[$ticketId][$from->resourceId] = [
                'conn' => $from,
                'userId' => $userId
            ];
            
            echo "Client {$from->resourceId} registered for ticket {$ticketId}\n";
        }
        elseif ($data->type === 'new_message') {
            // Broadcast new message to all clients registered for this ticket
            $ticketId = $data->ticketId;
            
            if (isset($this->clientsByTicket[$ticketId])) {
                foreach ($this->clientsByTicket[$ticketId] as $clientId => $client) {
                    if ($client['conn'] !== $from) { // Don't send to the sender
                        $client['conn']->send(json_encode([
                            'type' => 'new_message',
                            'message' => $data->message
                        ]));
                    }
                }
            }
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        // Remove client from all ticket registrations
        foreach ($this->clientsByTicket as $ticketId => $clients) {
            if (isset($clients[$conn->resourceId])) {
                unset($this->clientsByTicket[$ticketId][$conn->resourceId]);
                echo "Client {$conn->resourceId} unregistered from ticket {$ticketId}\n";
            }
            
            // Clean up empty ticket arrays
            if (empty($this->clientsByTicket[$ticketId])) {
                unset($this->clientsByTicket[$ticketId]);
            }
        }
        
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Set up the Ratchet server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    8080
);

echo "WebSocket server running at port 8080...\n";
$server->run();
