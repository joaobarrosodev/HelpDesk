<?php
session_start();
include('conflogin.php');
include('db.php');

$entity = $_SESSION['usuario_id'];

try {
    // Buscar contratos do cliente com informações detalhadas
    $sql = "SELECT 
                x2Extra.XDfree02_KeyId,
                x2Extra.TotalHours,
                x2Extra.SpentHours,
                x2Extra.Status,
                x2Extra.StartDate,
                x2Extra.TotalAmount,
                e.name as CompanyName
            FROM info_xdfree02_extrafields x2Extra
            LEFT JOIN entities e ON e.KeyId = x2Extra.Entity
            WHERE x2Extra.Entity = :entity 
            ORDER BY 
                CASE 
                    WHEN x2Extra.Status = 'Em Utilização' THEN 1
                    WHEN x2Extra.Status = 'Por Começar' THEN 2
                    ELSE 3
                END,
                x2Extra.TotalHours DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':entity', $entity);
    $stmt->execute();
    $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totais
    $tempoTotalComprado = 0;
    $tempoTotalGasto = 0;
    $tempoRestante = 0;
    $contratosExcedidos = 0;
    
    foreach ($contratos as &$contrato) {
        // TotalHours and SpentHours are already in minutes in database
        $totalMinutos = $contrato['TotalHours']; // Already in minutes
        $gastoMinutos = ($contrato['SpentHours'] ?? 0); // Already in minutes
        $restanteMinutos = max(0, $totalMinutos - $gastoMinutos);
        
        $contrato['restanteMinutos'] = $restanteMinutos;
        $contrato['excedido'] = ($contrato['SpentHours'] ?? 0) > $contrato['TotalHours'];
        
        $tempoTotalComprado += $totalMinutos;
        $tempoTotalGasto += $gastoMinutos;
        
        if (!$contrato['excedido']) {
            $tempoRestante += $restanteMinutos;
        }
        
        if ($contrato['excedido']) {
            $contratosExcedidos++;
        }
    }
    
    // Buscar tickets utilizados nos contratos
    $sql_tickets = "SELECT 
                        t.XDfree02_KeyId,
                        t.TicketNumber, 
                        t.TotTime,
                        free.Name as TicketName,
                        info.CreationDate,
                        info.Status as TicketStatus
                    FROM tickets_xdfree02_extrafields t
                    LEFT JOIN xdfree01 free ON t.TicketNumber = free.id
                    LEFT JOIN info_xdfree01_extrafields info ON free.KeyId = info.XDFree01_KeyID
                    WHERE t.XDfree02_KeyId IN (" . implode(',', array_fill(0, count($contratos), '?')) . ")
                    ORDER BY info.CreationDate DESC";
    
    if (!empty($contratos)) {
        $stmt_tickets = $pdo->prepare($sql_tickets);
        $contratoIds = array_column($contratos, 'XDfree02_KeyId');
        $stmt_tickets->execute($contratoIds);
        $tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $tickets = [];
    }
    
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar contratos: " . $e->getMessage();
    $contratos = [];
    $tickets = [];
    $tempoTotalComprado = $tempoTotalGasto = $tempoRestante = $contratosExcedidos = 0;
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<head>
    <style>
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
        }
        
        .summary-card .card-body {
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        
        .contract-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
            height: 100%;
        }
        
        .contract-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .contract-active { border-left-color: #28a745; }
        .contract-warning { border-left-color: #ffc107; }
        .contract-danger { border-left-color: #dc3545; }
        .contract-info { border-left-color: #17a2b8; }
        .contract-secondary { border-left-color: #6c757d; }
        
        .time-display {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .progress-custom {
            height: 8px;
            border-radius: 4px;
        }
        
        .ticket-item {
            border-left: 3px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
            margin-bottom: 8px;
            padding: 12px;
            transition: all 0.2s;
        }
        
        .ticket-item:hover {
            background: #e9ecef;
            border-left-color: #007bff;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
                    <h1 class="mb-3 display-5">Os Meus Contratos</h1>
                    <p class="text-muted">Gestão de pacotes de horas e tempo disponível</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="abrir_ticket.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Criar Ticket
                    </a>
                </div>
            </div>
            
            <?php if(isset($erro_db)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($erro_db); ?>
            </div>
            <?php endif; ?>
            
            <!-- Resumo Geral -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card summary-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-clock-history" style="font-size: 2rem;"></i>
                                    </div>
                                    <h4 class="mb-1">
                                        <?php 
                                        $totalCompradoH = floor($tempoTotalComprado / 60);
                                        $totalCompradoM = $tempoTotalComprado % 60;
                                        echo $totalCompradoH . 'h ' . $totalCompradoM . 'min';
                                        ?>
                                    </h4>
                                    <small class="opacity-75">Tempo Total Comprado</small>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-hourglass-split" style="font-size: 2rem;"></i>
                                    </div>
                                    <h4 class="mb-1">
                                        <?php 
                                        $totalGastoH = floor($tempoTotalGasto / 60);
                                        $totalGastoM = $tempoTotalGasto % 60;
                                        echo $totalGastoH . 'h ' . $totalGastoM . 'min';
                                        ?>
                                    </h4>
                                    <small class="opacity-75">Tempo Gasto</small>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-hourglass" style="font-size: 2rem;"></i>
                                    </div>
                                    <h4 class="mb-1 <?php echo $tempoRestante <= 0 ? 'text-warning' : ''; ?>">
                                        <?php 
                                        $restanteH = floor($tempoRestante / 60);
                                        $restanteM = $tempoRestante % 60;
                                        echo $restanteH . 'h ' . $restanteM . 'min';
                                        ?>
                                    </h4>
                                    <small class="opacity-75">Tempo Disponível</small>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="mb-2">
                                        <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
                                    </div>
                                    <h4 class="mb-1"><?php echo $contratosExcedidos; ?></h4>
                                    <small class="opacity-75">Contratos Excedidos</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Avisos -->
            <?php if ($tempoRestante <= 0): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Sem tempo disponível!</strong> Não tem horas restantes nos seus contratos. Contacte-nos para renovar.
            </div>
            <?php elseif ($tempoRestante <= 120): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-clock me-2"></i>
                <strong>Tempo baixo!</strong> Tem apenas <?php echo floor($tempoRestante / 60); ?>h <?php echo $tempoRestante % 60; ?>min restantes.
            </div>
            <?php endif; ?>
            
            <!-- Lista de Contratos -->
            <div class="row">
                <?php if (!empty($contratos)): ?>
                    <?php foreach ($contratos as $contrato): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card contract-card 
                                    <?php 
                                    if ($contrato['excedido']) {
                                        echo 'contract-danger';
                                    } elseif ($contrato['Status'] === 'Em Utilização') {
                                        echo ($contrato['restanteMinutos'] <= 120) ? 'contract-warning' : 'contract-active';
                                    } elseif ($contrato['Status'] === 'Por Começar') {
                                        echo 'contract-info';
                                    } else {
                                        echo 'contract-secondary';
                                    }
                                    ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0">Contrato <?php echo $contrato['TotalHours']; ?>h</h5>
                                    <span class="badge status-badge bg-<?php 
                                        switch(strtolower($contrato['Status'])) {
                                            case 'em utilização': echo 'success'; break;
                                            case 'por começar': echo 'info'; break;
                                            case 'concluído': echo 'secondary'; break;
                                            case 'excedido': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <?php echo htmlspecialchars($contrato['Status']); ?>
                                    </span>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6 text-center">
                                        <div class="time-display text-primary">
                                            <?php 
                                            // Convert minutes to hours for display
                                            $totalHorasDisplay = floor($contrato['TotalHours'] / 60);
                                            $totalMinutosResto = $contrato['TotalHours'] % 60;
                                            echo $totalHorasDisplay . 'h';
                                            if ($totalMinutosResto > 0) {
                                                echo ' ' . $totalMinutosResto . 'min';
                                            }
                                            ?>
                                        </div>
                                        <small class="text-muted">Total</small>
                                    </div>
                                    <div class="col-6 text-center">
                                        <div class="time-display <?php echo $contrato['excedido'] ? 'text-danger' : 'text-success'; ?>">
                                            <?php 
                                            $restH = floor($contrato['restanteMinutos'] / 60);
                                            $restM = $contrato['restanteMinutos'] % 60;
                                            echo $restH . 'h';
                                            if ($restM > 0) {
                                                echo ' ' . $restM . 'min';
                                            }
                                            ?>
                                        </div>
                                        <small class="text-muted">Restante</small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <?php 
                                    $percentage = ($contrato['TotalHours'] > 0) ? min(100, round(($contrato['SpentHours'] / $contrato['TotalHours']) * 100)) : 0;
                                    ?>
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Utilizado: 
                                            <?php 
                                            $spentH = floor(($contrato['SpentHours'] ?? 0) / 60);
                                            $spentM = ($contrato['SpentHours'] ?? 0) % 60;
                                            echo $spentH . 'h';
                                            if ($spentM > 0) {
                                                echo ' ' . $spentM . 'min';
                                            }
                                            ?>
                                        </small>
                                        <small><?php echo $percentage; ?>%</small>
                                    </div>
                                    <div class="progress progress-custom">
                                        <div class="progress-bar bg-<?php echo $contrato['excedido'] ? 'danger' : 'primary'; ?>" 
                                             style="width: <?php echo min($percentage, 100); ?>%"></div>
                                        <?php if ($contrato['excedido'] && $percentage > 100): ?>
                                        <div class="progress-bar bg-danger bg-opacity-50" 
                                             style="width: <?php echo min($percentage - 100, 100); ?>%"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between text-muted small">
                                    <span>
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo $contrato['StartDate'] ? date('d/m/Y', strtotime($contrato['StartDate'])) : 'N/A'; ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-currency-euro me-1"></i>
                                        <?php echo number_format($contrato['TotalAmount'] ?? 0, 2, ',', '.'); ?>€
                                    </span>
                                </div>
                                
                                <!-- Tickets deste contrato -->
                                <?php 
                                $ticketsContrato = array_filter($tickets, function($ticket) use ($contrato) {
                                    return $ticket['XDfree02_KeyId'] === $contrato['XDfree02_KeyId'];
                                });
                                if (!empty($ticketsContrato)): 
                                ?>
                                <div class="mt-3">
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-ticket-perforated me-1"></i>
                                        Tickets (<?php echo count($ticketsContrato); ?>)
                                    </h6>
                                    <div style="max-height: 150px; overflow-y: auto;">
                                        <?php foreach (array_slice($ticketsContrato, 0, 3) as $ticket): ?>
                                        <div class="ticket-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <small class="fw-bold"><?php echo htmlspecialchars($ticket['TicketName'] ?? 'N/A'); ?></small>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php 
                                                        $tempo = intval($ticket['TotTime']);
                                                        $h = floor($tempo / 60);
                                                        $m = $tempo % 60;
                                                        echo $h . 'h ' . $m . 'min';
                                                        ?>
                                                    </small>
                                                </div>
                                                <a href="detalhes_ticket.php?keyid=<?php echo htmlspecialchars($ticket['TicketNumber']); ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($ticketsContrato) > 3): ?>
                                        <div class="text-center">
                                            <small class="text-muted">
                                                +<?php echo count($ticketsContrato) - 3; ?> mais tickets
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-file-earmark-text"></i>
                        <h4>Nenhum contrato encontrado</h4>
                        <p>Não tem contratos ativos no momento. Contacte-nos para adquirir um pacote de horas.</p>
                        <a href="mailto:suporte@empresa.com" class="btn btn-primary">
                            <i class="bi bi-envelope me-1"></i>Contactar Suporte
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>