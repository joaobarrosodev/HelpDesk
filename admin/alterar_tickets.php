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
            </tr>
</table>


<form action="processar_alteracao.php" method="POST">
	<input type="hidden" name="keyid" value="<?php echo htmlspecialchars($keyid); ?>">
	 <div class="mb-3">
                <label for="status" class="form-label">Estado</label>
                <select id="status" name="status" class="form-select">
                    <option value="Em Análise" <?php echo ($ticket['Status'] == 'Em Análise') ? 'selected' : ''; ?>>Em Análise</option>
                    <option value="Em Resolução" <?php echo ($ticket['Status'] == 'Em Resolução') ? 'selected' : ''; ?>>Em Resolução</option>
                    <option value="Aguarda Resposta Cliente" <?php echo ($ticket['Status'] == 'Aguarda Resposta Cliente') ? 'selected' : ''; ?>>Aguarda Resposta Cliente</option>
                    <option value="Concluído" <?php echo ($ticket['Status'] == 'Concluído') ? 'selected' : ''; ?>>Concluído</option>
                </select>
            </div>


           <!-- Tempo de Resolução -->
<div class="mb-3">
    <label for="resolution_time" class="form-label">Tempo de Resolução (HH:MM)</label>
    <input type="text" id="resolution_time" name="resolution_time" class="form-control" pattern="^([0-9]{1,2}):([0-5][0-9])$" placeholder="Ex: 01:30" value="<?php echo htmlspecialchars($ticket['ResolutionTime'] ?? ''); ?>" required>
    <small class="text-muted">Formato: HH:MM (exemplo: 1:30 para 1 hora e 30 minutos)</small>
</div>

<!-- Descrição da Resolução -->
            <div class="mb-3">
                <label for="resolution_description" class="form-label">Descrição da Resolução</label>
                <textarea id="resolution_description" name="resolution_description" class="form-control" rows="3"><?php echo htmlspecialchars($ticket['ResolutionDescription'] ?? ''); ?></textarea>
            </div>

            <!-- Informação Extra -->
            <div class="mb-3">
                <label for="extra_info" class="form-label">Informação Extra</label>
                <textarea id="extra_info" name="extra_info" class="form-control" rows="3"><?php echo htmlspecialchars($ticket['ExtraInfo'] ?? ''); ?></textarea>
            </div>

            <!-- Botões -->
            <button type="submit" class="btn btn-success">Guardar Alterações</button>
            <a href="meus_tickets.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html>

