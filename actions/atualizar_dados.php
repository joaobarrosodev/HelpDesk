<?php
session_start();

include('conflogin.php');
include('db.php');

// Verificar se a mensagem foi enviada
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Captura os dados enviados pelo formulário
    $nome = $_POST['name'];
    $codigo_cliente = $_POST['entity_keyid'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Verificar se a mensagem não está vazia
    if (!empty($password)) {
        // Preparar o SQL para inserir a mensagem na tabela 'comments_xdfree01_extrafields'
        $sql = "UPDATE online_entity_extrafields set Name = :nome, Password = :password where email=:email";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':email', $email);
        if ($stmt->execute()) {
            // Redireciona de volta para a página do ticket após inserir a mensagem
            header("Location: user.php");
            exit;
        } else {
            echo "Erro ao enviar a mensagem. Tente novamente.";
        }
    } else {
        echo "Por favor, digite uma mensagem.";
    }
}
?>