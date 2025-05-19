<?php
// Verificar se o utilizador está autenticado
if (!isset($_SESSION['usuario_id'])) {
    // Se não estiver autenticado, redireciona para a página de login
    header("Location: login.php");
    exit;
}
?>