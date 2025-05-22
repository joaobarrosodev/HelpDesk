<?php
session_start();
include('db.php'); // Conexão com o banco de dados
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON response header for AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
}

// Processar ação de "fechar ticket" via GET
if (isset($_GET['action']) && $_GET['action'] == 'close' && isset($_GET['keyid'])) {
    // Redirecionar para a página de detalhes com um parâmetro para pré-selecionar o status "Concluído"
    $keyid = $_GET['keyid'];
    echo "<script>window.location.href='detalhes_ticket.php?keyid=" . $keyid . "&pre_close=1';</script>";
    exit;
}

// Processar formulário via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    try {
        // **NOVA FUNCIONALIDADE: Atualização Individual de Campos**
        if (isset($_POST['single_field_update']) && $_POST['single_field_update'] == '1') {
            // Handle single field update
            $keyid = $_POST['keyid'] ?? '';
            $fieldName = $_POST['field_name'] ?? '';
            $fieldValue = $_POST['field_value'] ?? '';
            
            if (empty($keyid) || empty($fieldName)) {
                throw new Exception('Dados obrigatórios em falta.');
            }
            
            // Map field names to database columns
            $fieldMapping = [
                'status' => 'Status',
                'assigned_user' => 'Atribuido',
                'resolution_time' => 'Tempo',
                'resolution_description' => 'Relatorio',
                'extra_info' => 'MensagensInternas'
            ];
            
            if (!isset($fieldMapping[$fieldName])) {
                throw new Exception('Campo inválido.');
            }
            
            $dbColumn = $fieldMapping[$fieldName];
            
            // Special validation for specific fields
            if ($fieldName === 'resolution_time') {
                if (!is_numeric($fieldValue) || $fieldValue <= 0) {
                    throw new Exception('O tempo de resolução deve ser um número positivo.');
                }
                $fieldValue = (int)$fieldValue; // Convert to integer
            }
            
            if ($fieldName === 'assigned_user' && empty($fieldValue)) {
                throw new Exception('É necessário selecionar um responsável.');
            }
            
            // Iniciar transação
            $pdo->beginTransaction();
            
            // Update the specific field + dateu (data de atualização)
            $sql = "UPDATE info_xdfree01_extrafields SET {$dbColumn} = :field_value, dateu = NOW() WHERE XDFree01_KeyID = :keyid";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':field_value', $fieldValue);
            $stmt->bindParam(':keyid', $keyid);
            
            if ($stmt->execute()) {
                // Confirmar as alterações
                $pdo->commit();
                
                // Log the change
                error_log("Field '{$fieldName}' updated for ticket {$keyid} by " . ($_SESSION['admin_email'] ?? 'Unknown'));
                
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Campo atualizado com sucesso.',
                        'field' => $fieldName,
                        'value' => $fieldValue
                    ]);
                } else {
                    echo "<script>alert('Campo atualizado com sucesso!'); window.location.href='detalhes_ticket.php?keyid=" . $keyid . "';</script>";
                }
            } else {
                throw new Exception('Erro ao atualizar o campo na base de dados.');
            }
            
        } else {
            // **LÓGICA ORIGINAL: Atualização Completa do Formulário**
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
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        echo json_encode(['success' => false, 'message' => 'Para fechar um ticket, é necessário informar o tempo de resolução em minutos.']);
                        exit;
                    } else {
                        echo "<script>alert('Para fechar um ticket, é necessário informar o tempo de resolução em minutos.'); window.history.back();</script>";
                        exit;
                    }
                }
               
                // Verificar se a descrição da resolução está preenchida
                if (empty($description)) {
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        echo json_encode(['success' => false, 'message' => 'Para fechar um ticket, é necessário fornecer uma descrição da resolução.']);
                        exit;
                    } else {
                        echo "<script>alert('Para fechar um ticket, é necessário fornecer uma descrição da resolução.'); window.history.back();</script>";
                        exit;
                    }
                }
               
                // Verificar se um usuário está atribuído ao ticket
                if (empty($user)) {
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        echo json_encode(['success' => false, 'message' => 'Para fechar um ticket, é necessário atribuí-lo a um responsável.']);
                        exit;
                    } else {
                        echo "<script>alert('Para fechar um ticket, é necessário atribuí-lo a um responsável.'); window.history.back();</script>";
                        exit;
                    }
                }
            }
            
            // Validar que o tempo é um número positivo
            if (!is_numeric($resolution_time) || $resolution_time <= 0) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    echo json_encode(['success' => false, 'message' => 'Tempo de resolução inválido! Deve ser um número positivo.']);
                    exit;
                } else {
                    echo "<script>alert('Tempo de resolução inválido! Deve ser um número positivo.'); window.history.back();</script>";
                    exit;
                }
            }    
            
            // Converter para inteiro
            $time_formatted = (int)$resolution_time;
           
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
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                echo json_encode(['success' => true, 'message' => 'Ticket atualizado com sucesso!']);
            } else {
                echo "<script>alert('Ticket atualizado com sucesso!'); window.location.href='tickets_atribuidos.php';</script>";
            }
        }
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Error in processar_alteracao.php: " . $e->getMessage());
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } else {
            echo "<script>alert('Erro ao atualizar o ticket: " . $e->getMessage() . "'); window.history.back();</script>";
        }
    }
} else {
    echo "<script>alert('Acesso inválido!'); window.location.href='tickets_atribuidos.php';</script>";
}
?>