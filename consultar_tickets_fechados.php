<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');

// Consultar os tickets do usuário logado
$usuario_id = $_SESSION['usuario_id'];

// Recupera o filtro de estado, se existir
$estado_filtro = isset($_GET['status']) ? $_GET['status'] : '';

// Verifica o grupo do usuário para determinar a consulta SQL
if ($_SESSION['Grupo'] == 'Admin') {
    // Prepara a SQL para admin
    $sql = "SELECT xdfree01.KeyId, xdfree01.id, xdfree01.Name, info_xdfree01_extrafields.User, info_xdfree01_extrafields.Description, info_xdfree01_extrafields.Priority, info_xdfree01_extrafields.Status, info_xdfree01_extrafields.CreationDate, info_xdfree01_extrafields.dateu, online.name as CreationUser,
            (SELECT user FROM comments_xdfree01_extrafields WHERE XDFree01_KeyID = xdfree01.KeyId ORDER BY Date DESC LIMIT 1) as LastCommentUser
            FROM xdfree01 
            JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
            LEFT JOIN online_entity_extrafields online on info_xdfree01_extrafields.CreationUser = online.email
            WHERE info_xdfree01_extrafields.Entity = :usuario_id and info_xdfree01_extrafields.Status = 'Concluído'";
    if ($estado_filtro != '') {
        $sql .= " AND info_xdfree01_extrafields.Status = :estado_filtro";
    }
    $sql .= " ORDER BY xdfree01.KeyId asc";
} else {
    // Prepara a SQL para usuários normais
    $sql = "SELECT xdfree01.KeyId, xdfree01.id, xdfree01.Name, info_xdfree01_extrafields.Description, info_xdfree01_extrafields.Priority, info_xdfree01_extrafields.Status, info_xdfree01_extrafields.CreationDate, info_xdfree01_extrafields.dateu, online.name as CreationUser,
            (SELECT user FROM comments_xdfree01_extrafields WHERE XDFree01_KeyID = xdfree01.KeyId ORDER BY Date DESC LIMIT 1) as LastCommentUser
            FROM xdfree01 
            JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
            LEFT JOIN online_entity_extrafields online on info_xdfree01_extrafields.CreationUser = online.email
            WHERE info_xdfree01_extrafields.Entity = :usuario_id AND online.email = :usuario_email";
    if ($estado_filtro != '') {
        $sql .= " AND info_xdfree01_extrafields.Status = :estado_filtro";
    }
    $sql .= " ORDER BY xdfree01.KeyId asc";
}

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id);
if ($_SESSION['Grupo'] != 'Admin') {
    $stmt->bindParam(':usuario_email', $_SESSION['usuario_email']);
}

if ($estado_filtro != '') {
    $stmt->bindParam(':estado_filtro', $estado_filtro);
}

$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <h2>Meus Tickets</h2>

        <!-- Filtro de estado -->
        <form method="get" action="">
            <div class="mb-3">
                <label for="status" class="form-label">Filtrar por Estado</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="Em Análise" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Em Análise') ? 'selected' : ''; ?>>Em Análise</option>
                    <option value="Em Resolução" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Em Resolução') ? 'selected' : ''; ?>>Em Resolução</option>
                    <option value="Aguarda Resposta Cliente" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Aguarda Resposta Cliente') ? 'selected' : ''; ?>>Aguarda Resposta Cliente</option>
                    <option value="Concluído" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Concluído') ? 'selected' : ''; ?>>Concluído</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary mb-3">Filtrar</button>
        </form>

        <?php if (count($tickets) > 0): ?>
            <table class="table table-striped" id="tabelaTickets">
                <thead>
                    <tr>
                        <th scope="col" class="sortable" data-column="KeyId">Código <i class="bi bi-arrow-up"></i><i class="bi bi-arrow-down"></i></th>
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
                            <td>
                                <a href="detalhes_ticket.php?keyid=<?php echo $ticket['id']; ?>" class="text-decoration-none text-dark d-flex align-items-center">
                                    <i class="bi bi-arrow-right-circle me-2"></i> 
                                    <?php echo $ticket['KeyId']; ?>
                                </a>
                            </td>

                            <td><?php echo $ticket['Name']; ?></td>
                            <td><?php echo $ticket['User']; ?></td>
                            <td>
                                <?php 
                                $priority = $ticket['Priority'];
                                $priorityClass = '';
                                if ($priority == 'Baixa') {
                                    $priorityClass = 'badge bg-success'; // Verde
                                } elseif ($priority == 'Normal') {
                                    $priorityClass = 'badge bg-warning'; // Amarelo
                                } elseif ($priority == 'Alta') {
                                    $priorityClass = 'badge bg-danger'; // Vermelho
                                } else {
                                    $priorityClass = 'badge bg-dark'; // Preto para qualquer outro valor
                                }
                                ?>
                                <span class="<?php echo $priorityClass; ?>"><?php echo $priority; ?></span>
                            </td>
                            <td>
                                <?php 
                                $status = $ticket['Status'];
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
                            </td>
                            <td><?php echo $ticket['CreationUser']; ?></td>                            
                            <td><?php echo $ticket['CreationDate']; ?></td>
                            <td><?php echo $ticket['dateu']; ?></td>
                            <td><?php echo $ticket['LastCommentUser'] ? $ticket['LastCommentUser'] : 'Nenhum comentário'; ?></td> <!-- Exibe o último comentarista -->
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="alert alert-warning">Você não tem tickets registrados.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('tabelaTickets');
            const headers = table.querySelectorAll('.sortable');
            
            headers.forEach(function(header) {
                header.addEventListener('click', function() {
                    const column = header.getAttribute('data-column');
                    const rows = Array.from(table.querySelectorAll('tbody tr'));
                    const index = Array.from(header.parentNode.children).indexOf(header);
                    const isAscending = header.classList.contains('asc');

                    rows.sort(function(rowA, rowB) {
                        const cellA = rowA.cells[index].textContent.trim();
                        const cellB = rowB.cells[index].textContent.trim();

                        if (column === 'Priority' || column === 'Status') {
                            return isAscending
                                ? cellA.localeCompare(cellB)
                                : cellB.localeCompare(cellA);
                        } else {
                            return isAscending
                                ? cellA.localeCompare(cellB)
                                : cellB.localeCompare(cellA);
                        }
                    });

                    rows.forEach(function(row) {
                        table.querySelector('tbody').appendChild(row);
                    });

                    headers.forEach(function(header) {
                        header.classList.remove('asc', 'desc');
                    });

                    header.classList.toggle(isAscending ? 'desc' : 'asc');
                });
            });
        });
    </script>
</body>
</html>
