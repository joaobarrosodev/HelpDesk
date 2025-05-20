<?php
session_start(); // Inicia a sessão

// Se o usuário já está logado, redireciona para index
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-form shadow-lg p-4 mb-5 bg-white rounded w-100">
                    <div class="text-center mb-4">
                        <img src="../img/logo.png" alt="Logo" class="img-fluid" style="max-height: 80px;">
                        <h2 class="fw-bold mt-3">Acesso Administrativo</h2>
                    </div>
                    
                    <form action="logged.php" method="POST">
                        <!-- Campo de E-mail -->
                        <div class="input-group mb-4 mx-auto">
                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Digite o seu e-mail..." required style="background-color: #f0f0f0; border: none;">
                        </div>

                        <!-- Campo de Senha -->
                        <div class="input-group mb-4 mx-auto">
                            <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Digite a sua palavra-passe..." required style="background-color: #f0f0f0; border: none;">
                        </div>

                        <!-- Botão Para enviar Formulário -->
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




















