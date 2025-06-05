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
            <img src="../img/logo.png" alt="Info.exe - Logótipo" class="img-fluid" width="120" height="40">
        </a>
        <button class="btn border-0 bg-transparent d-lg-none d-block p-0 " id="sidebarClose" aria-label="Fechar menu">
            <i class="bi bi-x text-muted" aria-hidden="true"></i>
        </button>
        </header>
    
    <div class="mt-2">
        <ul class="nav flex-column m-0 p-0">
            <!-- Se o utilizador tiver o login feito, aparecem estes menus -->
            <?php if (isset($_SESSION['admin_id'])): ?>
                <li class="nav-item px-2 py-1">
                    <a href="index.php" id="dashboard" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2 me-3 " aria-hidden="true"></i> 
                        <span>Painel de Controlo</span>
                    </a>
                </li>
                
                <?php if (isFullAdmin()): ?>
                <li class="nav-item px-2 py-1">
                    <a href="tickets_sem_atribuicao.php" id="tickets" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'tickets_sem_atribuicao.php' ? 'active' : ''; ?>">
                        <i class="bi bi-ticket me-3 " aria-hidden="true"></i> 
                        <span>Tickets sem Atribuição</span>
                    </a>
                </li>
                
                <li class="nav-item px-2 py-1">
                    <a href="consultar_tickets.php" id="tickets-todos" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'consultar_tickets.php' ? 'active' : ''; ?>">
                        <i class="bi bi-search me-3 " aria-hidden="true"></i> 
                        <span>Consultar Tickets</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item px-2 py-1">
                    <a href="tickets_atribuidos.php" id="tickets-atribuidos" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'tickets_atribuidos.php' ? 'active' : ''; ?>">
                        <i class="bi bi-ticket-detailed me-3 " aria-hidden="true"></i> 
                        <span>Tickets Atribuídos</span>
                    </a>
                </li>

                <li class="nav-item px-2 py-1">
                    <a href="tickets_fechados.php" id="tickets-all" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'tickets_fechados.php' ? 'active' : ''; ?>">
                        <i class="bi bi-list-check me-3 " aria-hidden="true"></i> 
                        <span>Tickets Fechados</span>
                    </a>
                </li>

                <?php if (isFullAdmin()): ?>
                <li class="nav-item px-2 py-1">
                    <a href="consultar_contratos.php" id="tickets-all" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'consultar_contratos.php' ? 'active' : ''; ?>">
                        <i class="bi bi-list-check me-3 " aria-hidden="true"></i> 
                        <span>Consultar Contratos</span>
                    </a>
                </li>
                <?php endif; ?>
            <?php else: ?>
                <li class="nav-item px-2 py-1">
                    <a href="login.php" id="login" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark">
                        <i class="bi bi-box-arrow-in-right me-3 " aria-hidden="true"></i>
                        <span>Iniciar Sessão</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <!-- User Profile -->

    <?php if (isset($_SESSION['admin_id'])): ?>
        <a class="mt-auto text-decoration-none user-profile-link p-3 d-flex align-items-center rounded-2 mx-2 mb-1 bg-light" title="Ver perfil do utilizador">
            <div class="user-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true">                <?php 
                if (isset($_SESSION['Nome'])) {
                    $primeira_letra = strtoupper($_SESSION['Nome'][0]); // Pega a primeira letra do nome e coloca em maiúscula
                    echo $primeira_letra;
                } elseif (isset($_SESSION['admin_email'])) {
                    $email = $_SESSION['admin_email'];
                    $primeira_letra = strtoupper($email[0]); // Pega a primeira letra do email e coloca em maiúscula
                    echo $primeira_letra;
                } else {
                    echo "U";
                }
                ?>
            </div>            <div class="ms-3 flex-grow-1 overflow-hidden">                <p class="fw-semibold m-0 text-truncate text-dark">
                    <?php echo isset($_SESSION['admin_nome']) ? htmlspecialchars($_SESSION['admin_nome']) : 'Utilizador'; ?>
                </p>
                <p class="small text-muted m-0 text-truncate">
                    <?php echo isset($_SESSION['admin_email']) ? htmlspecialchars($_SESSION['admin_email']) : 'email@exemplo.com'; ?>
                </p>
            </div>
        </a>
        <div class="px-2 mb-3">
            <a href="logout.php" class="btn btn-danger w-100 d-flex align-items-center justify-content-center" role="button">
                <span>Terminar Sessão</span> 
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