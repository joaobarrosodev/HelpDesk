<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');

// Verifica se existem filtros
$data_filtro = isset($_GET['data']) ? $_GET['data'] : '';
$prioridade_filtro = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';
$status_filtro = isset($_GET['status']) ? $_GET['status'] : '';
$usuario_filtro = isset($_GET['usuario']) ? $_GET['usuario'] : '';

$params = [];

// Prepara a SQL para admin
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.id, 
            xdfree01.Name as titulo_do_ticket, 
            info_xdfree01_extrafields.Atribuido as User, 
            u.Name as atribuido_a,
            info_xdfree01_extrafields.Relatorio as Description, 
            info_xdfree01_extrafields.Priority as prioridade, 
            info_xdfree01_extrafields.Status as status, 
            DATE_FORMAT(info_xdfree01_extrafields.CreationDate, '%d/%m/%Y') as criado, 
            DATE_FORMAT(info_xdfree01_extrafields.dateu, '%d/%m/%Y') as atualizado, 
            online.name as CreationUser,
            (SELECT oee.Name 
             FROM comments_xdfree01_extrafields c 
             JOIN online_entity_extrafields oee ON c.user = oee.email 
             WHERE c.XDFree01_KeyID = xdfree01.KeyId 
             ORDER BY c.Date DESC LIMIT 1) as LastCommentUser
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LEFT JOIN users u ON info_xdfree01_extrafields.Atribuido = u.id
        LEFT JOIN online_entity_extrafields online on info_xdfree01_extrafields.CreationUser = online.email";

// Adiciona condição apenas se não for incluir os fechados
if (empty($status_filtro) || $status_filtro != 'Concluído') {
    $sql .= " WHERE (info_xdfree01_extrafields.Status <> 'Concluído' OR info_xdfree01_extrafields.Status IS NULL)";
} else {
    $sql .= " WHERE 1=1"; // Condição que sempre é verdadeira para manter a estrutura do SQL
}

// Adiciona filtros se existirem
if (!empty($data_filtro)) {
    $sql .= " AND DATE(info_xdfree01_extrafields.dateu) = :data_filtro";
    $params[':data_filtro'] = $data_filtro;
}

if (!empty($prioridade_filtro)) {
    $sql .= " AND info_xdfree01_extrafields.Priority = :prioridade_filtro";
    $params[':prioridade_filtro'] = $prioridade_filtro;
}

if (!empty($status_filtro)) {
    $sql .= " AND LOWER(TRIM(info_xdfree01_extrafields.Status)) = :processed_status_filtro";
    $params[':processed_status_filtro'] = strtolower(trim($status_filtro));
}

if (!empty($usuario_filtro)) {
    $sql .= " AND info_xdfree01_extrafields.Atribuido = :usuario_filtro";
    $params[':usuario_filtro'] = $usuario_filtro;
}

$sql .= " ORDER BY info_xdfree01_extrafields.dateu DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consultar todos os usuários para o dropdown de filtro
$sql_users = "SELECT id, Name FROM users ORDER BY Name ASC";
$stmt_users = $pdo->prepare($sql_users);
$stmt_users->execute();
$users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
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
                    <h1 class="mb-3 display-5">Consultar Tickets</h1>
                    <p class="">Visualize e gerencie todos os tickets do sistema. Utilize os filtros abaixo para refinar a visualização.</p>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <!-- Filters -->
                    <form method="get" action="" class="row g-3 mb-4">
                        <div class="col-md-2">
                            <label for="data" class="form-label">Data</label>
                            <input type="date" class="form-control" id="data" name="data" value="<?php echo $data_filtro; ?>">
                        </div>
                       
                        <div class="col-md-3">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
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
                        
                        <div class="col-md-3">
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
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-dark w-100">Filtrar</button>
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
                                    <th scope="col" class="sortable text-nowrap">Criador</th>
                                    <th scope="col" class="sortable text-nowrap">Última Mensagem Por</th>
                                    <th scope="col" class="text-nowrap">Ações</th>
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
                                            <td><?php echo $ticket['assunto']; ?></td>
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
                                            <td><?php echo $ticket['CreationUser']; ?></td>
                                            <td><?php echo !empty($ticket['LastCommentUser']) ? htmlspecialchars($ticket['LastCommentUser']) : '-'; ?></td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $ticket['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-gear"></i>
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $ticket['id']; ?>">
                                                        <li><a class="dropdown-item" href="detalhes_ticket.php?keyid=<?php echo $ticket['KeyId']; ?>"><i class="bi bi-eye me-2"></i> Ver detalhes</a></li>
                                                        <?php if ($ticket['status'] !== 'Concluído'): ?>
                                                            <li><a class="dropdown-item text-danger fechar-ticket" href="#" data-id="<?php echo $ticket['KeyId']; ?>"><i class="bi bi-x-circle me-2"></i> Fechar ticket</a></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="bi bi-info-circle me-2"></i> Não há tickets correspondentes aos filtros aplicados.
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

    <!-- Modal de confirmação para fechar ticket -->
    <div class="modal fade" id="fecharTicketModal" tabindex="-1" aria-labelledby="fecharTicketModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fecharTicketModalLabel">Confirmar fechamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja fechar este ticket? Esta ação não pode ser desfeita.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="confirmarFecharTicket" class="btn btn-danger">Confirmar fechamento</a>
                </div>
            </div>
        </div>
    </div>    <script>
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
        
        // Fechar ticket functionality
        const fecharBtns = document.querySelectorAll('.fechar-ticket');
        const modal = new bootstrap.Modal(document.getElementById('fecharTicketModal'));
        const confirmarBtn = document.getElementById('confirmarFecharTicket');
        
        fecharBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const ticketId = this.getAttribute('data-id');
                confirmarBtn.setAttribute('href', `processar_alteracao.php?action=close&keyid=${ticketId}`);
                modal.show();
            });
        });
    });
    </script>
</body>
</html>
