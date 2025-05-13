<!-- filepath: c:\xampp\htdocs\infoexe\HelpDesk\Menu.php -->
<?php
/**
 * Menu principal do sistema HelpDesk
 * Contém a navegação lateral com links para as principais funcionalidades
 */
?>
<!-- Estilos específicos para o menu -->
<style>
    /* Novo estilo para a sidebar - com foco em acessibilidade e SEO */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        width: 250px;
        background-color: #ffffff;
        padding: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
        z-index: 1000;
        overflow-y: auto;
    }
    
    /* Logo */
    .sidebar-logo {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #f5f5f5;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .sidebar-logo img {
        max-width: 120px;
        height: auto; /* Mantém proporção da imagem */
    }
    
    .sidebar-toggle {
        display: none;
        font-size: 1.5rem;
        background: none;
        border: none;
        color: #777;
        cursor: pointer;
        padding: 0;
    }
    
    /* Links na sidebar */
    .sidebar-menu {
        padding: 0;
        list-style: none;
        margin: 10px 0;
    }
    
    .sidebar-menu li {
        margin: 2px 0;
    }
    
    .sidebar-menu a {
        display: flex;
        align-items: center;
        color: #333;
        text-decoration: none;
        padding: 10px 15px;
        border-radius: 8px;
        margin: 0 8px;
        transition: all 0.2s ease;
    }
    
    .sidebar-menu a:hover, 
    .sidebar-menu a:focus, /* Suporte a navegação por teclado */
    .sidebar-menu a.active {
        background-color: rgba(76, 180, 231, 0.1);
        color: #4CB4E7;
        outline: none;
    }
    
    .sidebar-menu a.active {
        background-color: rgba(76, 180, 231, 0.15);
        font-weight: 500;
    }
    
    /* Ícones */
    .sidebar-menu a i {
        margin-right: 12px;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        color:rgb(0, 0, 0);
    }
.dropdown-toggle::after {
        content: none; /* Remove o ícone padrão do Bootstrap */
    }

    /* Dropdown de manuais */
    .dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
    }
    
    .dropdown-toggle i.bi-chevron-down {
        transition: transform 0.3s ease;
    }
    
    .dropdown-toggle.collapsed i.bi-chevron-down {
        transform: rotate(-90deg);
    }
    
    /* Dropdown menu styles */
    #manuaisCollapse {
        transition: all 0.3s ease;
    }    /* User profile area */
    .sidebar-user {
        margin-top: auto;
        padding: 12px 15px;
        border-top: 1px solid #f5f5f5;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
        border-radius: 8px;
        margin: 5px 8px 0 8px;
        background-color: rgba(76, 180, 231, 0.03);
    }
    
    .sidebar-user:hover {
        background-color: rgba(76, 180, 231, 0.1);
        cursor: pointer;
        transform: translateY(-1px);
    }
    
    .user-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background-color: #4CB4E7;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        margin-right: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        flex-shrink: 0;
    }
    
    .user-info {
        flex: 1;
        overflow: hidden;
    }
    
    .user-name {
        font-weight: 600;
        margin: 0;
        font-size: 0.95rem;
        color: #333;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .user-email {
        font-size: 0.8rem;
        color: #777;
        margin: 2px 0 0 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .profile-icon {
        font-size: 0.8rem;
        opacity: 0.6;
        transition: transform 0.3s ease;
        margin-left: 5px;
        flex-shrink: 0;
    }
    
    .sidebar-user:hover .profile-icon {
        transform: translateX(3px);
        opacity: 1;
    }
      /* Logout button similar to the screenshot */
    .logout-button {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background-color: #BF5555;
        color: #FFFFFF;
        border: none;
        padding: 12px 15px;
        border-radius: 8px;
        margin: 15px 8px 8px 8px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        width: calc(100% - 16px);
    }
    
    .logout-button:hover,
    .logout-button:focus {
        background-color: #D65C5C;
        color: #FFFFFF;
        text-decoration: none;
        outline: none;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(191, 85, 85, 0.25);
    }
    
    .logout-button:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(191, 85, 85, 0.25);
    }
    
    .logout-button i {
        color: #FFFFFF;
        transition: transform 0.3s ease;
    }
    
    .logout-button:hover i {
        transform: translateX(3px);
    }

    /* Hamburger menu for mobile */
    .menu-toggle {
        display: none;
        position: fixed;
        top: 15px;
        right: 15px;
        z-index: 1010;
        background-color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        color: #333;
        font-size: 1.2rem;
        cursor: pointer;
        align-items: center;
        justify-content: center;
    }
    
    /* Content area */
    .content {
        margin-left: 250px;
        padding: 20px;
        transition: margin-left 0.3s ease;
    }
    
    /* Responsive design */
    @media (max-width: 991px) {
        .sidebar {
            width: 100%;
            max-width: 280px;
            left: -280px;
            transition: left 0.3s ease;
        }
        
        .sidebar.active {
            left: 0;
        }
        
        .content {
            margin-left: 0;
        }
        
        .menu-toggle,
        .sidebar-toggle {
            display: flex;
        }
        
        body.sidebar-open::after {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 999;
        }
    }
    
    @media (max-width: 576px) {
        .sidebar {
            width: 100%;
            max-width: 100%;
            left: -100%;
        }
    }
