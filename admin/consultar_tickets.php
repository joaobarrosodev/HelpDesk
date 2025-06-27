<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');

// Helper function to limit text length
function limitCharacters($text, $limit = 35) {
    if (strlen($text) > $limit) {
        return substr($text, 0, $limit) . '...';
    }
    return $text;
}

// Allow both admin and comum users to access tickets consultation
// Restrict access based on user permissions in the query instead

// Verifica se existem filtros
$data_filtro = isset($_GET['data']) ? $_GET['data'] : '';
$prioridade_filtro = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';
$status_filtro = isset($_GET['status']) ? $_GET['status'] : '';
$usuario_filtro = isset($_GET['usuario']) ? $_GET['usuario'] : '';
$criador_filtro = isset($_GET['criador']) ? $_GET['criador'] : '';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

$params = [];

// Get current user info
$current_user_id = null;
$admin_email = $_SESSION['admin_email'] ?? '';
$admin_id = $_SESSION['admin_id'];

$user_sql = "SELECT id FROM users WHERE email = :admin_email";
$user_stmt = $pdo->prepare($user_sql);
$user_stmt->bindParam(':admin_email', $admin_email);
$user_stmt->execute();
$user_result = $user_stmt->fetch(PDO::FETCH_ASSOC);
$current_user_id = $user_result['id'] ?? null;

// Prepara a SQL para consultar todos os tickets
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.id, 
            xdfree01.Name as titulo_do_ticket, 
            info_xdfree01_extrafields.Atribuido as User, 
            u.Name as atribuido_a,
            info_xdfree01_extrafields.Relatorio as Description, 
            info_xdfree01_extrafields.User as assunto_do_ticket, 
            info_xdfree01_extrafields.Priority as prioridade, 
            info_xdfree01_extrafields.Status as status, 
            DATE_FORMAT(info_xdfree01_extrafields.CreationDate, '%d/%m/%Y') as criado, 
            DATE_FORMAT(info_xdfree01_extrafields.dateu, '%d/%m/%Y') as atualizado, 
            online.name as CreationUser,
            info_xdfree01_extrafields.CreationUser as CreationUserEmail,
            e.Name as entidadenome,
            (SELECT oee.Name 
             FROM comments_xdfree01_extrafields c 
             JOIN online_entity_extrafields oee ON c.user = oee.email 
             WHERE c.XDFree01_KeyID = xdfree01.KeyId 
             ORDER BY c.Date DESC LIMIT 1) as LastCommentUser,
            info_xdfree01_extrafields.Tempo as ResolutionTime
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LEFT JOIN users u ON info_xdfree01_extrafields.Atribuido = u.id
        LEFT JOIN online_entity_extrafields online on info_xdfree01_extrafields.CreationUser = online.email
        LEFT JOIN entities e ON online.Entity_KeyId = e.KeyId";

// Base WHERE clause
$whereConditions = [];

// Filter based on user permissions
if (isAdmin()) {
    // Full admins can see all tickets
    // No additional restriction needed
} else {
    // Common users can only see tickets assigned to them or created by them
    $whereConditions[] = "(info_xdfree01_extrafields.Atribuido = :current_user_id OR info_xdfree01_extrafields.CreationUser = :admin_email)";
    $params[':current_user_id'] = $current_user_id;
    $params[':admin_email'] = $admin_email;
}

// Add WHERE clause if we have conditions
if (!empty($whereConditions)) {
    $sql .= " WHERE " . implode(" AND ", $whereConditions);
}

// Adiciona filtros se existirem
$hasWhere = !empty($whereConditions);

if (!empty($data_filtro)) {
    $sql .= ($hasWhere ? " AND" : " WHERE") . " DATE(info_xdfree01_extrafields.dateu) = :data_filtro";
    $params[':data_filtro'] = $data_filtro;
    $hasWhere = true;
}

if (!empty($data_inicio)) {
    $sql .= ($hasWhere ? " AND" : " WHERE") . " DATE(info_xdfree01_extrafields.dateu) >= :data_inicio";
    $params[':data_inicio'] = $data_inicio;
    $hasWhere = true;
}

if (!empty($data_fim)) {
    $sql .= ($hasWhere ? " AND" : " WHERE") . " DATE(info_xdfree01_extrafields.dateu) <= :data_fim";
    $params[':data_fim'] = $data_fim;
    $hasWhere = true;
}

