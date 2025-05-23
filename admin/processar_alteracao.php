<?php
session_start();
include('conflogin.php');
include('db.php');

if (!isset($_GET['id'])) {
    header('Location: consultar_tickets.php?error=missing_id');
    exit;
}

$ticketId = $_GET['id'];
$updateFields = [];
$params = [':id' => $ticketId];

// Check what fields need to be updated
if (isset($_GET['Status'])) {
    $updateFields[] = "Status = :status";
    $params[':status'] = $_GET['Status'];
}

if (isset($_GET['Atribuido'])) {
    $updateFields[] = "Atribuido = :atribuido";
    $params[':atribuido'] = $_GET['Atribuido'] ?: null;
}

if (empty($updateFields)) {
    header('Location: consultar_tickets.php?error=no_changes');
    exit;
}

try {
    // Get ticket KeyId first
    $getKeySql = "SELECT KeyId FROM xdfree01 WHERE id = :id";
    $getKeyStmt = $pdo->prepare($getKeySql);
    $getKeyStmt->bindParam(':id', $ticketId);
    $getKeyStmt->execute();
    $keyResult = $getKeyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$keyResult) {
        header('Location: consultar_tickets.php?error=ticket_not_found');
        exit;
    }
    
    $keyId = $keyResult['KeyId'];
    
    // Update the ticket
    $sql = "UPDATE info_xdfree01_extrafields SET " . implode(', ', $updateFields) . ", dateu = NOW() WHERE XDFree01_KeyID = :keyid";
    $params[':keyid'] = $keyId;
    unset($params[':id']); // Remove the numeric ID parameter
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        header('Location: detalhes_ticket.php?keyid=' . urlencode($keyId) . '&success=updated');
    } else {
        header('Location: consultar_tickets.php?error=update_failed');
    }
} catch (Exception $e) {
    error_log('Error in processar_alteracao.php: ' . $e->getMessage());
    header('Location: consultar_tickets.php?error=database_error');
}
exit;
?>