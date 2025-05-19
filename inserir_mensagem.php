<?php
session_start();
include('conflogin.php');
include('db.php');
include('ws-manager.php');

// Check if user is logged in
if (!isset($_SESSION['usuario_email'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // If AJAX request
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
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
    $deviceId = isset($_POST['deviceId']) ? $_POST['deviceId'] : null;
    
    // Determine message type (0 for admin, 1 for user)
    $type = (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']) ? 0 : 1;
    
    if (!empty($message)) {
        try {
            // Insert message into database
            $sql = "INSERT INTO comments_xdfree01_extrafields 
                    (XDFree01_KeyID, Date, Message, Type, user) 
                    VALUES (:keyid, NOW(), :message, :type, :user)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':keyid', $keyid);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':user', $user);
            
            $result = $stmt->execute();
            
            if ($result) {
                // Update the last update time for the ticket
                $sql_update = "UPDATE info_xdfree01_extrafields 
                               SET dateu = NOW() 
                               WHERE XDFree01_KeyID = :keyid";
                
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':keyid', $keyid);
                $stmt_update->execute();
                
                // Get the exact timestamp of the inserted message
                $sql_get_timestamp = "SELECT Date FROM comments_xdfree01_extrafields 
                                     WHERE XDFree01_KeyID = :keyid 
                                     AND user = :user
                                     ORDER BY Date DESC LIMIT 1";
                $stmt_timestamp = $pdo->prepare($sql_get_timestamp);
                $stmt_timestamp->bindParam(':keyid', $keyid);
                $stmt_timestamp->bindParam(':user', $user);
                $stmt_timestamp->execute();
                $exactTimestamp = $stmt_timestamp->fetchColumn();
                
                // Generate a unique message ID to help with deduplication
                $messageId = uniqid('msg_');
                
                // Prepare the message data
                $messageData = [
                    'Message' => nl2br(htmlspecialchars($message)),
                    'type' => $type,
                    'CommentTime' => $exactTimestamp,
                    'user' => $user,
                    'sourceDeviceId' => $deviceId,
                    'messageId' => $messageId
                ];
                
                // Create temp directory if it doesn't exist
                $tempDir = __DIR__ . '/temp';
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0777, true);
                    chmod($tempDir, 0777);
                }
                
                // Create a sync file - this is the most reliable method
                $syncId = uniqid();
                $cleanKeyId = str_replace('#', '', $keyid); // Remove # for consistency
                $syncFile = "temp/sync_{$cleanKeyId}_{$syncId}.txt";
                file_put_contents($syncFile, json_encode([
                    'timestamp' => $exactTimestamp,
                    'message' => $messageData
                ]));
                @chmod($syncFile, 0666);
                
                // Success response
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Mensagem enviada com sucesso',
                        'type' => $type,
                        'user' => $user,
                        'time' => date('H:i'),
                        'exactTimestamp' => $exactTimestamp,
                        'messageId' => $messageId,
                        'syncFile' => $syncFile,
                        'deviceId' => $deviceId
                    ]);
                    exit;
                } else {
                    header("Location: detalhes_ticket.php?keyid=" . $ticket_id);
                    exit;
                }
            } else {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Erro ao inserir a mensagem no banco de dados']);
                    exit;
                } else {
                    echo "Erro ao enviar a mensagem. <a href='detalhes_ticket.php?keyid=" . $ticket_id . "'>Voltar</a>";
                }
            }
        } catch (PDOException $e) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Erro no banco de dados']);
                exit;
            } else {
                echo "Erro de banco de dados. <a href='detalhes_ticket.php?keyid=" . $ticket_id . "'>Voltar</a>";
            }
        }
    } else {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Mensagem vazia']);
            exit;
        } else {
            echo "A mensagem não pode estar vazia. <a href='detalhes_ticket.php?keyid=" . $ticket_id . "'>Voltar</a>";
        }
    }
} else {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dados incompletos']);
        exit;
    } else {
        echo "Requisição inválida. <a href='meus_tickets.php'>Voltar aos meus tickets</a>";
    }
}
?>
