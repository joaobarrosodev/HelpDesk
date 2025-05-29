<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminar Sessão</title>
</head>
<body>
<?php
session_start();  // Inicia a sessão

// Destruir todas as variáveis de sessão
session_destroy();

// Redirecionar para a página de início de sessão
header("Location: login.php");
exit;
?>
</body>
</html>