<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout</title>
</head>
<body>
<?php
session_start();  // Inicia a sessão

// Destruir todas as variáveis de sessão
session_destroy();

// Redirecionar para a página de login
header("Location: login.php");
exit;
?>
</body>
</html>