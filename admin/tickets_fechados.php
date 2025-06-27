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

// Use the admin_id from session directly - this should be the correct user ID
$admin_id = $_SESSION['admin_id'];

// Recupera os filtros se existirem
$prioridade_filtro = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';
$criador_filtro = isset($_GET['criador']) ? $_GET['criador'] : '';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$params = [];

$whereConditions = ["info_xdfree01_extrafields.Status = 'Concluído'"];
$whereConditions[] = "info_xdfree01_extrafields.Atribuido = :current_user_id";
$params[':current_user_id'] = $admin_id;

// Adicionar filtros adicionais
if (!empty($prioridade_filtro)) {
    $whereConditions[] = "info_xdfree01_extrafields.Priority = :prioridade_filtro";
    $params[':prioridade_filtro'] = $prioridade_filtro;
}

if (!empty($criador_filtro)) {
    $whereConditions[] = "info_xdfree01_extrafields.CreationUser = :criador_filtro";
    $params[':criador_filtro'] = $criador_filtro;
}

if (!empty($data_inicio)) {
    $whereConditions[] = "DATE(info_xdfree01_extrafields.dateu) >= :data_inicio";
    $params[':data_inicio'] = $data_inicio;
}

if (!empty($data_fim)) {
    $whereConditions[] = "DATE(info_xdfree01_extrafields.dateu) <= :data_fim";
    $params[':data_fim'] = $data_fim;
}

// Prepara a SQL para tickets fechados (concluídos)
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.id, 
            xdfree01.Name as titulo_do_ticket, 
            info_xdfree01_extrafields.Atribuido as User, 
            info_xdfree01_extrafields.Relatorio as Description, 
            info_xdfree01_extrafields.User as assunto_do_ticket,
            info_xdfree01_extrafields.Priority as prioridade, 
            info_xdfree01_extrafields.Status as status, 
            DATE_FORMAT(info_xdfree01_extrafields.CreationDate, '%d/%m/%Y') as criado, 
            DATE_FORMAT(info_xdfree01_extrafields.dateu, '%d/%m/%Y') as atualizado, 
            online.name as CreationUser,
            info_xdfree01_extrafields.CreationUser as CreationUserEmail,
            u.Name as atribuido_a,
            e.Name as entidadenome,
            (SELECT oee.Name 
             FROM comments_xdfree01_extrafields c 
             JOIN online_entity_extrafields oee ON c.user = oee.email 
             WHERE c.XDFree01_KeyID = xdfree01.KeyId 
             ORDER BY c.Date DESC LIMIT 1) as LastCommentUser,
            info_xdfree01_extrafields.Tempo as ResolutionTime,
            info_xdfree01_extrafields.Relatorio as ResolutionDescription
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LEFT JOIN users u ON info_xdfree01_extrafields.Atribuido = u.id
        LEFT JOIN online_entity_extrafields online ON info_xdfree01_extrafields.CreationUser = online.email
        LEFT JOIN entities e ON online.Entity_KeyId = e.KeyId
        WHERE " . implode(" AND ", $whereConditions) . "
        ORDER BY info_xdfree01_extrafields.dateu DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter listas para os dropdowns de filtro
$sql_criadores = "SELECT DISTINCT online.email, online.name 
                  FROM info_xdfree01_extrafields info 
                  LEFT JOIN online_entity_extrafields online ON info.CreationUser = online.email 
                  WHERE info.Status = 'Concluído' 
                  AND info.Atribuido = :current_user_id 
                  AND online.name IS NOT NULL 
                  ORDER BY online.name ASC";
