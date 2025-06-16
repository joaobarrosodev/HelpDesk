<?php
// verificar_tempo_disponivel.php
// Sistema de verificação e gestão de tempo nos contratos

function verificarTempoDisponivel($entity, $tempoNecessario, $pdo) {
    try {
        // Converter tempo necessário para minutos (se estiver em horas)
        $tempoNecessarioMinutos = is_numeric($tempoNecessario) ? intval($tempoNecessario) : 0;
        
        // Buscar todos os contratos do cliente ordenados por prioridade
        $sql = "SELECT Id, XDfree02_KeyId, Entity, TotalHours, SpentHours, Status, StartDate
                FROM info_xdfree02_extrafields 
                WHERE Entity = :entity 
                ORDER BY 
                    CASE 
                        WHEN Status = 'Em Utilização' AND SpentHours < TotalHours THEN 1
                        WHEN Status = 'Em Utilização' AND SpentHours >= TotalHours THEN 2
                        WHEN Status = 'Por Começar' THEN 3
                        ELSE 4
                    END,
                    TotalHours DESC, -- Maior número de horas primeiro dentro do mesmo status
                    StartDate ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entity', $entity);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($contratos)) {
            return [
                'temTempo' => false,
                'mensagem' => 'Cliente não possui contratos ativos.',
                'distribuicao' => [],
                'tempoRestanteTotal' => 0
            ];
        }
        
        // Calcular tempo restante total e distribuição
        $tempoRestanteTotal = 0;
        $distribuicaoSimulada = [];
        $tempoRestante = $tempoNecessarioMinutos;
        
        foreach ($contratos as $contrato) {
            // TotalHours and SpentHours are already in minutes in the database
            $totalHorasMinutos = $contrato['TotalHours']; // Already in minutes
            $spentHorasMinutos = ($contrato['SpentHours'] ?? 0); // Already in minutes
            $disponivelContrato = max(0, $totalHorasMinutos - $spentHorasMinutos);
            
            // Calcular tempo restante total (apenas contratos não excedidos)
            if ($contrato['SpentHours'] <= $contrato['TotalHours']) {
                $tempoRestanteTotal += $disponivelContrato;
            }
            
            // Simular distribuição do tempo necessário
            if ($tempoRestante > 0 && $disponivelContrato > 0) {
                $tempoAUsar = min($tempoRestante, $disponivelContrato);
                
                // Convert contract total hours for display
                $totalHorasContrato = $contrato['TotalHours']; // Already in minutes
                $horasDisplay = floor($totalHorasContrato / 60);
                $minutosDisplay = $totalHorasContrato % 60;
                
                $contratoNomeDisplay = "Contrato " . $horasDisplay . "h";
                if ($minutosDisplay > 0) {
                    $contratoNomeDisplay .= " " . $minutosDisplay . "min";
                }
                
                $distribuicaoSimulada[] = [
                    'contratoId' => $contrato['XDfree02_KeyId'],
                    'contratoNome' => $contratoNomeDisplay,
                    'tempoUsado' => $tempoAUsar,
                    'tempoDisponivelAntes' => $disponivelContrato,
                    'tempoDisponivelDepois' => $disponivelContrato - $tempoAUsar,
                    'ficaExcedido' => ($spentHorasMinutos + $tempoAUsar) > $totalHorasMinutos
                ];
                $tempoRestante -= $tempoAUsar;
            }
        }
        
        // Verificar se tem tempo suficiente
        $temTempo = $tempoRestanteTotal >= $tempoNecessarioMinutos;
        
        if (!$temTempo) {
            $tempoEmFalta = $tempoNecessarioMinutos - $tempoRestanteTotal;
            $horasEmFalta = floor($tempoEmFalta / 60);
            $minutosEmFalta = $tempoEmFalta % 60;
            
            $mensagem = "Tempo insuficiente. Necessário: {$tempoNecessarioMinutos} min, Disponível: {$tempoRestanteTotal} min. ";
            $mensagem .= "Em falta: ";
            if ($horasEmFalta > 0) {
                $mensagem .= "{$horasEmFalta}h ";
            }
            if ($minutosEmFalta > 0) {
                $mensagem .= "{$minutosEmFalta}min";
            }
        } else {
            $mensagem = "Tempo suficiente disponível.";
        }
        
        return [
            'temTempo' => $temTempo,
            'mensagem' => $mensagem,
            'distribuicao' => $distribuicaoSimulada,
            'tempoRestanteTotal' => $tempoRestanteTotal,
            'tempoNecessario' => $tempoNecessarioMinutos,
            'contratos' => $contratos
        ];
        
    } catch (Exception $e) {
        return [
            'temTempo' => false,
            'mensagem' => 'Erro ao verificar tempo disponível: ' . $e->getMessage(),
            'distribuicao' => [],
            'tempoRestanteTotal' => 0
        ];
    }
}