</style>

<!-- Toggle button for mobile - acessibilidade melhorada -->
<button class="menu-toggle" id="menuToggle" aria-label="Abrir menu" aria-expanded="false">
    <i class="bi bi-list" aria-hidden="true"></i>
</button>

<!-- Sidebar - estruturada usando nav para melhor semântica HTML5 -->
<nav class="sidebar" id="sidebar" aria-label="Menu principal">   
    <header class="sidebar-logo">
        <a href="index.php" title="Página inicial">
            <img src="img/logo.png" alt="Info.exe - Logo" class="img-fluid" width="120" height="40">
        </a>
        <button class="sidebar-toggle" id="sidebarClose" aria-label="Fechar menu">
            <i class="bi bi-x" aria-hidden="true"></i>
        </button>
    </header>
    
    <div class="mt-2">
        <ul class="sidebar-menu nav flex-column">
            <!-- Se o utilizador tiver o login feito, aparecem estes menus -->
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <li class="nav-item">
                    <a href="index.php" id="dashboard" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'page' : 'false'; ?>">
                        <i class="bi bi-speedometer2" aria-hidden="true"></i> 
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ticket.php" id="ticket" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' ? 'page' : 'false'; ?>">
                        <i class="bi bi-ticket" aria-hidden="true"></i> 
                        <span>Abrir Ticket</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="consultar_tickets.php" id="tickets" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'consultar_tickets.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'consultar_tickets.php' ? 'page' : 'false'; ?>">
                        <i class="bi bi-ticket-detailed" aria-hidden="true"></i> 
                        <span>Ticket Resolvidos</span>
                    </a>
                </li>
                
                <?php if (isset($_SESSION['Grupo']) && $_SESSION['Grupo'] == 'Admin'): ?>
                    <li class="nav-item">
                        <a href="cc.php" id="cc" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cc.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'cc.php' ? 'page' : 'false'; ?>">
                            <i class="bi bi-bank" aria-hidden="true"></i> 
                            <span>Conta Corrente</span>
                        </a>
                    </li>
                <?php endif; ?>                <!-- Dropdown para Manuais - implementação personalizada -->
                <li class="nav-item mt-2">
                    <a href="javascript:void(0);" class="nav-link dropdown-toggle collapsed" 
                       aria-expanded="false" 
                       aria-controls="manuaisCollapse" 
                       id="manuaisDropdown">
                        <i class="bi bi-book" aria-hidden="true"></i> 
                        <span>Manuais</span>
                        <i class="bi bi-chevron-down ms-auto" aria-hidden="true"></i>
                    </a>
                    <div class="collapse" id="manuaisCollapse">
                        <ul class="sidebar-menu nav flex-column ms-3">
                            <li class="nav-item">
                                <a href="#" id="manual-xd" class="nav-link">
                                    <i class="bi bi-file-earmark-text" aria-hidden="true"></i> 
                                    <span>Manual XD</span>
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a href="#" id="manual-sage" class="nav-link">
                                    <i class="bi bi-file-earmark-text" aria-hidden="true"></i> 
                                    <span>Manual Sage</span>
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a href="#" id="manual-office" class="nav-link">
                                    <i class="bi bi-file-earmark-text" aria-hidden="true"></i> 
                                    <span>Manual Office</span>
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a href="#" id="manual-impressoras" class="nav-link">
                                    <i class="bi bi-file-earmark-text" aria-hidden="true"></i> 
                                    <span>Manual Impressoras</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            <?php else: ?>
                <!-- Se não tiver o Login feito aparece apenas a opção para fazer login -->
                <li class="nav-item">
                    <a href="login.php" id="login" class="nav-link">
                        <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> 
                        <span>Login</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>      <!-- Área do Usuário -->
    <?php if (isset($_SESSION['usuario_id'])): ?>
        <a href="user.php" class="sidebar-user mt-auto text-decoration-none" title="Ver perfil de usuário">
            <div class="user-avatar" aria-hidden="true">
                <?php 
                if (isset($_SESSION['usuario_email'])) {
                    $email = $_SESSION['usuario_email'];
                    $primeira_letra = strtoupper($email[0]); // Pega a primeira letra do email e coloca em maiúscula
                    echo $primeira_letra;
                } else {
                    echo "U";
                }
                ?>
            </div>
            <div class="user-info">
                <p class="user-name">
                    <?php 
                    if (isset($_SESSION['usuario_nome'])) {
                        echo htmlspecialchars($_SESSION['usuario_nome']);
                    } elseif (isset($_SESSION['usuario_email'])) {
                        $parts = explode('@', $_SESSION['usuario_email']);
                        echo htmlspecialchars($parts[0]);
                    } else {
                        echo "Usuário";
                    }
                    ?>
                </p>
                <p class="user-email">
                    <?php echo isset($_SESSION['usuario_email']) ? htmlspecialchars($_SESSION['usuario_email']) : 'email@exemplo.com'; ?>
                </p>
            </div>
        </a>
        <div class="px-2">
            <a href="logout.php" class="logout-button btn" role="button">
                <span>Logout</span> 
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            </a>        </div>
    <?php endif; ?>
