<?php
session_start();
include('conflogin.php');
include('db.php');

if (!isset($_GET['keyid']) && !isset($_POST['keyid'])) {
    header('Location: consultar_tickets.php?error=missing_id');
    exit;
}

$ticketId = isset($_GET['keyid']) ? $_GET['keyid'] : $_POST['keyid'];
$updateFields = [];
$params = [':keyid' => $ticketId];

// Verificar que campos precisam de ser atualizados
if (isset($_GET['Status']) || isset($_POST['status'])) {
    $updateFields[] = "Status = :status";
    $params[':status'] = isset($_GET['Status']) ? $_GET['Status'] : $_POST['status'];
}

if (isset($_GET['Atribuido']) || isset($_POST['assigned_user'])) {
    $updateFields[] = "Atribuido = :atribuido";
    $params[':atribuido'] = isset($_GET['Atribuido']) ? ($_GET['Atribuido'] ?: null) : ($_POST['assigned_user'] ?: null);
}

if (isset($_POST['resolution_time'])) {
    $updateFields[] = "Tempo = :resolution_time";
    $params[':resolution_time'] = $_POST['resolution_time'];
}

if (isset($_POST['resolution_description'])) {
    $updateFields[] = "Relatorio = :resolution_description";
    $params[':resolution_description'] = $_POST['resolution_description'];
}

if (isset($_POST['extra_info'])) {
    $updateFields[] = "MensagensInternas = :extra_info";
    $params[':extra_info'] = $_POST['extra_info'];
}

if (empty($updateFields)) {
    $response = ['success' => false, 'message' => 'Nenhuma alteração fornecida'];
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    header('Location: consultar_tickets.php?error=no_changes');
    exit;
}

try {
    // Atualizar o bilhete diretamente usando KeyId
    $sql = "UPDATE info_xdfree01_extrafields SET " . implode(', ', $updateFields) . ", dateu = NOW() WHERE XDFree01_KeyID = :keyid";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        $response = ['success' => true, 'message' => 'Alterações guardadas com sucesso'];
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&success=updated');
    } else {
        $response = ['success' => false, 'message' => 'Falha ao atualizar bilhete'];
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        header('Location: consultar_tickets.php?error=update_failed');
    }
} catch (Exception $e) {
    error_log('Erro em processar_alteracao.php: ' . $e->getMessage());
    
    $response = ['success' => false, 'message' => 'Erro de base de dados: ' . $e->getMessage()];
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    header('Location: consultar_tickets.php?error=database_error');
}
exit;
?>