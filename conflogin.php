<?php
// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    // Se não estiver logado, redireciona para o login
    header("Location: login.php");
    exit;
}
?>