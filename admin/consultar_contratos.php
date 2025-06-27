<?php
session_start();  // Inicia a sessão

include('conflogin.php');

// Restrict access to full admins only
requireFullAdmin();

include('db.php');
include('../verificar_tempo_disponivel.php'); // Modificado para apontar para arquivo raiz

// Parâmetros de pesquisa - EXPANDED
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$data_inicio_de = isset($_GET['data_inicio_de']) ? $_GET['data_inicio_de'] : '';
$data_inicio_ate = isset($_GET['data_inicio_ate']) ? $_GET['data_inicio_ate'] : '';
$tempo_restante_filter = isset($_GET['tempo_restante']) ? $_GET['tempo_restante'] : '';
$pack_horas_filter = isset($_GET['pack_horas']) ? $_GET['pack_horas'] : '';

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

try {
    // Atualizar status de todos os contratos antes de exibir
    $sqlEntities = "SELECT DISTINCT Entity FROM info_xdfree02_extrafields WHERE Entity IS NOT NULL";
    $stmtEntities = $pdo->prepare($sqlEntities);
    $stmtEntities->execute();
    $entities = $stmtEntities->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($entities as $entity) {
        atualizarStatusContratos($entity, $pdo);
    }
    
    // Construir query com filtros EXPANDIDOS
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $search_conditions = [];
        $search_conditions[] = "e.name LIKE :search";
        $search_conditions[] = "e.ContactEmail LIKE :search";
        $search_conditions[] = "e.MobilePhone1 LIKE :search";
        $search_conditions[] = "e.Phone1 LIKE :search";
        $search_conditions[] = "x2Extra.Status LIKE :search";
        $search_conditions[] = "x2Extra.XDfree02_KeyId LIKE :search"; // Add contract ID search
        
        $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
        $params['search'] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "x2Extra.Status = :status";
        $params['status'] = $status_filter;
    }
    
    // Date range filter
    if (!empty($data_inicio_de)) {
        $where_conditions[] = "x2Extra.StartDate >= :data_inicio_de";
        $params['data_inicio_de'] = $data_inicio_de . ' 00:00:00';
    }
    
    if (!empty($data_inicio_ate)) {
        $where_conditions[] = "x2Extra.StartDate <= :data_inicio_ate";
        $params['data_inicio_ate'] = $data_inicio_ate . ' 23:59:59';
    }
    
    // Pack hours filter - filter by total hours ranges
    if (!empty($pack_horas_filter)) {
        switch ($pack_horas_filter) {
            case '5':
                $where_conditions[] = "x2Extra.TotalHours >= 240 AND x2Extra.TotalHours <= 360"; // 4-6 hours range
                break;
            case '10':
                $where_conditions[] = "x2Extra.TotalHours >= 540 AND x2Extra.TotalHours <= 660"; // 9-11 hours range
                break;
            case '20':
                $where_conditions[] = "x2Extra.TotalHours >= 1140 AND x2Extra.TotalHours <= 1260"; // 19-21 hours range
                break;
        }
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Contar total de registros
    $sql_count = "SELECT COUNT(*) as total 
                  FROM info_xdfree02_extrafields x2Extra
                  LEFT JOIN entities e ON e.KeyId = x2Extra.Entity
                  $where_clause";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Main query with all fields
    $sql = "SELECT 
                x2Extra.XDfree02_KeyId,
                x2Extra.*,
                e.name as CompanyName,
                e.ContactEmail as EntityEmail,
                e.MobilePhone1 as EntityMobilePhone,
                e.Phone1 as EntityPhone
            FROM info_xdfree02_extrafields x2Extra
            LEFT JOIN entities e ON e.KeyId = x2Extra.Entity
            $where_clause 
            ORDER BY x2Extra.id 
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter by remaining time after fetching (since this requires calculation)
    if (!empty($tempo_restante_filter) && !empty($contratos)) {
        $filtered_contratos = [];
        foreach ($contratos as $contrato) {
            $total_minutes = (int)($contrato['TotalHours'] ?? 0);
            $used_minutes = (int)($contrato['SpentHours'] ?? 0);
            $remaining_minutes = $total_minutes - $used_minutes;
            
            $include = false;
            switch ($tempo_restante_filter) {
                case 'disponivel':
                    $include = $remaining_minutes > 0;
                    break;
                case 'excedido':
                    $include = $remaining_minutes < 0;
                    break;
                case 'esgotado':
                    $include = $remaining_minutes <= 0;
                    break;
                case 'critico':
                    $include = $remaining_minutes > 0 && $remaining_minutes <= 60; // Less than 1 hour
                    break;
            }
            
            if ($include) {
                $filtered_contratos[] = $contrato;
            }
        }
        $contratos = $filtered_contratos;
        
        // Recalculate totals for filtered results
        $total_records = count($contratos);
        $total_pages = ceil($total_records / $records_per_page);
        
        // Apply pagination to filtered results
        $contratos = array_slice($contratos, $offset, $records_per_page);
    }
    
    // Obter valores únicos de status
    $sql_status = "SELECT DISTINCT x2Extra.Status 
                   FROM info_xdfree02_extrafields x2Extra
                   WHERE x2Extra.Status IS NOT NULL AND x2Extra.Status != ''";
    $stmt_status = $pdo->prepare($sql_status);
    $stmt_status->execute();
    $status_options = $stmt_status->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar contratos: " . $e->getMessage();
    $contratos = [];
    $total_records = 0;
    $total_pages = 0;
    $status_options = [];
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
                    <h1 class="mb-3 display-5">Consultar Contratos</h1>
                    <p class="text-muted">Gestão de contratos do sistema</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-info fs-6"><?php echo number_format($total_records); ?> contratos encontrados</span>
                </div>
            </div>
            
            <?php if(isset($erro_db)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($erro_db); ?>
            </div>
            <?php endif; ?>
            
            <!-- Filtros EXPANDIDOS -->
            <div class="bg-white p-3 rounded border mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Pesquisar</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nome, email, telefone, ID contrato (SP-002)...">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">Todos os status</option>
                            <?php foreach ($status_options as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" 
                                    <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="pack_horas" class="form-label">Pack de Horas</label>
                        <select class="form-control" id="pack_horas" name="pack_horas">
                            <option value="">Todos os packs</option>
                            <option value="5" <?php echo $pack_horas_filter === '5' ? 'selected' : ''; ?>>5 Horas</option>
                            <option value="10" <?php echo $pack_horas_filter === '10' ? 'selected' : ''; ?>>10 Horas</option>
                            <option value="20" <?php echo $pack_horas_filter === '20' ? 'selected' : ''; ?>>20 Horas</option>
                            <option value="outros" <?php echo $pack_horas_filter === 'outros' ? 'selected' : ''; ?>>Outros</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="tempo_restante" class="form-label">Tempo Restante</label>
                        <select class="form-control" id="tempo_restante" name="tempo_restante">
                            <option value="">Todos</option>
                            <option value="disponivel" <?php echo $tempo_restante_filter === 'disponivel' ? 'selected' : ''; ?>>Com tempo disponível</option>
                            <option value="critico" <?php echo $tempo_restante_filter === 'critico' ? 'selected' : ''; ?>>Crítico (&lt;1h)</option>
                            <option value="esgotado" <?php echo $tempo_restante_filter === 'esgotado' ? 'selected' : ''; ?>>Esgotado</option>
                            <option value="excedido" <?php echo $tempo_restante_filter === 'excedido' ? 'selected' : ''; ?>>Excedido</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data de Início</label>
                        <div class="d-flex gap-1">
                            <input type="date" class="form-control" name="data_inicio_de" 
                                   value="<?php echo htmlspecialchars($data_inicio_de); ?>" 
                                   placeholder="De">
                            <input type="date" class="form-control" name="data_inicio_ate" 
                                   value="<?php echo htmlspecialchars($data_inicio_ate); ?>" 
                                   placeholder="Até">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i> Pesquisar
                            </button>
                            <a href="consultar_contratos.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Limpar
                            </a>
                            
                            <!-- Show active filters -->
                            <?php
                            $active_filters = [];
                            if (!empty($search)) $active_filters[] = "Pesquisa: " . htmlspecialchars($search);
                            if (!empty($status_filter)) $active_filters[] = "Status: " . htmlspecialchars($status_filter);
                            if (!empty($pack_horas_filter)) $active_filters[] = "Pack: " . htmlspecialchars($pack_horas_filter) . "h";
                            if (!empty($tempo_restante_filter)) $active_filters[] = "Tempo: " . htmlspecialchars($tempo_restante_filter);
                            if (!empty($data_inicio_de)) $active_filters[] = "De: " . htmlspecialchars($data_inicio_de);
                            if (!empty($data_inicio_ate)) $active_filters[] = "Até: " . htmlspecialchars($data_inicio_ate);
                            
                            if (!empty($active_filters)):
                            ?>
                            <div class="ms-auto">
                                <small class="text-muted">Filtros ativos: <?php echo implode(' | ', $active_filters); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
           <!-- Tabela de Contratos -->
            <div class="bg-white rounded border">
                <?php if (!empty($contratos)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pack de Horas</th>
                                <th>Cliente</th>
                                <th>Data Início</th>
                                <th>Status</th>
                                <th>Tempo Utilizado</th>
                                <th>Tempo Restante</th>
                                <th>Valor</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contracts_displayed = [];
                            foreach ($contratos as $contrato): 
                                $contract_id = $contrato['XDfree02_KeyId'] ?? '';
                                
                                // Skip if already displayed
                                if (empty($contract_id) || in_array($contract_id, $contracts_displayed)) {
                                    continue;
                                }
                                $contracts_displayed[] = $contract_id;
                                
                                $total_minutes = (int)($contrato['TotalHours'] ?? 0);
                                $used_minutes = (int)($contrato['SpentHours'] ?? 0);
                                $remaining_minutes = $total_minutes - $used_minutes;
                                $total_hours = $total_minutes / 60;
                                $percentage = $total_minutes > 0 ? round(($used_minutes / $total_minutes) * 100) : 0;
                                $excedido = $used_minutes > $total_minutes;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold">
                                        <?php 
                                        $display_hours = floor($total_hours);
                                        $display_minutes = $total_minutes % 60;
                                        echo $display_hours . "h";
                                        if ($display_minutes > 0) {
                                            echo " " . $display_minutes . "min";
                                        }
                                        ?>
                                    </div>
                                    <small class="text-muted">ID: <?php echo htmlspecialchars($contract_id); ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($contrato['CompanyName'] ?? 'N/A'); ?></div>
                                    <?php if (!empty($contrato['EntityEmail'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($contrato['EntityEmail']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $start_date = $contrato['StartDate'] ?? '';
                                    if (!empty($start_date) && $start_date !== '0000-00-00 00:00:00') {
                                        echo date('d/m/Y', strtotime($start_date));
                                    } else {
                                        echo '<span class="text-muted">A definir</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $status = $contrato['Status'] ?? '';
                                    $status_class = '';
                                    switch(strtolower($status)) {
                                        case 'em utilização': $status_class = 'bg-success'; break;
                                        case 'por começar': $status_class = 'bg-warning'; break;
                                        case 'concluido': $status_class = 'bg-info'; break;
                                        case 'excedido': $status_class = 'bg-danger'; break;
                                        case 'regularizado': $status_class = 'bg-primary'; break; // New status
                                        default: $status_class = 'bg-secondary';
                                    }
                                    if ($excedido && $status !== 'Concluído' && $status !== 'Regularizado') {
                                        $status_class = 'bg-danger';
                                        $status = 'Excedido';
                                    }
                                    echo !empty($status) ? "<span class='badge $status_class'>" . htmlspecialchars($status) . "</span>" : '<span class="badge bg-secondary">N/A</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($used_minutes > 0) {
                                        $used_h = floor($used_minutes / 60);
                                        $used_m = $used_minutes % 60;
                                        echo "<span class='fw-bold'>{$used_h}h {$used_m}min</span>";
                                        echo "<br><small class='text-muted'>$percentage% utilizado</small>";
                                    } else {
                                        echo '<span class="text-muted">0h</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($excedido) {
                                        $remaining_h = floor(abs($remaining_minutes) / 60);
                                        $remaining_m = abs($remaining_minutes) % 60;
                                        echo "<span class='fw-bold text-danger'>Excedido</span>";
                                        echo "<br><small class='text-danger'>{$remaining_h}h {$remaining_m}min em excesso</small>";
                                    } elseif ($remaining_minutes > 0) {
                                        $remaining_h = floor($remaining_minutes / 60);
                                        $remaining_m = $remaining_minutes % 60;
                                        echo "<span class='fw-bold text-success'>{$remaining_h}h {$remaining_m}min</span>";
                                        echo "<br><small class='text-muted'>Disponível</small>";
                                    } else {
                                        echo '<span class="text-muted">0h</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="fw-bold">€<?php echo number_format($contrato['TotalAmount'] ?? 0, 2, ',', '.'); ?></div>
                                    <small class="text-muted">
                                        €<?php echo $total_hours > 0 ? number_format(($contrato['TotalAmount'] ?? 0) / $total_hours, 2, ',', '.') : '0,00'; ?>/hora
                                    </small>
                                </td>
                                <td>
                                    <a href="detalhes_contrato.php?id=<?php echo htmlspecialchars($contract_id); ?>" class="btn btn-outline-primary btn-sm" title="Ver detalhes">
                                        <i class="bi bi-eye me-1"></i> Detalhes
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
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
            background-color: #e7f3ff;
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
        
        .progress-bar.bg-success {
            background-color: #529ebe !important;
        }
        
        .page-link {
            color: #529ebe;
        }
        
        .page-item.active .page-link {
            background-color: #529ebe;
            border-color: #529ebe;
        }
        
        .badge.bg-info {
            background-color: #529ebe !important;
        }
    </style>
</body>
</html>
