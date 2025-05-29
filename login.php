<?php
$errorMsg = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>

<body class="bg-light">    
    <div class="d-flex align-items-center min-vh-100 py-5 w-100" style="background-color: #f8f9fa; background-image: url('img/pattern-bg.png'); background-repeat: repeat;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-8 col-sm-10">                <div class="text-center mb-4">
                        <img src="img/logo.png" alt="Logótipo HelpDesk" height="60" class="mb-3" onerror="this.style.display='none'">
                    </div>
                    <div class="card shadow border-0 rounded-lg">                        <div class="card-header bg-primary text-white text-center py-4">
                            <h4 class="fw-bold mb-0">HelpDesk</h4>
                            <p class="text-white-50 mb-0">Sistema de Suporte Técnico</p>
                        </div>
                        
                        <div class="card-body p-4">                            <div class="text-center mb-4">
                                <i class="bi bi-person-circle fs-1 text-muted"></i>
                                <h5 class="mt-2">Acesso ao Sistema</h5>
                                <p class="text-muted">Introduza as suas credenciais para aceder à plataforma</p>
                            </div>
                            
                            <?php if (!empty($errorMsg)): ?>
                            <div class="alert alert-danger mb-4" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo htmlspecialchars($errorMsg); ?>
                            </div>
                            <?php endif; ?>
                            
                            <form action="logged.php" method="POST">                                <!-- Campo de E-mail -->                                <div class="mb-3">
                                    <label for="email" class="form-label"><i class="bi bi-envelope me-2"></i>Correio Eletrónico</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                        placeholder="o.seu.email@empresa.pt" required>
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

                                <!-- Botão Para enviar Formulário -->                                <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sessão
                                </button>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember" name="remember">                                        <label class="form-check-label small text-muted" for="remember">
                                            Memorizar início de sessão
                                        </label>
                                    </div>
                                    <div>                                            <a href="#" class="text-decoration-none small">Esqueceu-se da palavra-passe?</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="card-footer text-center py-3 bg-light">
                            <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> HelpDesk - Sistema de Suporte</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>    
    <!-- Scripts do Bootstrap e JQuery -->
    <script src="js/bootstrap.bundle.min.js"></script>

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
            background-color: #0d6efd;
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
        
        .btn-primary {
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.25);
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: #0d6efd;
        }
    </style>
</body>
</html>