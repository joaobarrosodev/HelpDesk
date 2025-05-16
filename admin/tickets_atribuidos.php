<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');
$admin_id = $_SESSION['admin_id'];
// Recupera o filtro de estado, se existir
$estado_filtro = isset($_GET['status']) ? $_GET['status'] : '';
// Prepara a SQL para admin
$sql = "SELECT xdfree01.KeyId, xdfree01.id, xdfree01.Name, info_xdfree01_extrafields.User, 
        info_xdfree01_extrafields.Description, info_xdfree01_extrafields.Priority, 
        info_xdfree01_extrafields.Status, info_xdfree01_extrafields.CreationDate, 
        info_xdfree01_extrafields.dateu, online.name as CreationUser,
        (SELECT user FROM comments_xdfree01_extrafields WHERE XDFree01_KeyID = xdfree01.KeyId 
        ORDER BY Date DESC LIMIT 1) as LastCommentUser
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LEFT JOIN online_entity_extrafields online on info_xdfree01_extrafields.CreationUser = online.email
        WHERE info_xdfree01_extrafields.AttUser = :AttUser AND info_xdfree01_extrafields.Status <> 'Concluído'";
$params[':AttUser'] = $admin_id;
if (!empty($estado_filtro)) {
    $sql .= " AND info_xdfree01_extrafields.Status = :estado_filtro";
    $params[':estado_filtro'] = $estado_filtro;
}

$sql .= " ORDER BY xdfree01.KeyId ASC";
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
        <h2>Meus Tickets</h2>

        <form method="get" action="">
            <div class="mb-3">
                <label for="status" class="form-label">Filtrar por Estado</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="Em Análise" <?php echo ($estado_filtro == 'Em Análise') ? 'selected' : ''; ?>>Em Análise</option>
                    <option value="Em Resolução" <?php echo ($estado_filtro == 'Em Resolução') ? 'selected' : ''; ?>>Em Resolução</option>
                    <option value="Aguarda Resposta Cliente" <?php echo ($estado_filtro == 'Aguarda Resposta Cliente') ? 'selected' : ''; ?>>Aguarda Resposta Cliente</option>
                    <option value="Concluído" <?php echo ($estado_filtro == 'Concluído') ? 'selected' : ''; ?>>Concluído</option>
                </select>
            </div>
            <button type="submit" class="btn btn-dark mb-3">Filtrar</button>
        </form>

        <?php if (count($tickets) > 0): ?>
            <table class="table table-striped" id="tabelaTickets">
                <thead>
                    <tr>
                        <th scope="col" class="sortable" data-column="KeyId">Código</th>
                        <th scope="col">Título</th>
                        <th scope="col">Assunto</th>
                        <th scope="col">Prioridade</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Criador Ticket</th>
                        <th scope="col">Data Criação</th>
                        <th scope="col">Última Atualização</th>
                        <th scope="col">Último Comentário</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><a href="detalhes_ticket.php?keyid=<?php echo $ticket['id']; ?>"> <?php echo $ticket['KeyId']; ?> </a></td>
                            <td><?php echo $ticket['Name']; ?></td>
                            <td><?php echo $ticket['User']; ?></td>
                            <td><span class="badge bg-<?php echo ($ticket['Priority'] == 'Alta') ? 'danger' : (($ticket['Priority'] == 'Normal') ? 'warning' : 'success'); ?>"> <?php echo $ticket['Priority']; ?> </span></td>
                            <td><span class="badge bg-<?php echo ($ticket['Status'] == 'Em Análise') ? 'info' : (($ticket['Status'] == 'Em Resolução') ? 'warning' : (($ticket['Status'] == 'Aguarda Resposta Cliente') ? 'secondary' : 'success')); ?>"> <?php echo $ticket['Status']; ?> </span></td>
                            <td><?php echo $ticket['CreationUser']; ?></td>
                            <td><?php echo $ticket['CreationDate']; ?></td>
                            <td><?php echo $ticket['dateu']; ?></td>
                            <td><?php echo $ticket['LastCommentUser'] ?? 'Nenhum comentário'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="alert alert-warning">Você não tem tickets registrados.</p>
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
