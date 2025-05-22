<?php
session_start();
include('db.php'); // Conexão com o banco de dados
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Processar ação de "fechar ticket" via GET
if (isset($_GET['action']) && $_GET['action'] == 'close' && isset($_GET['keyid'])) {
    // Redirecionar para a página de detalhes com um parâmetro para pré-selecionar o status "Concluído"
    $keyid = $_GET['keyid'];
    echo "<script>window.location.href='detalhes_ticket.php?keyid=" . $keyid . "&pre_close=1';</script>";
    exit;
}

// Processar formulário via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $keyid = $_POST['keyid'];
    $status = $_POST['status'];
    
    // Use o usuário selecionado no formulário se existir, caso contrário usa o atual admin
    $user = isset($_POST['assigned_user']) && !empty($_POST['assigned_user']) 
            ? $_POST['assigned_user'] 
            : $_SESSION['admin_id'];
    $description = $_POST['resolution_description'];
    $extra_info = $_POST['extra_info'];
    $resolution_time = $_POST['resolution_time'];

    // Verificações adicionais se o status for "Concluído"
    if ($status == 'Concluído') {
        // Verificar se o tempo de resolução está preenchido
        if (empty($resolution_time) || !is_numeric($resolution_time) || $resolution_time <= 0) {
            echo "<script>alert('Para fechar um ticket, é necessário informar o tempo de resolução em minutos.'); window.history.back();</script>";
            exit;
        }
        
        // Verificar se a descrição da resolução está preenchida
        if (empty($description)) {
            echo "<script>alert('Para fechar um ticket, é necessário fornecer uma descrição da resolução.'); window.history.back();</script>";
            exit;
        }
        
        // Verificar se um usuário está atribuído ao ticket
        if (empty($user)) {
            echo "<script>alert('Para fechar um ticket, é necessário atribuí-lo a um responsável.'); window.history.back();</script>";
            exit;
        }
    }

    // Validar que o tempo é um número positivo
    if (!is_numeric($resolution_time) || $resolution_time <= 0) {
        echo "<script>alert('Tempo de resolução inválido! Deve ser um número positivo.'); window.history.back();</script>";
        exit;
    }    
    // Converter para inteiro
    $time_formatted = (int)$resolution_time;
    
    try {
        // Iniciar transação
        $pdo->beginTransaction();

        // Atualizar a tabela `info_xdfree01_extrafields` com todos os campos relevantes
        $sql = "UPDATE info_xdfree01_extrafields 
                SET Status = :status, 
                    dateu = NOW(),
                    Atribuido = :user, 
                    Tempo = :time, 
                    Relatorio = :description,
                    MensagensInternas = :extra_info
                WHERE XDFree01_KeyID = :keyid";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':user', $user);
        $stmt->bindParam(':time', $time_formatted);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':extra_info', $extra_info);
        $stmt->bindParam(':keyid', $keyid, PDO::PARAM_INT);
        $stmt->execute();

        // Confirmar as alterações no banco de dados
        $pdo->commit();

        echo "<script>alert('Ticket atualizado com sucesso!'); window.location.href='tickets_atribuidos.php';</script>";
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        echo "<script>alert('Erro ao atualizar o ticket: " . $e->getMessage() . "'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Acesso inválido!'); window.location.href='tickets_atribuidos.php';</script>";
}
