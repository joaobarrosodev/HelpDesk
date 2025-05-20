<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');

// Prepara a SQL para tickets fechados (concluídos)
$sql = "SELECT xdfree01.KeyId, xdfree01.id, xdfree01.Name, info_xdfree01_extrafields.User, 
        info_xdfree01_extrafields.Description, info_xdfree01_extrafields.Priority, 
        info_xdfree01_extrafields.Status, info_xdfree01_extrafields.CreationDate, 
        info_xdfree01_extrafields.dateu, online.name as CreationUser,
        (SELECT user FROM comments_xdfree01_extrafields WHERE XDFree01_KeyID = xdfree01.KeyId 
        ORDER BY Date DESC LIMIT 1) as LastCommentUser,
        internal_xdfree01_extrafields.Time as ResolutionTime
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LEFT JOIN online_entity_extrafields online on info_xdfree01_extrafields.CreationUser = online.email
        LEFT JOIN internal_xdfree01_extrafields on xdfree01.KeyId = internal_xdfree01_extrafields.XDFree01_KeyID
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
        <h2>Tickets Fechados</h2>

        <?php if (count($tickets) > 0): ?>
            <table class="table table-striped" id="tabelaTickets">
                <thead>
                    <tr>
                        <th scope="col" class="sortable" data-column="KeyId">Código</th>
                        <th scope="col">Título</th>
                        <th scope="col">Assunto</th>
                        <th scope="col">Prioridade</th>
                        <th scope="col">Tempo de Resolução</th>
                        <th scope="col">Criador Ticket</th>
                        <th scope="col">Data Criação</th>
                        <th scope="col">Data Conclusão</th>
                        <th scope="col">Último Comentário</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><a href="detalhes_ticket.php?keyid=<?php echo $ticket['id']; ?>"><?php echo $ticket['KeyId']; ?></a></td>
                            <td><?php echo $ticket['Name']; ?></td>
                            <td><?php echo $ticket['User']; ?></td>
                            <td><span class="badge bg-<?php echo ($ticket['Priority'] == 'Alta') ? 'danger' : (($ticket['Priority'] == 'Normal') ? 'warning' : 'success'); ?>"><?php echo $ticket['Priority']; ?></span></td>
                            <td><?php echo $ticket['ResolutionTime'] ?? 'N/A'; ?></td>
                            <td><?php echo $ticket['CreationUser']; ?></td>
                            <td><?php echo $ticket['CreationDate']; ?></td>
                            <td><?php echo $ticket['dateu']; ?></td>
                            <td><?php echo $ticket['LastCommentUser'] ?? 'Nenhum comentário'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="alert alert-warning">Não há tickets concluídos registrados.</p>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('tabelaTickets');
            const headers = table.querySelectorAll('.sortable');
            headers.forEach(header => {
                header.addEventListener('click', function() {
                    const column = header.getAttribute('data-column');
                    const rows = Array.from(table.querySelectorAll('tbody tr'));
                    const index = Array.from(header.parentNode.children).indexOf(header);
                    const isAscending = header.classList.contains('asc');

                    rows.sort((rowA, rowB) => {
                        const cellA = rowA.cells[index].textContent.trim();
                        const cellB = rowB.cells[index].textContent.trim();
                        return isAscending ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
                    });

                    rows.forEach(row => table.querySelector('tbody').appendChild(row));
                    headers.forEach(header => header.classList.remove('asc', 'desc'));
                    header.classList.toggle(isAscending ? 'desc' : 'asc');
                });
            });
        });
    </script>
</body>
</html>
