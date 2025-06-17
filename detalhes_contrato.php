<?php
session_start();
include('conflogin.php');
include('db.php');

$entity = $_SESSION['usuario_id'];

// Get contract ID - keep as string
$contract_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($contract_id)) {
    header('Location: meus_contratos.php?error=no_id');
    exit;
}

try {
    // FORÇAR a busca do contrato com debug extensivo
    error_log("=== DEBUG DETALHES CONTRATO ===");
    error_log("Contract ID: '$contract_id' | Entity: '$entity'");
    
    $contrato = null;
    
    // Método 1: Query normal
    $sql1 = "SELECT x2.* FROM info_xdfree02_extrafields x2 WHERE x2.XDfree02_KeyId = ? AND x2.Entity = ?";
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute([$contract_id, $entity]);
    $result1 = $stmt1->fetch(PDO::FETCH_ASSOC);
    
    if ($result1) {
        $contrato = $result1;
        error_log("SUCESSO - Método 1 (query normal)");
    } else {
        error_log("Método 1 falhou");
        
        // Método 2: Só por contract_id para ver se existe
        $sql2 = "SELECT x2.* FROM info_xdfree02_extrafields x2 WHERE x2.XDfree02_KeyId = ?";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$contract_id]);
        $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($result2) {
            error_log("Contrato existe mas entity não confere: Contract Entity='{$result2['Entity']}' vs User Entity='$entity'");
            
            // Verificar se é questão de tipo ou espaços
            if (trim($result2['Entity']) == trim($entity) || 
                strval($result2['Entity']) == strval($entity)) {
                $contrato = $result2;
                error_log("SUCESSO - Método 2 (problema de comparação)");
            }
        } else {
            error_log("Contrato '$contract_id' não existe na BD");
        }
        
        // Método 3: TRIM both sides
        if (!$contrato) {
            $sql3 = "SELECT x2.* FROM info_xdfree02_extrafields x2 WHERE x2.XDfree02_KeyId = ? AND TRIM(x2.Entity) = TRIM(?)";
            $stmt3 = $pdo->prepare($sql3);
            $stmt3->execute([$contract_id, $entity]);
            $result3 = $stmt3->fetch(PDO::FETCH_ASSOC);
            
            if ($result3) {
                $contrato = $result3;
                error_log("SUCESSO - Método 3 (TRIM)");
            }
        }
    }

    if (!$contrato) {
        error_log("ERRO: Contrato não encontrado com nenhum método");
        header('Location: meus_contratos.php?error=contract_not_found');
        exit;
    }

    error_log("Contrato carregado: ID={$contrato['XDfree02_KeyId']}, Entity='{$contrato['Entity']}'");

    // Buscar nome da empresa separadamente
    $sql_empresa = "SELECT name FROM entities WHERE KeyId = ?";
    $stmt_empresa = $pdo->prepare($sql_empresa);
    $stmt_empresa->execute([$entity]);
    $empresa_info = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
    
    $nome_empresa = $empresa_info ? $empresa_info['name'] : "Cliente ID: $entity";

    // Calculate contract metrics
    $totalMinutos = $contrato['TotalHours']; // Already in minutes
    $gastoMinutos = ($contrato['SpentHours'] ?? 0); // Already in minutes
    $restanteMinutos = max(0, $totalMinutos - $gastoMinutos);
    $excedido = $gastoMinutos > $totalMinutos;

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
                    WHERE t.XDfree02_KeyId = ?
                    ORDER BY info.CreationDate DESC";
    
    $stmt_tickets = $pdo->prepare($sql_tickets);
    $stmt_tickets->execute([$contract_id]);
    $tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);

    // Get all contracts for this entity using correct field
    $sql_outros_contratos = "SELECT 
                                XDfree02_KeyId as id,
                                TotalHours as totalHoras,
                                SpentHours as gastoHoras,
                                Status as status
                            FROM info_xdfree02_extrafields
                            WHERE Entity = ? AND XDfree02_KeyId != ?
                            ORDER BY 
                                CASE 
                                    WHEN Status = 'Em Utilização' THEN 1
                                    WHEN Status = 'Por Começar' THEN 2
                                    ELSE 3
                                END,
                                TotalHours DESC";
    
    $stmt_outros = $pdo->prepare($sql_outros_contratos);
    $stmt_outros->execute([$entity, $contract_id]);
    $outros_contratos = $stmt_outros->fetchAll(PDO::FETCH_ASSOC);

    // Calculate remaining time for other contracts
    $tempo_total_restante = 0;
    foreach ($outros_contratos as &$outro) {
        $outro_restante = max(0, $outro['totalHoras'] - ($outro['gastoHoras'] ?? 0));
        $outro['restanteMinutos'] = $outro_restante;
        $outro['excedido'] = ($outro['gastoHoras'] ?? 0) > $outro['totalHoras'];
        if (!$outro['excedido']) {
            $tempo_total_restante += $outro_restante;
        }
    }
    
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar contrato: " . $e->getMessage();
    error_log("Erro SQL detalhes contrato: " . $e->getMessage());
    $tickets = [];
    $contrato = null;
    $outros_contratos = [];
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<head>
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
</head>
<body>
    <?php include('menu.php'); ?>
    
    <div class="content">
        <div class="container-fluid p-4">
            
            <!-- Cabeçalho -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2">Detalhes do Contrato</h1>
                    <p class="text-muted">Informações completas do contrato e tickets utilizados</p>
                </div>
                <div>
                    <a href="meus_contratos.php" class="btn btn-outline-secondary">
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
                    <!-- Contract Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Detalhes do Contrato</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <strong>Pack de Horas:</strong><br>
                                    <span class="fs-5 fw-bold text-primary">
                                        <?php 
                                        $totalHorasDisplay = floor($totalMinutos / 60);
                                        $totalMinutosResto = $totalMinutos % 60;
                                        echo $totalHorasDisplay . ' horas';
                                        if ($totalMinutosResto > 0) {
                                            echo ' e ' . $totalMinutosResto . ' minutos';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
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
                            <h5 class="mb-0">Os Meus Tickets (<?php echo count($tickets); ?>)</h5>
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
                            // Calculate remaining minutes
                            $remaining_minutes = $totalMinutos - $gastoMinutos;
                            
                            // Convert for display
                            $total_hours_display = $totalMinutos / 60;
                            $used_hours_display = $gastoMinutos / 60;
                            
                            // Calculate percentage
                            $percentage = $totalMinutos > 0 ? round(($gastoMinutos / $totalMinutos) * 100) : 0;
                            
                            // Display remaining time correctly
                            $remaining_h = floor(abs($remaining_minutes) / 60);
                            $remaining_m = abs($remaining_minutes) % 60;
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
                    
                    <!-- Outros Contratos -->
                    <?php if (!empty($outros_contratos)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">Os Meus Outros Contratos</h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($outros_contratos as $outroContrato): ?>
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
                                        <small class="text-muted"><?php echo htmlspecialchars($outroContrato['status']); ?></small>
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
                            <?php endforeach; ?>
                            
                            <div class="mt-3 pt-2 border-top">
                                <?php 
                                $totalRestH = floor($tempo_total_restante / 60);
                                $totalRestM = $tempo_total_restante % 60;
                                ?>
                                <small class="text-muted">Total disponível: <strong><?php echo $totalRestH; ?>h <?php echo $totalRestM; ?>min</strong></small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Contract Details -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="mb-0">Informações do Contrato</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Valor Pago:</strong><br>
                                <span class="text-success fw-bold">€<?php echo number_format($contrato['TotalAmount'] ?? 0, 2, ',', '.'); ?></span>
                            </div>
                            <div class="mb-2">
                                <strong>Preço por Hora:</strong><br>
                                <small class="text-muted">€<?php echo number_format(($contrato['TotalAmount'] ?? 0) / ($totalMinutos / 60), 2, ',', '.'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning">
                <h5>Contrato não encontrado</h5>
                <p>O contrato solicitado não foi encontrado ou não tem permissão para visualizá-lo.</p>
                <a href="meus_contratos.php" class="btn btn-primary">Voltar aos meus contratos</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>