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
            // Preparar a consulta SQL - buscar diretamente na tabela online_userbe_extrafields
            $stmt = $pdo->prepare("SELECT ou.UserBE_Id, ou.Email, ou.Password, u.Name
                FROM online_userbe_extrafields ou
                LEFT JOIN users u ON u.id = ou.UserBE_Id 
                WHERE ou.Email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            // Verificar se o utilizador foi encontrado
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario && $senha == $usuario['Password']) {
                // Se as credenciais estiverem corretas, iniciar a sessão
                $_SESSION['admin_id'] = $usuario['UserBE_Id'];
                $_SESSION['admin_email'] = $usuario['Email'];
                $_SESSION['admin_nome'] = $usuario['Name'] ?? 'Administrador';
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
        $erro = "Não foi possível conectar ao servidor de base de dados.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>

<body class="bg-light">    
    <div class="d-flex align-items-center min-vh-100 py-5 w-100" style="background-color: #f8f9fa; background-image: url('../img/pattern-bg.png'); background-repeat: repeat;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-8 col-sm-10">
                    <div class="text-center mb-4">
                        <img src="../img/logo.png" alt="Logótipo HelpDesk" height="60" class="mb-3" onerror="this.style.display='none'">
                    </div>
                    <div class="card shadow border-0 rounded-lg">
                        <div class="card-header bg-primary text-white text-center py-4">
                            <h4 class="fw-bold mb-0">HelpDesk Admin</h4>
                            <p class="text-white-50 mb-0">Painel de Administração</p>
                        </div>
                        
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <i class="bi bi-shield-lock fs-1 text-muted"></i>
                                <h5 class="mt-2">Acesso Administrativo</h5>
                                <p class="text-muted">Área restrita para administradores do sistema</p>
                            </div>
                            
                            <?php if (isset($erro)): ?>
                            <div class="alert alert-danger mb-4" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo htmlspecialchars($erro); ?>
                            </div>
                            <?php endif; ?>
                            
                            <form action="logged.php" method="POST">
                                <!-- Campo de E-mail -->
                                <div class="mb-3">
                                    <label for="email" class="form-label"><i class="bi bi-person me-2"></i>Correio Eletrónico</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                        value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="admin@empresa.pt" required>
                                </div>

                                <!-- Campo de Palavra-Passe -->
                                <div class="mb-4">
                                    <label for="password" class="form-label"><i class="bi bi-lock me-2"></i>Palavra-Passe</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" 
                                            placeholder="********" required>
                                        <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Botão Para enviar Formulário -->
                                <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                                    <i class="bi bi-shield-check me-2"></i>Aceder ao Painel
                                </button>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                        <label class="form-check-label small text-muted" for="remember">
                                            Memorizar início de sessão
                                        </label>
                                    </div>
                                    <div>
                                        <a href="../index.php" class="text-decoration-none small">Voltar para área do cliente</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="card-footer text-center py-3 bg-light">
                            <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> HelpDesk - Painel Administrativo</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>    
    
    <!-- Scripts do Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>        
    // Alternar visibilidade da palavra-passe
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Atualizar ícone
            this.innerHTML = type === 'password' ? 
                '<i class="bi bi-eye"></i>' : 
                '<i class="bi bi-eye-slash"></i>';
        });
        
        // Destacar input ao focar
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('input-focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('input-focused');
            });
        });
    </script>
    
    <style>
        :root {
            --bs-primary: #529ebe;
            --bs-primary-rgb: 82, 158, 190;
        }
        
        .btn-primary {
            background-color: #e7f3ff;
            border-color: #529ebe;
        }
        
        .btn-primary:hover {
            background-color: #4a8ba8;
            border-color: #4a8ba8;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(82, 158, 190, 0.25);
        }
        
        .btn-primary:focus {
            background-color: #4a8ba8;
            border-color: #4a8ba8;
            box-shadow: 0 0 0 0.2rem rgba(82, 158, 190, 0.25);
        }
        
        .bg-primary {
            background-color: #529ebe !important;
        }
        
        .text-primary {
            color: #529ebe !important;
        }
        
        .input-focused {
            position: relative;
        }
        
        .input-focused::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -2px;
            height: 2px;
            background-color: #529ebe;
            animation: focusAnimation 0.3s ease forwards;
        }
        
        @keyframes focusAnimation {
            from { transform: scaleX(0); }
            to { transform: scaleX(1); }
        }
        
        .card {
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08) !important;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: #529ebe;
        }
        
        a {
            color: #529ebe;
        }
        
        a:hover {
            color: #4a8ba8;
        }
    </style>
</body>
</html>


