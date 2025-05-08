<?php
// Verificar se o usuário está logado
if (!isset($_SESSION['admin_id'])) {
    // Se não estiver logado, redireciona para o login
    header("Location: login.php");
    exit;
}
?>