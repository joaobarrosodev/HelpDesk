<?php
session_start();  // Inicia a sessão

// Debug session information
error_log("meus_tickets.php - Session ID: " . session_id());
error_log("meus_tickets.php - Session data: " . print_r($_SESSION, true));

include('conflogin.php');
include('db.php');

// Helper function to limit text length
function limitCharacters($text, $limit = 35) {
    if (strlen($text) > $limit) {
        return substr($text, 0, $limit) . '...';
    }
    return $text;
}

// Consultar os tickets do utilizador autenticado (abertos e resolvidos)
$usuario_email = $_SESSION['usuario_email'];
$usuario_id = $_SESSION['usuario_id'];

// Verifica se existem filtros
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$prioridade_filtro = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';
$status_filtro = isset($_GET['status']) ? $_GET['status'] : '';
$assunto_filtro = isset($_GET['assunto']) ? $_GET['assunto'] : ''; // Novo filtro de assunto

$params = [];
$whereConditions = ["i.XDFree01_KeyID IS NOT NULL"];

// Modify the SQL query based on user role
if (isAdmin()) {
    // Admins see ALL tickets from THEIR entity
    $sql = "SELECT 
                t.id, 
                t.KeyId,
                t.Name as titulo_do_ticket,
                i.User as assunto_do_ticket, 
                DATE_FORMAT(i.dateu, '%d/%m/%Y') as atualizado, 
                DATE_FORMAT(i.CreationDate, '%d/%m/%Y') as criado,
                i.Status as status,
                i.Priority as prioridade,
                i.CreationUser,
                e.Name as entity_name,
                u.Name as atribuido_a,
                (SELECT 
                    CASE
                        WHEN c.user = 'admin' THEN 'Administrador'
                        WHEN oee2.Name IS NOT NULL THEN oee2.Name
                        ELSE SUBSTRING_INDEX(c.user, '@', 1)
                    END
                 FROM comments_xdfree01_extrafields c 
                 LEFT JOIN online_entity_extrafields oee2 ON c.user = oee2.email 
                 WHERE c.XDFree01_KeyID = t.KeyId 
                 ORDER BY c.Date DESC LIMIT 1) as LastCommentUser
            FROM 
                xdfree01 t
            LEFT JOIN 
                info_xdfree01_extrafields i ON t.KeyId = i.XDFree01_KeyID
            LEFT JOIN users u ON i.Atribuido = u.id
            LEFT JOIN
                online_entity_extrafields oee ON i.CreationUser = oee.email
            LEFT JOIN
                entities e ON e.KeyId = oee.Entity_KeyId";
    
    $whereConditions[] = "oee.Entity_KeyId = :usuario_entity_id";
    
    // Get the admin's entity ID
    $admin_entity_sql = "SELECT Entity_KeyId FROM online_entity_extrafields WHERE email = :admin_email";
    $admin_stmt = $pdo->prepare($admin_entity_sql);
    $admin_stmt->bindParam(':admin_email', $_SESSION['usuario_email']);
    $admin_stmt->execute();
    $admin_entity = $admin_stmt->fetch(PDO::FETCH_ASSOC);
    
    $params[':usuario_entity_id'] = $admin_entity['Entity_KeyId'];
} else {
    // Common users see only tickets THEY created (by their email)
    $sql = "SELECT 
                t.id, 
                t.KeyId,
                t.Name as titulo_do_ticket,
                i.User as assunto_do_ticket, 
                DATE_FORMAT(i.dateu, '%d/%m/%Y') as atualizado, 
                DATE_FORMAT(i.CreationDate, '%d/%m/%Y') as criado,
                i.Status as status,
                i.Priority as prioridade,
                i.CreationUser,
                u.Name as atribuido_a,
                (SELECT 
                    CASE
                        WHEN c.user = 'admin' THEN 'Administrador'
                        WHEN oee2.Name IS NOT NULL THEN oee2.Name
                        ELSE SUBSTRING_INDEX(c.user, '@', 1)
                    END
                 FROM comments_xdfree01_extrafields c 
                 LEFT JOIN online_entity_extrafields oee2 ON c.user = oee2.email 
                 WHERE c.XDFree01_KeyID = t.KeyId 
                 ORDER BY c.Date DESC LIMIT 1) as LastCommentUser
            FROM 
                xdfree01 t
            LEFT JOIN 
                info_xdfree01_extrafields i ON t.KeyId = i.XDFree01_KeyID
            LEFT JOIN users u ON i.Atribuido = u.id";
    
    $whereConditions[] = "i.CreationUser = :usuario_email";
    $params[':usuario_email'] = $_SESSION['usuario_email'];
}

