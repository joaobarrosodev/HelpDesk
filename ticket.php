<?php
session_start();  // Inicia a sessão
include('conflogin.php');
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<head>
    <!-- Importando Dropzone.js para o Drag & Drop -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css">
</head>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <h2>Criar Ticket de Suporte</h2>
        <form action="criar_ticket.php" method="POST" enctype="multipart/form-data">
            <!-- Nome do Computador/Utilizador -->
            <div class="mb-3">
                <label for="nome_computador" class="form-label fw-bold">Assunto: </label>
                <input type="text" class="form-control" id="nome_computador" name="nome_computador" required>
            </div>

            <!-- Descrição do problema -->
            <div class="mb-3">
                <label for="descricao_problema" class="form-label fw-bold ">Descrição do Problema:</label>
                <textarea class="form-control" id="descricao_problema" name="descricao_problema" rows="4" required></textarea>
            </div>

            <!-- Prioridade -->
            <div class="mb-3">
                <label for="prioridade" class="form-label fw-bold ">Prioridade:</label>
                <select class="form-select" id="prioridade" name="prioridade" required>
                    <option value="Baixa">Baixa</option>
                    <option value="Normal">Normal</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>

            <!-- Upload de Imagem (Drag & Drop) -->
            <div class="mb-3">
                <label class="form-label fw-bold ">Anexar Imagem:</label>
                <div id="dropzone" class="dropzone"></div>
                <input type="hidden" name="imagem" id="imagem_path">
            </div>

            <!-- Botão de envio -->
            <button type="submit" class="btn btn-primary">Criar Ticket</button>
        </form>
    </div>

    <!-- Inclusão do JS do Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Dropzone.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>
    <script>
        Dropzone.autoDiscover = false;
        var dropzone = new Dropzone("#dropzone", {
            url: "upload_imagem.php",  // Script para processar o upload
            maxFiles: 1,
            acceptedFiles: "image/*",
            addRemoveLinks: true,
            success: function(file, response) {
                document.getElementById("imagem_path").value = response.caminho; // Armazena o caminho no input oculto
            }
        });
    </script>
</body>
</html>
