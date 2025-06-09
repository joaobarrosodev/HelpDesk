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

// Function to check if user is Admin (full access)
function isAdmin() {
    return isset($_SESSION['Grupo']) && $_SESSION['Grupo'] === 'Admin';
}

// Function to check if user is Comum (restricted access)
function isComum() {
    return isset($_SESSION['Grupo']) && $_SESSION['Grupo'] === 'Comum';
}

// Function to check if user has admin access (only Admin users)
function hasAdminAccess() {
    return isAdmin(); // Only Admin users have full access
}

// Function to check if user has any valid access (both Admin and Comum)
function hasValidAccess() {
    return isAdmin() || isComum();
}

function requireFullAdmin() {
    if (!isAdmin()) {
        header("Location: index.php?error=" . urlencode("Acesso negado. Esta página é apenas para administradores."));
        exit;
    }
}

// Get current page name
$currentPage = basename($_SERVER['PHP_SELF']);

// Pages that ONLY ADMIN can access
$adminOnlyPages = [
    'tickets_sem_atribuicao.php',
    'consultar_tickets.php',
    'consultar_contratos.php',
    'detalhes_contrato.php'
];

// Check if current page requires admin access
if (in_array($currentPage, $adminOnlyPages)) {
    if (!isAdmin()) {
        header("Location: index.php?error=" . urlencode("Acesso negado. Esta página é apenas para administradores."));
        exit;
    }
}

// Function to check if Comum user can access a specific ticket
function canAccessTicket($ticketId) {
    global $pdo;
    
    // Check if PDO connection exists
    if (!isset($pdo) || $pdo === null) {
        error_log("PDO connection not available in canAccessTicket");
        return false;
    }
    
    if (isAdmin()) {
        return true; // Admin can access all tickets
    }
    
    if (isComum()) {
        try {
            // For comum users, check if ticket is assigned to them
            $stmt = $pdo->prepare("SELECT free.id 
                FROM xdfree01 free
                LEFT JOIN info_xdfree01_extrafields info ON free.KeyId = info.XDFree01_KeyID
                WHERE free.id = :ticket_id AND info.Atribuido = :user_id");
            $stmt->bindParam(':ticket_id', $ticketId);
            $stmt->bindParam(':user_id', $_SESSION['admin_id']);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("canAccessTicket error: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}
?>