// Adiciona filtros se existirem
if (!empty($data_inicio)) {
    $whereConditions[] = "DATE(i.dateu) >= :data_inicio";
    $params[':data_inicio'] = $data_inicio;
}

if (!empty($data_fim)) {
    $whereConditions[] = "DATE(i.dateu) <= :data_fim";
    $params[':data_fim'] = $data_fim;
}

if (!empty($prioridade_filtro)) {
    $whereConditions[] = "i.Priority = :prioridade_filtro";
    $params[':prioridade_filtro'] = $prioridade_filtro;
}

if (!empty($status_filtro)) {
    $whereConditions[] = "i.Status = :status_filtro";
    $params[':status_filtro'] = $status_filtro;
}

if (!empty($assunto_filtro)) {
    $whereConditions[] = "i.User LIKE :assunto_filtro";
    $params[':assunto_filtro'] = "%$assunto_filtro%";
}

$sql .= " WHERE " . implode(" AND ", $whereConditions);
$sql .= " ORDER BY i.dateu DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter lista de assuntos únicos para o dropdown
if (isAdmin()) {
    // Admins see subjects from tickets in their entity
    $admin_entity_sql = "SELECT Entity_KeyId FROM online_entity_extrafields WHERE email = :admin_email";
    $admin_stmt = $pdo->prepare($admin_entity_sql);
    $admin_stmt->bindParam(':admin_email', $_SESSION['usuario_email']);
    $admin_stmt->execute();
    $admin_entity = $admin_stmt->fetch(PDO::FETCH_ASSOC);
    
    $sql_assuntos = "SELECT DISTINCT i.User 
                     FROM info_xdfree01_extrafields i 
                     INNER JOIN online_entity_extrafields oee ON i.CreationUser = oee.email
                     WHERE oee.Entity_KeyId = :usuario_entity_id
                     AND i.User IS NOT NULL 
                     ORDER BY i.User";
    $stmt_assuntos = $pdo->prepare($sql_assuntos);
    $stmt_assuntos->bindParam(':usuario_entity_id', $admin_entity['Entity_KeyId']);
} else {
    // Common users see only subjects from their own tickets
    $sql_assuntos = "SELECT DISTINCT i.User 
                     FROM info_xdfree01_extrafields i 
                     WHERE i.CreationUser = :usuario_email
                     AND i.User IS NOT NULL 
                     ORDER BY i.User";
    $stmt_assuntos = $pdo->prepare($sql_assuntos);
    $stmt_assuntos->bindParam(':usuario_email', $_SESSION['usuario_email']);
}

