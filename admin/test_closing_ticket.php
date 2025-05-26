<?php
// test_closing_ticket.php - Script de teste para verificar a funcionalidade de encerramento de bilhetes
include('db.php');

echo "===== TESTE DE VALIDAÇÃO DE ENCERRAMENTO DE BILHETES =====\n\n";

// 1. Verificar bilhetes com campos obrigatórios são devidamente reconhecidos
echo "1. Consulta SQL para bilhetes que necessitam de campos de encerramento:\n";
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.Name as ticket_title,
            info_xdfree01_extrafields.Status as status
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        WHERE info_xdfree01_extrafields.Status = 'Concluído'
        AND (info_xdfree01_extrafields.Tempo IS NULL OR info_xdfree01_extrafields.Tempo = '' OR info_xdfree01_extrafields.Tempo = 0)";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Encontrados " . count($tickets) . " bilhetes encerrados sem tempo de resolução.\n";
foreach($tickets as $ticket) {
    echo "- Ticket " . $ticket['KeyId'] . ": " . $ticket['ticket_title'] . " (Estado: " . $ticket['status'] . ")\n";
}
echo "\n";

// 2. Verificar bilhetes sem descrição são devidamente reconhecidos
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.Name as ticket_title,
            info_xdfree01_extrafields.Status as status
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        WHERE info_xdfree01_extrafields.Status = 'Concluído'
        AND (info_xdfree01_extrafields.Relatorio IS NULL OR info_xdfree01_extrafields.Relatorio = '')";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "2. Encontrados " . count($tickets) . " bilhetes encerrados sem descrição de resolução.\n";
foreach($tickets as $ticket) {
    echo "- Ticket " . $ticket['KeyId'] . ": " . $ticket['ticket_title'] . " (Estado: " . $ticket['status'] . ")\n";
}
echo "\n";

// 3. Verificar bilhetes sem utilizador atribuído são devidamente reconhecidos
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.Name as ticket_title,
            info_xdfree01_extrafields.Status as status
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        WHERE info_xdfree01_extrafields.Status = 'Concluído'
        AND (info_xdfree01_extrafields.Atribuido IS NULL OR info_xdfree01_extrafields.Atribuido = '')";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "3. Encontrados " . count($tickets) . " bilhetes encerrados sem utilizador atribuído.\n";
foreach($tickets as $ticket) {
    echo "- Ticket " . $ticket['KeyId'] . ": " . $ticket['ticket_title'] . " (Estado: " . $ticket['status'] . ")\n";
}
echo "\n";

// 4. Contar bilhetes por estado
$sql = "SELECT 
            info_xdfree01_extrafields.Status as status,
            COUNT(*) as count
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        GROUP BY info_xdfree01_extrafields.Status";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "4. Contagem de bilhetes por estado:\n";
foreach($statusCounts as $status) {
    echo "- " . $status['status'] . ": " . $status['count'] . " bilhetes\n";
}
echo "\n";

// 5. Contar bilhetes por responsável
$sql = "SELECT 
            info_xdfree01_extrafields.Atribuido as user_id,
            users.Name as user_name,
            COUNT(*) as count
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LEFT JOIN users ON info_xdfree01_extrafields.Atribuido = users.id
        GROUP BY info_xdfree01_extrafields.Atribuido
        ORDER BY count DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$userCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "5. Contagem de bilhetes por responsável:\n";
foreach($userCounts as $user) {
    $userName = !empty($user['user_name']) ? $user['user_name'] : 'Ninguém';
    echo "- " . $userName . " (ID: " . $user['user_id'] . "): " . $user['count'] . " bilhetes\n";
}

echo "\n===== TESTE CONCLUÍDO =====\n";
?>
