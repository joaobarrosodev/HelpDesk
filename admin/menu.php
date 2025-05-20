<?php
/**
 * Menu principal do sistema HelpDesk - Seção Admin
 * Contém a navegação lateral com links para as principais funcionalidades administrativas
 */
?>

<!-- Toggle button for mobile - acessibilidade melhorada -->
<button class="btn rounded-circle shadow-sm position-fixed d-lg-none d-block bg-white border-0" id="menuToggle" 
    style="top: 15px; right: 15px; width: 40px; height: 40px; z-index: 1040;" 
    aria-label="Abrir menu" aria-expanded="false">
    <i class="bi bi-list" aria-hidden="true"></i>
</button>

<!-- Sidebar - estruturada usando nav para melhor semântica HTML5 -->
<nav class="sidebar-helpdesk bg-white shadow-sm d-flex flex-column" id="sidebar" aria-label="Menu principal">   
    <header class="sidebar-logo-area p-3 d-flex justify-content-between align-items-center">
        <a href="index.php" title="Página inicial" class="text-decoration-none">
            <img src="../img/logo.png" alt="Info.exe - Logo" class="img-fluid" width="150">
        </a>
        <button class="btn border-0 bg-transparent d-lg-none d-block p-0 fs-5" id="sidebarClose" aria-label="Fechar menu">
            <i class="bi bi-x text-muted" aria-hidden="true"></i>
        </button>
    </header>
    
    <div class="mt-4">
        <ul class="nav flex-column m-0 p-0">
            <!-- Se o utilizador tiver o login feito, aparecem estes menus -->
            <?php if (isset($_SESSION['admin_id'])): ?>
                <li class="nav-item px-3 py-1">
                    <a href="index.php" id="dashboard" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2 me-3 fs-5" aria-hidden="true"></i> 
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item px-3 py-1">
                    <a href="consultar_tickets.php" id="tickets" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'consultar_tickets.php' ? 'active' : ''; ?>">
                        <i class="bi bi-ticket me-3 fs-5" aria-hidden="true"></i> 
                        <span>Tickets por Atribuir</span>
                    </a>
                </li>
                
                <li class="nav-item px-3 py-1">
                    <a href="tickets_atribuidos.php" id="tickets-atribuidos" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'tickets_atribuidos.php' ? 'active' : ''; ?>">
                        <i class="bi bi-ticket-detailed me-3 fs-5" aria-hidden="true"></i> 
                        <span>Tickets Atribuídos</span>
                    </a>
                </li>

                <li class="nav-item px-3 py-1">
                    <a href="consultar_tickets_fechados.php" id="tickets-all" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'consultar_tickets_fechados.php' ? 'active' : ''; ?>">
                        <i class="bi bi-list-check me-3 fs-5" aria-hidden="true"></i> 
                        <span>Tickets Fechados</span>
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item px-3 py-1">
                    <a href="login.php" id="login" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark">
                        <i class="bi bi-box-arrow-in-right me-3 fs-5" aria-hidden="true"></i>
                        <span>Login</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <!-- User Profile -->
    <?php if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_nome'])): ?>
    <div class="mt-auto p-3 border-top">
        <div class="d-flex align-items-center user-profile-link rounded p-2">
            <div class="user-avatar rounded-circle d-flex align-items-center justify-content-center me-2">
                <?php echo strtoupper(substr($_SESSION['admin_nome'], 0, 1)); ?>
            </div>
            <div class="d-flex flex-column">
                <span class="fw-medium text-dark"><?php echo $_SESSION['admin_nome']; ?></span>
                <small class="text-muted"><?php echo $_SESSION['admin_email'] ?? ''; ?></small>
            </div>
        </div>
        <a href="logout.php" class="btn btn-danger w-100 mt-3 d-flex align-items-center justify-content-center">
            <i class="bi bi-power me-2"></i> Terminar Sessão
        </a>
    </div>
    <?php endif; ?>
</nav>

<!-- Overlay para dispositivos móveis -->
<div class="sidebar-overlay position-fixed top-0 start-0 w-100 h-100 d-lg-none" style="z-index: 1025; display: none;"></div>

<!-- Scripts para funcionamento do menu -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        // Função para mostrar menu
        function showSidebar() {
            sidebar.classList.add('show-sidebar');
            overlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // Função para esconder menu
        function hideSidebar() {
            sidebar.classList.remove('show-sidebar');
            overlay.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Toggle menu no mobile
        if (menuToggle) {
            menuToggle.addEventListener('click', showSidebar);
        }
        
        // Fechar menu
        if (sidebarClose) {
            sidebarClose.addEventListener('click', hideSidebar);
        }
        
        // Fechar ao clicar no overlay
        if (overlay) {
            overlay.addEventListener('click', hideSidebar);
        }
    });
</script>