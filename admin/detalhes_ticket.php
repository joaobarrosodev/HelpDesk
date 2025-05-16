<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');

// Verificar se o 'KeyId' do ticket foi passado pela URL
if (isset($_GET['keyid'])) {
    $keyid = $_GET['keyid'];

    // Remover o símbolo '#' caso ele exista (se o banco não usa o '#')
    $keyid_sem_hash = str_replace('#', '', $keyid);

    // Consultar os detalhes do ticket
    $sql = "SELECT free.KeyId, free.id, free.Name, info.Description, info.Priority, info.Status, info.CreationUser, info.CreationDate, info.dateu, info.image, internal.User, internal.Time, internal.Description as Descr, internal.info
            FROM xdfree01 free
            LEFT JOIN info_xdfree01_extrafields info ON free.KeyId = info.XDFree01_KeyID
            LEFT JOIN internal_xdfree01_extrafields internal on free.KeyId = internal.XDFree01_KeyID
            WHERE free.id = :keyid";  // Comparar sem o #

    // Preparar a consulta
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':keyid', $keyid);
    $stmt->execute();
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo "Ticket não encontrado.";
        exit;
    }

    $ticket_id = $ticket['KeyId'];


    // Consultar todas as mensagens associadas ao ticket
    $sql_messages = "SELECT comments.Message, comments.type, comments.Date as CommentTime, comments.user
                     FROM comments_xdfree01_extrafields comments
                     WHERE comments.XDFree01_KeyID = :keyid
                     ORDER BY comments.Date ASC";  // Ordenar pela data

    $stmt_messages = $pdo->prepare($sql_messages);
    $stmt_messages->bindParam(':keyid', $ticket_id);
    $stmt_messages->execute();
    $messages = $stmt_messages->fetchAll(PDO::FETCH_ASSOC);

} else {
    echo "Ticket não especificado.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<!-- Modal para Exibir Imagem -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imageModalLabel">Imagem do Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img id="modalImage" src="" class="img-fluid" alt="Imagem do Ticket">
      </div>
    </div>
  </div>
</div>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <table class="table">
            <tr>
                <td class="bg-secondary text-white"><h5><?php echo "Detalhes do " . $ticket['Name']; ?></h5></td>
                <td class="bg-secondary text-white"><h5><?php echo "Estado atual: " . $ticket['Status']; ?></h5></td>
            </tr>
            <tr>
                <th>Descrição</th>
                <td><?php echo $ticket['Description']; ?></td>
            </tr>
            <tr>
                <th>Prioridade</th>
                <td><?php echo $ticket['Priority']; ?></td>
            </tr>
            <tr>
                <th>Criador Ticket</th>
                <td><?php echo $ticket['CreationUser']; ?></td>
            </tr>
            <tr>
                <th>Data de Criação</th>
                <td><?php echo $ticket['CreationDate']; ?></td>
            </tr>
            <tr>
                <th>Última Atualização</th>
                <td><?php echo $ticket['dateu']; ?></td>
            </tr>
                        <tr>
                <th>Imagem do Problema</th>
                <td>
                <?php if (!empty($ticket['image'])) { ?>
    <div>
        <img src="<?php echo $ticket['image']; ?>" alt="Imagem do Ticket" class="img-thumbnail" style="max-width: 150px; cursor: pointer;" onclick="showImage('<?php echo $ticket['image']; ?>')">
    </div>
<?php } ?> 
                </td>
            </tr>
            <tr class="bg-secondary text-white">
                <td class="bg-secondary text-white"><h5>Detalhes da Resolução</h5></td>
                <td class="bg-secondary text-white"></td>
            </tr>    
            <tr>
                <th>Colaborador</th>
                <td><?php echo $ticket['User']; ?></td>
            </tr>
            <tr>
                <th>Tempo dispendido</th>
                <td><?php echo $ticket['Time']; ?></td>
            </tr>
            <tr>
                <th>Detalhes</th>
                <td><?php echo $ticket['Descr']; ?></td>
            </tr>
            <tr>
                <th>Informações Extra</th>
                <td><?php echo $ticket['info']; ?></td>
            </tr>
        </table>


        <!-- Exibir todas as mensagens do ticket -->
        <h5>Comentários acerca do Ticket:</h5>
<?php
if ($messages) {
    // Iterar sobre as mensagens e exibi-las
    foreach ($messages as $message) {
        // Definir a cor da borda com base no valor de $message['tipo']
        $borderColor = ($message['type'] == 1) ? 'orange' : 'blue';
        $aligntext = ($message['type'] == 1) ? 'right' : 'left';
        // Exibir a mensagem com a borda colorida
        echo "<div class='card mb-3 bg-light' style='border-left: 5px solid $borderColor;'>";
        echo "<div class='card-body'>";
        echo "<p class='card-text text-muted' style='text-align: $aligntext;'>" . nl2br($message['Message']) . "</p>";
        echo "<small class='text-muted float-end bg-secondary text-white px-2 py-1 rounded'>" . date('d/m/Y H:i', strtotime($message['CommentTime'])) . "</small>";
        echo "<small class='text-muted bg-info text-white px-2 py-1 rounded'>" . $message['user'] . "</small>";
        echo "</div>";
        echo "</div>";
    }
} else {
    echo "<p>Não há mensagens para este ticket.</p>";
}
?>

        <!-- Verificar se o estado do ticket é "Fechado" antes de exibir o formulário -->
<?php if ($ticket['Status'] !== 'Concluído') { ?>
    <!-- Formulário para Enviar Nova Mensagem -->
    <form method="POST" action="inserir_mensagem.php">
        <input type="hidden" name="keyid" value="<?php echo $ticket['KeyId']; ?>">
        <input type="hidden" name="id" value="<?php echo $ticket['id']; ?>"> 
        <div class="form-group">
            <label for="message"><h5>Enviar nova mensagem:</h5></label>
            <textarea name="message" class="form-control" placeholder="Escreva aqui a sua Mensagem..." required></textarea><br>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Enviar Mensagem</button>
    </form>
<?php } else { ?>
    <!-- Caso o estado seja "Fechado", exibe uma mensagem informando -->
    <p class="text-muted">Este ticket está fechado. Não é possível enviar novas mensagens.</p>
<?php } ?>
        <td>
    <a href="alterar_tickets.php?keyid=<?php echo $ticket['id']; ?>" class="btn btn-warning mt-3">
        Finalizar/Alterar Ticket
    </a>
</td>
        <a href="consultar_tickets.php" class="btn btn-secondary mt-3">Voltar para meus tickets</a>
    </div>

    <!-- Inclusão do JS do Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
function showImage(src) {
    document.getElementById('modalImage').src = src;
    var myModal = new bootstrap.Modal(document.getElementById('imageModal'));
    myModal.show();
}
</script>
</body>
</html>