if (!empty($prioridade_filtro)) {
    $sql .= ($hasWhere ? " AND" : " WHERE") . " info_xdfree01_extrafields.Priority = :prioridade_filtro";
    $params[':prioridade_filtro'] = $prioridade_filtro;
    $hasWhere = true;
}

if (!empty($status_filtro)) {
    $sql .= ($hasWhere ? " AND" : " WHERE") . " LOWER(TRIM(info_xdfree01_extrafields.Status)) = :processed_status_filtro";
    $params[':processed_status_filtro'] = strtolower(trim($status_filtro));
    $hasWhere = true;
}

if (!empty($usuario_filtro)) {
    $sql .= ($hasWhere ? " AND" : " WHERE") . " info_xdfree01_extrafields.Atribuido = :usuario_filtro";
    $params[':usuario_filtro'] = $usuario_filtro;
    $hasWhere = true;
}

if (!empty($criador_filtro)) {
    $sql .= ($hasWhere ? " AND" : " WHERE") . " info_xdfree01_extrafields.CreationUser = :criador_filtro";
    $params[':criador_filtro'] = $criador_filtro;
    $hasWhere = true;
}

$sql .= " ORDER BY info_xdfree01_extrafields.dateu DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consultar todos os utilizadores para o dropdown de filtro (only for admins)
if (isAdmin()) {
    $sql_users = "SELECT id, Name FROM users ORDER BY Name ASC";
    $stmt_users = $pdo->prepare($sql_users);
    $stmt_users->execute();
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} else {
    $users = [];
}