$stmt_criadores = $pdo->prepare($sql_criadores);
$stmt_criadores->bindParam(':current_user_id', $admin_id);
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
            min-width: 800px; /* Force horizontal scroll on small screens */
        }

        /* Mobile sticky first column */
        @media (max-width: 768px) {
            .table-wrapper {
                position: relative;
            }

            /* First column sticky */
            .table th:first-child,
            .table td:first-child {
                position: sticky;
                left: 0;
                background-color: white;
                z-index: 1;
                box-shadow: 2px 0 4px rgba(0,0,0,0.1);
            }

            .table th:first-child {
                background-color: #f8f9fa;
                z-index: 3; /* Higher z-index for header of first column */
            }

            /* Alternating row colors for sticky column */
            .table tbody tr:nth-child(even) td:first-child {
                background-color: #f9f9f9;
            }

            .table tbody tr:hover td:first-child {
                background-color: #f0f8ff;
            }

            /* Scroll indicator shadow */
            .table-wrapper::after {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                bottom: 0;
                width: 20px;
                background: linear-gradient(to left, rgba(0,0,0,0.1), transparent);
                pointer-events: none;
            }
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
    </style>
</head>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-column flex-lg-row">
                <div class="flex-grow-1">
                    <h1 class="mb-3 display-5">Tickets Fechados</h1>
                    <p class="">Lista de todos os tickets concluídos, com tempo de resolução e informações de conclusão.</p>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <!-- Filters -->
                    <form method="get" action="" class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="prioridade" class="form-label">Prioridade</label>
                            <select class="form-select" id="prioridade" name="prioridade">
                                <option value="">Todas</option>
                                <option value="Baixa" <?php echo $prioridade_filtro == 'Baixa' ? 'selected' : ''; ?>>Baixa</option>
                                <option value="Normal" <?php echo $prioridade_filtro == 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="Alta" <?php echo $prioridade_filtro == 'Alta' ? 'selected' : ''; ?>>Alta</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
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
                        
                        <div class="col-12">
                            <div class="d-flex">
                                <a href="tickets_fechados.php" class="btn btn-sm btn-link ms-auto text-muted">
                                    <i class="bi bi-x-circle me-1"></i>Limpar filtros
                                </a>
                            </div>
                        </div>
                    </form>
                        
                    <!-- Table -->
                    <div class="table-responsive table-wrapper">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="sortable text-nowrap">Título</th>
                                    <th scope="col" class="sortable text-nowrap">Assunto</th>
                                    <th scope="col" class="sortable text-nowrap">Cliente</th>
                                    <th scope="col" class="sortable text-nowrap">Data Criação</th>
                                    <th scope="col" class="sortable text-nowrap">Data Atualização</th>
                                    <th scope="col" class="sortable text-nowrap">Prioridade</th>
                                    <th scope="col" class="sortable text-nowrap">Tempo (min)</th>
                                    <th scope="col" class="sortable text-nowrap">Última DM</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tickets) > 0): ?>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td>
                                                <a href="detalhes_ticket.php?keyid=<?php echo $ticket['id']; ?>" class="text-decoration-none text-dark d-flex align-items-center text-nowrap">
                                                    <i class="bi bi-check-circle-fill me-2 text-success"></i> 
                                                    <?php echo htmlspecialchars($ticket['titulo_do_ticket']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($ticket['assunto_do_ticket'] ?? ''); ?></td>
                                            <td><?php 
                                                $clientName = ($ticket['CreationUser'] ?? '');
                                                if (!empty($ticket['entidadenome'])) {
                                                    $clientName .= ' - ' . $ticket['entidadenome'];
                                                }
                                                echo htmlspecialchars(limitCharacters($clientName));
                                            ?></td>
                                            <td><?php echo $ticket['criado']; ?></td>
                                            <td><?php echo $ticket['atualizado']; ?></td>
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
                                            <td><?php echo !empty($ticket['ResolutionTime']) ? htmlspecialchars($ticket['ResolutionTime']) : '-'; ?></td>
                                            <td><?php echo !empty($ticket['LastCommentUser']) ? htmlspecialchars($ticket['LastCommentUser']) : $ticket['atualizado']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="bi bi-info-circle me-2"></i> Não há tickets fechados para exibir.
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
                    
                    // Special sorting for "Prioridade" column (index 3)
                    if (index === 3) {
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
                    
                    // Try to sort as numbers if possible
                    const numA = Number(cellAContent);
                    const numB = Number(cellBContent);
                    
                    if (!isNaN(numA) && !isNaN(numB)) {
                        return isAscending ? numA - numB : numB - numA;
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

        // Detect mobile and show tips
        function isMobile() {
            return window.innerWidth <= 768;
        }

        if (isMobile()) {
            console.log('Vista mobile ativa - primeira coluna fixa habilitada');
        }

        // Implement auto-filter on change
        const filterElements = document.querySelectorAll('select, input[type="date"]');
        filterElements.forEach(element => {
            element.addEventListener('change', function() {
                this.form.submit();
            });
        });
    });
    </script>
</body>
</html>