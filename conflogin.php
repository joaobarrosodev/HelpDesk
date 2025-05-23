<?php
// Debug session information
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));

// Verificar se o utilizador está autenticado
if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario_id'])) {
    // Log the redirect for debugging
    error_log("User not authenticated, redirecting to login. Current page: " . $_SERVER['REQUEST_URI']);
    
    // Se não estiver autenticado, redireciona para a página de login
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Additional check for session timeout (optional)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    // Session expired after 1 hour
    session_destroy();
    header("Location: login.php?error=" . urlencode("Sessão expirada. Por favor, faça login novamente."));
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();
?>