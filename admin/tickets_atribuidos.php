<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');

// Use the admin_id from session directly - this should be the correct user ID
$admin_id = $_SESSION['admin_id'];

// Recupera os filtros se existirem
$estado_filtro = isset($_GET['status']) ? $_GET['status'] : '';
$prioridade_filtro = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';
$criador_filtro = isset($_GET['criador']) ? $_GET['criador'] : '';
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$params = [];

// Get current user's assigned user ID for all users
$current_user_id = null;
$admin_email = $_SESSION['admin_email'] ?? '';

// Obter o ID do usuário atual - usuário que está logado
$user_sql = "SELECT id FROM users WHERE email = :admin_email";
$user_stmt = $pdo->prepare($user_sql);
$user_stmt->bindParam(':admin_email', $admin_email);
$user_stmt->execute();
$user_result = $user_stmt->fetch(PDO::FETCH_ASSOC);
$current_user_id = $user_result['id'] ?? null;

// Prepara a SQL para tickets atribuídos
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.id, 
            xdfree01.Name as titulo_do_ticket, 
            info_xdfree01_extrafields.Atribuido as User, 
            info_xdfree01_extrafields.Priority as prioridade, 
            info_xdfree01_extrafields.Status as status, 
            DATE_FORMAT(info_xdfree01_extrafields.CreationDate, '%d/%m/%Y') as criado, 
            DATE_FORMAT(info_xdfree01_extrafields.dateu, '%d/%m/%Y') as atualizado, 
            online.name as CreationUser,
            info_xdfree01_extrafields.CreationUser as CreationUserEmail,
            u.Name as atribuido_a,
            (SELECT oee.Name 
             FROM comments_xdfree01_extrafields c 
             JOIN online_entity_extrafields oee ON c.user = oee.email 
             WHERE c.XDFree01_KeyID = xdfree01.KeyId 
             ORDER BY c.Date DESC LIMIT 1) as LastCommentUser
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LEFT JOIN users u ON info_xdfree01_extrafields.Atribuido = u.id
        LEFT JOIN online_entity_extrafields online on info_xdfree01_extrafields.CreationUser = online.email
        WHERE (info_xdfree01_extrafields.Atribuido IS NOT NULL AND info_xdfree01_extrafields.Atribuido <> '') 
        AND info_xdfree01_extrafields.Status <> 'Concluído'";

// Filter tickets based on user permissions
// Both admin and comum users should only see their own assigned tickets
$sql .= " AND info_xdfree01_extrafields.Atribuido = :current_user_id";
$params[':current_user_id'] = $admin_id;

// Adicionar filtros adicionais
if (!empty($estado_filtro)) {
    $sql .= " AND info_xdfree01_extrafields.Status = :estado_filtro";
    $params[':estado_filtro'] = $estado_filtro;
}

if (!empty($prioridade_filtro)) {
    $sql .= " AND info_xdfree01_extrafields.Priority = :prioridade_filtro";
    $params[':prioridade_filtro'] = $prioridade_filtro;
}

if (!empty($criador_filtro)) {
    $sql .= " AND info_xdfree01_extrafields.CreationUser = :criador_filtro";
    $params[':criador_filtro'] = $criador_filtro;
}

if (!empty($data_inicio)) {
    $sql .= " AND DATE(info_xdfree01_extrafields.dateu) >= :data_inicio";
    $params[':data_inicio'] = $data_inicio;
}

if (!empty($data_fim)) {
    $sql .= " AND DATE(info_xdfree01_extrafields.dateu) <= :data_fim";
    $params[':data_fim'] = $data_fim;
}

