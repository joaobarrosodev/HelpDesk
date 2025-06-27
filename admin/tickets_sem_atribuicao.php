<?php
session_start();  // Inicia a sessão

include('conflogin.php');

// Restrict access to admins only - comum users shouldn't see unassigned tickets
if (!isAdmin()) {
    header('Location: index.php?error=' . urlencode('Acesso negado. Apenas administradores podem ver tickets sem atribuição.'));
    exit();
}

include('db.php');

// Helper function to limit text length
function limitCharacters($text, $limit = 35) {
    if (strlen($text) > $limit) {
        return substr($text, 0, $limit) . '...';
    }
    return $text;
}

// Recupera os filtros se existirem
$estado_filtro = isset($_GET['status']) ? $_GET['status'] : '';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$prioridade_filtro = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';

$params = [];
$whereConditions = ["(info_xdfree01_extrafields.Atribuido IS NULL OR info_xdfree01_extrafields.Atribuido = '')"];
$whereConditions[] = "info_xdfree01_extrafields.Status <> 'Concluído'";

// Adicionar filtros adicionais
if (!empty($estado_filtro)) {
    $whereConditions[] = "info_xdfree01_extrafields.Status = :estado_filtro";
    $params[':estado_filtro'] = $estado_filtro;
}

if (!empty($prioridade_filtro)) {
    $whereConditions[] = "info_xdfree01_extrafields.Priority = :prioridade_filtro";
    $params[':prioridade_filtro'] = $prioridade_filtro;
}

if (!empty($data_inicio)) {
    $whereConditions[] = "DATE(info_xdfree01_extrafields.dateu) >= :data_inicio";
    $params[':data_inicio'] = $data_inicio;
}

if (!empty($data_fim)) {
    $whereConditions[] = "DATE(info_xdfree01_extrafields.dateu) <= :data_fim";
    $params[':data_fim'] = $data_fim;
}

// Prepara a SQL para tickets sem atribuição
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.id, 
            xdfree01.Name as titulo_do_ticket, 
            info_xdfree01_extrafields.Atribuido as User, 
            info_xdfree01_extrafields.User as assunto_do_ticket,
            info_xdfree01_extrafields.Priority as prioridade, 
            info_xdfree01_extrafields.Status as status, 
            DATE_FORMAT(info_xdfree01_extrafields.CreationDate, '%d/%m/%Y') as criado, 
            DATE_FORMAT(info_xdfree01_extrafields.dateu, '%d/%m/%Y') as atualizado, 
            online.name as CreationUser,
            e.Name as entidadenome,
            (SELECT oee.Name 
             FROM comments_xdfree01_extrafields c 
             JOIN online_entity_extrafields oee ON c.user = oee.email 
             WHERE c.XDFree01_KeyID = xdfree01.KeyId 
             ORDER BY c.Date DESC LIMIT 1) as LastCommentUser
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LEFT JOIN online_entity_extrafields online on info_xdfree01_extrafields.CreationUser = online.email
        LEFT JOIN entities e ON online.Entity_KeyId = e.KeyId
        WHERE " . implode(" AND ", $whereConditions) . "
        ORDER BY info_xdfree01_extrafields.dateu DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <div class="container-fluid p-4">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4 flex-column flex-lg-row">
                <div class="flex-grow-1">
                    <h1 class="mb-3 display-5">Tickets Sem Atribuição</h1>
                    <p class="">Lista de tickets que ainda não foram atribuídos a nenhum responsável.</p>
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
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="Em Análise" <?php echo $estado_filtro == 'Em Análise' ? 'selected' : ''; ?>>Em Análise</option>
                                <option value="Em Resolução" <?php echo $estado_filtro == 'Em Resolução' ? 'selected' : ''; ?>>Em Resolução</option>
                                <option value="Aguarda Resposta" <?php echo $estado_filtro == 'Aguarda Resposta' ? 'selected' : ''; ?>>Aguarda Resposta</option>
                            </select>
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
                        
                        <div class="col-12">
                            <div class="d-flex">
                                <a href="tickets_sem_atribuicao.php" class="btn btn-sm btn-link ms-auto text-muted">
                                    <i class="bi bi-x-circle me-1"></i>Limpar filtros
                                </a>
                            </div>
                        </div>
                    </form>
                        
                    <!-- Table -->
                    <div class="table-responsive table-wrapper pb-5">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="sortable text-nowrap">Título</th>
                                    <th scope="col" class="sortable text-nowrap">Assunto</th>
                                    <th scope="col" class="sortable text-nowrap">Cliente</th>
                                    <th scope="col" class="sortable text-nowrap">Data Criação</th>
                                    <th scope="col" class="sortable text-nowrap">Data Atualização</th>
                                    <th scope="col" class="sortable text-nowrap">Prioridade</th>
                                    <th scope="col" class="sortable text-nowrap">Última DM</th>
                                    <th scope="col" class="text-nowrap">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tickets) > 0): ?>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td>
                                                <a href="detalhes_ticket.php?keyid=<?php echo $ticket['id']; ?>" class="text-decoration-none text-dark d-flex align-items-center text-nowrap">
                                                    <i class="bi bi-arrow-right-circle me-2"></i> 
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
                                            <td><?php echo !empty($ticket['LastCommentUser']) ? htmlspecialchars($ticket['LastCommentUser']) : '-'; ?></td>
                                            <td>
                                                <a href="?action=atribuir_mim&id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-person-check me-1"></i> Atribuir a mim
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="bi bi-info-circle me-2"></i> Não há tickets sem atribuição no momento.
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