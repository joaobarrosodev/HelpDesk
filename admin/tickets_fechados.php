<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');

// Prepara a SQL para tickets fechados (concluídos)
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.id, 
            xdfree01.Name as titulo_do_ticket, 
            info_xdfree01_extrafields.Atribuido as User, 
            info_xdfree01_extrafields.Relatorio as Description, 
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
             ORDER BY c.Date DESC LIMIT 1) as LastCommentUser,
            info_xdfree01_extrafields.Tempo as ResolutionTime,
            info_xdfree01_extrafields.Relatorio as ResolutionDescription
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LEFT JOIN users u ON info_xdfree01_extrafields.Atribuido = u.id
        LEFT JOIN online_entity_extrafields online ON info_xdfree01_extrafields.CreationUser = online.email
        WHERE info_xdfree01_extrafields.Status = 'Concluído'";

$sql .= " ORDER BY info_xdfree01_extrafields.dateu DESC"; // Ordenar pelo mais recente
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <h1 class="mb-3 display-5">Tickets Fechados</h1>
                    <p class="">Lista de todos os tickets concluídos, com tempo de resolução e informações de conclusão.</p>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="sortable text-nowrap">Título</th>
                                    <th scope="col" class="sortable text-nowrap">Atualizado</th>
                                    <th scope="col" class="sortable text-nowrap">Criado</th>
                                    <th scope="col" class="sortable text-nowrap">Prioridade</th>
                                    <th scope="col" class="sortable text-nowrap">Tempo (min)</th>
                                    <th scope="col" class="sortable text-nowrap">Atribuído a</th>
                                    <th scope="col" class="sortable text-nowrap">Criador</th>
                                    <th scope="col" class="text-nowrap">Ações</th>
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
                                            <td><?php echo $ticket['atualizado']; ?></td>
                                            <td><?php echo $ticket['criado']; ?></td>
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
                                            <td><?php echo !empty($ticket['atribuido_a']) ? htmlspecialchars($ticket['atribuido_a']) : '-'; ?></td>
                                            <td><?php echo $ticket['CreationUser']; ?></td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $ticket['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-gear"></i>
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $ticket['id']; ?>">
                                                        <li><a class="dropdown-item" href="detalhes_ticket.php?keyid=<?php echo $ticket['id']; ?>"><i class="bi bi-eye me-2"></i> Ver detalhes</a></li>
                                                        <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalResolucao<?php echo $ticket['id']; ?>" href="#"><i class="bi bi-info-circle me-2"></i> Ver resolução</a></li>
                                                    </ul>
                                                </div>

                                                <!-- Modal de Resolução -->
                                                <div class="modal fade" id="modalResolucao<?php echo $ticket['id']; ?>" tabindex="-1" aria-labelledby="modalLabel<?php echo $ticket['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="modalLabel<?php echo $ticket['id']; ?>">Detalhes da Resolução</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <h6>Título do Ticket</h6>
                                                                    <p><?php echo htmlspecialchars($ticket['titulo_do_ticket']); ?></p>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <h6>Resolvido por</h6>
                                                                    <p><?php echo !empty($ticket['atribuido_a']) ? htmlspecialchars($ticket['atribuido_a']) : 'Não especificado'; ?></p>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <h6>Tempo de Resolução</h6>
                                                                    <p><?php echo !empty($ticket['ResolutionTime']) ? htmlspecialchars($ticket['ResolutionTime']) . ' minutos' : 'Não especificado'; ?></p>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <h6>Descrição da Resolução</h6>
                                                                    <p><?php echo !empty($ticket['ResolutionDescription']) ? nl2br(htmlspecialchars($ticket['ResolutionDescription'])) : 'Não especificado'; ?></p>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <h6>Data de Conclusão</h6>
                                                                    <p><?php echo $ticket['atualizado']; ?></p>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                                                <a href="detalhes_ticket.php?keyid=<?php echo $ticket['id']; ?>" class="btn btn-primary">Ver Ticket Completo</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
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
    });
    </script>
</body>
</html>
