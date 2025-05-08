<?php
session_start();  // Inicia a sessão

include('conflogin.php');

// Caso esteja logado, pode mostrar o conteúdo da página
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>

  <!-- Conteúdo da página -->
    <?php include('header.php'); ?>
    <div class="content">
    <!-- Mostrar Conta Corrente em aberto -->
    <?php
    include('db.php');


    // Consultar Conta Corrente em Aberto
    $sql = "Select * from online_entity_extrafields where email = :email";

    // Preparar a consulta
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $_SESSION['usuario_email']);
    $stmt->execute();
    $cc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cc) {
        echo "Ticket não encontrado.";
        exit;
    }
    ?>
    <!-- Exibição das Informações Gerais -->
    <h3 class="mb-4">Informações Gerais do Utilizador</h3>   
    <form action="atualizar_dados.php" method="POST">
        <!-- Código de Cliente (Não Editável) -->
        <div class="form-group">
            <label for="entity_keyid" class="fw-bold">Código de Cliente:</label>
            <input type="text" class="form-control bg-light mb-3" id="entity_keyid" name="entity_keyid" value="<?php echo $cc['Entity_KeyId']; ?>" readonly>
        </div>
        <!-- Nome -->
        <div class="form-group">
            <label for="name" class="fw-bold">Nome:</label>
            <input type="text" class="form-control mb-3" id="name" name="name" value="<?php echo $cc['Name']; ?>" required>
        </div>        

        <!-- Email -->
        <div class="form-group">
            <label for="email" class="fw-bold">Email:</label>
            <input type="email" class="form-control bg-light mb-3" id="entity_keyid" name="email" value="<?php echo $cc['email']; ?>" required>
        </div>

        <div class="form-group">
            <label for="permissions" class="fw-bold">Permissões:</label>
            <input type="email" class="form-control bg-light mb-3" id="entity_keyid" name="grupo" value="<?php echo $cc['Grupo']; ?>" required>
        </div>

        <!-- Palavra-Passe -->
<div class="form-group">
    <label for="password" class="fw-bold">Palavra-Passe:</label>
    <div class="input-group">
        <input type="password" class="form-control mb-3" id="password" name="password" value="<?php echo $cc['Password']; ?>" required>
        <div class="input-group-append">
            <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                <i class="fa fa-eye"></i> <!-- Ícone de olho para mostrar/esconder -->
            </button>
        </div>
    </div>
</div>

<!-- Script para alternar a visibilidade da palavra-passe -->
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        var passwordField = document.getElementById('password');
        var type = passwordField.type === 'password' ? 'text' : 'password';
        passwordField.type = type;

        // Alterna o ícone de olho
        this.innerHTML = type === 'password' ? '<i class="fa fa-eye"></i>' : '<i class="fa fa-eye-slash"></i>';
    });
</script>

        <!-- Botão de atualização -->
        <button type="submit" class="btn btn-primary mt-3">Atualizar Dados</button>
    </form>
</div>

</div>
   

    <!-- Scripts do Bootstrap e JQuery -->
    <script src="script/script.js"></script>    
</body>
</html>