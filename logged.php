<?php
session_start();  // Inicia a sessão

include('db.php');

// Verificar se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obter os dados do formulário
    $email = $_POST['email'];
    $senha = $_POST['password'];

    // Preparar a consulta SQL
    $stmt = $pdo->prepare("select entities.KeyId, entities.Name, online.email, online.Password, online.Grupo
from online_entity_extrafields online 
Inner Join entities on online.Entity_KeyId = entities.KeyId where online.email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    // Verificar se o usuário foi encontrado
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $senha == $usuario['Password']) {
        // Se as credenciais estiverem corretas, iniciar a sessão
        $_SESSION['usuario_id'] = $usuario['KeyId'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['Nome'] = $usuario['Name'];
        $_SESSION['Grupo'] = $usuario['Grupo'];

        // Redirecionar para a página principal ou dashboard
        header("Location: index.php");
        exit;
    } else {
        // Se a autenticação falhar, mostrar mensagem de erro
        $erro = "E-mail ou senha incorretos.";
    }
}
?>

<!-- Formulário de Login (HTML) -->
<?php include('head.php'); ?>
 <?php include('menu.php'); ?>
    <div class="content">
        <div class="login-container">
        <div class="login-form shadow-lg p-3 mb-5 bg-white rounded w-100 mx-auto">
            <h2 class="fw-bold mb-4 mt-3">Login</h2>
            <form action="logged.php" method="POST">
                 <!-- Campo de E-mail -->
            <div class="input-group mb-4 w-75 mx-auto d-flex justify-content-center">

                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                <input type="email" class="form-control" id="email" name="email" placeholder="Digite o seu e-mail..." required style="background-color: #f0f0f0; border: none;">
            </div>

             <!-- Campo de Senha -->
                <div class="input-group mb-4 w-75 mx-auto d-flex justify-content-center">

                    <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Digite a sua senha..." required style="background-color: #f0f0f0; border: none;">
                </div>
                 <button type="submit" class="btn btn-primary mb-4 w-75 mx-auto d-flex justify-content-center">Entrar</button>
                <?php
                // Exibir mensagem de erro, caso haja
                if (isset($erro)) {
                echo "<p style='color: red;'>$erro</p>";
                }
                ?>
            </form>
        </div>
    </div>
    </div>
    <!-- Scripts do Bootstrap e JQuery -->
    <script src="script/script.js"></script>    


