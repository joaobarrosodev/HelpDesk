<?php
// IMPORTANT: Clear all prior output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start with a fresh buffer
ob_start();

// Set headers right away
header('Content-Type: application/json');

try {
    // Set a reasonable execution time limit
    set_time_limit(120); // 2 minutes should be plenty
    
    session_start();
    include('conflogin.php');
    include('db.php');
    include('../verificar_tempo_disponivel.php');

    // Default error response
    $response = ['success' => false, 'message' => 'Unknown error occurred'];

    // Transaction flag
    $transactionActive = false;

    // Debug logging
    error_log("processar_alteracao.php - Request method: " . $_SERVER['REQUEST_METHOD']);
    error_log("processar_alteracao.php - POST data: " . print_r($_POST, true));

    // Verificar se temos os parâmetros necessários
    if (!isset($_GET['keyid']) && !isset($_POST['keyid'])) {
        $response = ['success' => false, 'message' => 'ID do ticket não fornecido'];
        throw new Exception('ID do ticket não fornecido');
    }

    $ticketId = isset($_GET['keyid']) ? $_GET['keyid'] : $_POST['keyid'];

    // Start transaction
    $pdo->beginTransaction();
    $transactionActive = true;
    error_log("processar_alteracao.php - Transaction started");
    
    // Procurar informações do ticket atual
    $sql = "SELECT info.*, free.id as ticket_number, free.Name
            FROM info_xdfree01_extrafields info
            LEFT JOIN xdfree01 free ON info.XDFree01_KeyID = free.KeyId
            WHERE info.XDFree01_KeyID = :keyid";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':keyid', $ticketId);
    $stmt->execute();
    $ticketAtual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticketAtual) {
        $response = ['success' => false, 'message' => 'Ticket não encontrado'];
        if ($transactionStarted) {
            $pdo->rollBack();
            $transactionStarted = false;
        }
        echo json_encode($response);
        exit;
    }
    
    // Verificar se o ticket já está encerrado
    if ($ticketAtual['Status'] === 'Concluído') {
        $response = ['success' => false, 'message' => 'Este ticket já foi encerrado e não pode mais ser modificado'];
        echo json_encode($response);
        exit;
    }
    
    // Preparar campos para atualização
    $updateFields = [];
    $params = [':keyid' => $ticketId];
    
    // Determinar novos valores dos campos
    $newStatus = isset($_GET['Status']) ? $_GET['Status'] : (isset($_POST['status']) ? $_POST['status'] : null);
    $newAssignedUser = isset($_GET['Atribuido']) ? $_GET['Atribuido'] : (isset($_POST['assigned_user']) ? $_POST['assigned_user'] : null);
    $newResolutionTime = isset($_POST['resolution_time']) ? $_POST['resolution_time'] : null;
    $newResolutionDescription = isset($_POST['resolution_description']) ? $_POST['resolution_description'] : null;
    $newExtraInfo = isset($_POST['extra_info']) ? $_POST['extra_info'] : null;
    
    // Debug log for the critical parameters
    error_log("processar_alteracao.php - New status: " . $newStatus);
    error_log("processar_alteracao.php - New resolution time: " . $newResolutionTime);
    error_log("processar_alteracao.php - New resolution description: " . $newResolutionDescription);
    
    // Check for debit feature
    $usarDebito = isset($_POST['usar_debito']) && ($_POST['usar_debito'] === 'true' || $_POST['usar_debito'] === '1');
    error_log("processar_alteracao.php - Usar débito: " . ($usarDebito ? 'SIM' : 'NÃO'));
    
    // Se o status está sendo alterado para "Concluído", fazer verificações adicionais
    if ($newStatus === 'Concluído' && $ticketAtual['Status'] !== 'Concluído') {
        // Usar valores atuais se não foram fornecidos novos valores
        $resolutionTime = $newResolutionTime ?: $ticketAtual['Tempo'];
        $resolutionDescription = $newResolutionDescription ?: $ticketAtual['Relatorio'];
        $assignedUser = $newAssignedUser ?: $ticketAtual['Atribuido'];
        
        // Validações obrigatórias para fechamento
        if (empty($resolutionTime) || !is_numeric($resolutionTime) || $resolutionTime <= 0) {
            $response = ['success' => false, 'message' => 'Tempo de resolução é obrigatório e deve ser maior que zero'];
            echo json_encode($response);
            exit;
        }
        
        if (empty($assignedUser)) {
            $response = ['success' => false, 'message' => 'Ticket deve ser atribuído a um responsável antes de ser fechado'];
            echo json_encode($response);
            exit;
        }
        
        // Verificar tempo disponível nos contratos do cliente
        $entity = $ticketAtual['Entity']; // ID da entidade/cliente
        $tempoNecessario = intval($resolutionTime);
        
        // Check available time with debt option enabled
        $verificacaoTempo = verificarTempoDisponivel($entity, $tempoNecessario, $pdo, true);
        
        if (!$verificacaoTempo['temTempo']) {
            // Cliente não tem tempo suficiente - verificar se pode usar débito
            $resumoContratos = obterResumoContratos($entity, $pdo);
            
            // If debit is not enabled in the request, return an error with the option
            if (!$usarDebito) {
                $tempoEmFaltaHoras = floor($verificacaoTempo['tempoEmFalta'] / 60);
                $tempoEmFaltaMin = $verificacaoTempo['tempoEmFalta'] % 60;
                $tempoDisponivelHoras = floor($verificacaoTempo['tempoRestanteTotal'] / 60);
                $tempoDisponivelMin = $verificacaoTempo['tempoRestanteTotal'] % 60;
                
                // Format detailed message about missing time
                $mensagemTempo = "Tempo insuficiente nos contratos. ";
                $mensagemTempo .= "Necessário: {$tempoNecessario} minutos. ";
                $mensagemTempo .= "Disponível: {$verificacaoTempo['tempoRestanteTotal']} minutos.";
                
                if ($verificacaoTempo['podeUsarDebito']) {
                    $mensagemTempo .= " É possível utilizar débito para o tempo restante.";
                }
                
                error_log("processar_alteracao.php - " . $mensagemTempo);
                
                $response = [
                    'success' => false, 
                    'message' => 'Tempo insuficiente nos contratos',
                    'tempoNecessario' => $tempoNecessario,
                    'tempoDisponivel' => $verificacaoTempo['tempoRestanteTotal'],
                    'podeUsarDebito' => $verificacaoTempo['podeUsarDebito'],
                    'tempoEmFalta' => $verificacaoTempo['tempoEmFalta']
                ];
                
                echo json_encode($response);
                exit;
            }
        }
    }
    
    // Construir campos para atualização baseado nos parâmetros recebidos
    if ($newStatus !== null) {
        $updateFields[] = "Status = :status";
        $params[':status'] = $newStatus;
    }
    
    if ($newAssignedUser !== null) {
        $updateFields[] = "Atribuido = :atribuido";
        $params[':atribuido'] = $newAssignedUser ?: null;
    }
    
    if ($newResolutionTime !== null) {
        $updateFields[] = "Tempo = :resolution_time";
        $params[':resolution_time'] = $newResolutionTime;
    }
    
    if ($newResolutionDescription !== null) {
        $updateFields[] = "Relatorio = :resolution_description";
        $params[':resolution_description'] = $newResolutionDescription;
    }
    
    if ($newExtraInfo !== null) {
        $updateFields[] = "MensagensInternas = :extra_info";
        $params[':extra_info'] = $newExtraInfo;
    }
    
    if (empty($updateFields)) {
        $response = ['success' => false, 'message' => 'Nenhuma alteração fornecida'];
        echo json_encode($response);
        exit;
    }
    
    // Atualizar o ticket
    $sql = "UPDATE info_xdfree01_extrafields SET " . implode(', ', $updateFields) . ", dateu = NOW() WHERE XDFree01_KeyID = :keyid";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if (!$result) {
        $response = ['success' => false, 'message' => 'Falha ao atualizar ticket'];
        echo json_encode($response);
        exit;
    }
    
    // Se o ticket foi fechado, distribuir o tempo pelos contratos
    if ($newStatus === 'Concluído' && $ticketAtual['Status'] !== 'Concluído') {
        $tempoGasto = intval($newResolutionTime ?: $ticketAtual['Tempo']);
        $entity = $ticketAtual['Entity'];
        $ticketNumber = $ticketAtual['ticket_number'];
        
        // Final verification of available time
        $verificacaoFinal = verificarTempoDisponivel($entity, $tempoGasto, $pdo, true);
        error_log("processar_alteracao.php - Verificação final: " . print_r($verificacaoFinal, true));
        
        if ($verificacaoFinal['temTempo']) {
            error_log("processar_alteracao.php - Cliente tem tempo suficiente, distribuindo normalmente");
            // Tem tempo suficiente - distribuir normalmente
            $distribuicao = distribuirTempoContratos($entity, $tempoGasto, $ticketNumber, $pdo);
            error_log("processar_alteracao.php - Resultado da distribuição normal: " . print_r($distribuicao, true));
            
            if (!$distribuicao['sucesso']) {
                // Reverter o fechamento do ticket se a distribuição falhou
                $sqlRevert = "UPDATE info_xdfree01_extrafields SET Status = :oldStatus WHERE XDFree01_KeyID = :keyid";
                $stmtRevert = $pdo->prepare($sqlRevert);
                $stmtRevert->bindParam(':oldStatus', $ticketAtual['Status']);
                $stmtRevert->bindParam(':keyid', $ticketId);
                $stmtRevert->execute();
                
                $pdo->rollBack();
                $response = [
                    'success' => false, 
                    'message' => 'Erro ao distribuir tempo pelos contratos: ' . $distribuicao['erro']
                ];
                echo json_encode($response);
                exit;
            }
        } elseif ($usarDebito && $verificacaoFinal['podeUsarDebito']) {
            error_log("processar_alteracao.php - Usando sistema de débito - Transaction active: " . ($transactionActive ? 'SIM' : 'NAO'));
            // Usar tempo disponível primeiro, depois criar débito
            $tempoDisponivel = $verificacaoFinal['tempoRestanteTotal'];
            $tempoParaDebito = $tempoGasto - $tempoDisponivel;
            
            error_log("processar_alteracao.php - Tempo disponível: {$tempoDisponivel}, Tempo para débito: {$tempoParaDebito}");
            
            try {
                // Check if we still have a transaction before proceeding
                if (!$pdo->inTransaction()) {
                    error_log("processar_alteracao.php - Transaction lost before debt processing");
                    // Re-start transaction if needed
                    $pdo->beginTransaction();
                    $transactionActive = true;
                    error_log("processar_alteracao.php - Transaction restarted");
                }
                
                // Distribuir tempo disponível primeiro se houver algum
                if ($tempoDisponivel > 0) {
                    error_log("processar_alteracao.php - Distribuindo tempo disponível: {$tempoDisponivel}");
                    $distribuicaoDisponivel = distribuirTempoContratos($entity, $tempoDisponivel, $ticketNumber, $pdo);
                    if (!$distribuicaoDisponivel['sucesso']) {
                        throw new Exception('Erro ao distribuir tempo disponível: ' . ($distribuicaoDisponivel['erro'] ?? 'Erro desconhecido'));
                    }
                }
                
                // Criar débito para o tempo restante
                if ($tempoParaDebito > 0) {
                    error_log("processar_alteracao.php - Criando débito para: {$tempoParaDebito}. Transaction active: " . ($pdo->inTransaction() ? 'SIM' : 'NAO'));
                    
                    // Make sure we still have a transaction
                    if (!$pdo->inTransaction()) {
                        error_log("processar_alteracao.php - Transaction lost before creating debt");
                        $pdo->beginTransaction();
                        $transactionActive = true;
                        error_log("processar_alteracao.php - Transaction restarted before debt creation");
                    }
                    
                    try {
                        // Create the debt using our function - note we're now letting the exceptions bubble up
                        criarDebitoTempo($entity, $tempoParaDebito, $ticketNumber, $pdo);
                        error_log("processar_alteracao.php - Débito criado com sucesso");
                    } catch (Exception $debitoEx) {
                        // Log and rethrow the specific error from the debt creation function
                        error_log("processar_alteracao.php - Erro específico ao criar débito: " . $debitoEx->getMessage());
                        throw $debitoEx; // Re-throw to be caught by outer catch block
                    }
                }
                
            } catch (Exception $debitoEx) {
                // Specific error handling for debit creation failures
                error_log("processar_alteracao.php - Erro no processamento do débito: " . $debitoEx->getMessage());
                
                // Check if transaction is still active before rolling back
                if ($transactionActive && $pdo->inTransaction()) {
                    $pdo->rollBack();
                    $transactionActive = false;
                    error_log("processar_alteracao.php - Transaction rolled back after debt error");
                }
                
                // Try to revert ticket status if possible
                try {
                    $sqlRevert = "UPDATE info_xdfree01_extrafields SET Status = :oldStatus WHERE XDFree01_KeyID = :keyid";
                    $stmtRevert = $pdo->prepare($sqlRevert);
                    $stmtRevert->bindParam(':oldStatus', $ticketAtual['Status']);
                    $stmtRevert->bindParam(':keyid', $ticketId);
                    $stmtRevert->execute();
                    error_log("processar_alteracao.php - Ticket status reverted to: " . $ticketAtual['Status']);
                } catch (Exception $revertEx) {
                    error_log("processar_alteracao.php - Failed to revert ticket status: " . $revertEx->getMessage());
                }
                
                $response = ['success' => false, 'message' => 'Erro ao processar débito de tempo: ' . $debitoEx->getMessage()];
                echo json_encode($response);
                exit;
            }
        } else {
            error_log("processar_alteracao.php - Não tem tempo e não pode usar débito");
            $sqlRevert = "UPDATE info_xdfree01_extrafields SET Status = :oldStatus WHERE XDFree01_KeyID = :keyid";
            $stmtRevert = $pdo->prepare($sqlRevert);
            $stmtRevert->bindParam(':oldStatus', $ticketAtual['Status']);
            $stmtRevert->bindParam(':keyid', $ticketId);
            $stmtRevert->execute();
            
            $pdo->rollBack();
            $response = ['success' => false, 'message' => 'Tempo insuficiente nos contratos'];
            echo json_encode($response);
            exit;
        }
        
        // Atualizar status de contratos
        error_log("processar_alteracao.php - Atualizando status de contratos. Transaction active: " . ($pdo->inTransaction() ? 'SIM' : 'NAO'));
        
        // Check if we still have an active transaction
        if (!$pdo->inTransaction()) {
            error_log("processar_alteracao.php - Transaction lost before updating contract status");
            $pdo->beginTransaction();
            $transactionActive = true;
            error_log("processar_alteracao.php - Transaction restarted before updating contract status");
        }
        
        atualizarStatusContratos($entity, $pdo);
        
        // Log da operação
        if (isset($tempoParaDebito) && $tempoParaDebito > 0) {
            error_log("Ticket {$ticketId} fechado com débito - Tempo usado: {$tempoDisponivel}min, Débito criado: {$tempoParaDebito}min");
        } else {
            error_log("Ticket {$ticketId} fechado - Tempo distribuído normalmente");
        }
    }
    
    // After contract activation or creation (for example, after updating status to 'Em Utilização'):
    if ($newStatus === 'Em Utilização') {
        // Compensate any outstanding debt automatically
        processarDebitosAutomaticos($ticketAtual['Entity'], $pdo);
    }
    
    // If we get here, check if transaction is still active before committing
    if ($transactionActive && $pdo->inTransaction()) {
        error_log("processar_alteracao.php - Committing transaction");
        $pdo->commit();
        $transactionActive = false;
    } else if ($transactionActive) {
        error_log("processar_alteracao.php - Cannot commit - transaction no longer active");
        $transactionActive = false;
    }
    
    $response = [
        'success' => true, 
        'message' => 'Alterações guardadas com sucesso',
        'ticketFechado' => ($newStatus === 'Concluído'),
        'tempoDistribuido' => ($newStatus === 'Concluído') ? ($newResolutionTime ?: $ticketAtual['Tempo']) : null,
        'debitoUsado' => isset($tempoParaDebito) && $tempoParaDebito > 0
    ];
    
} catch (PDOException $e) {
    // Only rollback if transaction is active
    error_log("processar_alteracao.php - PDO Exception: " . $e->getMessage());
    if (isset($transactionActive) && $transactionActive && isset($pdo) && $pdo->inTransaction()) {
        error_log("processar_alteracao.php - Rolling back transaction due to PDO error");
        $pdo->rollBack();
    }
    $transactionActive = false;
    $response = ['success' => false, 'message' => 'Erro na base de dados: ' . $e->getMessage()];
} catch (Exception $e) {
    // Only rollback if transaction is active
    error_log("processar_alteracao.php - General Exception: " . $e->getMessage());
    if (isset($transactionActive) && $transactionActive && isset($pdo) && $pdo->inTransaction()) {
        error_log("processar_alteracao.php - Rolling back transaction due to general error");
        $pdo->rollBack();
    }
    $transactionActive = false;
    $response = ['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()];
} finally {
    // Clean any previous output that might break our JSON
    ob_end_clean();
    
    // Output ONLY the JSON response
    echo json_encode($response);
    exit;
}