$sql .= " ORDER BY info_xdfree01_extrafields.dateu DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter listas para os dropdowns de filtro
$sql_criadores = "SELECT DISTINCT online.email, online.name 
                  FROM info_xdfree01_extrafields info 
                  LEFT JOIN online_entity_extrafields online ON info.CreationUser = online.email 
                  WHERE info.Atribuido = :current_user_id 
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
                    <h1 class="mb-3 display-5"> Os Meus Tickets
                    </h1>
                    <p class="">
                        Aqui pode ver os tickets que estão atribuídos a si, juntamente com o seu estado, prioridade e outras informações relevantes. Utilize os filtros para encontrar tickets específicos.</p>
                    </p>
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
                                <option value="Em Análise" <?php echo $estado_filtro == 'Em Análise' ? 'selected' : ''; ?>>Em Análise</option>
                                <option value="Em Resolução" <?php echo $estado_filtro == 'Em Resolução' ? 'selected' : ''; ?>>Em Resolução</option>
                                <option value="Aguarda Resposta" <?php echo $estado_filtro == 'Aguarda Resposta' ? 'selected' : ''; ?>>Aguarda Resposta</option>
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
                        
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-dark w-100">
                                <i class="bi bi-funnel me-1"></i>Filtrar
                            </button>
                        </div>
                        
                        <div class="col-md-1 d-flex align-items-end">
                            <a href="tickets_atribuidos.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-x-circle me-1"></i>Limpar
                            </a>
                        </div>
                    </form>
                        
                    <!-- Table -->
                    <div class="table-responsive pb-5">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="sortable text-nowrap">Título</th>
                                    <th scope="col" class="sortable text-nowrap">Atualizado</th>
                                    <th scope="col" class="sortable text-nowrap">Criado</th>
                                    <th scope="col" class="sortable text-nowrap">Estado</th>
                                    <th scope="col" class="sortable text-nowrap">Prioridade</th>
                                    <th scope="col" class="sortable text-nowrap">Criador</th>
                                    <th scope="col" class="sortable text-nowrap">Atribuído a</th>
                                    <th scope="col" class="sortable text-nowrap">Última Mensagem Por</th>
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
                                            <td><?php echo !empty($ticket['atribuido_a']) ? htmlspecialchars($ticket['atribuido_a']) : '-'; ?></td>
                                            <td><?php echo !empty($ticket['LastCommentUser']) ? htmlspecialchars($ticket['LastCommentUser']) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="bi bi-info-circle me-2"></i> Não há tickets atribuídos no momento.
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
        // Time adjustment functions for modal
        window.adjustTimeModal = function(minutesToAdd) {
            const timeInput = document.getElementById('tempo_resolucao');
            const timeDisplay = document.getElementById('timeDisplayModal');
            const removeBtn = document.getElementById('removeTimeBtnModal');
            
            let currentTime = parseInt(timeInput.value) || 15;
            let newTime = currentTime + minutesToAdd;
            
            // Ensure minimum time is 15 minutes
            if (newTime < 15) {
                newTime = 15;
            }
            
            // Update hidden input
            timeInput.value = newTime;
            
            // Update display
            updateTimeDisplayModal(newTime);
            
            // Enable/disable remove button based on minimum
            if (removeBtn) {
                removeBtn.disabled = (newTime <= 15);
                if (newTime <= 15) {
                    removeBtn.classList.add('disabled');
                } else {
                    removeBtn.classList.remove('disabled');
                }
            }
        };
        
        window.updateTimeDisplayModal = function(timeInMinutes) {
            const timeDisplay = document.getElementById('timeDisplayModal');
            if (!timeDisplay) return;
            
            const hours = Math.floor(timeInMinutes / 60);
            const minutes = timeInMinutes % 60;
            
            let displayText = '';
            if (hours > 0) {
                displayText = hours + 'h';
                if (minutes > 0) {
                    displayText += ' ' + minutes + 'min';
                }
            } else {
                displayText = minutes + ' minuto' + (minutes !== 1 ? 's' : '');
            }
            
            timeDisplay.textContent = displayText;
        };

        // Close ticket modal functionality
        const closeTicketModal = document.getElementById('closeTicketModal');
        closeTicketModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const ticketId = button.getAttribute('data-ticket-id');
            const ticketTitle = button.getAttribute('data-ticket-title');
            
            document.getElementById('ticket_id').value = ticketId;
            document.getElementById('ticketTitle').textContent = ticketTitle;
            
            // Reset time to 15 minutes when modal opens
            document.getElementById('tempo_resolucao').value = 15;
            updateTimeDisplayModal(15);
            const removeBtn = document.getElementById('removeTimeBtnModal');
            if (removeBtn) {
                removeBtn.disabled = true;
                removeBtn.classList.add('disabled');
            }
        });

        // Form validation
        document.getElementById('closeTicketForm').addEventListener('submit', function(e) {
            const descricao = document.getElementById('resolucao_descricao').value.trim();
            const tempo = document.getElementById('tempo_resolucao').value;
            
            if (descricao.length < 5) {
                e.preventDefault();
                alert('Insira uma descrição da resolução.');
                return;
            }
            
            if (!tempo || tempo < 15) {
                e.preventDefault();
                alert('O tempo mínimo de resolução é de 15 minutos.');
                return;
            }
            
            // Debug - log the values being sent
            console.log('Ticket ID:', document.getElementById('ticket_id').value);
            console.log('Descrição:', descricao);
            console.log('Tempo:', tempo);
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
    });
    </script>
</body>
</html>