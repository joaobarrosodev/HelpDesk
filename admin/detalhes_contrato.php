<?php
session_start();
include('conflogin.php');
include('db.php');
include('../verificar_tempo_disponivel.php'); // Incluir o sistema de verificação

// Get contract ID - keep as string
$contract_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($contract_id)) {
    header('Location: consultar_contratos.php?error=no_id');
    exit;
}

try {
    // Get contract details with only the specific entity fields you want
    $sql = "SELECT 
                x2Extra.*,
                e.name as CompanyName,
                e.ContactEmail as EntityEmail,
                e.MobilePhone1 as EntityMobilePhone,
                e.Phone1 as EntityPhone
            FROM info_xdfree02_extrafields x2Extra
            LEFT JOIN entities e ON e.KeyId = x2Extra.Entity
            WHERE x2Extra.XDfree02_KeyId = :contract_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':contract_id', $contract_id, PDO::PARAM_STR);
    $stmt->execute();
    
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contrato) {
        // Try with numeric ID as fallback
        $sql_numeric = "SELECT 
                    x2Extra.*,
                    e.name as CompanyName,
                    e.ContactEmail as EntityEmail,
                    e.MobilePhone1 as EntityMobilePhone,
                    e.Phone1 as EntityPhone
                FROM info_xdfree02_extrafields x2Extra
                LEFT JOIN entities e ON e.KeyId = x2Extra.Entity
                WHERE x2Extra.id = :contract_id";
        
        $stmt_numeric = $pdo->prepare($sql_numeric);
        $stmt_numeric->bindValue(':contract_id', (int)$contract_id, PDO::PARAM_INT);
        $stmt_numeric->execute();
        
        $contrato = $stmt_numeric->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$contrato) {
        header('Location: consultar_contratos.php?error=contract_not_found');
        exit;
    }

    // Atualizar status do contrato antes de exibir
    $entity = $contrato['Entity'];
    atualizarStatusContratos($entity, $pdo);
    
    // Recarregar dados do contrato após atualização de status
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':contract_id', $contract_id, PDO::PARAM_STR);
    $stmt->execute();
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get tickets used in this contract - IMPROVED query to correctly fetch debt status fields
    $sql_tickets = "SELECT 
                t.Id as TicketRecordId,
                t.TicketNumber, 
                t.TotTime,
                t.IsDebt,
                t.IsProcessed,
                t.PartiallyProcessed,
                t.DebtOriginId,
                free.KeyId as TicketKeyId,
                free.Name as TicketName,
                info.Description as TicketDescription,
                info.Status as TicketStatus,
                info.CreationDate,
                info.CreationUser,
                (SELECT td.XDfree02_KeyId FROM tickets_xdfree02_extrafields td WHERE td.Id = t.DebtOriginId LIMIT 1) as OriginContractId,
                (SELECT COUNT(*) FROM tickets_xdfree02_extrafields tc WHERE tc.DebtOriginId = t.Id) as CompensationCount,
                (SELECT GROUP_CONCAT(tc.XDfree02_KeyId) FROM tickets_xdfree02_extrafields tc WHERE tc.DebtOriginId = t.Id) as CompensatedInContractIds
            FROM tickets_xdfree02_extrafields t
            LEFT JOIN xdfree01 free ON t.TicketNumber = free.id
            LEFT JOIN info_xdfree01_extrafields info ON free.KeyId = info.XDFree01_KeyID
            WHERE t.XDfree02_KeyId = :contract_id
            ORDER BY t.TicketNumber, t.IsDebt DESC";
    
    $stmt_tickets = $pdo->prepare($sql_tickets);
    $stmt_tickets->bindValue(':contract_id', $contract_id, PDO::PARAM_STR);
    $stmt_tickets->execute();
    $tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);

    // Get all contracts for this entity (for context)
    $entity = $contrato['Entity'];
    $resumoContratos = obterResumoContratos($entity, $pdo);
    
    // Attempt to process any pending debts
    if ($contrato['Status'] === 'Em Utilização' || $contrato['Status'] === 'Por Começar') {
        error_log("detalhes_contrato.php - Processing debts for entity {$entity}, contract {$contract_id}");
        if (processarDebitosAutomaticos($entity, $pdo)) {
            // If debts were processed, reload contract data to reflect changes
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':contract_id', $contract_id, PDO::PARAM_STR);
            $stmt->execute();
            $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Reload tickets and resume
            $stmt_tickets->execute();
            $tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);
            $resumoContratos = obterResumoContratos($entity, $pdo);
            error_log("detalhes_contrato.php - Contract data reloaded after debt processing");
        }
    }
    
    // Certifique-se de que o SpentHours está atualizado para este contrato
    recalcularSpentHours($contract_id, $pdo);
    
    // Recarregar dados do contrato após recálculo
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':contract_id', $contract_id, PDO::PARAM_STR);
    $stmt->execute();
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar contrato: " . $e->getMessage();
    $tickets = [];
    $contrato = null;
    $resumoContratos = ['contratos' => [], 'tempoRestante' => 0];
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <div class="container-fluid p-4">
            
            <!-- Cabeçalho -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2">Detalhes do Contrato #<?php echo htmlspecialchars($contract_id); ?></h1>
                    <p class="text-muted">Informações completas do contrato e tickets utilizados</p>
                </div>
                <div>
                    <a href="consultar_contratos.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Voltar
                    </a>
                </div>
            </div>
            
            <?php if(isset($erro_db)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($erro_db); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($contrato) && $contrato): ?>
            <?php 
            // Calculate remaining minutes early for use in ticket processing
            $total_minutes = (int)($contrato['TotalHours'] ?? 0); // Already in minutes from DB
            $used_minutes = (int)($contrato['SpentHours'] ?? 0);   // Already in minutes from DB
            $remaining_minutes = $total_minutes - $used_minutes;
            ?>
            <div class="row">
                <!-- Contract Information -->
                <div class="col-md-8">
                    <!-- Company Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-building me-2"></i>Informações da Empresa</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <strong>Nome da Empresa:</strong><br>
                                    <span class="fs-5 fw-bold text-primary"><?php echo htmlspecialchars($contrato['CompanyName'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Email:</strong><br>
                                    <?php if (!empty($contrato['EntityEmail'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($contrato['EntityEmail']); ?>" class="text-decoration-none">
                                        <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($contrato['EntityEmail']); ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Telefone:</strong><br>
                                    <?php if (!empty($contrato['EntityPhone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($contrato['EntityPhone']); ?>" class="text-decoration-none">
                                        <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($contrato['EntityPhone']); ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Telemóvel:</strong><br>
                                    <?php if (!empty($contrato['EntityMobilePhone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($contrato['EntityMobilePhone']); ?>" class="text-decoration-none">
                                        <i class="bi bi-phone me-1"></i><?php echo htmlspecialchars($contrato['EntityMobilePhone']); ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contract Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Detalhes do Contrato</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Status:</strong><br>
                                    <?php 
                                    $status = $contrato['Status'] ?? '';
                                    if (!empty($status)) {
                                        $status_class = '';
                                        switch(strtolower($status)) {
                                            case 'em utilização': $status_class = 'bg-success'; break;
                                            case 'por começar': $status_class = 'bg-warning'; break;
                                            case 'concluído': $status_class = 'bg-info'; break;
                                            case 'excedido': $status_class = 'bg-danger'; break;
                                            case 'regularizado': $status_class = 'bg-primary'; break; // New status with primary color
                                            default: $status_class = 'bg-secondary';
                                        }
                                        echo "<span class='badge $status_class fs-6'>" . htmlspecialchars($status) . "</span>";
                                    } else {
                                        echo '<span class="text-muted">N/A</span>';
                                    }
                                    ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Data de Início:</strong><br>
                                    <?php 
                                    $start_date = $contrato['StartDate'] ?? '';
                                    echo !empty($start_date) ? '<i class="bi bi-calendar-event me-1"></i>' . date('d/m/Y', strtotime($start_date)) : '<span class="text-muted">N/A</span>';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tickets Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Tickets Utilizados (<?php echo count($tickets); ?>)</h5>
                            <small class="text-muted">
                                <?php
                                $tempoTotalUtilizado = array_sum(array_column($tickets, 'TotTime'));
                                $horasUtilizadas = floor($tempoTotalUtilizado / 60);
                                $minutosUtilizados = $tempoTotalUtilizado % 60;
                                echo "Total: {$horasUtilizadas}h {$minutosUtilizados}min";
                                ?>
                            </small>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($tickets)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Ticket</th>
                                            <th>Descrição</th>
                                            <th>Tempo Usado</th>
                                            <th>Status</th>
                                            <th>Criado em</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Track tickets we've already displayed
                                        $displayedTickets = [];
                                        
                                        foreach ($tickets as $ticket): 
                                            // Get unique ticket identifier - include IsDebt flag to distinguish between regular and debt records
                                            $ticketKey = $ticket['TicketNumber'] . '-' . ($ticket['IsDebt'] ? 'debt' : 'normal');
                                            
                                            // Skip if we've already displayed this specific record
                                            if (in_array($ticketKey, $displayedTickets)) {
                                                continue;
                                            }
                                            
                                            // Add to our tracking array
                                            $displayedTickets[] = $ticketKey;
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ticket['TicketName'] ?? 'N/A'); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($ticket['TicketKeyId'] ?? 'N/A'); ?></small>
                                                <small class="text-muted d-block">Record ID: <?php echo htmlspecialchars($ticket['TicketRecordId'] ?? 'N/A'); ?></small>
                                                
                                                <?php 
                                                // Debug helper - show raw debt values for admins
                                                if (isset($_GET['debug'])): 
                                                ?>
                                                <div class="mt-1 small text-muted bg-light p-1 rounded">
                                                    <code>
                                                    IsDebt: <?php echo $ticket['IsDebt'] ? 'Yes' : 'No'; ?> | 
                                                    IsProcessed: <?php echo $ticket['IsProcessed'] ? 'Yes' : 'No'; ?> | 
                                                    PartiallyProcessed: <?php echo $ticket['PartiallyProcessed'] ? 'Yes' : 'No'; ?> | 
                                                    CompensationCount: <?php echo $ticket['CompensationCount'] ?? '0'; ?>
                                                    </code>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($ticket['IsDebt']) && $ticket['IsDebt'] == 1): ?>
                                                <!-- This is a debt ticket - show where it was or will be compensated -->
                                                <div class="mt-2">
                                                    <?php
                                                    // Calculate exceeded time for this debt ticket
                                                    $ticketTime = (int)($ticket['TotTime'] ?? 0);
                                                    $exceededTime = 0;
                                                    
                                                    // For debt tickets, we need to estimate how much time exceeded the contract
                                                    // This is an approximation since we don't have the exact state when the ticket was created
                                                    if ($ticketTime > 0) {
                                                        // If we have remaining time in current contract, the whole ticket time exceeded
                                                        if ($remaining_minutes > 0) {
                                                            $exceededTime = $ticketTime;
                                                        } else {
                                                            // If contract is already exceeded, estimate based on current excess
                                                            // This is a rough estimate - ideally we'd need historical data
                                                            $exceededTime = min($ticketTime, abs($remaining_minutes));
                                                        }
                                                    }
                                                    
                                                    $exceededHours = floor($exceededTime / 60);
                                                    $exceededMins = $exceededTime % 60;
                                                    
                                                    $exceededText = '';
                                                    if ($exceededHours > 0) {
                                                        $exceededText = $exceededHours . 'h';
                                                        if ($exceededMins > 0) {
                                                            $exceededText .= ' ' . $exceededMins . 'min';
                                                        }
                                                    } else {
                                                        $exceededText = $exceededMins . 'min';
                                                    }
                                                    ?>
                                                    <span class="badge bg-danger">Ticket Excede Plafond em: <?php echo $exceededText; ?></span>
                                                  
                                                    <?php if (!empty($ticket['CompensationCount']) && $ticket['CompensationCount'] > 0): ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-info">
                                                            <i class="bi bi-arrow-right-circle me-1"></i>
                                                            Tempo debitado no contrato: <?php echo htmlspecialchars($ticket['CompensatedInContractIds'] ?? 'N/A'); ?>
                                                        </span>
                                                    </div>
                                                    <?php elseif (!empty($ticket['IsProcessed']) && $ticket['IsProcessed'] == 1): ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle me-1"></i>
                                                            Débito processado
                                                        </span>
                                                    </div>
                                                    <?php elseif (!empty($ticket['PartiallyProcessed']) && $ticket['PartiallyProcessed'] == 1): ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-clock me-1"></i>
                                                            Débito parcialmente processado
                                                        </span>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-clock me-1"></i>
                                                            Pendente de compensação
                                                        </span>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($ticket['DebtOriginId'])): ?>
                                                <!-- This is a compensation ticket - show the origin ticket record -->
                                                <div class="mt-2">
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-arrow-left-circle me-1"></i>
                                                        Compensa ticket ID: <?php echo htmlspecialchars($ticket['DebtOriginId']); ?>
                                                        <?php if (!empty($ticket['OriginContractId'])): ?>
                                                        do contrato: <?php echo htmlspecialchars($ticket['OriginContractId']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $description = $ticket['TicketDescription'] ?? '';
                                                if (!empty($description)) {
                                                    // Decode HTML entities and strip any remaining HTML tags
                                                    $cleanDescription = strip_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8'));
                                                    echo htmlspecialchars(substr($cleanDescription, 0, 100)) . (strlen($cleanDescription) > 100 ? '...' : '');
                                                } else {
                                                    echo '<span class="text-muted">N/A</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $tempo = intval($ticket['TotTime']);
                                                $horas = floor($tempo / 60);
                                                $minutos = $tempo % 60;
                                                if ($horas > 0) {
                                                    echo "<strong>{$horas}h {$minutos}min</strong>";
                                                } else {
                                                    echo "<strong>{$minutos}min</strong>";
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <!-- Existing status display -->
                                                <?php 
                                                $status = $ticket['TicketStatus'] ?? '';
                                                $badge_class = '';
                                                switch(strtolower($status)) {
                                                    case 'concluído': $badge_class = 'bg-success'; break;
                                                    case 'em resolução': $badge_class = 'bg-warning'; break;
                                                    case 'em análise': $badge_class = 'bg-info'; break;
                                                    default: $badge_class = 'bg-secondary';
                                                }
                                                echo !empty($status) ? "<span class='badge $badge_class'>" . htmlspecialchars($status) . "</span>" : '<span class="text-muted">N/A</span>';
                                                ?>
                                                
                                                <?php if ($ticket['IsDebt']): ?>
                                                <div class="mt-1">
                                                    <span class="badge bg-danger">Excedido</span>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $created = $ticket['CreationDate'] ?? '';
                                                echo !empty($created) ? date('d/m/Y H:i', strtotime($created)) : '<span class="text-muted">N/A</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($ticket['TicketNumber'])): ?>
                                                <a href="detalhes_ticket.php?keyid=<?php echo htmlspecialchars($ticket['TicketNumber']); ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Ver ticket">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-ticket-perforated text-muted" style="font-size: 3rem;"></i>
                                <h6 class="mt-3 text-muted">Nenhum ticket encontrado</h6>
                                <p class="text-muted">Este contrato ainda não possui tickets associados.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Hours Summary -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Resumo de Horas</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            // Use the already calculated values
                            // Fix: Ensure we're working with minutes consistently
                            // $total_minutes and $used_minutes already calculated above
                            
                            // Convert for display
                            $total_hours_display = $total_minutes / 60;
                            $used_hours_display = $used_minutes / 60;
                            
                            // Fix: Calculate percentage based on actual values
                            $percentage = $total_minutes > 0 ? round(($used_minutes / $total_minutes) * 100) : 0;
                            
                            // Fix: Display remaining time correctly
                            $remaining_h = floor(abs($remaining_minutes) / 60);
                            $remaining_m = abs($remaining_minutes) % 60;
                            
                            // Debug: Log the actual values
                            error_log("Contract Debug - Total: {$total_minutes}min, Used: {$used_minutes}min, Remaining: {$remaining_minutes}min");
                            ?>
                            
                            <div class="text-center mb-3">
                                <h3 class="<?php echo $remaining_minutes <= 0 ? 'text-danger' : 'text-primary'; ?>">
                                    <?php if ($remaining_minutes < 0): ?>
                                        -<?php echo $remaining_h; ?>h <?php echo $remaining_m; ?>min
                                    <?php else: ?>
                                        <?php echo $remaining_h; ?>h <?php echo $remaining_m; ?>min
                                    <?php endif; ?>
                                </h3>
                                <small class="text-muted">
                                    <?php echo $remaining_minutes <= 0 ? 'Horas Excedidas' : 'Horas Restantes'; ?>
                                </small>
                            </div>
                            
                            <div class="progress mb-3" style="height: 10px;">
                                <div class="progress-bar <?php echo $percentage >= 100 ? 'bg-danger' : ($percentage >= 80 ? 'bg-warning' : 'bg-primary'); ?>" 
                                     style="width: <?php echo min(100, $percentage); ?>%"></div>
                                <?php if ($percentage > 100): ?>
                                <div class="progress-bar bg-danger bg-opacity-50" 
                                     style="width: <?php echo min($percentage - 100, 100); ?>%"></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <?php
                                    // Format total hours consistently using hours and minutes
                                    $total_hours = floor($total_minutes / 60);
                                    $total_mins = $total_minutes % 60;
                                    $total_display = $total_hours . 'h';
                                    if ($total_mins > 0) {
                                        $total_display .= ' ' . $total_mins . 'min';
                                    }
                                    ?>
                                    <strong><?php echo $total_display; ?></strong><br>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-6">
                                    <?php
                                    // Format used hours consistently using hours and minutes
                                    $used_hours = floor($used_minutes / 60);
                                    $used_mins = $used_minutes % 60;
                                    $used_display = $used_hours . 'h';
                                    if ($used_mins > 0) {
                                        $used_display .= ' ' . $used_mins . 'min';
                                    }
                                    ?>
                                    <strong><?php echo $used_display; ?></strong><br>
                                    <small class="text-muted">Utilizadas</small>
                                </div>
                            </div>
                            
                            <?php if ($percentage >= 80): ?>
                            <div class="alert alert-<?php echo $percentage >= 100 ? 'danger' : 'warning'; ?> mt-3 p-2" role="alert">
                                <small><i class="bi bi-exclamation-triangle me-1"></i>
                                <?php if ($percentage >= 100): ?>
                                    <?php
                                    // Calculate exceeded time in minutes instead of decimal hours
                                    $exceeded_minutes = ($used_minutes - $total_minutes);
                                    $exceeded_hours = floor($exceeded_minutes / 60);
                                    $exceeded_mins = $exceeded_minutes % 60;
                                    
                                    // Format as hours and minutes
                                    $exceeded_time = '';
                                    if ($exceeded_hours > 0) {
                                        $exceeded_time .= $exceeded_hours . 'h';
                                        if ($exceeded_mins > 0) {
                                            $exceeded_time .= ' ' . $exceeded_mins . 'min';
                                        }
                                    } else {
                                        $exceeded_time = $exceeded_mins . 'min';
                                    }
                                    ?>
                                    Contrato excedido em <?php echo $exceeded_time; ?>
                                <?php else: ?>
                                    Poucas horas restantes (<?php echo 100 - $percentage; ?>%)
                                <?php endif; ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Outros Contratos da Entity -->
                    <?php if (!empty($resumoContratos['contratos']) && count($resumoContratos['contratos']) > 1): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">Outros Contratos</h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($resumoContratos['contratos'] as $outroContrato): ?>
                                <?php 
                                // Make sure we have an ID to compare and the array is properly formatted
                                // Try different possible ID keys that might be returned by obterResumoContratos
                                $contractID = $outroContrato['XDfree02_KeyId'] ?? $outroContrato['id'] ?? '';
                                if (!empty($contractID) && $contractID !== $contract_id): 
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 border-start border-3 <?php echo (!empty($outroContrato['excedido']) && $outroContrato['excedido']) ? 'border-danger' : 'border-primary'; ?>">
                                    <div>
                                <?php 
                                // Convert totalHoras from minutes to hours for display
                                // Try different possible keys that might contain the total hours
                                $totalMinutos = 0;
                                if (isset($outroContrato['TotalHours'])) {
                                    $totalMinutos = (int)$outroContrato['TotalHours'];
                                } elseif (isset($outroContrato['totalHoras'])) {
                                    $totalMinutos = (int)$outroContrato['totalHoras'];
                                } elseif (isset($outroContrato['total_hours'])) {
                                    $totalMinutos = (int)$outroContrato['total_hours'];
                                }
                                
                                $totalHorasDisplay = floor($totalMinutos / 60);
                                $totalMinutosResto = $totalMinutos % 60;
                                $horasTexto = $totalHorasDisplay . 'h';
                                if ($totalMinutosResto > 0) {
                                    $horasTexto .= ' ' . $totalMinutosResto . 'min';
                                }
                                ?>
                                <small class="fw-bold"><?php echo $horasTexto; ?></small><br>
                                <small class="text-muted"><?php echo $outroContrato['Status'] ?? $outroContrato['status'] ?? 'N/A'; ?></small>
                            </div>
                            <div class="text-end">
                                <?php 
                                // Get remaining minutes or fallback to 0
                                // Try different possible keys for remaining time
                                $restanteMinutos = 0;
                                if (isset($outroContrato['restanteMinutos'])) {
                                    $restanteMinutos = (int)$outroContrato['restanteMinutos'];
                                } elseif (isset($outroContrato['remaining_minutes'])) {
                                    $restanteMinutos = (int)$outroContrato['remaining_minutes'];
                                } elseif (isset($outroContrato['tempoRestante'])) {
                                    $restanteMinutos = (int)$outroContrato['tempoRestante'];
                                }
                                
                                $restH = floor($restanteMinutos / 60);
                                $restM = $restanteMinutos % 60;
                                ?>
                                <a href="detalhes_contrato.php?id=<?php echo htmlspecialchars($contractID); ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <span>
                                        <?php echo $restH; ?>h <?php echo $restM; ?>min
                                    </span>
                                    <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <!-- Debug section to see what data is actually available -->
                            <?php if (isset($_GET['debug'])): ?>
                            <div class="mt-3 p-2 bg-light rounded">
                                <small class="text-muted">Debug - Contract data structure:</small>
                                <pre style="font-size: 10px; max-height: 200px; overflow-y: auto;">
                                <?php 
                                foreach ($resumoContratos['contratos'] as $index => $contract) {
                                    echo "Contract $index:\n";
                                    print_r($contract);
                                    echo "\n---\n";
                                }
                                ?>
                                </pre>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Contract Details -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">Detalhes Técnicos</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>ID:</strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($contrato['XDfree02_KeyId'] ?? 'N/A'); ?></small>
                            </div>
                            <div class="mb-2">
                                <strong>Entidade:</strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($contrato['Entity'] ?? 'N/A'); ?></small>
                            </div>
                            <div class="mb-2">
                                <strong>Montante Total:</strong><br>
                                <small class="text-muted"><?php echo number_format($contrato['TotalAmount'] ?? 0, 2, ',', '.'); ?>€</small>
                            </div>
                            <div class="mb-2">
                                <strong>Preço por Hora:</strong><br>
                                <?php
                                // Calculate price per hour correctly - fix calculation
                                $totalMinutes = (int)($contrato['TotalHours'] ?? 0);
                                $totalAmount = (float)($contrato['TotalAmount'] ?? 0);
                                $pricePerHour = 0;
                                
                                // Calculate price per hour correctly by using the total hours in the contract
                                if ($totalMinutes > 0) {
                                    $totalHours = $totalMinutes / 60; // Convert minutes to hours
                                    if ($totalHours > 0) {
                                        $pricePerHour = $totalAmount / $totalHours;
                                    }
                                }
                                ?>
                                <small class="text-muted"><?php echo number_format($pricePerHour, 2, ',', '.'); ?>€/hora</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <h5>Contrato não encontrado</h5>
                <p>O contrato com ID '<?php echo htmlspecialchars($contract_id); ?>' não foi encontrado no sistema.</p>
                <a href="consultar_contratos.php" class="btn btn-primary">Voltar à lista de contratos</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
        :root {
            --bs-primary: #529ebe;
            --bs-primary-rgb: 82, 158, 190;
        }
        
        .btn-primary, .btn-outline-primary {
            --bs-btn-color: #529ebe;
            --bs-btn-border-color: #529ebe;
        }
        
        .btn-primary {
            background-color: #529ebe;
            border-color: #529ebe;
        }
        
        .btn-primary:hover {
            background-color: #4a8ba8;
            border-color: #4a8ba8;
        }
        
        .btn-outline-primary:hover {
            background-color: #529ebe;
            border-color: #529ebe;
        }
        
        .text-primary {
            color: #529ebe !important;
        }
        
        .progress-bar {
            background-color: #529ebe;
        }
        
        .progress-bar.bg-primary {
            background-color: #529ebe !important;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>