<?php
session_start();
include('conflogin.php');
include('db.php');

// Set headers to prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// For debugging
$debug_log = "log_messages.txt";
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Request: " . print_r($_GET, true) . "\n", FILE_APPEND);

// Verificar se o usuário está autenticado
if (!isset($_SESSION['usuario_email'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Verificar se todos os parâmetros necessários foram fornecidos
if (!isset($_GET['keyid']) || !isset($_GET['timestamp'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$keyid = $_GET['keyid'];
$timestamp = $_GET['timestamp'];

// Buscar novas mensagens a partir do timestamp fornecido - with a small buffer to avoid missing messages
// Subtract 2 seconds from the timestamp to ensure we don't miss any messages
$adjusted_timestamp = date('Y-m-d H:i:s', strtotime($timestamp) - 2);

// Log the timestamps for debugging
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Original timestamp: $timestamp, Adjusted: $adjusted_timestamp\n", FILE_APPEND);

$sql = "SELECT Message, type, Date as CommentTime, user
        FROM comments_xdfree01_extrafields
        WHERE XDFree01_KeyID = :keyid
        AND Date > :timestamp
        ORDER BY Date ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':keyid', $keyid);
    $stmt->bindParam(':timestamp', $adjusted_timestamp);
    $stmt->execute();

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lastTimestamp = $timestamp;  // Default to the original timestamp
    
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Found " . count($messages) . " messages\n", FILE_APPEND);

    // Se houver novas mensagens, atualiza o timestamp para o da última mensagem
    if (!empty($messages)) {
        $lastMessage = end($messages);
        $lastTimestamp = $lastMessage['CommentTime'];
        
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Last message timestamp: $lastTimestamp\n", FILE_APPEND);
        
        // Process message content for HTML display
        foreach ($messages as &$message) {
            $message['Message'] = nl2br(htmlspecialchars($message['Message']));
        }
    }

    // Retornar as novas mensagens e o timestamp atualizado
    echo json_encode([
        'messages' => $messages,
        'lastTimestamp' => $lastTimestamp,
        'status' => 'success',
        'serverTime' => date('Y-m-d H:i:s.u'),
        'requestTimestamp' => $timestamp,
        'queryTimestamp' => $adjusted_timestamp
    ]);
    
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Response sent\n", FILE_APPEND);
    
} catch (Exception $e) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
    
    echo json_encode([
        'error' => 'Database error', 
        'message' => $e->getMessage(),
        'status' => 'error'
    ]);
}
?>
