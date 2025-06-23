<?php
// Start session first if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['Grupo']) && $_SESSION['Grupo'] === 'Admin';
}

// Function to check if user is common user
function isCommonUser() {
    return isset($_SESSION['Grupo']) && $_SESSION['Grupo'] === 'Comum';
}

// Function to restrict page access to admins only
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: index.php?error=" . urlencode("Acesso negado. Esta página é apenas para administradores."));
        exit;
    }
}

// Function to check if user can access a specific page
function canAccessPage($page) {
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }
    
    // Pages that only admins can access
    $adminOnlyPages = ['cc.php', 'meus_contratos.php', 'detalhes_contratos.php'];
    
    if (in_array($page, $adminOnlyPages)) {
        return isAdmin();
    }
    
    return true; // All other pages are accessible to logged-in users
}

// Enhanced session check with role validation
if (!isset($_SESSION['usuario_id'])) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header("Location: login.php?redirect=" . $redirect . "&error=" . urlencode("Por favor, faça login para continuar."));
    exit;
}

// Check page-specific access permissions
$currentPage = basename($_SERVER['PHP_SELF']);
if (!canAccessPage($currentPage)) {
    header("Location: index.php?error=" . urlencode("Acesso negado. Não tem permissões para aceder a esta página."));
    exit;
}
?>