function distribuirTempoContratos($entity, $tempoGasto, $ticketId, $pdo) {
    try {
        // Verificar primeiro se há tempo suficiente
        $verificacao = verificarTempoDisponivel($entity, $tempoGasto, $pdo);
        
        if (!$verificacao['temTempo']) {
            throw new Exception($verificacao['mensagem']);
        }
        
        $tempoRestante = intval($tempoGasto);
        $distribuicoes = [];
        
        // Buscar contratos ordenados por prioridade
        $sql = "SELECT Id, XDfree02_KeyId, Entity, TotalHours, SpentHours, Status
                FROM info_xdfree02_extrafields 
                WHERE Entity = :entity 
                ORDER BY 
                    CASE 
                        WHEN Status = 'Em Utilização' AND SpentHours < TotalHours THEN 1
                        WHEN Status = 'Por Começar' THEN 2
                        ELSE 3
                    END,
                    TotalHours DESC,
                    Id ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entity', $entity);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($contratos as $contrato) {
            if ($tempoRestante <= 0) break;
            
            // TotalHours and SpentHours are already in minutes in the database
            $totalHorasMinutos = $contrato['TotalHours']; // Already in minutes
            $spentHorasMinutos = ($contrato['SpentHours'] ?? 0); // Already in minutes
            $disponivelContrato = max(0, $totalHorasMinutos - $spentHorasMinutos);
            
            if ($disponivelContrato > 0) {
                $tempoAUsar = min($tempoRestante, $disponivelContrato);
                
                // Verificar se já existe um registro para este ticket e contrato
                $sqlCheck = "SELECT Id FROM tickets_xdfree02_extrafields 
                            WHERE XDfree02_KeyId = :contratoId AND TicketNumber = :ticketNumber";
                $stmtCheck = $pdo->prepare($sqlCheck);
                $stmtCheck->bindParam(':contratoId', $contrato['XDfree02_KeyId']);
                $stmtCheck->bindParam(':ticketNumber', $ticketId);
                $stmtCheck->execute();
                $existingRecord = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($existingRecord) {
                    // Atualizar registro existente
                    $sqlUpdate = "UPDATE tickets_xdfree02_extrafields 
                                 SET TotTime = TotTime + :totTime 
                                 WHERE Id = :id";
                    $stmtUpdate = $pdo->prepare($sqlUpdate);
                    $stmtUpdate->bindParam(':totTime', $tempoAUsar);
                    $stmtUpdate->bindParam(':id', $existingRecord['Id']);
                    $stmtUpdate->execute();
                } else {
                    // Inserir novo registro
                    $sqlInsert = "INSERT INTO tickets_xdfree02_extrafields (XDfree02_KeyId, TicketNumber, TotTime) 
                                 VALUES (:contratoId, :ticketNumber, :totTime)";
                    $stmtInsert = $pdo->prepare($sqlInsert);
                    $stmtInsert->bindParam(':contratoId', $contrato['XDfree02_KeyId']);
                    $stmtInsert->bindParam(':ticketNumber', $ticketId);
                    $stmtInsert->bindParam(':totTime', $tempoAUsar);
                    $stmtInsert->execute();
                }
                
                // Atualizar SpentHours do contrato
                $novoSpentHours = ($contrato['SpentHours'] ?? 0) + $tempoAUsar; // Keep in minutes
                
                // Determinar novo status baseado no tempo restante
                $novoStatus = '';
                if ($novoSpentHours > $contrato['TotalHours']) {
                    $novoStatus = 'Excedido';
                } elseif ($novoSpentHours >= $contrato['TotalHours']) {
                    $novoStatus = 'Concluído';
                } elseif ($contrato['Status'] === 'Por Começar' && $novoSpentHours > 0) {
                    $novoStatus = 'Em Utilização';
                } else {
                    // Manter status atual se já está em utilização e ainda tem tempo
                    $novoStatus = $contrato['Status'];
                }
                
                $sqlUpdate = "UPDATE info_xdfree02_extrafields 
                             SET SpentHours = :spentHours,
                                 Status = :status
                             WHERE XDfree02_KeyId = :contratoId";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':spentHours', $novoSpentHours);
                $stmtUpdate->bindParam(':status', $novoStatus);
                $stmtUpdate->bindParam(':contratoId', $contrato['XDfree02_KeyId']);
                $stmtUpdate->execute();
                
                $distribuicoes[] = [
                    'contratoId' => $contrato['XDfree02_KeyId'],
                    'tempoUsado' => $tempoAUsar,
                    'novoSpentHours' => $novoSpentHours
                ];
                
                $tempoRestante -= $tempoAUsar;
            }
        }
        
        return [
            'sucesso' => true,
            'distribuicoes' => $distribuicoes,
            'tempoTotal' => $tempoGasto
        ];
        
    } catch (Exception $e) {
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
}