// Obter listas de criadores para o dropdown de filtro
if (isAdmin()) {
    $sql_criadores = "SELECT DISTINCT online.email, online.name 
                      FROM info_xdfree01_extrafields info 
                      LEFT JOIN online_entity_extrafields online ON info.CreationUser = online.email 
                      WHERE online.name IS NOT NULL 
                      ORDER BY online.name ASC";
} else {
    $sql_criadores = "SELECT DISTINCT online.email, online.name 
                      FROM info_xdfree01_extrafields info 
                      LEFT JOIN online_entity_extrafields online ON info.CreationUser = online.email 
                      WHERE (info.Atribuido = :current_user_id OR info.CreationUser = :admin_email)
                      AND online.name IS NOT NULL 
                      ORDER BY online.name ASC";
}
$stmt_criadores = $pdo->prepare($sql_criadores);
if (!isAdmin()) {
    $stmt_criadores->bindParam(':current_user_id', $current_user_id);
    $stmt_criadores->bindParam(':admin_email', $admin_email);
}
$stmt_criadores->execute();
$criadores = $stmt_criadores->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<head>
    <!-- Add sticky table styles -->
    <style>
        /* Sticky first column styles */
        .table-wrapper {
            overflow-x: auto;
            position: relative;
        }

        .table {
            min-width: 900px; /* Force horizontal scroll on small screens */
        }

        /* Mobile sticky first column */
        @media (max-width: 991px) {
            /* First column sticky */
            .table th:first-child,
            .table td:first-child {
                position: sticky;
                left: 0;
                background-color: inherit;
                z-index: 1;
                /* Lighter, cleaner shadow */
                box-shadow: 2px 0 5px -2px rgba(0,0,0,0.15);
            }

            .table th:first-child {
                background-color: #f8f9fa;
                z-index: 3; /* Higher z-index for header of first column */
            }

            /* Fix alternating row colors for sticky column */
            .table tbody tr:nth-of-type(odd) td:first-child {
                background-color: #fff;
            }
            
            .table tbody tr:nth-of-type(even) td:first-child {
                background-color: #f9f9f9;
            }

            .table tbody tr:hover td:first-child {
                background-color: #f0f8ff;
            }
        }

        /* Remove the problematic gradient shadow */
        .table-wrapper::after {
            display: none;
        }

        /* Scroll indicator */
        .scroll-indicator {
            display: none;
            text-align: center;
            padding: 10px;
            color: #666;
            font-size: 14px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .scroll-indicator {
                display: block;
            }
        }

        :root {
            --bs-primary: #529ebe;
            --bs-primary-rgb: 82, 158, 190;
        }
        
        .content {
            border-top: none !important;
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        
        .container-fluid {
            border-top: none !important;
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
</head>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-column flex-lg-row">
                <div class="flex-grow-1">
                    <h1 class="mb-3 display-5">Consultar Tickets</h1>
                    <p class="">Consulte todos os tickets do sistema com filtros avançados e informações detalhadas.</p>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <!-- Filters -->
                    <form method="get" action="" class="row g-3 mb-4">
                        <div class="col-md-2">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="Aberto" <?php echo $status_filtro == 'Aberto' ? 'selected' : ''; ?>>Aberto</option>
                                <option value="Em Análise" <?php echo $status_filtro == 'Em Análise' ? 'selected' : ''; ?>>Em Análise</option>
                                <option value="Em Resolução" <?php echo $status_filtro == 'Em Resolução' ? 'selected' : ''; ?>>Em Resolução</option>
                                <option value="Aguarda Resposta" <?php echo $status_filtro == 'Aguarda Resposta' ? 'selected' : ''; ?>>Aguarda Resposta</option>
                                <option value="Concluído" <?php echo $status_filtro == 'Concluído' ? 'selected' : ''; ?>>Concluído</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="prioridade" class="form-label">Prioridade</label>
                            <select class="form-select" id="prioridade" name="prioridade">
                                <option value="">Todas</option>
                                <option value="Baixa" <?php echo $prioridade_filtro == 'Baixa' ? 'selected' : ''; ?>>Baixa</option>
                                <option value="Normal" <?php echo $prioridade_filtro == 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="Alta" <?php echo $prioridade_filtro == 'Alta' ? 'selected' : ''; ?>>Alta</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="criador" class="form-label">Criador</label>
                            <select class="form-select" id="criador" name="criador">
                                <option value="">Todos</option>
                                <?php foreach ($criadores as $criador): ?>
                                    <option value="<?php echo htmlspecialchars($criador['email']); ?>" <?php echo $criador_filtro == $criador['email'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($criador['name'] ?? $criador['email']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (isAdmin()): ?>
                        <div class="col-md-2">
                            <label for="usuario" class="form-label">Atribuído a</label>
                            <select class="form-select" id="usuario" name="usuario">
                                <option value="">Todos</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $usuario_filtro == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-12">
                            <div class="d-flex">
                                <a href="consultar_tickets.php" class="btn btn-sm btn-link ms-auto text-muted">
                                    <i class="bi bi-x-circle me-1"></i>Limpar filtros
                                </a>
                            </div>
                        </div>
                    </form                
                        
                    <!-- Table -->
                    <div class="table-responsive table-wrapper">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Título</th>
                                    <th>Assunto</th>
                                    <th>Cliente</th>
                                    <th>Atribuído a</th>
                                    <th>Data Criação</th>
                                    <th>Data Atualização</th>
                                    <th>Estado</th>
                                    <th>Prioridade</th>
                                    <th>Última DM</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tickets) > 0): ?>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo generateTicketUrl($ticket['KeyId'], true); ?>" class="text-decoration-none text-dark d-flex align-items-center">
                                                    <?php if ($ticket['status'] == 'Concluído'): ?>
                                                        <i class="bi bi-check-circle-fill me-2 text-success"></i>
                                                    <?php elseif ($ticket['status'] == 'Em Resolução'): ?>
                                                        <i class="bi bi-gear-fill me-2 text-warning"></i>
                                                    <?php elseif ($ticket['status'] == 'Em Análise'): ?>
                                                        <i class="bi bi-search me-2 text-info"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-circle me-2 text-secondary"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($ticket['titulo_do_ticket']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo $ticket['assunto_do_ticket'] ?? ''; ?></td>
                                            <td>
                                                <?php 
                                                $clientName = ($ticket['CreationUser'] ?? '');
                                                if (!empty($ticket['entidadenome'])) {
                                                    $clientName .= ' - ' . $ticket['entidadenome'];
                                                }
                                                echo htmlspecialchars(limitCharacters($clientName));
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($ticket['atribuido_a'] ?? '-'); ?></td>
                                            <td><?php echo $ticket['criado']; ?></td>
                                            <td><?php echo $ticket['atualizado']; ?></td>
                                            <td>
                                                <?php 
                                                $status = $ticket['status'];
                                                $statusClass = '';
                                                if ($status == 'Concluído') {
                                                    $statusClass = 'badge w-100 bg-success';
                                                } elseif ($status == 'Em Análise') {
                                                    $statusClass = 'badge w-100 bg-info';
                                                } elseif ($status == 'Em Resolução') {
                                                    $statusClass = 'badge w-100 bg-warning';
                                                } elseif ($status == 'Aguarda Resposta') {
                                                    $statusClass = 'badge w-100 bg-secondary';
                                                } elseif ($status == 'Aberto') {
                                                    $statusClass = 'badge w-100 bg-primary';
                                                } else {
                                                    $statusClass = 'badge w-100 bg-dark';
                                                }
                                                ?>
                                                <span class="<?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                $badgeClass = 'w-100 bg-success';
                                                if ($ticket['prioridade'] == 'Normal') {
                                                    $badgeClass = 'w-100 bg-warning';
                                                } else if ($ticket['prioridade'] == 'Alta') {
                                                    $badgeClass = 'w-100 bg-danger';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $ticket['prioridade']; ?></span>
                                            </td>
                                            <td><?php echo !empty($ticket['LastCommentUser']) ? htmlspecialchars($ticket['LastCommentUser']) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="bi bi-info-circle me-2"></i> Nenhum ticket encontrado com os filtros selecionados.
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sorting functionality
        const table = document.querySelector('table');
        const headers = table.querySelectorAll('th.sortable');
        const priorityMap = {
            'Baixa': 1,
            'Normal': 2,
            'Alta': 3
        };
        const statusMap = {
            'Aberto': 1,
            'Em Análise': 2,
            'Em Resolução': 3,
            'Aguarda Resposta': 4,
            'Concluído': 5
        };
        
        headers.forEach(function(header, index) {
            header.addEventListener('click', function() {
                const isAscending = !this.classList.contains('asc');
                
                // Reset all headers
                headers.forEach(h => {
                    h.classList.remove('asc', 'desc');
                });
                
                // Set current header
                this.classList.add(isAscending ? 'asc' : 'desc');
                
                const rows = Array.from(table.querySelectorAll('tbody tr'));
                
                // Sort the rows
                rows.sort(function(rowA, rowB) {
                    const cellAContent = rowA.cells[index].textContent.trim();
                    const cellBContent = rowB.cells[index].textContent.trim();
                    
                    // Special sorting for "Estado" column (index 3)
                    if (index === 3) {
                        const statusA = statusMap[cellAContent] || 0;
                        const statusB = statusMap[cellBContent] || 0;
                        return isAscending ? statusA - statusB : statusB - statusA;
                    }
                    
                    // Special sorting for "Prioridade" column (index 4)
                    if (index === 4) {
                        const priorityA = priorityMap[cellAContent] || 0;
                        const priorityB = priorityMap[cellBContent] || 0;
                        return isAscending ? priorityA - priorityB : priorityB - priorityA;
                    }
                    
                    // Try to sort as dates if possible
                    const dateA = parseDate(cellAContent);
                    const dateB = parseDate(cellBContent);
                    
                    if (dateA && dateB) {
                        return isAscending ? dateA - dateB : dateB - dateA;
                    }
                    
                    // Otherwise sort as strings
                    return isAscending ? 
                        cellAContent.localeCompare(cellBContent) : 
                        cellBContent.localeCompare(cellAContent);
                });
                
                // Reorder the rows
                const tbody = table.querySelector('tbody');
                rows.forEach(row => tbody.appendChild(row));
            });
        });
        
        // Helper function to try to parse dates (DD/MM/YYYY format)
        function parseDate(dateStr) {
            const parts = dateStr.match(/(\d{2})\/(\d{2})\/(\d{4})/);
            if (parts) {
                return new Date(parts[3], parts[2] - 1, parts[1]);
            }
            return null;
        }

        // Sticky table functionality
        const tableWrapper = document.querySelector('.table-wrapper');
        const scrollIndicator = document.querySelector('.scroll-indicator');

        if (tableWrapper && scrollIndicator) {
            tableWrapper.addEventListener('scroll', function() {
                if (this.scrollLeft > 0) {
                    scrollIndicator.style.display = 'none';
                } else if (window.innerWidth <= 768) {
                    scrollIndicator.style.display = 'block';
                }
            });
        }

        // Detect mobile and tablet views
        function isMobileOrTablet() {
            return window.innerWidth <= 991;
        }

        if (isMobileOrTablet()) {
            console.log('Vista mobile/tablet ativa - primeira coluna fixa habilitada');
        }
    });
    </script>
</body>
</html>