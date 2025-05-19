<?php
session_start();
include('conflogin.php');
include('db.php');

// Check if user is logged in
if (!isset($_SESSION['usuario_email'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        // If AJAX request
        http_response_code(401);
        echo "Não autorizado";
        exit;
    } else {
        // If normal form submit
        header("Location: index.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && isset($_POST['keyid']) && isset($_POST['id'])) {
    $message = trim($_POST['message']);
    $keyid = $_POST['keyid'];
    $ticket_id = $_POST['id'];
    $user = $_SESSION['usuario_email'];
    
    // Determine message type (0 for admin, 1 for user)
    $type = isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin'] ? 0 : 1;
    
    if (!empty($message)) {
        // Insert message into database
        $sql = "INSERT INTO comments_xdfree01_extrafields 
                (XDFree01_KeyID, Date, Message, Type, user) 
                VALUES (:keyid, NOW(), :message, :type, :user)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':keyid', $keyid);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':user', $user);
        
        if ($stmt->execute()) {
            // Update the last update time for the ticket
            $sql_update = "UPDATE info_xdfree01_extrafields 
                          SET dateu = NOW() 
                          WHERE XDFree01_KeyID = :keyid";
            
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindParam(':keyid', $keyid);
            $stmt_update->execute();
            
            // Check if this is AJAX request
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
                // For AJAX requests, return success status
                http_response_code(200);
                echo "Mensagem enviada com sucesso";
                exit;
            } else {
                // For normal form submits, redirect back to ticket details
                header("Location: detalhes_ticket.php?keyid=" . $ticket_id);
                exit;
            }
        } else {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
                http_response_code(500);
                echo "Erro ao enviar a mensagem";
                exit;
            } else {
                echo "Erro ao enviar a mensagem. <a href='detalhes_ticket.php?keyid=" . $ticket_id . "'>Voltar</a>";
            }
        }
    } else {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            http_response_code(400);
            echo "Mensagem vazia";
            exit;
        } else {
            echo "A mensagem não pode estar vazia. <a href='detalhes_ticket.php?keyid=" . $ticket_id . "'>Voltar</a>";
        }
    }
} else {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        http_response_code(400);
        echo "Dados incompletos";
        exit;
    } else {
        echo "Requisição inválida. <a href='meus_tickets.php'>Voltar aos meus tickets</a>";
    }
}
?>
