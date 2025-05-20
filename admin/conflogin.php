<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    // Save requested URL for redirect after login (optional)
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Se não estiver logado, redireciona para o login
    header("Location: login.php");
    exit;
}

// Opcional: Verificar se a sessão não expirou (por exemplo, após 2 horas)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // Se passaram mais de 2 horas, encerrar a sessão
    session_unset();
    session_destroy();
    
    // Redirecionar para login com mensagem
    header("Location: login.php?expired=1");
    exit;
}

// Atualizar o timestamp da última atividade
$_SESSION['last_activity'] = time();
?>