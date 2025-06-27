<!-- filepath: c:\xampp\htdocs\infoexe\HelpDesk\menu.php -->
<?php
/**
 * Menu principal do sistema HelpDesk
 * Contém a navegação lateral com links para as principais funcionalidades
 */
?>

<!-- Toggle button for mobile - transformado em botão de toggle que muda de hambúrguer para X -->
<button class="btn hamburger-btn rounded-circle shadow-sm position-fixed d-lg-none d-block bg-white border-0" id="menuToggle" 
    style="top: 15px; right: 15px; width: 50px; height: 50px; z-index: 1060; cursor: pointer;" 
    aria-label="Alternar menu" aria-expanded="false">
    <div class="hamburger-icon">
        <span></span>
        <span></span>
        <span></span>
    </div>
</button>

<!-- Sidebar - estruturada usando nav para melhor semântica HTML5 -->
<nav class="sidebar-helpdesk bg-white shadow-sm d-flex flex-column" id="sidebar" aria-label="Menu principal">   
      <header class="sidebar-logo-area p-3 d-flex justify-content-between align-items-center">
        <a href="index.php" title="Página inicial" class="text-decoration-none">
            <img src="img/logo.png" alt="Info.exe - Logo" class="img-fluid" width="120" height="40">
        </a>
        <!-- Botão X interno removido completamente -->
      </header>
    
    <div class="mt-2">
        <ul class="nav flex-column m-0 p-0">
            <!-- Se o utilizador tiver o login feito, aparecem estes menus -->
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <li class="nav-item px-2 py-1">
                    <a href="index.php" id="dashboard" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'page' : 'false'; ?>">
                        <i class="bi bi-speedometer2 me-3 nav-menu-item" aria-hidden="true"></i> 
                        <span>Painel Principal</span>
                    </a>
                </li>
                
                <li class="nav-item px-2 py-1">
                    <a href="abrir_ticket.php" id="ticket" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'abrir_ticket.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' ? 'page' : 'false'; ?>">
                        <i class="bi bi-ticket me-3 nav-menu-item" aria-hidden="true"></i> 
                        <span>Abrir Ticket</span>
                    </a>
                </li>
                  <li class="nav-item px-2 py-1">
                    <a href="meus_tickets.php" id="tickets" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'meus_tickets.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'meus_tickets.php' ? 'page' : 'false'; ?>">
                        <i class="bi bi-ticket-detailed me-3 nav-menu-item" aria-hidden="true"></i> 
                        <span>Os Meus Tickets</span>
                    </a>
                </li>
        
                
                <?php if (isset($_SESSION['Grupo']) && $_SESSION['Grupo'] == 'Admin'): ?>
                    <li class="nav-item px-2 py-1">
                        <a href="meus_contratos.php" id="cc" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'meus_contratos.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'meus_contratos.php' ? 'page' : 'false'; ?>">
                            <i class="bi bi-bank me-3 nav-menu-item" aria-hidden="true"></i> 
                            <span>Os Meus Contratos</span>
                        </a>
                    </li>
                    <li class="nav-item px-2 py-1">
                        <a href="cc.php" id="cc" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'cc.php' ? 'active' : ''; ?>" aria-current="<?php echo basename($_SERVER['PHP_SELF']) == 'cc.php' ? 'page' : 'false'; ?>">
                            <i class="bi bi-bank me-3 nav-menu-item" aria-hidden="true"></i> 
                            <span>Conta Corrente</span>
                        </a>
                    </li>
                    
                <?php endif; ?>                
                <!-- Dropdown para Manuais - implementação personalizada -->
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
                        <span>Iniciar Sessão</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>   
    <!-- Área do Usuário -->
    <?php if (isset($_SESSION['usuario_id'])): ?>
        <a href="user.php" class="mt-auto text-decoration-none user-profile-link p-3 d-flex align-items-center rounded-2 mx-2 mb-1 bg-light" title="Ver perfil de utilizador">
            <div class="user-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true">                <?php 
                if (isset($_SESSION['Nome'])) {
                    $primeira_letra = strtoupper($_SESSION['Nome'][0]); // Pega a primeira letra do nome e coloca em maiúscula
                    echo $primeira_letra;
                } elseif (isset($_SESSION['usuario_email'])) {
                    $email = $_SESSION['usuario_email'];
                    $primeira_letra = strtoupper($email[0]); // Pega a primeira letra do email e coloca em maiúscula
                    echo $primeira_letra;
                } else {
                    echo "U";
                }
                ?>
            </div>            <div class="ms-3 flex-grow-1 overflow-hidden">                <p class="fw-semibold m-0 text-truncate text-dark">
                    <?php echo isset($_SESSION['Nome']) ? htmlspecialchars($_SESSION['Nome']) : 'Utilizador'; ?>
                </p>
                <p class="small text-muted m-0 text-truncate">
                    <?php echo isset($_SESSION['usuario_email']) ? htmlspecialchars($_SESSION['usuario_email']) : 'email@exemplo.com'; ?>
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
    const sidebar = document.getElementById('sidebar');
    const manuaisDropdown = document.getElementById('manuaisDropdown');
    const manuaisCollapse = document.getElementById('manuaisCollapse');
    const hamburgerIcon = document.querySelector('.hamburger-icon');
    let menuIsOpen = false;
    
    // Função para alternar o estado do menu
    function toggleMenu(event) {
        // Prevenir comportamento padrão e propagação do evento
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        console.log("Menu toggle clicked - Current state: " + (menuIsOpen ? "open" : "closed"));
        
        if (!menuIsOpen) {
            // Abrir menu
            sidebar.classList.add('active');
            document.body.classList.add('sidebar-open');
            menuToggle.setAttribute('aria-expanded', 'true');
            
            // Alternar para o X - adicionando classe ao ícone
            hamburgerIcon.classList.add('open');
            
            // Mostrar overlay
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                overlay.style.display = 'block';
            }
            
            // Ajustar conteúdo no mobile
            if (window.innerWidth <= 991) {
                const contentElements = document.querySelectorAll('.content, .content-area');
                contentElements.forEach(element => {
                    element.style.marginLeft = '0';
                    element.style.width = '100%';
                });
            }
            
            menuIsOpen = true;
        } else {
            // Fechar menu
            closeSidebar();
        }
    }
    
    // Adicionar evento ao botão de hambúrguer para abertura e fechamento
    if (menuToggle) {
        ['click', 'touchstart'].forEach(eventType => {
            menuToggle.addEventListener(eventType, function(event) {
                // Usar preventDefault apenas em touchstart para evitar problemas
                if (eventType === 'touchstart') {
                    event.preventDefault();
                }
                toggleMenu(event);
            }, { passive: false });
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
        if (e.key === 'Escape' && menuIsOpen) {
            toggleMenu();
        }
    });
    
    // Função para fechar a sidebar
    function closeSidebar() {
        sidebar.classList.remove('active');
        document.body.classList.remove('sidebar-open');
        
        // Restaurar ícone de hambúrguer - removendo a classe
        hamburgerIcon.classList.remove('open');
        
        menuToggle.setAttribute('aria-expanded', 'false');
        
        // Esconder overlay
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.style.display = 'none';
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
        }
        
        menuIsOpen = false;
    }
});
</script>

