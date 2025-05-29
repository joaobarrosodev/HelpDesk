<?php
session_start();
include('conflogin.php');
include('db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tickets_atribuidos.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$ticket_id = $_POST['ticket_id'] ?? '';
$resolucao_descricao = $_POST['resolucao_descricao'] ?? '';
$tempo_resolucao = $_POST['tempo_resolucao'] ?? '';

// Debug - log the received data
error_log("Fechar ticket - ticket_id: " . $ticket_id);
error_log("Fechar ticket - resolucao_descricao: " . $resolucao_descricao);
error_log("Fechar ticket - tempo_resolucao: " . $tempo_resolucao);

// Validação básica
if (empty($ticket_id) || empty($resolucao_descricao) || empty($tempo_resolucao)) {
    $_SESSION['error'] = 'Todos os campos são obrigatórios.';
    header('Location: tickets_atribuidos.php');
    exit();
}

if ($tempo_resolucao < 15) {
    $_SESSION['error'] = 'O tempo mínimo de resolução é de 15 minutos.';
    header('Location: tickets_atribuidos.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Verificar se o ticket existe primeiro
    $stmt = $pdo->prepare("SELECT XDFree01_KeyID, Status FROM info_xdfree01_extrafields WHERE XDFree01_KeyID = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        throw new Exception('Ticket não encontrado.');
    }
    
    error_log("Ticket encontrado - KeyID: " . $ticket['XDFree01_KeyID'] . ", Status atual: " . $ticket['Status']);
    
    // Atualizar o status do ticket para Concluído
    $stmt = $pdo->prepare("UPDATE info_xdfree01_extrafields SET 
                          Status = 'Concluído', 
                          dateu = NOW(), 
                          Relatorio = ?,
                          Tempo = ?
                          WHERE XDFree01_KeyID = ?");
    
    $result = $stmt->execute([$resolucao_descricao, $tempo_resolucao, $ticket_id]);
    
    if (!$result) {
        throw new Exception('Erro ao atualizar status do ticket.');
    }
    
    error_log("Status atualizado com sucesso");
    
    // Preparar comentário de fechamento
    $timeDisplay = '';
    $hours = floor($tempo_resolucao / 60);
    $minutes = $tempo_resolucao % 60;
    if ($hours > 0) {
        $timeDisplay = $hours . 'h';
        if ($minutes > 0) $timeDisplay .= ' ' . $minutes . 'min';
    } else {
        $timeDisplay = $minutes . ' minutos';
    }
    
    $comentario = "**TICKET FECHADO**\n\nDescrição da Resolução: " . $resolucao_descricao . "\nTempo de Resolução: " . $timeDisplay;
    
    // Obter email do admin usando a estrutura correta
    $admin_email = null;
    
    // Primeiro tentar obter da sessão (mais direto)
    if (isset($_SESSION['admin_email']) && !empty($_SESSION['admin_email'])) {
        $admin_email = $_SESSION['admin_email'];
    } else {
        // Tentar obter da tabela online_userbe_extrafields (estrutura usada no login)
        $stmt = $pdo->prepare("SELECT ou.Email 
                              FROM users u 
                              JOIN online_userbe_extrafields ou ON u.id = ou.UserBE_Id 
                              WHERE u.id = ?");
        $stmt->execute([$admin_id]);
        $admin_email = $stmt->fetchColumn();
        
        // Se ainda não encontrou, usar fallback
        if (!$admin_email) {
            $admin_email = 'admin@sistema.local';
        }
    }
    
    error_log("Email do admin: " . $admin_email);
    
    // Inserir comentário usando a coluna correta (Message ao invés de Comment)
    $stmt = $pdo->prepare("INSERT INTO comments_xdfree01_extrafields (XDFree01_KeyID, user, Message, Date) VALUES (?, ?, ?, NOW())");
    $result = $stmt->execute([$ticket_id, $admin_email, $comentario]);
    
    if (!$result) {
        throw new Exception('Erro ao inserir comentário de fechamento.');
    }
    
    error_log("Comentário inserido com sucesso");
    
    $pdo->commit();
    $_SESSION['success'] = 'Ticket fechado com sucesso!';
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log("Erro ao fechar ticket: " . $e->getMessage());
    $_SESSION['error'] = 'Erro ao fechar ticket: ' . $e->getMessage();
}

header('Location: tickets_atribuidos.php');
exit();
?>
