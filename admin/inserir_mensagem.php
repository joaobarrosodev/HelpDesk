<?php
session_start();

include('conflogin.php');
include('db.php');

// Verificar se a mensagem foi enviada
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Captura a mensagem e o KeyId do ticket
    $keyid = $_POST['keyid']; // KeyId do ticket
    $id = $_POST['id'];
    $message = $_POST['message']; // Mensagem

    // Verificar se a mensagem não está vazia
    if (!empty($message)) {
        // Preparar o SQL para inserir a mensagem na tabela 'comments_xdfree01_extrafields'
        $sql = "INSERT INTO comments_xdfree01_extrafields (xdfree01_KeyId, Date, Message, Type, user) 
                VALUES (:keyid, NOW(), :message, 0, :email)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':keyid', $keyid);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':email', $_SESSION['admin_email']);
        
        // Executa o comando para inserir a mensagem
        if ($stmt->execute()) {
            // Atualizar a data de atualização na tabela 'info_xdfree01_extrafields'
            $update_sql = "UPDATE info_xdfree01_extrafields 
                           SET dateu = NOW() 
                           WHERE XDfree01_KeyId = :keyid";
            
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(':keyid', $keyid);
            
            // Executa o comando de update
            if ($update_stmt->execute()) {
                // Redireciona de volta para a página do ticket após inserir a mensagem
                header("Location: detalhes_ticket.php?keyid=$id");
                exit;
            } else {
                echo "Erro ao atualizar a data de atualização. Tente novamente.";
            }
        } else {
            echo "Erro ao enviar a mensagem. Tente novamente.";
        }
    } else {
        echo "Por favor, digite uma mensagem.";
    }
}
?>
