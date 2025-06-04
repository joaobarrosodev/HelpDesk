<?php
session_start();
include('conflogin.php');
include('db.php');

// Parâmetros de pesquisa
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

try {
    // Verificar colunas disponíveis - usar tabela correta
    $sql_columns = "SHOW COLUMNS FROM info_xdfree02_extrafields";
    $stmt_columns = $pdo->prepare($sql_columns);
    $stmt_columns->execute();
    $columns = $stmt_columns->fetchAll(PDO::FETCH_COLUMN);
    
    // Construir query com filtros - usar estrutura correta
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $search_conditions = [];
        $search_conditions[] = "e.name LIKE :search";
        $search_conditions[] = "x2Extra.Email LIKE :search";
        $search_conditions[] = "x2Extra.Telefone LIKE :search";
        $search_conditions[] = "x2Extra.Status LIKE :search";
        
        $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
        $params['search'] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "x2Extra.Status = :status";
        $params['status'] = $status_filter;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Contar total de registros - usar query correta
    $sql_count = "SELECT COUNT(*) as total 
                  FROM info_xdfree02_extrafields x2Extra
                  LEFT JOIN entities e ON e.KeyId = x2Extra.Entity
                  $where_clause";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Buscar contratos - usar query que funciona
    $sql = "SELECT 
                x2Extra.XDfree02_KeyId,
                x2Extra.*,
                e.name as CompanyName
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
    
    // Obter valores únicos de status - usar query correta
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
                    <h1 class="h3 mb-2">Consultar Contratos</h1>
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
            
            <!-- Filtros -->
            <div class="bg-white p-3 rounded border mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Pesquisar</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Pesquisar em todos os campos...">
                    </div>
                    <div class="col-md-3">
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
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i> Pesquisar
                            </button>
                            <a href="consultar_contratos.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Limpar
                            </a>
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
                                <th>Empresa</th>
                                <th>Data Início</th>
                                <th>Status</th>
                                <th>Horas Restantes</th>
                                <th>Progresso</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contratos as $contrato): ?>
                            <tr>
                                <td>
                                    <div><?php echo htmlspecialchars($contrato['CompanyName'] ?? 'N/A'); ?></div>
                                    <?php if (!empty($contrato['Email'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($contrato['Email']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($contrato['Telefone'])): ?>
                                    <br><small class="text-muted"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($contrato['Telefone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $start_date = $contrato['StartDate'] ?? '';
                                    if (!empty($start_date)) {
                                        echo date('d/m/Y', strtotime($start_date));
                                    } else {
                                        echo '<span class="text-muted">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $status = $contrato['Status'] ?? '';
                                    $status_class = '';
                                    switch(strtolower($status)) {
                                        case 'em utilização':
                                            $status_class = 'bg-success';
                                            break;
                                        case 'por começar':
                                            $status_class = 'bg-warning';
                                            break;
                                        case 'concluido':
                                            $status_class = 'bg-info';
                                            break;
                                        default:
                                            $status_class = 'bg-secondary';
                                    }
                                    if (!empty($status)) {
                                        echo "<span class='badge $status_class'>" . htmlspecialchars($status) . "</span>";
                                    } else {
                                        echo '<span class="badge bg-secondary">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $total_hours = (int)($contrato['TotalHours'] ?? 0);
                                    $used_hours = (int)($contrato['SpentHours'] ?? 0);
                                    $remaining_hours = $total_hours - $used_hours;
                                    
                                    if ($total_hours > 0) {
                                        $hours_class = '';
                                        if ($remaining_hours <= 0) {
                                            $hours_class = 'text-danger';
                                        } elseif ($remaining_hours <= ($total_hours * 0.2)) {
                                            $hours_class = 'text-warning';
                                        } else {
                                            $hours_class = 'text-success';
                                        }
                                        echo "<span class='fw-bold $hours_class'>{$remaining_hours}h</span>";
                                        echo "<br><small class='text-muted'>de {$total_hours}h</small>";
                                    } else {
                                        echo '<span class="text-muted">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($total_hours > 0): ?>
                                    <?php 
                                    $percentage = min(100, round(($used_hours / $total_hours) * 100));
                                    $progress_class = '';
                                    if ($percentage >= 100) {
                                        $progress_class = 'bg-danger';
                                    } elseif ($percentage >= 80) {
                                        $progress_class = 'bg-warning';
                                    } else {
                                        $progress_class = 'bg-success';
                                    }
                                    ?>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?php echo $progress_class; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%"
                                             title="<?php echo $percentage; ?>% utilizado">
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo $percentage; ?>%</small>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="detalhes_contrato.php?id=<?php echo htmlspecialchars($contrato['id']); ?>" 
                                           class="btn btn-outline-primary btn-sm"
                                           title="Ver detalhes">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-success btn-sm" 
                                                onclick="editContract(<?php echo htmlspecialchars($contrato['id']); ?>)"
                                                title="Editar contrato">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($remaining_hours > 0): ?>
                                        <button type="button" 
                                                class="btn btn-outline-info btn-sm" 
                                                onclick="addHours(<?php echo htmlspecialchars($contrato['id']); ?>)"
                                                title="Adicionar horas">
                                            <i class="bi bi-plus-circle"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <div class="text-muted">
                        Mostrando <?php echo number_format($offset + 1); ?> a <?php echo number_format(min($offset + $records_per_page, $total_records)); ?> 
                        de <?php echo number_format($total_records); ?> registros
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Anterior</a>
                            </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Próximo</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-text text-muted" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 text-muted">Nenhum contrato encontrado</h5>
                    <p class="text-muted">Tente ajustar os filtros de pesquisa.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function editContract(id) {
            // Implementar edição do contrato
            window.location.href = 'editar_contrato.php?id=' + id;
        }
        
        function addHours(id) {
            // Implementar adição de horas
            window.location.href = 'adicionar_horas.php?id=' + id;
        }
    </script>
</body>
</html>
