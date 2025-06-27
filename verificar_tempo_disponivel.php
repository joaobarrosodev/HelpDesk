<?php
// verificar_tempo_disponivel.php
// Sistema de verificação e gestão de tempo nos contratos
// Versão unificada - todos os arquivos devem incluir esta versão

function verificarTempoDisponivel($entity, $tempoNecessario, $pdo, $permitirDebito = false) {
    try {
        // Converter tempo necessário para minutos (se estiver em horas)
        $tempoNecessarioMinutos = is_numeric($tempoNecessario) ? intval($tempoNecessario) : 0;
        
        // Procurar todos os contratos do cliente ordenados por prioridade
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
                    TotalHours DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Se não houver contratos, retornar imediatamente
        if (empty($contratos)) {
            return [
                'temTempo' => false,
                'mensagem' => 'Cliente não possui contratos ativos.',
                'contratos' => [],
                'tempoRestanteTotal' => 0,
                'podeUsarDebito' => $permitirDebito,
                'tempoEmFalta' => $tempoNecessarioMinutos
            ];
        }
        
        // Calcular tempo total disponível em todos os contratos
        $tempoRestanteTotal = 0;
        $contratosDisponiveis = [];
        
        foreach ($contratos as $contrato) {
            // Considera apenas contratos em utilização ou por começar
            if ($contrato['Status'] === 'Em Utilização' || $contrato['Status'] === 'Por Começar') {
                $totalMinutos = intval($contrato['TotalHours']); // Já está em minutos no banco
                $gastoMinutos = intval($contrato['SpentHours'] ?? 0); // Já está em minutos no banco
                $restanteMinutos = max(0, $totalMinutos - $gastoMinutos);
                
                $tempoRestanteTotal += $restanteMinutos;
                
                $contratosDisponiveis[] = [
                    'id' => $contrato['Id'],
                    'keyId' => $contrato['XDfree02_KeyId'],
                    'totalHoras' => $totalMinutos,
                    'horasGastas' => $gastoMinutos,
                    'restante' => $restanteMinutos,
                    'status' => $contrato['Status']
                ];
            }
        }
        
        // Verificar se há tempo suficiente
        if ($tempoRestanteTotal >= $tempoNecessarioMinutos) {
            // Calcular distribuição para fins informativos
            $distribuicao = [];
            $tempoParaDistribuir = $tempoNecessarioMinutos;
            
            // Simular distribuição para visualização
            foreach ($contratosDisponiveis as $contrato) {
                if ($tempoParaDistribuir <= 0) break;
                
                $tempoAUsar = min($contrato['restante'], $tempoParaDistribuir);
                $tempoParaDistribuir -= $tempoAUsar;
                
                // Tempo que restaria após usar este contrato
                $tempoRestanteContrato = $contrato['restante'] - $tempoAUsar;
                
                // Calcular se o contrato ficaria excedido
                $ficaExcedido = ($contrato['horasGastas'] + $tempoAUsar) > $contrato['totalHoras'];
                
                // Calcular nome do contrato para exibição
                $totalHorasDisplay = floor($contrato['totalHoras'] / 60) . 'h';
                if ($contrato['totalHoras'] % 60 > 0) {
                    $totalHorasDisplay .= ' ' . ($contrato['totalHoras'] % 60) . 'min';
                }
                
                $distribuicao[] = [
                    'contratoId' => $contrato['keyId'],
                    'contratoNome' => $totalHorasDisplay,
                    'tempoUsado' => $tempoAUsar,
                    'tempoDisponivelAntes' => $contrato['restante'],
                    'tempoDisponivelDepois' => $tempoRestanteContrato,
                    'ficaExcedido' => $ficaExcedido
                ];
            }
            
            return [
                'temTempo' => true,
                'mensagem' => 'Cliente possui tempo suficiente.',
                'contratos' => $contratosDisponiveis,
                'tempoRestanteTotal' => $tempoRestanteTotal,
                'distribuicao' => $distribuicao
            ];
        } else {
            // Não há tempo suficiente
            $tempoEmFalta = $tempoNecessarioMinutos - $tempoRestanteTotal;
            
            return [
                'temTempo' => false,
                'mensagem' => "Cliente não possui tempo suficiente. Faltam {$tempoEmFalta} minutos.",
                'contratos' => $contratosDisponiveis,
                'tempoRestanteTotal' => $tempoRestanteTotal,
                'podeUsarDebito' => $permitirDebito,
                'tempoEmFalta' => $tempoEmFalta
            ];
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar tempo disponível: " . $e->getMessage());
        return [
            'temTempo' => false,
            'mensagem' => "Erro ao verificar tempo: " . $e->getMessage(),
            'erro' => true
        ];
    }
}

/**
 * Distribui o tempo gasto em um ticket entre os contratos disponíveis
 * @param string $entity ID da entidade/cliente
 * @param int $tempoGasto Tempo em minutos a ser distribuído
 * @param int $ticketNumber Número do ticket
 * @param PDO $pdo Conexão PDO com o banco de dados
 * @return array Resultado da operação
 */
function distribuirTempoContratos($entity, $tempoGasto, $ticketNumber, $pdo) {
    try {
        // Check if a transaction is already active
        $internalTransaction = false;
        if (!$pdo->inTransaction()) {
            error_log("distribuirTempoContratos - Starting new transaction");
            $pdo->beginTransaction();
            $internalTransaction = true;
        } else {
            error_log("distribuirTempoContratos - Using existing transaction");
        }
        
        // Buscar contratos disponíveis para este cliente
        $sql = "SELECT Id, XDfree02_KeyId, TotalHours, SpentHours, Status
                FROM info_xdfree02_extrafields 
                WHERE Entity = :entity 
                AND (Status = 'Em Utilização' OR Status = 'Por Começar')
                ORDER BY 
                    CASE 
                        WHEN Status = 'Em Utilização' AND SpentHours < TotalHours THEN 1
                        WHEN Status = 'Por Começar' THEN 2
                        ELSE 3
                    END,
                    TotalHours DESC";
                    
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($contratos)) {
            // Only rollback if we started the transaction
            if ($internalTransaction) {
                error_log("distribuirTempoContratos - Rolling back internal transaction: no contracts found");
                $pdo->rollBack();
            }
            return ['sucesso' => false, 'erro' => 'Cliente não possui contratos ativos.'];
        }
        
        // Determinar como distribuir o tempo
        $tempoRestante = $tempoGasto;
        $distribuicoes = [];
        
        foreach ($contratos as $contrato) {
            if ($tempoRestante <= 0) break;
            
            // Apenas contratos em utilização ou por começar
            if ($contrato['Status'] === 'Em Utilização' || $contrato['Status'] === 'Por Começar') {
                // Se for "Por Começar", ativar o contrato
                if ($contrato['Status'] === 'Por Começar') {
                    $sqlAtualizar = "UPDATE info_xdfree02_extrafields 
                                    SET Status = 'Em Utilização', 
                                        StartDate = NOW() 
                                    WHERE Id = :id";
                    $stmtAtualizar = $pdo->prepare($sqlAtualizar);
                    $stmtAtualizar->bindParam(':id', $contrato['Id'], PDO::PARAM_INT);
                    $stmtAtualizar->execute();
                    
                    // Atualizar objeto local
                    $contrato['Status'] = 'Em Utilização';
                    $contrato['SpentHours'] = 0; // Reset para garantir
                }
                
                $totalMinutos = intval($contrato['TotalHours']);
                $gastoMinutos = intval($contrato['SpentHours'] ?? 0);
                $disponivelMinutos = max(0, $totalMinutos - $gastoMinutos);
                
                // Determinar quanto tempo usar deste contrato
                $tempoAUsar = min($disponivelMinutos, $tempoRestante);
                $tempoRestante -= $tempoAUsar;
                
                // Registrar associação entre ticket e contrato
                $sqlInserir = "INSERT INTO tickets_xdfree02_extrafields 
                              (XDfree02_KeyId, TicketNumber, TotTime) 
                              VALUES (:contratoId, :ticketNumber, :tempoGasto)";
                $stmtInserir = $pdo->prepare($sqlInserir);
                $stmtInserir->bindParam(':contratoId', $contrato['XDfree02_KeyId'], PDO::PARAM_STR);
                $stmtInserir->bindParam(':ticketNumber', $ticketNumber, PDO::PARAM_INT);
                $stmtInserir->bindParam(':tempoGasto', $tempoAUsar, PDO::PARAM_INT);
                $stmtInserir->execute();
                
                // Registrar distribuição para o log
                $distribuicoes[] = [
                    'contratoId' => $contrato['XDfree02_KeyId'],
                    'tempoUsado' => $tempoAUsar
                ];
                
                // Log da operação
                $logMsg = "Tempo distribuído: {$tempoAUsar} minutos do ticket #{$ticketNumber} para contrato {$contrato['XDfree02_KeyId']}";
                error_log($logMsg);
                
                // Atualizar SpentHours apenas via recalculo
                recalcularSpentHours($contrato['XDfree02_KeyId'], $pdo);
            }
        }
        
        // Verificar se todo o tempo foi distribuído
        if ($tempoRestante > 0) {
            // Only rollback if we started the transaction
            if ($internalTransaction) {
                error_log("distribuirTempoContratos - Rolling back internal transaction: insufficient time");
                $pdo->rollBack();
            }
            return [
                'sucesso' => false, 
                'erro' => "Não há tempo suficiente para distribuir. Faltam {$tempoRestante} minutos.",
                'distribuicoes' => $distribuicoes
            ];
        }
        
        // Tudo ok, confirmar transação apenas se iniciamos uma
        if ($internalTransaction) {
            error_log("distribuirTempoContratos - Committing internal transaction");
            $pdo->commit();
        }
        
        return [
            'sucesso' => true, 
            'mensagem' => 'Tempo distribuído com sucesso.',
            'distribuicoes' => $distribuicoes
        ];
        
    } catch (Exception $e) {
        error_log("distribuirTempoContratos - Error: " . $e->getMessage());
        if (isset($internalTransaction) && $internalTransaction && $pdo->inTransaction()) {
            error_log("distribuirTempoContratos - Rolling back internal transaction due to error");
            $pdo->rollBack();
        }
        return ['sucesso' => false, 'erro' => "Erro ao distribuir tempo: " . $e->getMessage()];
    }
}
/**
 * Cria um registro de débito de tempo para uso futuro
 */
