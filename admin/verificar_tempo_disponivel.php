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
            $totalHorasMinutos = $contrato['TotalHours'] * 60; // Converter para minutos
            $spentHorasMinutos = ($contrato['SpentHours'] ?? 0) * 60; // Converter para minutos
            $disponivelContrato = max(0, $totalHorasMinutos - $spentHorasMinutos);
            
            // Calcular tempo restante total (apenas contratos não excedidos)
            if ($contrato['SpentHours'] <= $contrato['TotalHours']) {
                $tempoRestanteTotal += $disponivelContrato;
            }
            
            // Simular distribuição do tempo necessário
            if ($tempoRestante > 0 && $disponivelContrato > 0) {
                $tempoAUsar = min($tempoRestante, $disponivelContrato);
                $distribuicaoSimulada[] = [
                    'contratoId' => $contrato['XDfree02_KeyId'],
                    'contratoNome' => "Contrato {$contrato['TotalHours']}h",
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
            
            $totalHorasMinutos = $contrato['TotalHours'] * 60;
            $spentHorasMinutos = ($contrato['SpentHours'] ?? 0) * 60;
            $disponivelContrato = max(0, $totalHorasMinutos - $spentHorasMinutos);
            
            if ($disponivelContrato > 0) {
                $tempoAUsar = min($tempoRestante, $disponivelContrato);
                
                // Inserir na tabela tickets_xdfree02_extrafields
                $sqlInsert = "INSERT INTO tickets_xdfree02_extrafields (XDfree02_KeyId, TicketNumber, TotTime) 
                             VALUES (:contratoId, :ticketNumber, :totTime)";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->bindParam(':contratoId', $contrato['XDfree02_KeyId']);
                $stmtInsert->bindParam(':ticketNumber', $ticketId);
                $stmtInsert->bindParam(':totTime', $tempoAUsar);
                $stmtInsert->execute();
                
                // Atualizar SpentHours do contrato
                $novoSpentHours = ($contrato['SpentHours'] ?? 0) + ($tempoAUsar / 60);
                $sqlUpdate = "UPDATE info_xdfree02_extrafields 
                             SET SpentHours = :spentHours,
                                 Status = CASE 
                                     WHEN :spentHours > TotalHours THEN 'Excedido'
                                     WHEN :spentHours = TotalHours THEN 'Concluído'
                                     WHEN Status = 'Por Começar' THEN 'Em Utilização'
                                     ELSE Status
                                 END
                             WHERE XDfree02_KeyId = :contratoId";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':spentHours', $novoSpentHours);
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
            $totalMinutos = $contrato['TotalHours'] * 60;
            $gastoMinutos = ($contrato['SpentHours'] ?? 0) * 60;
            $restanteMinutos = max(0, $totalMinutos - $gastoMinutos);
            
            $resumo['contratos'][] = [
                'id' => $contrato['XDfree02_KeyId'],
                'totalHoras' => $contrato['TotalHours'],
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
?>