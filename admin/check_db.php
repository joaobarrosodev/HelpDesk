<?php
// Script temporário para verificar a estrutura da base de dados
include('db.php');

// Verificar tabela info_xdfree01_extrafields
echo "===== ESTRUTURA DA TABELA info_xdfree01_extrafields =====\n";
$sql = "DESCRIBE info_xdfree01_extrafields";
$stmt = $pdo->prepare($sql);
$stmt->execute();
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    echo "Campo: " . $row['Field'] . ", Tipo: " . $row['Type'] . "\n";
}

// Verificar alguns tickets de exemplo
echo "\n===== BILHETES DE EXEMPLO =====\n";
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.Name as titulo_ticket, 
            info_xdfree01_extrafields.Atribuido as atribuido_a, 
            info_xdfree01_extrafields.Priority as prioridade,
            info_xdfree01_extrafields.Status as estado,
            info_xdfree01_extrafields.Tempo as tempo_resolucao,
            info_xdfree01_extrafields.Relatorio as descricao_resolucao
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($tickets as $ticket) {
    echo "-----------------------------------\n";
    echo "ID do Ticket: " . $ticket['KeyId'] . "\n";
    echo "Título: " . $ticket['titulo_ticket'] . "\n";
    echo "Atribuído a: " . $ticket['atribuido_a'] . "\n";
    echo "Prioridade: " . $ticket['prioridade'] . "\n";
    echo "Estado: " . $ticket['estado'] . "\n";
    echo "Tempo de Resolução: " . $ticket['tempo_resolucao'] . "\n";
    echo "Descrição da Resolução: " . $ticket['descricao_resolucao'] . "\n";
}

?>
