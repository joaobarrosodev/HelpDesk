<?php
session_start();
include('conflogin.php');
include('db.php');

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

    // Set empty tickets for now
    $tickets = [];
    
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar contrato: " . $e->getMessage();
    $tickets = [];
    $contrato = null;
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
                                            case 'concluido': $status_class = 'bg-info'; break;
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
                        <div class="card-header">
                            <h5 class="mb-0">Tickets Utilizados (<?php echo count($tickets); ?>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="text-center py-4">
                                <i class="bi bi-ticket-perforated text-muted" style="font-size: 3rem;"></i>
                                <h6 class="mt-3 text-muted">Nenhum ticket encontrado</h6>
                                <p class="text-muted">Este contrato ainda não possui tickets associados.</p>
                            </div>
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
                            $total_hours = (int)($contrato['TotalHours'] ?? 0);
                            $used_hours = (int)($contrato['SpentHours'] ?? 0);
                            $remaining_hours = $total_hours - $used_hours;
                            $percentage = $total_hours > 0 ? round(($used_hours / $total_hours) * 100) : 0;
                            ?>
                            
                            <div class="text-center mb-3">
                                <h3 class="text-primary"><?php echo $remaining_hours; ?>h</h3>
                                <small class="text-muted">Horas Restantes</small>
                            </div>
                            
                            <div class="progress mb-3" style="height: 10px;">
                                <div class="progress-bar <?php echo $percentage >= 80 ? 'bg-warning' : ($percentage >= 100 ? 'bg-danger' : 'bg-primary'); ?>" 
                                     style="width: <?php echo min(100, $percentage); ?>%"></div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <strong><?php echo $total_hours; ?>h</strong><br>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-6">
                                    <strong><?php echo $used_hours; ?>h</strong><br>
                                    <small class="text-muted">Utilizadas</small>
                                </div>
                            </div>
                            
                            <?php if ($percentage >= 80): ?>
                            <div class="alert alert-warning mt-3 p-2" role="alert">
                                <small><i class="bi bi-exclamation-triangle me-1"></i>
                                <?php if ($percentage >= 100): ?>
                                Contrato esgotado
                                <?php else: ?>
                                Poucas horas restantes
                                <?php endif; ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
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
                                <small class="text-muted"><?php echo htmlspecialchars($contrato['TotalAmount'] ?? 'N/A'); ?></small>
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
</body>
</html>