function criarDebitoTempo($entity, $tempoExcedido, $ticketNumber, $pdo) {
    try {
        error_log("criarDebitoTempo - Iniciando para entity: {$entity}, tempo: {$tempoExcedido}, ticket: {$ticketNumber}");
        error_log("criarDebitoTempo - PDO in transaction: " . ($pdo->inTransaction() ? "SIM" : "NAO"));
        
        // Validate input parameters
        if (empty($entity)) {
            throw new Exception("Entity ID cannot be empty");
        }
        
        if ($tempoExcedido <= 0) {
            throw new Exception("Tempo excedido must be greater than zero");
        }
        
        if (empty($ticketNumber)) {
            throw new Exception("Ticket number cannot be empty");
        }
        
        // Use a transaction to ensure all operations are atomic
        $internalTransaction = false;
        
        // Check if we're not already in a transaction
        if (!$pdo->inTransaction()) {
            error_log("criarDebitoTempo - Iniciando nova transação pois não existe uma ativa");
            $pdo->beginTransaction();
            $internalTransaction = true;
        } else {
            error_log("criarDebitoTempo - Usando transação existente");
        }
        
        // Buscar o contrato mais recente 'Em Utilização' para marcar como excedido
        $sql = "SELECT Id, XDfree02_KeyId 
                FROM info_xdfree02_extrafields 
                WHERE Entity = :entity 
                AND Status = 'Em Utilização'
                ORDER BY StartDate DESC, Id DESC
                LIMIT 1";
                
        error_log("criarDebitoTempo - Running SQL: " . $sql . " with entity: " . $entity);
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entity', $entity, PDO::PARAM_STR);
        if (!$stmt->execute()) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to query active contract: " . $errorInfo[2]);
        }
        
        $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contrato) {
            error_log("criarDebitoTempo - No active contract found for entity: {$entity}, looking for any contract");
            
            // Check if there are any contracts at all
            $sql = "SELECT Id, XDfree02_KeyId 
                    FROM info_xdfree02_extrafields 
                    WHERE Entity = :entity 
                    ORDER BY StartDate DESC, Id DESC
                    LIMIT 1";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':entity', $entity, PDO::PARAM_STR);
            if (!$stmt->execute()) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Failed to query any contract: " . $errorInfo[2]);
            }
            
            $anyContract = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$anyContract) {
                error_log("criarDebitoTempo - No contracts found at all, creating placeholder contract");
                // If there are no contracts at all, we need to create a placeholder contract
                $keyId = 'DB' . date('YmdHis') . rand(1000, 9999);
                
                $sql = "INSERT INTO info_xdfree02_extrafields 
                         (XDfree02_KeyId, Entity, TotalHours, SpentHours, Status, StartDate) 
                         VALUES (:keyid, :entity, 0, 0, 'Excedido', NOW())";
                
                error_log("criarDebitoTempo - Creating placeholder contract with SQL: " . $sql);
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':keyid', $keyId, PDO::PARAM_STR);
                $stmt->bindParam(':entity', $entity, PDO::PARAM_STR);
                
                if (!$stmt->execute()) {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception("Failed to create placeholder contract: " . $errorInfo[2]);
                }
                
                $lastId = $pdo->lastInsertId();
                if (!$lastId) {
                    throw new Exception("Failed to get last insert ID for placeholder contract");
                }
                
                $contrato = ['Id' => $lastId, 'XDfree02_KeyId' => $keyId];
                error_log("criarDebitoTempo - Created placeholder contract with ID: {$keyId}, DB ID: {$lastId}");
            } else {
                $contrato = $anyContract;
                error_log("criarDebitoTempo - Using existing contract: " . $contrato['XDfree02_KeyId'] . ", ID: " . $contrato['Id']);
                
                // Mark contract as exceeded
                $sqlUpdate = "UPDATE info_xdfree02_extrafields 
                             SET Status = 'Excedido'
                             WHERE Id = :id";
                             
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':id', $contrato['Id'], PDO::PARAM_INT);
                
                if (!$stmtUpdate->execute()) {
                    $errorInfo = $stmtUpdate->errorInfo();
                    throw new Exception("Failed to mark contract as exceeded: " . $errorInfo[2]);
                }
                
                error_log("criarDebitoTempo - Marked contract " . $contrato['XDfree02_KeyId'] . " as exceeded");
            }
        } else {
            // Marcar contrato como excedido
            error_log("criarDebitoTempo - Found active contract: " . $contrato['XDfree02_KeyId'] . ", marking as exceeded");
            
            $sqlUpdate = "UPDATE info_xdfree02_extrafields 
                         SET Status = 'Excedido'
                         WHERE Id = :id";
                         
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':id', $contrato['Id'], PDO::PARAM_INT);
            
            if (!$stmtUpdate->execute()) {
                $errorInfo = $stmtUpdate->errorInfo();
                throw new Exception("Failed to mark contract as exceeded: " . $errorInfo[2]);
            }
            
            error_log("criarDebitoTempo - Marked contract " . $contrato['XDfree02_KeyId'] . " as exceeded");
        }
        
        // Criar registro de débito na tabela de associação (REMOVING IsDebt COLUMN)
        error_log("criarDebitoTempo - Creating debt record for ticket {$ticketNumber}, contract " . $contrato['XDfree02_KeyId']);
        
        // Validate data before inserting
        if (empty($contrato['XDfree02_KeyId'])) {
            throw new Exception("Contract KeyId is empty");
        }
        
        // FIXED: Add IsDebt column to identify debt records
        $sqlDebito = "INSERT INTO tickets_xdfree02_extrafields 
                    (XDfree02_KeyId, TicketNumber, TotTime, IsDebt) 
                    VALUES (:contratoId, :ticketNumber, :tempoExcedido, 1)";
                    
        $stmtDebito = $pdo->prepare($sqlDebito);
        $stmtDebito->bindParam(':contratoId', $contrato['XDfree02_KeyId'], PDO::PARAM_STR);
        $stmtDebito->bindParam(':ticketNumber', $ticketNumber, PDO::PARAM_INT);
        $stmtDebito->bindParam(':tempoExcedido', $tempoExcedido, PDO::PARAM_INT);
        
        if (!$stmtDebito->execute()) {
            $errorInfo = $stmtDebito->errorInfo();
            throw new Exception("Failed to create debt record: " . $errorInfo[2]);
        }
        
        error_log("criarDebitoTempo - Debt record created successfully");
        
        
        // Commit the transaction if we started it here
        if ($internalTransaction && $pdo->inTransaction()) {
            error_log("criarDebitoTempo - Committing internal transaction");
            $pdo->commit();
        }
        
        error_log("criarDebitoTempo - Operation completed successfully");
        return true;
    } catch (Exception $e) {
        // Log the detailed error
        $errorMsg = "criarDebitoTempo - ERROR: " . $e->getMessage();
        error_log($errorMsg);
        error_log("criarDebitoTempo - Stack trace: " . $e->getTraceAsString());
        
        // Rollback the transaction if we started it here
        if (isset($internalTransaction) && $internalTransaction && $pdo->inTransaction()) {
            error_log("criarDebitoTempo - Rolling back internal transaction due to error");
            $pdo->rollBack();
        }
        
        // Rethrow the exception to be handled by the caller
        throw new Exception("Falha ao criar débito de tempo: " . $e->getMessage());
    }
}

