<?php
// verificar_tempo_endpoint.php
session_start();
include('conflogin.php');
include('db.php'); 
include('verificar_tempo_disponivel.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

if (!isset($_POST['entity']) || !isset($_POST['tempo'])) {
    echo json_encode(['erro' => 'Parâmetros obrigatórios não fornecidos']);
    exit;
}

$entity = $_POST['entity'];
$tempo = intval($_POST['tempo']);

if ($tempo <= 0) {
    echo json_encode(['erro' => 'Tempo deve ser maior que zero']);
    exit;
}

try {
    $resultado = verificarTempoDisponivel($entity, $tempo, $pdo);
    echo json_encode($resultado);
} catch (Exception $e) {
    error_log("Erro ao verificar tempo: " . $e->getMessage());
    echo json_encode(['erro' => 'Erro interno do servidor']);
}
?>