$stmt_assuntos->execute();
$assuntos = $stmt_assuntos->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-column flex-lg-row">
                <div class="flex-grow-1">
                    <h1 class="mb-3 display-5">
                        <?php echo isAdmin() ? 'Tickets da Empresa' : 'Os Meus Tickets'; ?>
                    </h1>
                    <p class="">
                        <?php echo isAdmin() ? 'Lista de todos os tickets da sua empresa. Utilize os filtros abaixo para refinar a visualização.' : 'Lista dos tickets que criou. Utilize os filtros abaixo para refinar a visualização.'; ?>
                    </p>
                </div>
                <a href="abrir_ticket.php" class="btn btn-primary d-flex align-items-center">
                    Abrir Novo Ticket
                </a>
            </div>
            
            <div class="card shadow-sm mb-4">                
                <div class="card-body">
                    <!-- Filters -->
                    <form id="filterForm" method="get" action="" class="row g-3 mb-4">
                        <div class="col-md-2">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="date" class="form-control filter-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="date" class="form-control filter-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="assunto" class="form-label">Assunto</label>
                            <select class="form-select filter-control" id="assunto" name="assunto">
                                <option value="">Todos</option>
                                <?php foreach($assuntos as $assunto): ?>
                                <option value="<?php echo htmlspecialchars($assunto); ?>" <?php echo $assunto_filtro == $assunto ? 'selected' : ''; ?>><?php echo htmlspecialchars($assunto); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                       
                        <div class="col-md-2">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select filter-control" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="Em Análise" <?php echo $status_filtro == 'Em Análise' ? 'selected' : ''; ?>>Em Análise</option>
                                <option value="Em Resolução" <?php echo $status_filtro == 'Em Resolução' ? 'selected' : ''; ?>>Em Resolução</option>
                                <option value="Aguarda Resposta" <?php echo $status_filtro == 'Aguarda Resposta' ? 'selected' : ''; ?>>Aguarda Resposta</option>
                                <option value="Concluído" <?php echo $status_filtro == 'Concluído' ? 'selected' : ''; ?>>Concluído</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="prioridade" class="form-label">Prioridade</label>
                            <select class="form-select filter-control" id="prioridade" name="prioridade">
                                <option value="">Todas</option>
                                <option value="Baixa" <?php echo $prioridade_filtro == 'Baixa' ? 'selected' : ''; ?>>Baixa</option>
                                <option value="Normal" <?php echo $prioridade_filtro == 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="Alta" <?php echo $prioridade_filtro == 'Alta' ? 'selected' : ''; ?>>Alta</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-flex">
                                <a href="meus_tickets.php" class="btn btn-sm btn-link ms-auto text-muted">
                                    <i class="bi bi-x-circle me-1"></i>Limpar filtros
                                </a>
                            </div>
                        </div>
                    </form>
                    
                    <div class="alert alert-info p-2 d-flex align-items-center" id="filter-results">
                        <i class="bi bi-funnel-fill me-2"></i>
                        <span>A mostrar <strong><?php echo count($tickets); ?></strong> tickets</span>
                    </div>
                        
                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table align-middle">                            
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Assunto</th>
                                    <?php if (isAdmin()): ?>
                                    <th>Cliente</th>
                                    <th>Atribuído a</th>
                                    <?php endif; ?>
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
                                                <a href="detalhes_ticket.php?keyid=<?php echo urlencode($ticket['KeyId']); ?>" class="text-decoration-none text-dark d-flex align-items-center text-nowrap">
                                                    <i class="bi bi-arrow-right-circle me-2"></i> 
                                                    <?php echo htmlspecialchars($ticket['titulo_do_ticket']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($ticket['assunto_do_ticket'] ?? ''); ?></td>
                                            <?php if (isAdmin()): ?>
                                            <td><?php echo htmlspecialchars(limitCharacters($ticket['CreationUser'] ?? 'N/A')); ?></td>
                                            <td><?php echo htmlspecialchars($ticket['atribuido_a'] ?? '-'); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo $ticket['criado']; ?></td>
                                            <td><?php echo $ticket['atualizado']; ?></td>
                                            <td>
                                                <?php 
                                                $status = $ticket['status'];
                                                $statusClass = '';
                                                if ($status == 'Em Análise') {
                                                    $statusClass = 'badge w-100 bg-info';
                                                } elseif ($status == 'Em Resolução') {
                                                    $statusClass = 'badge w-100 bg-warning';
                                                } elseif ($status == 'Aguarda Resposta') {
                                                    $statusClass = 'badge w-100 bg-secondary';
                                                } elseif ($status == 'Concluído') {
                                                    $statusClass = 'badge w-100 bg-success';
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
                                        <td colspan="<?php echo isAdmin() ? '9' : '7'; ?>" class="text-center py-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="bi bi-info-circle me-2"></i> Não há tickets para exibir.
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
        // Auto-filtering
        const filterControls = document.querySelectorAll('.filter-control');
        filterControls.forEach(control => {
            control.addEventListener('change', () => {
                document.getElementById('filterForm').submit();
            });
        });
        
        // Clear filters button
        document.getElementById('clearFilters').addEventListener('click', function() {
            // Limpa os campos do formulário e submete
            const filterControls = document.querySelectorAll('.filter-control');
            filterControls.forEach(control => {
                control.value = '';
            });
            document.getElementById('filterForm').submit();
        });
        
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
                    
                    // Special sorting for "Prioridade" column (index 5)
                    if (index === 5) {
                        const priorityA = priorityMap[cellAContent] || 0;
                        const priorityB = priorityMap[cellBContent] || 0;
                        return isAscending ? priorityA - priorityB : priorityB - priorityA;
                    }
                    
                    // Try to sort as dates if possible
                    const dateA = parseDate(cellAContent);
                    const dateB = parseDate(cellBContent);
                    
                    if (dateA && dateB) {
                        return isAscending ? dateA - dateA : dateB - dateA;
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
    });
    </script>
</body>
</html>