<?php
session_start();

// Incluir base de dados do diretório pai
include_once('../db.php');

// Definir fuso horário para Portugal
date_default_timezone_set('Europe/Lisbon');

// Verificar se o administrador está ligado
if (!isset($_SESSION['admin_email'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Administrador não autenticado']);
    exit;
}

$user = $_SESSION['admin_email'];
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Verificar parâmetros obrigatórios
if (!isset($_POST['keyid']) || !isset($_POST['id']) || !isset($_POST['message'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Parâmetros obrigatórios em falta']);
    } else {
        header('Location: consultar_tickets.php?error=1');
    }
    exit;
}

$keyid = $_POST['keyid'];
$id = $_POST['id'];
$message = trim($_POST['message']);
$deviceId = isset($_POST['deviceId']) ? $_POST['deviceId'] : null;
$ws_origin = isset($_POST['ws_origin']) ? $_POST['ws_origin'] : '0';

// Registar os dados recebidos para depuração
error_log("Administrador inserir_mensagem - KeyID: $keyid, Mensagem: $message, Utilizador: $user");

try {
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Verificar mensagens duplicadas
    $checkSql = "SELECT id FROM comments_xdfree01_extrafields 
                WHERE XDFree01_KeyID = :keyid 
                AND Message = :message 
                AND user = :user 
                AND ABS(TIMESTAMPDIFF(SECOND, Date, NOW())) < 5";
    
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':keyid', $keyid);
    $checkStmt->bindParam(':message', $message);
    $checkStmt->bindParam(':user', $user);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $pdo->rollBack();
        error_log("Administrador inserir_mensagem - Mensagem duplicada detetada");
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Mensagem já guardada',
                'duplicate' => true
            ]);
        } else {
            header('Location: detalhes_ticket.php?keyid=' . urlencode($keyid));
        }
        exit;
    }
    
    // Guardar mensagem na base de dados
    $sql = "INSERT INTO comments_xdfree01_extrafields 
            (XDFree01_KeyID, Message, type, Date, user) 
            VALUES (:keyid, :message, :type, NOW(), :user)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':keyid', $keyid);
    $stmt->bindParam(':message', $message);
    $stmt->bindValue(':type', 2); // Admin type
    $stmt->bindParam(':user', $user);
    
    if (!$stmt->execute()) {
        throw new Exception('Falha ao inserir mensagem na base de dados');
    }
    
    // Obter o ID da mensagem inserida
    $messageId = $pdo->lastInsertId();
    error_log("Administrador inserir_mensagem - Mensagem guardada com ID: $messageId");
    
    // Atualizar hora da última atualização do bilhete
    $updateSql = "UPDATE info_xdfree01_extrafields 
                  SET dateu = NOW() 
                  WHERE XDFree01_KeyID = :keyid";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->bindParam(':keyid', $keyid);
    $updateStmt->execute();
    
    // Confirmar transação
    $pdo->commit();
    
    // Apenas enviar para WebSocket se esta mensagem não foi originada do WebSocket
    if ($ws_origin !== '1') {
        // Preparar dados da mensagem para WebSocket/sincronização
        $messageData = [
            'action' => 'sendMessage',
            'ticketId' => $keyid,
            'ticketNumericId' => $id,
            'message' => $message,
            'user' => $user,
            'type' => 2, // Admin type
            'deviceId' => $deviceId,
            'timestamp' => date('Y-m-d H:i:s'),
            'messageId' => 'db_' . $messageId,
            'alreadySaved' => true
        ];
        
        // Tentar enviar para servidor WebSocket
        $wsSuccess = sendToWebSocketServer($messageData);
        error_log("Administrador inserir_mensagem - Resultado do envio WebSocket: " . ($wsSuccess ? 'sucesso' : 'falhou'));
    } else {
        $wsSuccess = true;
    }
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Mensagem guardada com sucesso',
            'messageId' => $messageId,
            'websocketSent' => $wsSuccess
        ]);
    } else {
        header('Location: detalhes_ticket.php?keyid=' . urlencode($keyid));
    }

} catch (Exception $e) {
    // Reverter transação em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Erro em admin/inserir_mensagem.php: ' . $e->getMessage());
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Falha ao guardar mensagem: ' . $e->getMessage()
        ]);
    } else {
        header('Location: consultar_tickets.php?error=3');
    }
}

/**
 * Enviar mensagem para servidor WebSocket via HTTP ou ficheiro temporário
 */
function sendToWebSocketServer($messageData) {
    // Tentar HTTP primeiro
    $wsUrl = 'http://localhost:8080/send-message';
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $wsUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer helpdesk_secret_key'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return true;
        }
    }
    
    // Fallback: criar ficheiro temporário para o WebSocket processar
    $tempDir = dirname(__DIR__) . '/temp';
    if (!file_exists($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }
    
    // Criar ficheiro de sincronização para sincronização imediata
    $syncId = uniqid();
    $cleanTicketId = str_replace('#', '', $messageData['ticketId']);
    $syncFile = $tempDir . "/sync_{$cleanTicketId}_{$syncId}.txt";
    
    $syncData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => [
            'Message' => $messageData['message'],
            'user' => $messageData['user'],
            'type' => $messageData['type'],
            'CommentTime' => $messageData['timestamp'],
            'deviceId' => $messageData['deviceId'],
            'messageId' => $messageData['messageId']
        ],
        'ticketId' => $messageData['ticketId'],
        'created' => time()
    ];
    
    $result = @file_put_contents($syncFile, json_encode($syncData));
    
    if ($result !== false) {
        @chmod($syncFile, 0666);
        return true;
    }
    
    return false;
}
?>