</nav>

<!-- Scripts para controlar o comportamento do menu -->
<script>
/**
 * Script para manipulação do menu responsivo do HelpDesk
 * Implementa funcionalidades de acessibilidade e UX melhorada
 */
document.addEventListener('DOMContentLoaded', function() {
    // Elementos principais
    const menuToggle = document.getElementById('menuToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebar = document.getElementById('sidebar');
    const manuaisDropdown = document.getElementById('manuaisDropdown');
    const manuaisCollapse = document.getElementById('manuaisCollapse');
    
    // Lidar com abertura do menu em dispositivos móveis
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.add('active');
            document.body.classList.add('sidebar-open');
            this.setAttribute('aria-expanded', 'true');
            
            // Foca no primeiro elemento do menu para acessibilidade
            setTimeout(() => {
                const firstItem = sidebar.querySelector('.nav-link');
                if (firstItem) firstItem.focus();
            }, 100);
        });
    }
    
    // Lidar com fechamento do menu
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function() {
            closeSidebar();
        });
    }
    
    // Fechar sidebar quando clicar fora
    document.addEventListener('click', function(event) {
        if (document.body.classList.contains('sidebar-open') && 
            !sidebar.contains(event.target) && 
            event.target !== menuToggle) {
            closeSidebar();
        }
    });
      // Implementar funcionalidade de dropdown para manuais
    if (manuaisDropdown) {
        manuaisDropdown.addEventListener('click', function(e) {
            e.preventDefault();
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            // Toggle estado do dropdown
            this.setAttribute('aria-expanded', !isExpanded);
            this.classList.toggle('collapsed');
            
            // Toggle exibição do conteúdo do dropdown
            if (manuaisCollapse) {
                manuaisCollapse.classList.toggle('show');
                
                // Animar rotação do ícone chevron
                const chevronIcon = this.querySelector('.bi-chevron-down');
                if (chevronIcon) {
                    if (isExpanded) {
                        chevronIcon.style.transform = 'rotate(-90deg)';
                    } else {
                        chevronIcon.style.transform = 'rotate(0deg)';
                    }
                }
            }
        });
    }
    
    // Suporte para navegação por teclado (acessibilidade)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            closeSidebar();
        }
    });
    
    // Função para fechar a sidebar
    function closeSidebar() {
        sidebar.classList.remove('active');
        document.body.classList.remove('sidebar-open');
        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.focus(); // Devolve o foco ao botão para melhor acessibilidade
        }
    }
});
</script>