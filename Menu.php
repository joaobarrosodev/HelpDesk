<!-- filepath: c:\xampp\htdocs\infoexe\HelpDesk\Menu.php -->
<?php
/**
 * Menu principal do sistema HelpDesk
 * Contém a navegação lateral com links para as principais funcionalidades
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
            <img src="img/logo.png" alt="Info.exe - Logo" class="img-fluid" width="120" height="40">
        </a>
        <button class="btn border-0 bg-transparent d-lg-none d-block p-0 fs-5" id="sidebarClose" aria-label="Fechar menu">
            <i class="bi bi-x text-muted" aria-hidden="true"></i>
        </button>
    </header>
    
    <div class="mt-2">
        <ul class="nav flex-column m-0 p-0">
            <!-- Se o utilizador tiver o login feito, aparecem estes menus -->
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <li class="nav-item px-2 py-1">
                    <a href="index.php" id="dashboard" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'page' : 'false'; ?>">
                        <i class="bi bi-speedometer2 me-3 nav-menu-item" aria-hidden="true"></i> 
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item px-2 py-1">
                    <a href="ticket.php" id="ticket" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' ? 'page' : 'false'; ?>">
                        <i class="bi bi-ticket me-3 nav-menu-item" aria-hidden="true"></i> 
                        <span>Abrir Ticket</span>
                    </a>
                </li>
                  <li class="nav-item px-2 py-1">
                    <a href="meus_tickets.php" id="tickets" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'meus_tickets.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'meus_tickets.php' ? 'page' : 'false'; ?>">
                        <i class="bi bi-ticket-detailed me-3 nav-menu-item" aria-hidden="true"></i> 
                        <span>Meus Tickets</span>
                    </a>
                </li>
        
                
                <?php if (isset($_SESSION['Grupo']) && $_SESSION['Grupo'] == 'Admin'): ?>
                    <li class="nav-item px-2 py-1">
                        <a href="cc.php" id="cc" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'cc.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'cc.php' ? 'page' : 'false'; ?>">
                            <i class="bi bi-bank me-3 nav-menu-item" aria-hidden="true"></i> 
                            <span>Conta Corrente</span>
                        </a>
                    </li>
                <?php endif; ?>                <!-- Dropdown para Manuais - implementação personalizada -->
                <li class="nav-item px-2 py-1">
                    <a href="javascript:void(0);" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center justify-content-between text-dark" 
                       aria-expanded="false" 
                       aria-controls="manuaisCollapse" 
                       id="manuaisDropdown">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-book me-3 nav-menu-item" aria-hidden="true"></i> 
                            <span>Manuais</span>
                        </div>
                        <i class="bi bi-chevron-down dropdown-chevron" aria-hidden="true"></i>
                    </a>
                    <div class="collapse" id="manuaisCollapse">
                        <ul class="nav flex-column ms-4 mt-1">
                            <li class="nav-item px-2 py-1">
                                <a href="#" id="manual-xd" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark">
                                    <i class="bi bi-file-earmark-text me-3 nav-menu-item" aria-hidden="true"></i> 
                                    <span>Manual XD</span>
                                </a>
                            </li>
                            
                            <li class="nav-item px-2 py-1">
                                <a href="#" id="manual-sage" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark">
                                    <i class="bi bi-file-earmark-text me-3 nav-menu-item" aria-hidden="true"></i> 
                                    <span>Manual Sage</span>
                                </a>
                            </li>
                            
                            <li class="nav-item px-2 py-1">
                                <a href="#" id="manual-office" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark">
                                    <i class="bi bi-file-earmark-text me-3 nav-menu-item" aria-hidden="true"></i> 
                                    <span>Manual Office</span>
                                </a>
                            </li>
                            
                            <li class="nav-item px-2 py-1">
                                <a href="#" id="manual-impressoras" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark">
                                    <i class="bi bi-file-earmark-text me-3 nav-menu-item" aria-hidden="true"></i> 
                                    <span>Manual Impressoras</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            <?php else: ?>
                <!-- Se não tiver o Login feito aparece apenas a opção para fazer login -->
                <li class="nav-item px-2 py-1">
                    <a href="login.php" id="login" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark">
                        <i class="bi bi-box-arrow-in-right me-3 nav-menu-item" aria-hidden="true"></i> 
                        <span>Login</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>   <!-- Área do Usuário -->
    <?php if (isset($_SESSION['usuario_id'])): ?>
        <a href="user.php" class="mt-auto text-decoration-none user-profile-link p-3 d-flex align-items-center rounded-2 mx-2 mb-1 bg-light" title="Ver perfil de usuário">
            <div class="user-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true">
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
            <div class="ms-3 flex-grow-1 overflow-hidden">
                <p class="fw-semibold m-0 text-truncate text-dark">
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
                <p class="small text-muted m-0 text-truncate">
                    <?php echo isset($_SESSION['usuario_email']) ? htmlspecialchars($_SESSION['usuario_email']) : 'email@exemplo.com'; ?>
                </p>
            </div>
        </a>
        <div class="px-2 mb-3">
            <a href="logout.php" class="btn btn-danger text-white w-100 d-flex align-items-center justify-content-between py-2 px-3" role="button">
                <span>Logout</span> 
            </a>
        </div>
    <?php endif; ?>
</nav>

<!-- Script para garantir que o conteúdo principal esteja correto -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Verifica se há elementos com a classe content e aplicar os estilos corretos
    const contentElements = document.querySelectorAll('.content');
    contentElements.forEach(element => {
        // Aplicar marginLeft e width conforme o sidebar de 300px
        element.style.marginLeft = '300px';
        element.style.width = 'calc(100% - 300px)';
        
        if (!element.classList.contains('content-area')) {
            element.classList.add('content-area');
        }
    });
});
</script>

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
            
            // Quando em mobile, ajustar conteúdo quando o menu está aberto
            if (window.innerWidth <= 991) {
                const contentElements = document.querySelectorAll('.content, .content-area');
                contentElements.forEach(element => {
                    element.style.marginLeft = '0';
                    element.style.width = '100%';
                });
            }
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
                const chevronIcon = this.querySelector('.dropdown-chevron');
                if (chevronIcon) {
                    chevronIcon.style.transform = isExpanded ? 'rotate(-90deg)' : 'rotate(0deg)';
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
        
        // Quando em formato mobile, ajustar o conteúdo
        if (window.innerWidth <= 991) {
            const contentElements = document.querySelectorAll('.content, .content-area');
            contentElements.forEach(element => {
                element.style.marginLeft = '0';
                element.style.width = '100%';
            });
        } else {
            // Em desktop, manter o margin e width correto
            const contentElements = document.querySelectorAll('.content, .content-area');
            contentElements.forEach(element => {
                element.style.marginLeft = '300px';
                element.style.width = 'calc(100% - 300px)';
            });
        }    }
});
</script>