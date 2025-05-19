<?php
session_start();

include('conflogin.php');
include('db.php');

// Set header to return JSON response
header('Content-Type: application/json');

// Verificar se o usuário tem permissões de administrador
if (!isset($_SESSION['usuario_admin']) || $_SESSION['usuario_admin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Sem permissões necessárias']);
    exit;
}

// Verificar se o ID do ticket foi fornecido
if (isset($_GET['id'])) {
    $ticket_id = $_GET['id'];
    
    // Buscar KeyId baseado no ID
    $sql_keyid = "SELECT KeyId FROM xdfree01 WHERE id = :id";
    $stmt_keyid = $pdo->prepare($sql_keyid);
    $stmt_keyid->bindParam(':id', $ticket_id);
    $stmt_keyid->execute();
    $result = $stmt_keyid->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $keyid = $result['KeyId'];
        
        // Atualizar o status do ticket para "Concluído"
        $sql = "UPDATE info_xdfree01_extrafields 
                SET Status = 'Concluído', dateu = NOW() 
                WHERE XDFree01_KeyID = :keyid";
                
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':keyid', $keyid);
        
        if ($stmt->execute()) {
            // Adicionar uma mensagem de sistema notificando o fechamento
            $closeMessage = "Ticket fechado pelo administrador " . $_SESSION['usuario_email'];
            
            $sql_comment = "INSERT INTO comments_xdfree01_extrafields 
                           (XDFree01_KeyID, Date, Message, Type, user) 
                           VALUES (:keyid, NOW(), :message, 0, 'Sistema')";
                           
            $stmt_comment = $pdo->prepare($sql_comment);
            $stmt_comment->bindParam(':keyid', $keyid);
            $stmt_comment->bindParam(':message', $closeMessage);
            
            if ($stmt_comment->execute()) {
                // Retornar sucesso em formato JSON
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Ticket fechado com sucesso',
                    'timestamp' => date('H:i'),
                    'ticketId' => $ticket_id
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar comentário de fechamento']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao fechar o ticket. Por favor, tente novamente.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Ticket não encontrado.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ID do ticket não fornecido.']);
}
?>
