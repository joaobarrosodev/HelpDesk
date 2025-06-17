<?php
session_start();
include('conflogin.php');
include('db.php');
include('verificar_tempo_disponivel.php'); // Incluir o novo sistema

// Determinar se é uma requisição AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
}

// Verificar se temos os parâmetros necessários
if (!isset($_GET['keyid']) && !isset($_POST['keyid'])) {
    $response = ['success' => false, 'message' => 'ID do ticket não fornecido'];
    if ($isAjax) {
        echo json_encode($response);
        exit;
    }
    header('Location: consultar_tickets.php?error=missing_id');
    exit;
}

$ticketId = isset($_GET['keyid']) ? $_GET['keyid'] : $_POST['keyid'];

try {
    // Buscar informações do ticket atual
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
        if ($isAjax) {
            echo json_encode($response);
            exit;
        }
        header('Location: consultar_tickets.php?error=ticket_not_found');
        exit;
    }
    
    // Verificar se o ticket já está encerrado - NOVA VALIDAÇÃO
    if ($ticketAtual['Status'] === 'Concluído') {
        $response = ['success' => false, 'message' => 'Este ticket já foi encerrado e não pode mais ser modificado'];
        if ($isAjax) {
            echo json_encode($response);
            exit;
        }
        header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&error=ticket_closed');
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
    
    // Se o status está sendo alterado para "Concluído", fazer verificações adicionais
    if ($newStatus === 'Concluído' && $ticketAtual['Status'] !== 'Concluído') {
        // Usar valores atuais se não foram fornecidos novos valores
        $resolutionTime = $newResolutionTime ?: $ticketAtual['Tempo'];
        $resolutionDescription = $newResolutionDescription ?: $ticketAtual['Relatorio'];
        $assignedUser = $newAssignedUser ?: $ticketAtual['Atribuido'];
        
        // Validações obrigatórias para fechamento
        if (empty($resolutionTime) || !is_numeric($resolutionTime) || $resolutionTime <= 0) {
            $response = ['success' => false, 'message' => 'Tempo de resolução é obrigatório e deve ser maior que zero'];
            if ($isAjax) {
                echo json_encode($response);
                exit;
            }
            header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&error=time_required');
            exit;
        }
        
        if (empty($resolutionDescription)) {
            $response = ['success' => false, 'message' => 'Descrição da resolução é obrigatória para fechar o ticket'];
            if ($isAjax) {
                echo json_encode($response);
                exit;
            }
            header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&error=description_required');
            exit;
        }
        
        if (empty($assignedUser)) {
            $response = ['success' => false, 'message' => 'Ticket deve ser atribuído a um responsável antes de ser fechado'];
            if ($isAjax) {
                echo json_encode($response);
                exit;
            }
            header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&error=assignment_required');
            exit;
        }
        
        // Verificar tempo disponível nos contratos do cliente
        $entity = $ticketAtual['Entity']; // ID da entidade/cliente
        $tempoNecessario = intval($resolutionTime);
        
        $verificacaoTempo = verificarTempoDisponivel($entity, $tempoNecessario, $pdo, true); // Permitir débito
        
        if (!$verificacaoTempo['temTempo']) {
            // Cliente não tem tempo suficiente - verificar se pode usar débito
            $resumoContratos = obterResumoContratos($entity, $pdo);
            
            $mensagemDetalhada = $verificacaoTempo['mensagem'] . "\\n\\n";
            $mensagemDetalhada .= "Resumo dos contratos:\\n";
            
            foreach ($resumoContratos['contratos'] as $contrato) {
                $restanteHoras = floor($contrato['restanteMinutos'] / 60);
                $restanteMin = $contrato['restanteMinutos'] % 60;
                $statusDisplay = $contrato['excedido'] ? 'Excedido' : $contrato['status'];
                
                $totalMinutos = (int)$contrato['totalHoras'];
                $totalHorasDisplay = floor($totalMinutos / 60);
                $totalMinutosResto = $totalMinutos % 60;
                $contratoNome = $totalHorasDisplay . 'h';
                if ($totalMinutosResto > 0) {
                    $contratoNome .= ' ' . $totalMinutosResto . 'min';
                }
                
                $mensagemDetalhada .= "- Contrato {$contratoNome}: ";
                if ($contrato['restanteMinutos'] > 0) {
                    $mensagemDetalhada .= "{$restanteHoras}h {$restanteMin}min restantes";
                } else {
                    $mensagemDetalhada .= "Sem tempo disponível";
                }
                $mensagemDetalhada .= " ({$statusDisplay})\\n";
            }
            
            $totalRestanteHoras = floor($resumoContratos['tempoRestante'] / 60);
            $totalRestanteMin = $resumoContratos['tempoRestante'] % 60;
            $mensagemDetalhada .= "\\nTotal disponível: {$totalRestanteHoras}h {$totalRestanteMin}min";
            
            // Adicionar informação sobre débito
            if ($verificacaoTempo['podeUsarDebito']) {
                $tempoEmFaltaHoras = floor($verificacaoTempo['tempoEmFalta'] / 60);
                $tempoEmFaltaMin = $verificacaoTempo['tempoEmFalta'] % 60;
                $mensagemDetalhada .= "\\n\\nTempo em falta: {$tempoEmFaltaHoras}h {$tempoEmFaltaMin}min";
                $mensagemDetalhada .= "\\nEste tempo será criado como débito e descontado automaticamente no próximo contrato.";
                $mensagemDetalhada .= "\\nATENÇÃO: A criação de novos tickets ficará bloqueada até o débito ser quitado.";
            }
            
            $response = [
                'success' => false, 
                'message' => 'Tempo insuficiente nos contratos',
                'detalhes' => $mensagemDetalhada,
                'tempoNecessario' => $tempoNecessario,
                'tempoDisponivel' => $resumoContratos['tempoRestante'],
                'contratos' => $resumoContratos['contratos'],
                'podeUsarDebito' => $verificacaoTempo['podeUsarDebito'],
                'tempoEmFalta' => $verificacaoTempo['tempoEmFalta'] ?? 0
            ];
            
            if ($isAjax) {
                echo json_encode($response);
                exit;
            }
            
            header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&error=insufficient_time');
            exit;
        }
    }
    
    // Verificar se foi solicitado uso de débito
    $usarDebito = isset($_POST['usar_debito']) && $_POST['usar_debito'] === 'true';
    
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
        if ($isAjax) {
            echo json_encode($response);
            exit;
        }
        header('Location: consultar_tickets.php?error=no_changes');
        exit;
    }
    
    // Atualizar o ticket
    $sql = "UPDATE info_xdfree01_extrafields SET " . implode(', ', $updateFields) . ", dateu = NOW() WHERE XDFree01_KeyID = :keyid";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if (!$result) {
        $response = ['success' => false, 'message' => 'Falha ao atualizar ticket'];
        if ($isAjax) {
            echo json_encode($response);
            exit;
        }
        header('Location: consultar_tickets.php?error=update_failed');
        exit;
    }
    
    // Se o ticket foi fechado, distribuir o tempo pelos contratos
    if ($newStatus === 'Concluído' && $ticketAtual['Status'] !== 'Concluído') {
        $tempoGasto = intval($newResolutionTime ?: $ticketAtual['Tempo']);
        $entity = $ticketAtual['Entity'];
        $ticketNumber = $ticketAtual['ticket_number'];
        
        // Verificar novamente o tempo disponível
        $verificacaoFinal = verificarTempoDisponivel($entity, $tempoGasto, $pdo, true);
        
        if ($verificacaoFinal['temTempo']) {
            // Tem tempo suficiente - distribuir normalmente
            $distribuicao = distribuirTempoContratos($entity, $tempoGasto, $ticketNumber, $pdo);
            
            if (!$distribuicao['sucesso']) {
                // Reverter o fechamento do ticket se a distribuição falhou
                $sqlRevert = "UPDATE info_xdfree01_extrafields SET Status = :oldStatus WHERE XDFree01_KeyID = :keyid";
                $stmtRevert = $pdo->prepare($sqlRevert);
                $stmtRevert->bindParam(':oldStatus', $ticketAtual['Status']);
                $stmtRevert->bindParam(':keyid', $ticketId);
                $stmtRevert->execute();
                
                $response = [
                    'success' => false, 
                    'message' => 'Erro ao distribuir tempo pelos contratos: ' . $distribuicao['erro']
                ];
                
                if ($isAjax) {
                    echo json_encode($response);
                    exit;
                }
                
                header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&error=distribution_failed');
                exit;
            }
        } else if ($usarDebito && $verificacaoFinal['podeUsarDebito']) {
            // Usar tempo disponível primeiro, depois criar débito
            $tempoDisponivel = $verificacaoFinal['tempoRestanteTotal'];
            $tempoParaDebito = $tempoGasto - $tempoDisponivel;
            
            // Distribuir tempo disponível primeiro
            if ($tempoDisponivel > 0) {
                $distribuicaoDisponivel = distribuirTempoContratos($entity, $tempoDisponivel, $ticketNumber, $pdo);
                if (!$distribuicaoDisponivel['sucesso']) {
                    $sqlRevert = "UPDATE info_xdfree01_extrafields SET Status = :oldStatus WHERE XDFree01_KeyID = :keyid";
                    $stmtRevert = $pdo->prepare($sqlRevert);
                    $stmtRevert->bindParam(':oldStatus', $ticketAtual['Status']);
                    $stmtRevert->bindParam(':keyid', $ticketId);
                    $stmtRevert->execute();
                    
                    $response = ['success' => false, 'message' => 'Erro ao distribuir tempo disponível'];
                    if ($isAjax) {
                        echo json_encode($response);
                        exit;
                    }
                    header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&error=distribution_failed');
                    exit;
                }
            }
            
            // Criar débito para o tempo restante
            if ($tempoParaDebito > 0) {
                $debitoSucesso = criarDebitoTempo($entity, $tempoParaDebito, $ticketNumber, $pdo);
                if (!$debitoSucesso) {
                    // Reverter mudanças se não conseguir criar débito
                    $sqlRevert = "UPDATE info_xdfree01_extrafields SET Status = :oldStatus WHERE XDFree01_KeyID = :keyid";
                    $stmtRevert = $pdo->prepare($sqlRevert);
                    $stmtRevert->bindParam(':oldStatus', $ticketAtual['Status']);
                    $stmtRevert->bindParam(':keyid', $ticketId);
                    $stmtRevert->execute();
                    
                    $response = ['success' => false, 'message' => 'Erro ao criar débito de tempo'];
                    if ($isAjax) {
                        echo json_encode($response);
                        exit;
                    }
                    header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&error=debt_failed');
                    exit;
                }
            }
        } else {
            // Não tem tempo e não pode usar débito - não deveria chegar aqui
            $sqlRevert = "UPDATE info_xdfree01_extrafields SET Status = :oldStatus WHERE XDFree01_KeyID = :keyid";
            $stmtRevert = $pdo->prepare($sqlRevert);
            $stmtRevert->bindParam(':oldStatus', $ticketAtual['Status']);
            $stmtRevert->bindParam(':keyid', $ticketId);
            $stmtRevert->execute();
            
            $response = ['success' => false, 'message' => 'Tempo insuficiente nos contratos'];
            if ($isAjax) {
                echo json_encode($response);
                exit;
            }
            header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&error=insufficient_time');
            exit;
        }
        
        // Recalcular SpentHours para garantir precisão
        if (isset($distribuicao) && $distribuicao['sucesso']) {
            foreach ($distribuicao['distribuicoes'] as $dist) {
                recalcularSpentHours($dist['contratoId'], $pdo);
            }
        }
        
        // Processar débitos automáticos se houver novos contratos
        processarDebitosAutomaticos($entity, $pdo);
        
        // Log da operação
        if ($usarDebito && isset($tempoParaDebito) && $tempoParaDebito > 0) {
            error_log("Ticket {$ticketId} fechado com débito - Tempo usado: {$tempoDisponivel}min, Débito criado: {$tempoParaDebito}min");
        } else {
            error_log("Ticket {$ticketId} fechado - Tempo distribuído normalmente");
        }
    }
    
    $response = [
        'success' => true, 
        'message' => 'Alterações guardadas com sucesso',
        'ticketFechado' => ($newStatus === 'Concluído'),
        'tempoDistribuido' => ($newStatus === 'Concluído') ? ($newResolutionTime ?: $ticketAtual['Tempo']) : null,
        'debitoUsado' => isset($tempoParaDebito) && $tempoParaDebito > 0
    ];
    
    if ($isAjax) {
        echo json_encode($response);
        exit;
    }
    
    header('Location: detalhes_ticket.php?keyid=' . urlencode($ticketId) . '&success=updated');
    
} catch (PDOException $e) {
    error_log("Erro na base de dados: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Erro na base de dados: ' . $e->getMessage()];
    
    if ($isAjax) {
        echo json_encode($response);
        exit;
    }
    
    header('Location: consultar_tickets.php?error=database_error');
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()];
    
    if ($isAjax) {
        echo json_encode($response);
        exit;
    }
    
    header('Location: consultar_tickets.php?error=server_error');
}

exit;
?>