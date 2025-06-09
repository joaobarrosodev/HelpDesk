<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');
$admin_id = $_SESSION['admin_id'];

// Recupera o filtro de estado, se existir
$estado_filtro = isset($_GET['status']) ? $_GET['status'] : '';
$params = [];

// Get current user's assigned user ID for restricted admins
$current_user_id = null;
if (isComum()) {  // CHANGED: was isRestrictedAdmin()
    $user_sql = "SELECT id FROM users WHERE email = :admin_email";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->bindParam(':admin_email', $_SESSION['admin_email']);
    $user_stmt->execute();
    $user_result = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $current_user_id = $user_result['id'] ?? null;
}

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

// Add restriction for restricted admins
if (isComum()) {  // CHANGED: was isRestrictedAdmin()
    $sql .= " AND info_xdfree01_extrafields.Atribuido = :current_user_id";
    $params[':current_user_id'] = $current_user_id;
}

if (!empty($estado_filtro)) {
    $sql .= " AND info_xdfree01_extrafields.Status = :estado_filtro";
    $params[':estado_filtro'] = $estado_filtro;
}

$sql .= " ORDER BY info_xdfree01_extrafields.dateu DESC";
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
                    <h1 class="mb-3 display-5">
                        <?php echo isAdmin() ? 'Tickets Atribuídos' : 'Os Meus Tickets'; ?>
                    </h1>
                    <p class="">
                        <?php echo isAdmin() ? 'Lista de tickets em andamento que já possuem um responsável designado.' : 'Lista dos tickets que tem atribuídos a si.'; ?>
                    </p>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <!-- Filters -->
                    <form method="get" action="" class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="Em Análise" <?php echo $estado_filtro == 'Em Análise' ? 'selected' : ''; ?>>Em Análise</option>
                                <option value="Em Resolução" <?php echo $estado_filtro == 'Em Resolução' ? 'selected' : ''; ?>>Em Resolução</option>
                                <option value="Aguarda Resposta" <?php echo $estado_filtro == 'Aguarda Resposta' ? 'selected' : ''; ?>>Aguarda Resposta</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-dark w-100">Filtrar</button>
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
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $ticket['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-gear"></i>
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $ticket['id']; ?>">
                                                        <li><a class="dropdown-item" href="detalhes_ticket.php?keyid=<?php echo $ticket['id']; ?>"><i class="bi bi-eye me-2"></i> Ver detalhes</a></li>
                                                        <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#closeTicketModal" data-ticket-id="<?php echo $ticket['KeyId']; ?>" data-ticket-title="<?php echo htmlspecialchars($ticket['titulo_do_ticket']); ?>"><i class="bi bi-x-circle me-2"></i> Fechar ticket</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
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

    <!-- Close Ticket Modal -->
    <div class="modal fade" id="closeTicketModal" tabindex="-1" aria-labelledby="closeTicketModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="closeTicketModalLabel">
                        <i class="bi bi-x-circle me-2"></i>Fechar Ticket
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="closeTicketForm" method="post" action="processar_fechar_ticket.php">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Atenção:</strong> Esta ação irá marcar o ticket como concluído.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>Ticket:</strong></label>
                            <p id="ticketTitle" class="form-control-plaintext bg-light p-2 rounded"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="resolucao_descricao" class="form-label">
                                Descrição da Resolução <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="resolucao_descricao" name="resolucao_descricao" rows="4" placeholder="Como foi resolvido..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tempo_resolucao" class="form-label">
                                Tempo de Resolução <span class="text-danger">*</span>
                            </label>
                            <input type="hidden" id="tempo_resolucao" name="tempo_resolucao" value="15">
                            
                            <div class="time-control-container bg-light border rounded p-3">
                                <div class="time-display text-center mb-3">
                                    <span class="badge bg-primary fs-6 px-3 py-2" id="timeDisplayModal">15 minutos</span>
                                </div>
                                
                                <div class="time-buttons d-flex gap-2 justify-content-center flex-wrap">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="adjustTimeModal(-15)" id="removeTimeBtnModal" disabled>
                                        - 15min
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="adjustTimeModal(15)">
                                        + 15min
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="adjustTimeModal(30)">
                                        + 30min
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="adjustTimeModal(60)">
                                        + 1h
                                    </button>
                                </div>
                            </div>
                            
                            <small class="text-muted mt-2 d-block">Tempo mínimo: 15 minutos. Use os botões para ajustar.</small>
                        </div>
                        
                        <input type="hidden" id="ticket_id" name="ticket_id" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-check-circle me-1"></i>Fechar Ticket
                        </button>
                    </div>
                </form>
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