// Função para obter resumo dos contratos de um cliente
function obterResumoContratos($entity, $pdo) {
    try {
        // Primeiro, atualizar todos os status dos contratos baseado no tempo restante
        atualizarStatusContratos($entity, $pdo);
        
        $sql = "SELECT XDfree02_KeyId, TotalHours, SpentHours, Status, TotalAmount, StartDate
                FROM info_xdfree02_extrafields 
                WHERE Entity = :entity 
                ORDER BY Status, TotalHours DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entity', $entity);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $resumo = [
            'contratos' => [],
            'tempoTotalComprado' => 0,
            'tempoTotalGasto' => 0,
            'tempoRestante' => 0,
            'contratosExcedidos' => 0
        ];
        
        foreach ($contratos as $contrato) {
            // TotalHours and SpentHours are already in minutes in the database
            $totalMinutos = $contrato['TotalHours']; // Already in minutes
            $gastoMinutos = ($contrato['SpentHours'] ?? 0); // Already in minutes
            $restanteMinutos = max(0, $totalMinutos - $gastoMinutos);
            
            $resumo['contratos'][] = [
                'id' => $contrato['XDfree02_KeyId'],
                'totalHoras' => $contrato['TotalHours'], // Keep in minutes
                'gastasHoras' => $contrato['SpentHours'] ?? 0,
                'restanteMinutos' => $restanteMinutos,
                'status' => $contrato['Status'],
                'valor' => $contrato['TotalAmount'],
                'dataInicio' => $contrato['StartDate'],
                'excedido' => ($contrato['SpentHours'] ?? 0) > $contrato['TotalHours']
            ];
            
            $resumo['tempoTotalComprado'] += $totalMinutos;
            $resumo['tempoTotalGasto'] += $gastoMinutos;
            
            if (($contrato['SpentHours'] ?? 0) <= $contrato['TotalHours']) {
                $resumo['tempoRestante'] += $restanteMinutos;
            }
            
            if (($contrato['SpentHours'] ?? 0) > $contrato['TotalHours']) {
                $resumo['contratosExcedidos']++;
            }
        }
        
        return $resumo;
        
    } catch (Exception $e) {
        return [
            'erro' => $e->getMessage(),
            'contratos' => [],
            'tempoTotalComprado' => 0,
            'tempoTotalGasto' => 0,
            'tempoRestante' => 0,
            'contratosExcedidos' => 0
        ];
    }
}

