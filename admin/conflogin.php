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

// Function to check if user is full admin (unrestricted)
function isFullAdmin() {
    return isset($_SESSION['Grupo']) && $_SESSION['Grupo'] === 'Admin';
}

// Function to check if user is restricted admin (common user in admin panel)
function isRestrictedAdmin() {
    return isset($_SESSION['Grupo']) && $_SESSION['Grupo'] === 'Comum';
}

// Function to check if user has any admin access (full or restricted)
function hasAdminAccess() {
    return isFullAdmin() || isRestrictedAdmin();
}

// Function to restrict page access to full admins only
function requireFullAdmin() {
    if (!isFullAdmin()) {
        header("Location: index.php?error=" . urlencode("Acesso negado. Esta página é apenas para administradores com permissões completas."));
        exit;
    }
}

// Function to check if user can access a specific admin page
function canAccessAdminPage($page) {
    if (!hasAdminAccess()) {
        return false;
    }
    
    // Pages that only full admins can access
    $fullAdminOnlyPages = [
        'consultar_tickets.php',
        'tickets_sem_atribuicao.php',
        'consultar_contratos.php',
        'detalhes_contrato.php'
    ];
    
    // Pages that all admin users can access (both full and restricted)
    $allAdminPages = [
        'index.php',
        'tickets_atribuidos.php',
        'tickets_fechados.php',
        'detalhes_ticket.php',
        'processar_alteracao.php',
        'processar_fechar_ticket.php',
        'atribuir_a_mim.php',
        'logout.php',
        'user.php'
    ];
    
    // If it's a page all admins can access, allow it
    if (in_array($page, $allAdminPages)) {
        return true;
    }
    
    // If it's a full admin only page, check if user is full admin
    if (in_array($page, $fullAdminOnlyPages)) {
        return isFullAdmin();
    }
    
    // For any other page not explicitly listed, allow access if user has admin access
    // This is a fallback to prevent blocking of legitimate pages
    return true;
}

// Get current page name
$currentPage = basename($_SERVER['PHP_SELF']);

// Only perform page access check if user has admin access
// Skip the check entirely if user doesn't have admin access (will be handled by login check above)
if (hasAdminAccess()) {
    // Enhanced session check with role validation
    if (!canAccessAdminPage($currentPage)) {
        header("Location: index.php?error=" . urlencode("Acesso negado. Não tem permissões para aceder a esta página."));
        exit;
    }
}
?>