<!-- Adicionar overlay caso não exista -->
<div class="sidebar-overlay position-fixed top-0 start-0 w-100 h-100 d-lg-none" style="z-index: 1045; display: none; background-color: rgba(0,0,0,0.5);"></div>

<!-- CSS para melhorar o botão hambúrguer -->
<style>
    /* Estilo para o botão hambúrguer */
    .hamburger-btn {
        transition: background-color 0.3s;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
    }
    
    /* Ícone hambúrguer animado */
    .hamburger-icon {
        width: 24px;
        height: 18px;
        position: relative;
        transform: rotate(0deg);
        transition: .5s ease-in-out;
        cursor: pointer;
    }

    .hamburger-icon span {
        display: block;
        position: absolute;
        height: 3px;
        width: 100%;
        background: #333;
        border-radius: 3px;
        opacity: 1;
        left: 0;
        transform: rotate(0deg);
        transition: .25s ease-in-out;
    }

    /* Posição inicial das linhas */
    .hamburger-icon span:nth-child(1) {
        top: 0px;
    }

    .hamburger-icon span:nth-child(2) {
        top: 8px;
    }

    .hamburger-icon span:nth-child(3) {
        top: 16px;
    }

    /* Animação para o X */
    .hamburger-icon.open span:nth-child(1) {
        top: 8px;
        transform: rotate(135deg);
        background: #dc3545; /* Cor vermelha para o X */
    }

    .hamburger-icon.open span:nth-child(2) {
        opacity: 0;
        left: -60px;
    }

    .hamburger-icon.open span:nth-child(3) {
        top: 8px;
        transform: rotate(-135deg);
        background: #dc3545; /* Cor vermelha para o X */
    }
    
    /* Garantir que o sidebar fica acima de outros elementos */
    .sidebar-helpdesk.active {
        z-index: 1046;
    }
    
    /* Em dispositivos móveis, garantir que o botão está sempre visível */
    @media (max-width: 991.98px) {
        .hamburger-btn {
            position: fixed !important;
            z-index: 1060 !important;
        }
    }
</style>