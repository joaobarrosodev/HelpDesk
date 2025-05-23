<?php
session_start();  // Inicia a sessão

// Debug session information
error_log("meus_tickets.php - Session ID: " . session_id());
error_log("meus_tickets.php - Session data: " . print_r($_SESSION, true));

include('conflogin.php');
include('db.php');

// Consultar os tickets do utilizador autenticado (abertos e resolvidos)
$usuario_id = $_SESSION['usuario_id'];

// Verifica se existe um filtro de data
$data_filtro = isset($_GET['data']) ? $_GET['data'] : '';
$prioridade_filtro = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';
$status_filtro = isset($_GET['status']) ? $_GET['status'] : '';
$assunto_filtro = isset($_GET['assunto']) ? $_GET['assunto'] : ''; // Novo filtro de assunto

$params = [];

// Cria a consulta SQL base - corrigindo as informações de atribuição
$sql = "SELECT 
            t.id, 
            t.KeyId,
            t.Name as titulo_do_ticket,
            i.User as assunto_do_ticket, 
            DATE_FORMAT(i.dateu, '%d/%m/%Y') as atualizado, 
            DATE_FORMAT(i.CreationDate, '%d/%m/%Y') as criado,
            i.Status as status,
            i.Priority as prioridade,
            u.Name as atribuido_a,
            i.Atribuido as atribuido_id,
            (SELECT 
                CASE
                    WHEN oee.Name IS NOT NULL THEN oee.Name
                    WHEN c.user = 'admin' THEN 'Administrador'
                    ELSE c.user
                END
             FROM comments_xdfree01_extrafields c 
             LEFT JOIN online_entity_extrafields oee ON c.user = oee.email 
             WHERE c.XDFree01_KeyID = t.KeyId 
             ORDER BY c.Date DESC LIMIT 1) as LastCommentUser
        FROM 
            xdfree01 t
        LEFT JOIN 
            info_xdfree01_extrafields i ON t.KeyId = i.XDFree01_KeyID
        LEFT JOIN 
            users u ON i.Atribuido = u.id
        WHERE 
            i.Entity = :usuario_id";

$params[':usuario_id'] = $usuario_id;

// Adiciona filtros se existirem
if (!empty($data_filtro)) {
    $sql .= " AND DATE(i.dateu) = :data_filtro";
    $params[':data_filtro'] = $data_filtro;
}

if (!empty($prioridade_filtro)) {
    $sql .= " AND i.Priority = :prioridade_filtro";
    $params[':prioridade_filtro'] = $prioridade_filtro;
}

if (!empty($status_filtro)) {
    $sql .= " AND LOWER(TRIM(i.Status)) = :processed_status_filtro";
    $params[':processed_status_filtro'] = strtolower(trim($status_filtro));
}

if (!empty($assunto_filtro)) {
    $sql .= " AND i.User LIKE :assunto_filtro";
    $params[':assunto_filtro'] = "%$assunto_filtro%";
}

$sql .= " ORDER BY i.dateu DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter lista de assuntos únicos para o dropdown
$sql_assuntos = "SELECT DISTINCT User FROM info_xdfree01_extrafields WHERE Entity = :usuario_id AND User IS NOT NULL ORDER BY User";
$stmt_assuntos = $pdo->prepare($sql_assuntos);
$stmt_assuntos->bindParam(':usuario_id', $usuario_id);
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
                    <h1 class="mb-3 display-5">Os Meus Tickets</h1>
                    <p class="">Lista de todos os seus tickets, incluindo tickets em aberto e resolvidos. Utilize os filtros abaixo para refinar a visualização.</p>
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
                            <label for="data" class="form-label">Data</label>
                            <input type="date" class="form-control filter-control" id="data" name="data" value="<?php echo $data_filtro; ?>">
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
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" id="clearFilters" class="btn btn-outline-danger w-100">
                                <i class="bi bi-x-circle"></i> Limpar Filtros
                            </button>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <div class="alert alert-info p-2 d-flex align-items-center" id="filter-results">
                                <i class="bi bi-funnel-fill me-2"></i>
                                <span>Mostrando <strong><?php echo count($tickets); ?></strong> tickets</span>
                            </div>
                        </div>
                    </form>
                        
                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table align-middle">                            
                            <thead class="table-dark">                
                                <tr>
                                    <th scope="col" class="sortable text-nowrap">Título</th>
                                    <th scope="col" class="sortable text-nowrap">Assunto</th>
                                    <th scope="col" class="sortable text-nowrap">Atualizado</th>
                                    <th scope="col" class="sortable text-nowrap">Criado</th>
                                    <th scope="col" class="sortable text-nowrap">Estado</th>
                                    <th scope="col" class="sortable text-nowrap">Prioridade</th>
                                    <th scope="col" class="sortable text-nowrap">Atribuído a</th>
                                    <th scope="col" class="sortable text-nowrap">Última Mensagem Por</th>
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
                                            <td><?php echo $ticket['atualizado']; ?></td>
                                            <td><?php echo $ticket['criado']; ?></td>
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
                                            <td><?php echo !empty($ticket['atribuido_a']) ? htmlspecialchars($ticket['atribuido_a']) : '-'; ?></td>
                                            <td><?php echo !empty($ticket['LastCommentUser']) ? htmlspecialchars($ticket['LastCommentUser']) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
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
    });
    </script>
</body>
</html>