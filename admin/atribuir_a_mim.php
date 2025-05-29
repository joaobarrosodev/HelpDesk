<?php
session_start();
include('conflogin.php');
include('db.php');

$admin_id = $_SESSION['admin_id'];
$ticket_keyid = $_GET['keyid'] ?? '';

// Debug logging
error_log("atribuir_a_mim.php - Raw keyid from GET: " . print_r($_GET, true));
error_log("atribuir_a_mim.php - Initial ticket_keyid: " . $ticket_keyid);

// Clean the ticket keyid - decode URL but keep # if needed
$ticket_keyid = urldecode($ticket_keyid);

// Try both formats - with and without #
$ticket_keyid_with_hash = $ticket_keyid;
$ticket_keyid_without_hash = str_replace('#', '', $ticket_keyid);

// If it doesn't start with #, add it (database format likely uses #)
if (!str_starts_with($ticket_keyid_with_hash, '#')) {
    $ticket_keyid_with_hash = '#' . $ticket_keyid_with_hash;
}

error_log("atribuir_a_mim.php - Trying with hash: " . $ticket_keyid_with_hash);
error_log("atribuir_a_mim.php - Trying without hash: " . $ticket_keyid_without_hash);

if (empty($ticket_keyid)) {
    error_log("atribuir_a_mim.php - Ticket keyid is empty after cleaning");
    $_SESSION['error'] = 'Ticket não especificado. KeyID recebido: ' . ($_GET['keyid'] ?? 'vazio');
    header('Location: tickets_sem_atribuicao.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Try to find the ticket with both formats
    $stmt = $pdo->prepare("SELECT XDFree01_KeyID, Status, Atribuido FROM info_xdfree01_extrafields WHERE XDFree01_KeyID IN (?, ?)");
    $stmt->execute([$ticket_keyid_with_hash, $ticket_keyid_without_hash]);
    $ticket = $stmt->fetch();
    
    // Use the actual KeyID format found in database
    $actual_keyid = $ticket ? $ticket['XDFree01_KeyID'] : null;
    
    error_log("atribuir_a_mim.php - Query result: " . print_r($ticket, true));
    error_log("atribuir_a_mim.php - Actual KeyID in DB: " . $actual_keyid);
    
    if (!$ticket) {
        throw new Exception('Ticket não encontrado com KeyID: ' . $ticket_keyid . ' (testado: ' . $ticket_keyid_with_hash . ' e ' . $ticket_keyid_without_hash . ')');
    }
    
    if (!empty($ticket['Atribuido'])) {
        throw new Exception('Este ticket já está atribuído a outro responsável.');
    }
    
    if ($ticket['Status'] === 'Concluído') {
        throw new Exception('Não é possível atribuir um ticket já concluído.');
    }
    
    // Atribuir o ticket ao admin logado using the actual KeyID format
    $stmt = $pdo->prepare("UPDATE info_xdfree01_extrafields SET 
                          Atribuido = ?, 
                          Status = 'Em Análise',
                          dateu = NOW() 
                          WHERE XDFree01_KeyID = ?");
    
    $result = $stmt->execute([$admin_id, $actual_keyid]);
    
    if (!$result) {
        throw new Exception('Erro ao atribuir ticket.');
    }
    
    // Obter o nome do admin para o comentário
    $stmt = $pdo->prepare("SELECT Name FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin_name = $stmt->fetchColumn();
    
    // Obter email do admin para o comentário
    $admin_email = $_SESSION['admin_email'] ?? 'admin@sistema.local';
    
    // Adicionar comentário de atribuição using the actual KeyID format
    $comentario = "**TICKET ATRIBUÍDO**\n\nTicket atribuído automaticamente a: " . ($admin_name ?: 'Admin') . "\nStatus alterado para: Em Análise";
    
    $stmt = $pdo->prepare("INSERT INTO comments_xdfree01_extrafields (XDFree01_KeyID, user, Message, Date, type) VALUES (?, ?, ?, NOW(), 2)");
    $stmt->execute([$actual_keyid, $admin_email, $comentario]);
    
    $pdo->commit();
    $_SESSION['success'] = 'Ticket atribuído com sucesso! O ticket foi movido para os seus tickets atribuídos.';
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log("atribuir_a_mim.php - Exception: " . $e->getMessage());
    $_SESSION['error'] = 'Erro ao atribuir ticket: ' . $e->getMessage();
}

header('Location: tickets_sem_atribuicao.php');
exit();
?>
