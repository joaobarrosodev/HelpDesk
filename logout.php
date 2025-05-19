<?php
session_start();  // Inicia a sessão

// Destruir todas as variáveis de sessão
session_destroy();

// Redirecionar para a página de login
header("Location: login.php");
exit;
?>