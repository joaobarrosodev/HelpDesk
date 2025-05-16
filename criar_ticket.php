<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_computador = $_POST['nome_computador'];
    $descricao_problema = $_POST['descricao_problema'];
    $prioridade = $_POST['prioridade'];
    $usuario_id = $_SESSION['usuario_id'];
    $entidade_codigo = $_SESSION['usuario_email'];
    $imagem = $_POST['imagem']; // Caminho da imagem recebida
    try {
        $stmt = $pdo->query("SELECT KeyId FROM xdfree01 ORDER BY id DESC LIMIT 1");
        $ultimo_keyid = $stmt->fetchColumn();

        if ($ultimo_keyid) {
            $ultimo_numero = (int) substr($ultimo_keyid, 1);
            $novo_numero = $ultimo_numero + 1;
        } else {
            $novo_numero = 1;
        }

        $novo_keyid = "#" . str_pad($novo_numero, 3, "0", STR_PAD_LEFT);
        $titulo = "Ticket " . $novo_keyid; 

        $stmt = $pdo->prepare("INSERT INTO xdfree01 (KeyId, Name) VALUES (:keyId, :name)");
        $stmt->bindParam(':keyId', $novo_keyid);
        $stmt->bindParam(':name', $titulo);
        $stmt->execute();

        $keyId = $novo_keyid;

        $stmt = $pdo->prepare("INSERT INTO info_xdfree01_extrafields (XDFree01_KeyID, Entity, User, Description, Priority, Status, Image, CreationUser, CreationDate, dateu) 
                               VALUES (:keyId, :entity, :user, :description, :priority, :status, :image, :creationuser, NOW(), NOW())");
        $stmt->bindParam(':keyId', $keyId);
        $stmt->bindParam(':entity', $usuario_id);
        $stmt->bindParam(':user', $nome_computador);
        $stmt->bindParam(':description', $descricao_problema);
        $stmt->bindParam(':priority', $prioridade);
        $status = "Em Análise";
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':image', $imagem); // Salvar caminho da imagem
        $stmt->bindParam(':creationuser', $_SESSION['usuario_email']);

        $stmt->execute();

        echo "<p class='alert alert-success'>Ticket criado com sucesso! O seu KeyId: $novo_keyid</p>";
        header("Refresh: 2; url=index.php");
        exit;

    } catch (PDOException $e) {
        echo "<p class='alert alert-danger'>Erro ao criar ticket: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Ticket de Suporte</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css">
</head>
<body>
    <div class="container mt-5">
    <h2>Criar Pedido de Suporte</h2>
        <form action="criar_ticket.php" method="POST" enctype="multipart/form-data">
            <div class="mb-3">                <label for="nome_computador" class="form-label">Nome do Computador/Utilizador</label>
                <input type="text" class="form-control" id="nome_computador" name="nome_computador" required>
            </div>

            <div class="mb-3">                <label for="descricao_problema" class="form-label">Descrição do Problema</label>
                <textarea class="form-control" id="descricao_problema" name="descricao_problema" rows="4" required></textarea>
            </div>

            <div class="mb-3">
                <label for="prioridade" class="form-label">Prioridade</label>
                <select class="form-select" id="prioridade" name="prioridade" required>
                    <option value="Baixa">Baixa</option>
                    <option value="Normal">Normal</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>

            <!-- Upload de Imagem (Drag & Drop) -->
            <div class="mb-3">
                <label class="form-label">Anexar Imagem</label>
                <div id="dropzone" class="dropzone"></div>
                <input type="hidden" name="imagem" id="imagem_path">
            </div>

            <button type="submit" class="btn btn-success">Criar Ticket</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>
    <script>
        Dropzone.autoDiscover = false;
        var dropzone = new Dropzone("#dropzone", {
            url: "upload_imagem.php",
            maxFiles: 1,
            acceptedFiles: "image/*",
            addRemoveLinks: true,
            success: function(file, response) {
    response = JSON.parse(response);
    if (response.caminho) {
        document.getElementById("imagem_path").value = response.caminho;
    } else {
        console.error("Erro ao processar imagem:", response.erro);
    }
}
        });
    </script>
</body>
</html>
