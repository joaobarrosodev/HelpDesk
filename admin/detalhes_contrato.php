<?php
session_start();
include('conflogin.php');
include('db.php');
include('verificar_tempo_disponivel.php'); // Incluir o sistema de verificação

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

    // Get tickets used in this contract
    $sql_tickets = "SELECT 
                        t.TicketNumber, 
                        t.TotTime,
                        free.KeyId as TicketKeyId,
                        free.Name as TicketName,
                        info.Description as TicketDescription,
                        info.Status as TicketStatus,
                        info.CreationDate,
                        info.CreationUser
                    FROM tickets_xdfree02_extrafields t
                    LEFT JOIN xdfree01 free ON t.TicketNumber = free.id
                    LEFT JOIN info_xdfree01_extrafields info ON free.KeyId = info.XDFree01_KeyID
                    WHERE t.XDfree02_KeyId = :contract_id
                    ORDER BY info.CreationDate DESC";
    
    $stmt_tickets = $pdo->prepare($sql_tickets);
    $stmt_tickets->bindValue(':contract_id', $contract_id, PDO::PARAM_STR);
    $stmt_tickets->execute();
    $tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);

    // Get all contracts for this entity (for context)
    $entity = $contrato['Entity'];
    $resumoContratos = obterResumoContratos($entity, $pdo);
    
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
                                            case 'concluído': 
                                            case 'concluido': $status_class = 'bg-info'; break;
                                            case 'excedido': $status_class = 'bg-danger'; break;
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
                                        <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ticket['TicketName'] ?? 'N/A'); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($ticket['TicketKeyId'] ?? 'N/A'); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $description = $ticket['TicketDescription'] ?? '';
                                                echo !empty($description) ? htmlspecialchars(substr($description, 0, 100)) . (strlen($description) > 100 ? '...' : '') : '<span class="text-muted">N/A</span>';
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
                                                <?php if ($ticket['TicketStatus'] === 'Concluído'): ?>
                                                <span class="badge bg-secondary ms-1" title="Ticket encerrado - apenas leitura">
                                                    <i class="bi bi-lock-fill"></i>
                                                </span>
                                                <?php endif; ?>
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
                            // Fix: Ensure we're working with minutes consistently
                            $total_minutes = (int)($contrato['TotalHours'] ?? 0); // Already in minutes from DB
                            $used_minutes = (int)($contrato['SpentHours'] ?? 0);   // Already in minutes from DB
                            
                            // Calculate remaining minutes
                            $remaining_minutes = $total_minutes - $used_minutes;
                            
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
                                    <strong><?php echo number_format($total_hours_display, 1); ?>h</strong><br>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-6">
                                    <strong><?php echo number_format($used_hours_display, 1); ?>h</strong><br>
                                    <small class="text-muted">Utilizadas</small>
                                </div>
                            </div>
                            
                            <?php if ($percentage >= 80): ?>
                            <div class="alert alert-<?php echo $percentage >= 100 ? 'danger' : 'warning'; ?> mt-3 p-2" role="alert">
                                <small><i class="bi bi-exclamation-triangle me-1"></i>
                                <?php if ($percentage >= 100): ?>
                                Contrato excedido em <?php echo number_format(abs($used_hours_display - $total_hours_display), 1); ?>h
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
                                <?php if ($outroContrato['id'] !== $contract_id): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 border-start border-3 <?php echo $outroContrato['excedido'] ? 'border-danger' : 'border-primary'; ?>">
                                    <div>
                                        <?php 
                                        // Convert totalHoras from minutes to hours for display
                                        $totalMinutos = (int)$outroContrato['totalHoras'];
                                        $totalHorasDisplay = floor($totalMinutos / 60);
                                        $totalMinutosResto = $totalMinutos % 60;
                                        $horasTexto = $totalHorasDisplay . 'h';
                                        if ($totalMinutosResto > 0) {
                                            $horasTexto .= ' ' . $totalMinutosResto . 'min';
                                        }
                                        ?>
                                        <small class="fw-bold"><?php echo $horasTexto; ?></small><br>
                                        <small class="text-muted"><?php echo $outroContrato['status']; ?></small>
                                    </div>
                                    <div class="text-end">
                                        <?php 
                                        $restH = floor($outroContrato['restanteMinutos'] / 60);
                                        $restM = $outroContrato['restanteMinutos'] % 60;
                                        ?>
                                        <small class="<?php echo $outroContrato['excedido'] ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo $restH; ?>h <?php echo $restM; ?>min
                                        </small>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <div class="mt-3 pt-2 border-top">
                                <?php 
                                $totalRestH = floor($resumoContratos['tempoRestante'] / 60);
                                $totalRestM = $resumoContratos['tempoRestante'] % 60;
                                ?>
                                <small class="text-muted">Total disponível: <strong><?php echo $totalRestH; ?>h <?php echo $totalRestM; ?>min</strong></small>
                            </div>
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