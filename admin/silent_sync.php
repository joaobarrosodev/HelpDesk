<?php
session_start();
include('../db.php');

// Definir cabeçalho JSON
header('Content-Type: application/json');

// Desativar qualquer saída de erro
error_reporting(0);
ini_set('display_errors', 0);

// Verificar parâmetros obrigatórios
if (!isset($_GET['ticketId']) || !isset($_GET['lastCheck'])) {
    echo json_encode(['error' => 'Parâmetros em falta']);
    exit;
}

$ticketId = $_GET['ticketId'];
$lastCheck = $_GET['lastCheck'];
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : null;

try {
    // Consultar novas mensagens desde a última verificação
    $sql = "SELECT c.Message, c.type, c.Date as CommentTime, c.user, c.id as messageId
            FROM comments_xdfree01_extrafields c
            WHERE c.XDFree01_KeyID = :ticketId
            AND c.Date > :lastCheck
            ORDER BY c.Date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':ticketId', $ticketId);
    $stmt->bindParam(':lastCheck', $lastCheck);
    $stmt->execute();
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar se há novas mensagens
    if (count($messages) > 0) {
        // Adicionar IDs únicos de mensagem para desduplicação
        foreach ($messages as &$message) {
            $message['messageId'] = 'db_' . $message['messageId'];
        }
        
        echo json_encode([
            'hasNewMessages' => true,
            'messages' => $messages,
            'count' => count($messages)
        ]);
    } else {
        echo json_encode([
            'hasNewMessages' => false,
            'messages' => [],
            'count' => 0
        ]);
    }
    
} catch (Exception $e) {
    error_log('Erro em admin/silent_sync.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Erro de base de dados']);
}
?>
