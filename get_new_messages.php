<?php
session_start();
include('conflogin.php');
include('db.php');

header('Content-Type: application/json');

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

// Buscar novas mensagens a partir do timestamp fornecido
$sql = "SELECT Message, type, Date as CommentTime, user
        FROM comments_xdfree01_extrafields
        WHERE XDFree01_KeyID = :keyid
        AND Date > :timestamp
        ORDER BY Date ASC";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':keyid', $keyid);
$stmt->bindParam(':timestamp', $timestamp);
$stmt->execute();

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lastTimestamp = $timestamp;  // Default to the original timestamp

// Se houver novas mensagens, atualiza o timestamp para o da última mensagem
if (!empty($messages)) {
    $lastMessage = end($messages);
    $lastTimestamp = $lastMessage['CommentTime'];
    
    // Convert newlines to <br> tags for HTML display
    foreach ($messages as &$message) {
        $message['Message'] = nl2br(htmlspecialchars($message['Message']));
    }
}

// Retornar as novas mensagens e o timestamp atualizado
echo json_encode([
    'messages' => $messages,
    'lastTimestamp' => $lastTimestamp
]);
?>
