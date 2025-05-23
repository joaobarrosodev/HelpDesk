<?php
session_start();  // Inicia a sessão

include('db.php');

// Verificar se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obter os dados do formulário
    $email = $_POST['email'];
    $senha = $_POST['password'];
    
    // Verificar se a conexão PDO foi estabelecida
    if (isset($pdo)) {
        try {
            // Preparar a consulta SQL
            $stmt = $pdo->prepare("SELECT u.id, u.Name, ou.Email, ou.Password
                FROM users u
                JOIN online_userbe_extrafields ou ON u.id = ou.UserBE_Id 
                WHERE ou.Email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            // Verificar se o utilizador foi encontrado
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && $senha == $usuario['Password']) {
                // Se as credenciais estiverem corretas, iniciar a sessão
                $_SESSION['admin_id'] = $usuario['id'];
                $_SESSION['admin_email'] = $usuario['Email'];
                $_SESSION['admin_nome'] = $usuario['Name'];
                $_SESSION['last_activity'] = time();
                
                // Redirecionar para a página principal
                header("Location: index.php");
                exit;
            } else {
                // Se a autenticação falhar, mostrar mensagem de erro
                $erro = "E-mail ou palavra-passe incorretos.";
            }
        } catch (PDOException $e) {
            $erro = "Erro ao executar a consulta: " . $e->getMessage();
        }
    } else {
        $erro = "Não foi possível conectar ao servidor de banco de dados.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-form shadow p-4 mb-5 bg-white rounded">
                    <div class="text-center mb-4">
                        <img src="../img/logo.png" alt="Logo" class="img-fluid" style="max-height: 80px;">
                        <h2 class="fw-bold mt-3">Acesso Administrativo</h2>
                    </div>
                    
                    <?php if (isset($erro)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $erro; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form action="logged.php" method="POST">
                        <!-- Campo de E-mail -->
                        <div class="input-group mb-4">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="Digite o seu e-mail..." required style="background-color: #f0f0f0; border: none;">
                        </div>
                        
                        <!-- Campo de Senha -->
                        <div class="input-group mb-4">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Digite a sua senha..." required style="background-color: #f0f0f0; border: none;">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">Entrar</button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <p><a href="../index.php" class="text-decoration-none">Voltar para área do cliente</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts do Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


