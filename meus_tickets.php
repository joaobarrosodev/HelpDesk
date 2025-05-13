<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');

// Consultar os tickets do usuário logado (abertos e resolvidos)
$usuario_id = $_SESSION['usuario_id'];

// Verifica se existe um filtro de data
$data_filtro = isset($_GET['data']) ? $_GET['data'] : '';
$prioridade_filtro = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';
$status_filtro = isset($_GET['status']) ? $_GET['status'] : '';

$params = [];

// Cria a consulta SQL base
$sql = "SELECT 
            t.id, 
            t.KeyId,
            t.Name as titulo_do_ticket,
            i.User as assunto_do_ticket, 
            DATE_FORMAT(i.dateu, '%d/%m/%Y') as atualizado, 
            DATE_FORMAT(i.CreationDate, '%d/%m/%Y') as criado,
            i.Status as status,
            i.Priority as prioridade,
            '1' as nivel, -- Valor padrão já que i.Level não existe
            u.Name as atribuido_a            
        FROM 
            xdfree01 t
        JOIN 
            info_xdfree01_extrafields i ON t.KeyId = i.XDFree01_KeyID
        LEFT JOIN 
            users u ON i.AttUser = u.id
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
    $sql .= " AND i.Status = :status_filtro";
    $params[':status_filtro'] = $status_filtro;
}

$sql .= " ORDER BY i.dateu DESC";

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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="flex-grow-1">
                    <h1 class="mb-3">Meus Tickets</h1>
                    <p class="">Lista de todos os seus tickets, incluindo tickets em aberto e resolvidos. Use os filtros abaixo para refinar a visualização.</p>
                </div>
                <a href="ticket.php" class="btn btn-primary d-flex align-items-center">
                    <i class="bi bi-plus-circle me-2"></i> Abrir Novo Ticket
                </a>
            </div>
            
            <div class="card shadow-sm mb-4">                <div class="card-body">
                      <!-- Filters -->
                    <form method="get" action="" class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="data" class="form-label">Data</label>
                            <input type="date" class="form-control" id="data" name="data" value="<?php echo $data_filtro; ?>">
                        </div>
                       
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="Em Análise" <?php echo $status_filtro == 'Em Análise' ? 'selected' : ''; ?>>Em Análise</option>
                                <option value="Em Resolução" <?php echo $status_filtro == 'Em Resolução' ? 'selected' : ''; ?>>Em Resolução</option>
                                <option value="Aguarda Resposta Cliente" <?php echo $status_filtro == 'Aguarda Resposta Cliente' ? 'selected' : ''; ?>>Aguarda Resposta Cliente</option>
                                <option value="Concluído" <?php echo $status_filtro == 'Concluído' ? 'selected' : ''; ?>>Concluído</option>
                            </select>
                        </div>
                         <div class="col-md-3">
                            <label for="prioridade" class="form-label">Prioridade</label>                            <select class="form-select" id="prioridade" name="prioridade">
                                <option value="">Todas</option>
                                <option value="Baixa" <?php echo $prioridade_filtro == 'Baixa' ? 'selected' : ''; ?>>Baixa</option>
                                <option value="Normal" <?php echo $prioridade_filtro == 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="Alta" <?php echo $prioridade_filtro == 'Alta' ? 'selected' : ''; ?>>Alta</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        </div>
                    </form>
                        
                    
                    <!-- Table -->                    <div class="table-responsive">
                        <table class="table align-middle">                            <thead class="table-dark">                                <tr>
                                    <th scope="col" class="sortable">Título do Ticket</th>
                                    <th scope="col" class="sortable">Assunto</th>
                                    <th scope="col" class="sortable">Atualizado</th>
                                    <th scope="col" class="sortable">Criado</th>
                                    <th scope="col" class="sortable">Status</th>
                                    <th scope="col" class="sortable">Prioridade</th>
                                    <th scope="col" class="sortable">Atribuído a</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tickets) > 0): ?>                                    <?php foreach ($tickets as $ticket): ?>                                        <tr>                                            <td>
                                                <a href="detalhes_ticket.php?keyid=<?php echo $ticket['id']; ?>" class="text-decoration-none text-dark d-flex align-items-center">
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
                                                    $statusClass = 'badge bg-info';
                                                } elseif ($status == 'Em Resolução') {
                                                    $statusClass = 'badge bg-warning';
                                                } elseif ($status == 'Aguarda Resposta Cliente') {
                                                    $statusClass = 'badge bg-secondary';
                                                } elseif ($status == 'Concluído') {
                                                    $statusClass = 'badge bg-success';
                                                } else {
                                                    $statusClass = 'badge bg-dark';
                                                }
                                                ?>
                                                <span class="<?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                            </td>                                            <td>
                                                <?php 
                                                $badgeClass = 'bg-success';
                                                if ($ticket['prioridade'] == 'Normal') {
                                                    $badgeClass = 'bg-warning text-dark';
                                                } else if ($ticket['prioridade'] == 'Alta') {
                                                    $badgeClass = 'bg-danger';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $ticket['prioridade']; ?></span>
                                            </td>
                                            <td><?php echo !empty($ticket['atribuido_a']) ? htmlspecialchars($ticket['atribuido_a']) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>                                <?php else: ?>                                    <tr>                                        <td colspan="7" class="text-center py-4">
                                            <div class="alert alert-info mb-0">                                                <i class="bi bi-info-circle me-2"></i> Não há tickets para exibir.
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
                    const cellA = rowA.cells[index].textContent.trim();
                    const cellB = rowB.cells[index].textContent.trim();
                    
                    // Try to sort as dates if possible
                    const dateA = parseDate(cellA);
                    const dateB = parseDate(cellB);
                    
                    if (dateA && dateB) {
                        return isAscending ? dateA - dateB : dateB - dateA;
                    }
                    
                    // Otherwise sort as strings
                    return isAscending ? 
                        cellA.localeCompare(cellB) : 
                        cellB.localeCompare(cellA);
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