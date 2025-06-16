<?php
// obter_contratos.php
session_start();
include('conflogin.php');
include('db.php');
include('verificar_tempo_disponivel.php');

header('Content-Type: application/json');

if (!isset($_GET['entity']) || empty($_GET['entity'])) {
    echo json_encode(['erro' => 'Entity não fornecida']);
    exit;
}

$entity = $_GET['entity'];

try {
    // Obter resumo dos contratos
    $resumo = obterResumoContratos($entity, $pdo);
    
    if (isset($resumo['erro'])) {
        echo json_encode(['erro' => $resumo['erro']]);
        exit;
    }
    
    // Formatar datas e adicionar informações extras
    foreach ($resumo['contratos'] as &$contrato) {
        // Formatar data
        if ($contrato['dataInicio']) {
            $data = new DateTime($contrato['dataInicio']);
            $contrato['dataInicio'] = $data->format('d/m/Y');
        } else {
            $contrato['dataInicio'] = 'N/A';
        }
        
        // Formatar valor
        $contrato['valor'] = number_format($contrato['valor'] ?? 0, 2, ',', '.');
    }
    
    echo json_encode($resumo);
    
} catch (Exception $e) {
    error_log("Erro ao obter contratos: " . $e->getMessage());
    echo json_encode(['erro' => 'Erro interno do servidor']);
}
?>