// Nova função para atualizar status dos contratos automaticamente
function atualizarStatusContratos($entity, $pdo) {
    try {
        $sql = "SELECT XDfree02_KeyId, TotalHours, SpentHours, Status 
                FROM info_xdfree02_extrafields 
                WHERE Entity = :entity";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entity', $entity);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($contratos as $contrato) {
            $spentHours = $contrato['SpentHours'] ?? 0; // Already in minutes
            $totalHours = $contrato['TotalHours'] ?? 0; // Already in minutes
            $statusAtual = $contrato['Status'];
            
            $novoStatus = '';
            
            // Determinar novo status baseado no tempo gasto
            if ($spentHours > $totalHours) {
                $novoStatus = 'Excedido';
            } elseif ($spentHours >= $totalHours && $totalHours > 0) {
                $novoStatus = 'Concluído';
            } elseif ($spentHours > 0) {
                $novoStatus = 'Em Utilização';
            } else {
                $novoStatus = 'Por Começar';
            }
            
            // Atualizar apenas se o status mudou
            if ($novoStatus !== $statusAtual) {
                $sqlUpdate = "UPDATE info_xdfree02_extrafields 
                             SET Status = :status 
                             WHERE XDfree02_KeyId = :contratoId";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':status', $novoStatus);
                $stmtUpdate->bindParam(':contratoId', $contrato['XDfree02_KeyId']);
                $stmtUpdate->execute();
                
                error_log("Status do contrato {$contrato['XDfree02_KeyId']} atualizado de '{$statusAtual}' para '{$novoStatus}' (SpentHours: {$spentHours}, TotalHours: {$totalHours})");
            }
        }
        
    } catch (Exception $e) {
        error_log("Erro ao atualizar status dos contratos: " . $e->getMessage());
    }
}

// Nova função para recalcular SpentHours baseado nos tickets reais
function recalcularSpentHours($contratoId, $pdo) {
    try {
        // Somar o tempo real de todos os tickets deste contrato
        $sqlSoma = "SELECT COALESCE(SUM(TotTime), 0) as total_tempo 
                    FROM tickets_xdfree02_extrafields 
                    WHERE XDfree02_KeyId = :contratoId";
        $stmtSoma = $pdo->prepare($sqlSoma);
        $stmtSoma->bindParam(':contratoId', $contratoId);
        $stmtSoma->execute();
        $resultado = $stmtSoma->fetch(PDO::FETCH_ASSOC);
        
        $tempoRealGasto = (int)$resultado['total_tempo'];
        
        // Atualizar o SpentHours do contrato com o valor correto
        $sqlUpdate = "UPDATE info_xdfree02_extrafields 
                     SET SpentHours = :spentHours 
                     WHERE XDfree02_KeyId = :contratoId";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':spentHours', $tempoRealGasto);
        $stmtUpdate->bindParam(':contratoId', $contratoId);
        $stmtUpdate->execute();
        
        error_log("SpentHours recalculado para contrato {$contratoId}: {$tempoRealGasto} minutos");
        
        return $tempoRealGasto;
        
    } catch (Exception $e) {
        error_log("Erro ao recalcular SpentHours para contrato {$contratoId}: " . $e->getMessage());
        return 0;
    }
}

// Função para verificar e corrigir discrepâncias em todos os contratos
function verificarECorrigirDiscrepancias($pdo) {
    try {
        $sql = "SELECT XDfree02_KeyId, SpentHours 
                FROM info_xdfree02_extrafields 
                WHERE XDfree02_KeyId IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $correcoes = 0;
        
        foreach ($contratos as $contrato) {
            $contratoId = $contrato['XDfree02_KeyId'];
            $spentHoursAtual = (int)($contrato['SpentHours'] ?? 0);
            
            // Calcular tempo real dos tickets
            $sqlTickets = "SELECT COALESCE(SUM(TotTime), 0) as total_tempo 
                          FROM tickets_xdfree02_extrafields 
                          WHERE XDfree02_KeyId = :contratoId";
            $stmtTickets = $pdo->prepare($sqlTickets);
            $stmtTickets->bindParam(':contratoId', $contratoId);
            $stmtTickets->execute();
            $resultadoTickets = $stmtTickets->fetch(PDO::FETCH_ASSOC);
            $tempoRealTickets = (int)$resultadoTickets['total_tempo'];
            
            // Se há discrepância, corrigir
            if ($spentHoursAtual !== $tempoRealTickets) {
                recalcularSpentHours($contratoId, $pdo);
                $correcoes++;
                error_log("Discrepância corrigida no contrato {$contratoId}: DB tinha {$spentHoursAtual}min, tickets somam {$tempoRealTickets}min");
            }
        }
        
        return $correcoes;
        
    } catch (Exception $e) {
        error_log("Erro ao verificar discrepâncias: " . $e->getMessage());
        return 0;
    }
}
?>