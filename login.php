<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>

<body>

  <!-- Conteúdo da página -->
    <section class="w-100 h-100"

    style="background: #55A0BF;">
        <div class="container d-flex justify-content-center align-items-center h-100">
                <div class="col-4 p-5 bg-white rounded flex-column justify-content-center align-items-center fade-in box-shadow">
                    <h2 class="fw-bold mb-4 mt-4 text-center">Bem Vindo de Volta!</h2>
                    <form action="logged.php" method="POST">

                    <!-- Campo de E-mail -->
                    <div class="input-group mb-4 w-100  d-flex justify-content-center flex-column">
                        <label for="email" class="mb-1">Email:</label>
                        <input type="email" class="form-control w-100 rounded" id="email" name="email" placeholder="example@domain.com">
                    </div>

                        <!-- Campo de Palavra-Passe -->
                        <div class="input-group mb-4 w-100 d-flex justify-content-center flex-column">
                            <label for="password" class="mb-1">Palavra-Passe:</label>
                            <input type="password" class="form-control w-100 rounded" id="password" name="password" placeholder="********" >
                        </div>

                        <!-- Botão Para enviar Formulário -->
                        <button type="submit" class="btn btn-primary mb-4 w-75 mx-auto d-flex justify-content-center">Iniciar Sessão</button>
                    </form>
                </div>
        </div>
</section>
    <!-- Scripts do Bootstrap e JQuery -->
    <script src="script/script.js"></script>    
</body>
</html>




