/**
 * Recalcula o tempo utilizado (SpentHours) de um contrato com base nos tickets associados
 * @param string $contratoId ID do contrato
 * @param PDO $pdo Conexão PDO
 * @return bool Sucesso da operação
 */
function recalcularSpentHours($contratoId, $pdo) {
    try {
        error_log("recalcularSpentHours - Iniciando recalculo para contrato: {$contratoId}");
        
        // Detectar tickets duplicados (mesmo TicketNumber) que indicam contrato excedido
        $sqlDuplicados = "SELECT TicketNumber, COUNT(*) as total_entries 
                         FROM tickets_xdfree02_extrafields 
                         WHERE XDfree02_KeyId = :contratoId 
                         GROUP BY TicketNumber 
                         HAVING COUNT(*) > 1";
                         
        $stmtDuplicados = $pdo->prepare($sqlDuplicados);
        $stmtDuplicados->bindParam(':contratoId', $contratoId, PDO::PARAM_STR);
        $stmtDuplicados->execute();
        $ticketsDuplicados = $stmtDuplicados->fetchAll(PDO::FETCH_ASSOC);
        
        $temTicketsExcedidos = !empty($ticketsDuplicados);
        
        if ($temTicketsExcedidos) {
            error_log("recalcularSpentHours - Detectados tickets duplicados para o contrato: " . count($ticketsDuplicados) . " tickets");
        }
        
        // Calcular a soma total do tempo usado, incluindo débitos
        $sql = "SELECT SUM(TotTime) as TotalSpent 
                FROM tickets_xdfree02_extrafields 
                WHERE XDfree02_KeyId = :contratoId";
                
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':contratoId', $contratoId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalSpent = $result['TotalSpent'] ?? 0;
        error_log("recalcularSpentHours - Tempo total calculado: {$totalSpent} minutos");
        
        // Buscar a informação atual do contrato para determinar o status
        $sqlContrato = "SELECT TotalHours, Status FROM info_xdfree02_extrafields WHERE XDfree02_KeyId = :contratoId";
        $stmtContrato = $pdo->prepare($sqlContrato);
        $stmtContrato->bindParam(':contratoId', $contratoId, PDO::PARAM_STR);
        $stmtContrato->execute();
        $contrato = $stmtContrato->fetch(PDO::FETCH_ASSOC);
        
        if ($contrato) {
            $totalHours = intval($contrato['TotalHours']);
            $status = $contrato['Status'];
            
            // Atualizar o campo SpentHours do contrato
            $sqlUpdate = "UPDATE info_xdfree02_extrafields 
                         SET SpentHours = :totalSpent";
            
            // FIX: Only mark as exceeded if total spent is actually greater than total hours
            // AND the contract is currently in use
            $excedido = ($status === 'Em Utilização' && $totalSpent > $totalHours);
            
            // Check for tickets with IsDebt=1
            $hasDebtTickets = false;
            if (!$excedido) {
                // Check if there are any debt tickets associated with this contract
                $sqlDebtCheck = "SELECT COUNT(*) as count 
                                FROM tickets_xdfree02_extrafields 
                                WHERE XDfree02_KeyId = :contratoId 
                                AND IsDebt = 1";
                $stmtDebtCheck = $pdo->prepare($sqlDebtCheck);
                $stmtDebtCheck->bindParam(':contratoId', $contratoId, PDO::PARAM_STR);
                $stmtDebtCheck->execute();
                $debtResult = $stmtDebtCheck->fetch(PDO::FETCH_ASSOC);
                
                $hasDebtTickets = ($debtResult['count'] > 0);
                $excedido = $hasDebtTickets;
                error_log("recalcularSpentHours - Contrato tem {$debtResult['count']} tickets com IsDebt=1");
            }
            
            // IMPROVED REGULARIZADO LOGIC: Check if this contract was previously exceeded
            // and should now be regularized (has no debt, TotalSpent <= TotalHours)
            if ($status === 'Excedido') {
                error_log("recalcularSpentHours - Checking if contract should be regularized: excedido={$excedido}, hasDebtTickets={$hasDebtTickets}, totalSpent={$totalSpent}, totalHours={$totalHours}");
                
                // If all debt tickets are processed AND spent time is not exceeding total time
                if (!$hasDebtTickets && $totalSpent <= $totalHours) {
                    $sqlUpdate .= ", Status = 'Regularizado'";
                    error_log("recalcularSpentHours - Contract will be regularized: Status changed from Excedido to Regularizado");
                }
            }
            // Regular exceeded status handling
            else if ($excedido && $status !== 'Excedido' && $status !== 'Concluído' && $status !== 'Regularizado') {
                $sqlUpdate .= ", Status = 'Excedido'";
                error_log("recalcularSpentHours - Contrato excedido: " . 
                          ($totalSpent > $totalHours ? "SpentHours ({$totalSpent}) > TotalHours ({$totalHours})" : 
                          "Tickets com IsDebt detectados"));
            } else if (!$excedido && $status === 'Excedido') {
                // FIX: If contract is currently marked as exceeded but should not be, update it
                $sqlUpdate .= ", Status = 'Em Utilização'";
                error_log("recalcularSpentHours - Contrato não está excedido, atualizando status para Em Utilização");
            }
            
            $sqlUpdate .= " WHERE XDfree02_KeyId = :contratoId";
            
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':totalSpent', $totalSpent, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':contratoId', $contratoId, PDO::PARAM_STR);
            $stmtUpdate->execute();
            
            error_log("recalcularSpentHours - SpentHours atualizado para: {$totalSpent} minutos");
            
            // Update IsDebt flag for tickets if needed
            if ($temTicketsExcedidos) {
                foreach ($ticketsDuplicados as $ticket) {
                    $ticketNumber = $ticket['TicketNumber'];
                    
                    // Only mark as debt if SpentHours actually exceeds TotalHours
                    if ($totalSpent > $totalHours) {
                        $sqlUpdateTickets = "UPDATE tickets_xdfree02_extrafields 
                                           SET IsDebt = 1 
                                           WHERE XDfree02_KeyId = :contratoId 
                                           AND TicketNumber = :ticketNumber
                                           AND IsDebt = 0";
                                            
                        $stmtUpdateTickets = $pdo->prepare($sqlUpdateTickets);
                        $stmtUpdateTickets->bindParam(':contratoId', $contratoId, PDO::PARAM_STR);
                        $stmtUpdateTickets->bindParam(':ticketNumber', $ticketNumber, PDO::PARAM_INT);
                        $stmtUpdateTickets->execute();
                        
                        error_log("recalcularSpentHours - Ticket duplicado marcado como débito: {$ticketNumber}");
                    }
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao recalcular tempo utilizado: " . $e->getMessage());
        return false;
    }
}

/**
 * Função para verificar e processar débitos automaticamente quando novos contratos são adicionados
 * @param string $entity ID da entidade/cliente
 * @param PDO $pdo Conexão PDO
 * @return bool Sucesso da operação
 */
function processarDebitosAutomaticos($entity, $pdo) {
    try {
        // Log start of debt processing
        error_log("processarDebitosAutomaticos - Starting processing for entity: {$entity}");
        
        // Iniciar transação para garantir atomicidade
        $pdo->beginTransaction();
        
        // 1. Verificar se existem débitos para este cliente (contratos excedidos)
        $sqlContratoExcedido = "SELECT Id, XDfree02_KeyId 
                               FROM info_xdfree02_extrafields 
                               WHERE Entity = :entity 
                               AND Status = 'Excedido'
                               LIMIT 1";
                               
        $stmtContratoExcedido = $pdo->prepare($sqlContratoExcedido);
        $stmtContratoExcedido->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmtContratoExcedido->execute();
        
        if (!$stmtContratoExcedido->fetch()) {
            // Nenhum contrato excedido, não há nada a fazer
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            error_log("processarDebitosAutomaticos - No exceeded contracts found");
            return true;
        }
        
        // 2. Verificar se existem novos contratos "Em Utilização" ou "Por Começar" para compensar o débito
        $sqlNovoContrato = "SELECT Id, XDfree02_KeyId, TotalHours, Status
                           FROM info_xdfree02_extrafields 
                           WHERE Entity = :entity 
                           AND (Status = 'Por Começar' OR Status = 'Em Utilização')
                           AND SpentHours < TotalHours
                           ORDER BY 
                               CASE WHEN Status = 'Em Utilização' THEN 1 ELSE 2 END,
                               StartDate DESC, Id DESC
                           LIMIT 1";
                           
        $stmtNovoContrato = $pdo->prepare($sqlNovoContrato);
        $stmtNovoContrato->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmtNovoContrato->execute();
        $novoContrato = $stmtNovoContrato->fetch(PDO::FETCH_ASSOC);
        
        if (!$novoContrato) {
            // Nenhum novo contrato disponível, não pode compensar o débito ainda
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            error_log("processarDebitosAutomaticos - No available contracts found to compensate debt");
            return true;
        }
        
        error_log("processarDebitosAutomaticos - Found available contract: " . $novoContrato['XDfree02_KeyId']);
        
        // 3. Buscar todos os registros de débito
        $sqlDebitos = "SELECT 
                          t.Id, 
                          t.XDfree02_KeyId as ContratoOrigemId, 
                          t.TicketNumber, 
                          t.TotTime, 
                          c.Status,
                          t.IsDebt as IsDebitRecord
                      FROM tickets_xdfree02_extrafields t
                      JOIN info_xdfree02_extrafields c ON t.XDfree02_KeyId = c.XDfree02_KeyId
                      WHERE c.Entity = :entity 
                      AND c.Status = 'Excedido'
                      AND t.IsDebt = 1
                      AND (t.IsProcessed IS NULL OR t.IsProcessed = 0 OR t.IsProcessed = false)
                      ORDER BY t.Id ASC";
        
        error_log("processarDebitosAutomaticos - SQL to find debt records: " . $sqlDebitos);
        $stmtDebitos = $pdo->prepare($sqlDebitos);
        $stmtDebitos->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmtDebitos->execute();
        $debitos = $stmtDebitos->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("processarDebitosAutomaticos - Found " . count($debitos) . " debt records to process");
        
        if (empty($debitos)) {
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            error_log("processarDebitosAutomaticos - No debt records found");
            return true;
        }
        
        // 4. Iniciar processamento dos débitos
        $totalProcessado = 0;
        $debitosProcessados = [];
        $tempoRestanteNovoContrato = $novoContrato['TotalHours'];
        
        foreach ($debitos as $debito) {
            error_log("processarDebitosAutomaticos - Processing debt ID: " . $debito['Id'] . " with time: " . $debito['TotTime']);
            
            // Se não houver mais tempo no novo contrato, parar processamento
            if ($tempoRestanteNovoContrato <= 0) {
                error_log("processarDebitosAutomaticos - No more time available in contract");
                break;
            }
            
            $tempoDebitoRestante = $debito['TotTime'];
            $tempoADescontar = min($tempoDebitoRestante, $tempoRestanteNovoContrato);
            
            // Criar associação do ticket com o novo contrato (descontando o débito)
            // ENSURE DebtOriginId is properly set to the original debt record ID
            $sqlAssociacao = "INSERT INTO tickets_xdfree02_extrafields 
                            (XDfree02_KeyId, TicketNumber, TotTime, IsDebt, DebtOriginId) 
                            VALUES (:contratoId, :ticketNumber, :tempoGasto, 0, :debitoOrigemId)";
            
            $stmtAssociacao = $pdo->prepare($sqlAssociacao);
            $stmtAssociacao->bindParam(':contratoId', $novoContrato['XDfree02_KeyId'], PDO::PARAM_STR);
            $stmtAssociacao->bindParam(':ticketNumber', $debito['TicketNumber'], PDO::PARAM_INT);
            $stmtAssociacao->bindParam(':tempoGasto', $tempoADescontar, PDO::PARAM_INT);
            $stmtAssociacao->bindParam(':debitoOrigemId', $debito['Id'], PDO::PARAM_INT);
            $stmtAssociacao->execute();
            
            error_log("processarDebitosAutomaticos - Created association record: Contract=" . $novoContrato['XDfree02_KeyId'] . 
                      ", Ticket=" . $debito['TicketNumber'] . ", Time=" . $tempoADescontar);
            
            // Atualizar ou remover o débito original
            if ($tempoADescontar >= $tempoDebitoRestante) {
                // Débito totalmente compensado, marcar como processado
                $sqlAtualizarDebito = "UPDATE tickets_xdfree02_extrafields 
                                      SET IsProcessed = 1 
                                      WHERE Id = :id";
                
                $stmtAtualizarDebito = $pdo->prepare($sqlAtualizarDebito);
                $stmtAtualizarDebito->bindParam(':id', $debito['Id'], PDO::PARAM_INT);
                $stmtAtualizarDebito->execute();
                error_log("processarDebitosAutomaticos - Marked debt as fully processed: " . $debito['Id']);
            } else {
                // Débito parcialmente compensado, atualizar valor restante
                $tempoRestante = $tempoDebitoRestante - $tempoADescontar;
                
                $sqlAtualizarDebito = "UPDATE tickets_xdfree02_extrafields 
                                      SET TotTime = :tempoRestante, 
                                          PartiallyProcessed = 1 
                                      WHERE Id = :id";
                
                $stmtAtualizarDebito = $pdo->prepare($sqlAtualizarDebito);
                $stmtAtualizarDebito->bindParam(':tempoRestante', $tempoRestante, PDO::PARAM_INT);
                $stmtAtualizarDebito->bindParam(':id', $debito['Id'], PDO::PARAM_INT);
                $stmtAtualizarDebito->execute();
                error_log("processarDebitosAutomaticos - Updated debt record (partial processing): " . $debito['Id'] . 
                          " - Remaining time: " . $tempoRestante);
            }
            
            // Atualizar tempo restante do novo contrato
            $tempoRestanteNovoContrato -= $tempoADescontar;
            $totalProcessado += $tempoADescontar;
            
            // Registrar para log
            $debitosProcessados[] = [
                'ticketId' => $debito['TicketNumber'],
                'tempoProcessado' => $tempoADescontar,
                'debito_id' => $debito['Id']
            ];
        }
        
        // 5. Update the status of contracts with processed debts
        if ($totalProcessado > 0) {
            // If contract was 'Por Começar', update status to 'Em Utilização'
            if ($novoContrato['Status'] !== 'Em Utilização') {
                $sqlAtivarContrato = "UPDATE info_xdfree02_extrafields 
                             SET Status = 'Em Utilização', 
                             StartDate = NOW() 
                             WHERE Id = :id";
                $stmtAtivarContrato = $pdo->prepare($sqlAtivarContrato);
                $stmtAtivarContrato->bindParam(':id', $novoContrato['Id'], PDO::PARAM_INT);
                $stmtAtivarContrato->execute();
                error_log("processarDebitosAutomaticos - Updated contract status to 'Em Utilização': " . $novoContrato['Id']);
            }
            
            // Recalcular tempo usado no novo contrato
            recalcularSpentHours($novoContrato['XDfree02_KeyId'], $pdo);
            error_log("processarDebitosAutomaticos - Recalculated spent hours for contract: " . $novoContrato['XDfree02_KeyId']);
            
            // Get list of contracts that had debts processed
            $debtContractIds = [];
            foreach ($debitos as $debito) {
                if (!in_array($debito['ContratoOrigemId'], $debtContractIds)) {
                    $debtContractIds[] = $debito['ContratoOrigemId'];
                }
            }
            
            // Mark source contracts as 'Concluído' if they were used for debt
            if (!empty($debtContractIds)) {
                $placeholders = implode(',', array_fill(0, count($debtContractIds), '?'));
                $sqlMarcarContratosProcessados = "UPDATE info_xdfree02_extrafields 
                                                 SET Status = 'Concluído' 
                                                 WHERE XDfree02_KeyId IN ($placeholders)
                                                 AND Status = 'Excedido'";
                                                 
                $stmtMarcarContratosProcessados = $pdo->prepare($sqlMarcarContratosProcessados);
                foreach ($debtContractIds as $index => $contractId) {
                    $stmtMarcarContratosProcessados->bindValue($index + 1, $contractId);
                }
                $stmtMarcarContratosProcessados->execute();
                error_log("processarDebitosAutomaticos - Marked source contracts as 'Concluído': " . implode(', ', $debtContractIds));
            }
            
            // Check for remaining unprocessed debts for this entity
            $sqlDebitosNaoProcessados = "SELECT COUNT(*) as count
                                        FROM tickets_xdfree02_extrafields t
                                        JOIN info_xdfree02_extrafields c ON t.XDfree02_KeyId = c.XDfree02_KeyId
                                        WHERE c.Entity = :entity 
                                        AND (t.IsDebt = 1 OR t.IsDebt = true)
                                        AND (t.IsProcessed IS NULL OR t.IsProcessed = 0 OR t.IsProcessed = false)";
            
            $stmtDebitosNaoProcessados = $pdo->prepare($sqlDebitosNaoProcessados);
            $stmtDebitosNaoProcessados->bindParam(':entity', $entity, PDO::PARAM_STR);
            $stmtDebitosNaoProcessados->execute();
            $result = $stmtDebitosNaoProcessados->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                // Todos os débitos foram processados, normalizar quaisquer contratos excedidos restantes
                $sqlNormalizarContratos = "UPDATE info_xdfree02_extrafields 
                                          SET Status = 'Concluído' 
                                          WHERE Entity = :entity 
                                          AND Status = 'Excedido'";
                
                $stmtNormalizarContratos = $pdo->prepare($sqlNormalizarContratos);
                $stmtNormalizarContratos->bindParam(':entity', $entity, PDO::PARAM_STR);
                $stmtNormalizarContratos->execute();
                error_log("processarDebitosAutomaticos - All debts processed, normalized any remaining exceeded contracts to 'Concluído'");
            }
        }
        
        // 6. Confirmar transação
        if ($pdo->inTransaction()) {
            $pdo->commit();
            error_log("processarDebitosAutomaticos - Committed transaction successfully");
        }
        
        // 7. Check for contracts that should be regularized
        verificarEAtualizarContratos($entity, $pdo);
        
        // 8. Log das operações
        if (!empty($debitosProcessados)) {
            $logMsg = "Débitos processados para cliente {$entity}:";
            foreach ($debitosProcessados as $dp) {
                $logMsg .= " Ticket #{$dp['ticketId']}: {$dp['tempoProcessado']} minutos;";
            }
            error_log($logMsg);
        }
        
        return true;
    } catch (Exception $e) {
        // ...existing code...
    }
}

/**
 * Verifica e atualiza contratos para status "Regularizado" quando apropriado
 * @param string $entity ID da entidade/cliente
 * @param PDO $pdo Conexão PDO
 * @return bool Sucesso da operação
 */
function verificarEAtualizarContratos($entity, $pdo) {
    try {
        error_log("verificarEAtualizarContratos - Starting for entity: {$entity}");
        
        // Get all contracts in "Excedido" status
        $sql = "SELECT XDfree02_KeyId 
                FROM info_xdfree02_extrafields 
                WHERE Entity = :entity 
                AND Status = 'Excedido'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmt->execute();
        $excededContracts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($excededContracts)) {
            error_log("verificarEAtualizarContratos - No exceeded contracts found for entity: {$entity}");
            return true;
        }
        
        error_log("verificarEAtualizarContratos - Found " . count($excededContracts) . " exceeded contracts");
        
        // Recalculate each exceeded contract to potentially update status
        foreach ($excededContracts as $contratoId) {
            error_log("verificarEAtualizarContratos - Recalculating contract: {$contratoId}");
            recalcularSpentHours($contratoId, $pdo);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("verificarEAtualizarContratos - Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualiza o status dos contratos com base nas regras de negócio
 * @param string $entity ID da entidade/cliente
 * @param PDO $pdo Conexão PDO
 * @return bool Sucesso da operação
 */
function atualizarStatusContratos($entity, $pdo) {
    try {
        // Verificar e marcar contratos excedidos
        $sqlVerificarExcedidos = "UPDATE info_xdfree02_extrafields 
                                 SET Status = 'Excedido' 
                                 WHERE Entity = :entity 
                                 AND Status = 'Em Utilização' 
                                 AND SpentHours > TotalHours";
                                 
        $stmtVerificarExcedidos = $pdo->prepare($sqlVerificarExcedidos);
        $stmtVerificarExcedidos->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmtVerificarExcedidos->execute();
        
        // Verificar se há outros contratos em utilização
        $sqlVerificarEmUtilizacao = "SELECT COUNT(*) as count 
                                    FROM info_xdfree02_extrafields 
                                    WHERE Entity = :entity 
                                    AND Status = 'Em Utilização' 
                                    AND SpentHours < TotalHours";
                                    
        $stmtVerificarEmUtilizacao = $pdo->prepare($sqlVerificarEmUtilizacao);
        $stmtVerificarEmUtilizacao->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmtVerificarEmUtilizacao->execute();
        $temContratosValidos = ($stmtVerificarEmUtilizacao->fetch(PDO::FETCH_ASSOC)['count'] > 0);
        
        // Se não houver contratos válidos em utilização, ativar o próximo "Por Começar" se existir
        if (!$temContratosValidos) {
            $sqlProximoContrato = "SELECT Id 
                                  FROM info_xdfree02_extrafields 
                                  WHERE Entity = :entity 
                                  AND Status = 'Por Começar' 
                                  ORDER BY StartDate DESC, Id DESC
                                  LIMIT 1";
                                  
            $stmtProximoContrato = $pdo->prepare($sqlProximoContrato);
            $stmtProximoContrato->bindParam(':entity', $entity, PDO::PARAM_STR);
            $stmtProximoContrato->execute();
            $proximoContrato = $stmtProximoContrato->fetch(PDO::FETCH_ASSOC);
            
            if ($proximoContrato) {
                $sqlAtivarProximo = "UPDATE info_xdfree02_extrafields 
                                   SET Status = 'Em Utilização', 
                                       StartDate = NOW() 
                                   WHERE Id = :id";
                                   
                $stmtAtivarProximo = $pdo->prepare($sqlAtivarProximo);
                $stmtAtivarProximo->bindParam(':id', $proximoContrato['Id'], PDO::PARAM_INT);
                $stmtAtivarProximo->execute();
                
                // Log da operação
                $logMsg = "Ativado contrato {$proximoContrato['Id']} para cliente {$entity} automaticamente";
                error_log($logMsg);
                
                return true;
            }
        }
        
        // Processamento concluído com sucesso
        return true;
    } catch (Exception $e) {
        error_log("Erro ao atualizar status dos contratos: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se um cliente tem contratos excedidos que impedem criação de tickets
 * @param string $entity ID da entidade/cliente
 * @param PDO $pdo Conexão PDO
 * @return bool True se o cliente pode criar tickets, false caso contrário
 */
function clientePodeCriarTickets($entity, $pdo) {
    try {
        // Verificar se há contratos em status excedido
        $sql = "SELECT COUNT(*) as count 
               FROM info_xdfree02_extrafields 
               WHERE Entity = :entity 
               AND Status = 'Excedido'";
               
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $temContratosExcedidos = ($result['count'] > 0);
        
        // Verificar se há contratos ativos não excedidos
        $sqlAtivos = "SELECT COUNT(*) as count 
                     FROM info_xdfree02_extrafields 
                     WHERE Entity = :entity 
                     AND Status IN ('Em Utilização', 'Por Começar') 
                     AND (Status != 'Em Utilização' OR SpentHours <= TotalHours)";
                     
        $stmtAtivos = $pdo->prepare($sqlAtivos);
        $stmtAtivos->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmtAtivos->execute();
        $resultAtivos = $stmtAtivos->fetch(PDO::FETCH_ASSOC);
        
        $temContratosAtivos = ($resultAtivos['count'] > 0);
        
        // Cliente pode criar tickets se não tem contratos excedidos ou se tem contratos ativos
        return !$temContratosExcedidos || $temContratosAtivos;
    } catch (Exception $e) {
        error_log("Erro ao verificar se cliente pode criar tickets: " . $e->getMessage());
        return true; // Em caso de erro, permitir criação para não bloquear clientes indevidamente
    }
}

/**
 * Obtém resumo dos contratos de um cliente para exibição
 * @param string $entity ID da entidade/cliente
 * @param PDO $pdo Conexão PDO
 * @return array Dados resumidos dos contratos
 */
function obterResumoContratos($entity, $pdo) {
    try {
        $sql = "SELECT XDfree02_KeyId, TotalHours, SpentHours, Status, StartDate
                FROM info_xdfree02_extrafields 
                WHERE Entity = :entity 
                ORDER BY 
                    CASE 
                        WHEN Status = 'Em Utilização' AND SpentHours < TotalHours THEN 1
                        WHEN Status = 'Por Começar' THEN 2
                        ELSE 3
                    END,
                    TotalHours DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entity', $entity, PDO::PARAM_STR);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $resumo = [
            'contratos' => [],
            'tempoRestante' => 0
        ];
        
        foreach ($contratos as $contrato) {
            $totalMinutos = intval($contrato['TotalHours']);
            $gastoMinutos = intval($contrato['SpentHours'] ?? 0);
            $restanteMinutos = max(0, $totalMinutos - $gastoMinutos);
            
            $resumo['contratos'][] = [
                'id' => $contrato['XDfree02_KeyId'],
                'totalHoras' => $totalMinutos,
                'gastasHoras' => $gastoMinutos,
                'restanteMinutos' => $restanteMinutos,
                'status' => $contrato['Status'],
                'excedido' => ($gastoMinutos > $totalMinutos)
            ];
            
            if (($contrato['Status'] === 'Em Utilização' || $contrato['Status'] === 'Por Começar') && !($gastoMinutos > $totalMinutos)) {
                $resumo['tempoRestante'] += $restanteMinutos;
            }
        }
        
        return $resumo;
    } catch (Exception $e) {
        error_log("Erro ao obter resumo de contratos: " . $e->getMessage());
        return ['contratos' => [], 'tempoRestante' => 0];
    }
}

/**
 * Função que corrige os valores de SpentHours para todos os contratos
 * @param PDO $pdo Conexão PDO
 * @param string $entity ID da entidade/cliente (opcional - se não fornecido, corrige todos os contratos)
 * @return array Resultado da operação com detalhes dos contratos atualizados
 */
function corrigirSpentHours($pdo, $entity = null) {
    $resultado = [
        'success' => false,
        'contratos_corrigidos' => 0,
        'detalhes' => []
    ];
    
    try {
        // Inicia transação para garantir atomicidade da operação
        $pdo->beginTransaction();
        
        // Busca todos os contratos (ou apenas de uma entidade específica)
        if ($entity) {
            $sql = "SELECT XDfree02_KeyId, TotalHours, SpentHours, Status FROM info_xdfree02_extrafields WHERE Entity = :entity";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':entity', $entity, PDO::PARAM_STR);
        } else {
            $sql = "SELECT XDfree02_KeyId, TotalHours, SpentHours, Status FROM info_xdfree02_extrafields";
            $stmt = $pdo->prepare($sql);
        }
        
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($contratos as $contrato) {
            $contratoId = $contrato['XDfree02_KeyId'];
            $valorAntigo = $contrato['SpentHours'];
            
            // Calcular a soma total do tempo usado
            $sqlTempoGasto = "SELECT SUM(TotTime) as TotalSpent 
                         FROM tickets_xdfree02_extrafields 
                         WHERE XDfree02_KeyId = :contratoId";
                         
            $stmtTempoGasto = $pdo->prepare($sqlTempoGasto);
            $stmtTempoGasto->bindParam(':contratoId', $contratoId, PDO::PARAM_STR);
            $stmtTempoGasto->execute();
            $result = $stmtTempoGasto->fetch(PDO::FETCH_ASSOC);
            
            $totalSpent = $result['TotalSpent'] ?? 0;
            
            // Se o valor mudou, atualizar SpentHours
            if ($totalSpent != $valorAntigo) {
                $sqlUpdate = "UPDATE info_xdfree02_extrafields SET SpentHours = :totalSpent WHERE XDfree02_KeyId = :contratoId";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':totalSpent', $totalSpent, PDO::PARAM_INT);
                $stmtUpdate->bindParam(':contratoId', $contratoId, PDO::PARAM_STR);
                $stmtUpdate->execute();
                
                // Atualizar status com base no tempo gasto
                $totalHours = $contrato['TotalHours'];
                
                if ($totalSpent > $totalHours && $contrato['Status'] === 'Em Utilização') {
                    // Marcar como excedido se o tempo gasto excede o total
                    $sqlUpdateStatus = "UPDATE info_xdfree02_extrafields SET Status = 'Excedido' WHERE XDfree02_KeyId = :contratoId";
                    $stmtUpdateStatus = $pdo->prepare($sqlUpdateStatus);
                    $stmtUpdateStatus->bindParam(':contratoId', $contratoId, PDO::PARAM_STR);
                    $stmtUpdateStatus->execute();
                }
                
                $resultado['detalhes'][] = [
                    'contratoId' => $contratoId,
                    'valorAntigo' => $valorAntigo,
                    'valorNovo' => $totalSpent,
                    'diferenca' => $totalSpent - $valorAntigo
                ];
                
                $resultado['contratos_corrigidos']++;
            }
        }
        
        // Confirma as alterações
        $pdo->commit();
        
        $resultado['success'] = true;
        $resultado['mensagem'] = $resultado['contratos_corrigidos'] . ' contratos foram atualizados com sucesso.';
    } catch (Exception $e) {
        // Reverte as alterações em caso de erro
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $resultado['success'] = false;
        $resultado['mensagem'] = 'Erro ao corrigir SpentHours: ' . $e->getMessage();
        $resultado['erro'] = $e->getMessage();
    }
    
    return $resultado;
}

// Standardize contract referencing in the entire codebase
function standardizeContractReferences($pdo) {
    try {
        // Log operation start
        error_log("standardizeContractReferences - Starting database contract reference standardization");
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // 1. Check for any tickets_xdfree02_extrafields entries using numeric IDs instead of XDfree02_KeyId
        $sqlCheckTickets = "SELECT t.*, c.XDfree02_KeyId 
                           FROM tickets_xdfree02_extrafields t
                           JOIN info_xdfree02_extrafields c ON t.XDfree02_KeyId = c.Id
                           WHERE CAST(t.XDfree02_KeyId AS UNSIGNED) = t.XDfree02_KeyId";
        
        $stmtCheck = $pdo->prepare($sqlCheckTickets);
        $stmtCheck->execute();
        $badReferences = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. Fix any incorrect references found
        $fixCount = 0;
        foreach ($badReferences as $ref) {
            $sqlFix = "UPDATE tickets_xdfree02_extrafields 
                      SET XDfree02_KeyId = :correct_id 
                      WHERE Id = :record_id";
            
            $stmtFix = $pdo->prepare($sqlFix);
            $stmtFix->bindParam(':correct_id', $ref['XDfree02_KeyId'], PDO::PARAM_STR);
            $stmtFix->bindParam(':record_id', $ref['Id'], PDO::PARAM_INT);
            $stmtFix->execute();
            $fixCount++;
        }
        
        // 3. Add a log entry summarizing what was fixed
        if ($fixCount > 0) {
            error_log("standardizeContractReferences - Fixed $fixCount incorrect contract references");
        } else {
            error_log("standardizeContractReferences - No incorrect references found");
        }
        
        // 4. Commit transaction
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Database references checked. Fixed $fixCount incorrect references.",
            'fixed_count' => $fixCount
        ];
        
    } catch (Exception $e) {
        // Roll back transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("standardizeContractReferences - ERROR: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "Error checking database references: " . $e->getMessage()
        ];
    }
}
?>