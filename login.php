<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>

  <!-- Conteúdo da página -->
    <section class="container w-100 h-100 justify-content-center align-content-center">
    
        <div class="login-form shadow-lg p-3 mb-5 bg-white rounded w-100 mx-auto flex-column">
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

                <!-- Botão Para enviar Formulário -->
                <button type="submit" class="btn btn-primary mb-4 w-75 mx-auto d-flex justify-content-center">Entrar</button>
            </form>
        </div>
</section>
    <!-- Scripts do Bootstrap e JQuery -->
    <script src="script/script.js"></script>    
</body>
</html>




















