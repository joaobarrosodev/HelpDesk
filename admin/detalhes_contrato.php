<?php
session_start();
include('conflogin.php');
include('db.php');

// Get contract ID
$contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$contract_id) {
    header('Location: consultar_contratos.php');
    exit;
}

try {
    // Get contract details
    $sql = "SELECT 
                x2Extra.*,
                e.name as CompanyName
            FROM info_xdfree02_extrafields x2Extra
            LEFT JOIN entities e ON e.KeyId = x2Extra.Entity
            WHERE x2Extra.id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $contract_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contrato) {
        header('Location: consultar_contratos.php');
        exit;
    }
    
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar contrato: " . $e->getMessage();
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
                    <h1 class="h3 mb-2">Detalhes do Contrato #<?php echo htmlspecialchars($contrato['id']); ?></h1>
                    <p class="text-muted">Informações completas do contrato</p>
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
            
            <div class="row">
                <!-- Contract Information -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Informações do Contrato</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Empresa:</strong><br>
                                    <?php echo htmlspecialchars($contrato['CompanyName'] ?? 'N/A'); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong><br>
                                    <?php 
                                    $status = $contrato['Status'] ?? '';
                                    $status_class = '';
                                    switch(strtolower($status)) {
                                        case 'em utilização': $status_class = 'bg-success'; break;
                                        case 'por começar': $status_class = 'bg-warning'; break;
                                        case 'concluido': $status_class = 'bg-info'; break;
                                        default: $status_class = 'bg-secondary';
                                    }
                                    echo "<span class='badge $status_class'>" . htmlspecialchars($status) . "</span>";
                                    ?>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Data de Início:</strong><br>
                                    <?php 
                                    $start_date = $contrato['StartDate'] ?? '';
                                    echo !empty($start_date) ? date('d/m/Y', strtotime($start_date)) : 'N/A';
                                    ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Email:</strong><br>
                                    <?php echo htmlspecialchars($contrato['Email'] ?? 'N/A'); ?>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Telefone:</strong><br>
                                    <?php echo htmlspecialchars($contrato['Telefone'] ?? 'N/A'); ?>
                                </div>
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
                                <div class="progress-bar <?php echo $percentage >= 80 ? 'bg-warning' : 'bg-primary'; ?>" 
                                     style="width: <?php echo $percentage; ?>%"></div>
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
