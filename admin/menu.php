<?php
// Make sure we have the session started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include conflogin.php to get access to authentication functions like isAdmin()
include_once(__DIR__ . '/conflogin.php');
?>

<?php
/**
 * Menu principal do sistema HelpDesk - Seção Admin
 * Contém a navegação lateral com links para as principais funcionalidades administrativas
 */
?>

<!-- Botão hambúrguer que se transforma em X no mesmo local -->
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
            <img src="../img/logo.png" alt="Info.exe - Logótipo" class="img-fluid" width="120" height="40">
        </a>
        <!-- Botão X interno removido completamente -->
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

                <!-- Menus apenas para Admin (FULL ACCESS) -->
                <?php if (isAdmin()): ?> <!-- CORRECT: Only Admin users see these menus -->
                    <li class="nav-item px-2 py-1">
                        <a href="tickets_sem_atribuicao.php" id="tickets" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'tickets_sem_atribuicao.php' ? 'active' : ''; ?>">
                            <i class="bi bi-ticket me-3 " aria-hidden="true"></i>
                            <span>Tickets sem Atribuição</span>
                        </a>
                    </li>
                <?php endif; ?>
                <!-- Menus para Admin e Comum (BOTH ACCESS LEVELS) -->
                <li class="nav-item px-2 py-1">
                    <a href="tickets_atribuidos.php" id="tickets-atribuidos" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'tickets_atribuidos.php' ? 'active' : ''; ?>">
                        <i class="bi bi-ticket-detailed me-3 " aria-hidden="true"></i>
                        <span><?php echo isAdmin() ? 'Tickets Atribuídos' : 'Os Meus Tickets'; ?></span>
                    </a>
                </li>

                <?php if (isAdmin()): ?> <!-- CORRECT: Only Admin users see these menus -->
                    <li class="nav-item px-2 py-1">
                        <a href="consultar_tickets.php" id="tickets-todos" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'consultar_tickets.php' ? 'active' : ''; ?>">
                            <i class="bi bi-search me-3 " aria-hidden="true"></i>
                            <span>Consultar Tickets</span>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item px-2 py-1">
                    <a href="tickets_fechados.php" id="tickets-all" class="menu-link nav-link rounded-2 px-3 py-2 d-flex align-items-center text-dark <?php echo basename($_SERVER['PHP_SELF']) == 'tickets_fechados.php' ? 'active' : ''; ?>">
                        <i class="bi bi-list-check me-3 " aria-hidden="true"></i>
                        <span><?php echo isAdmin() ? 'Tickets Fechados' : 'Os Meus Tickets Fechados'; ?></span>
                    </a>
                </li>

                <?php if (isAdmin()): ?>
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
            <div class="user-avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true"> <?php
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
            </div>
            <div class="ms-3 flex-grow-1 overflow-hidden">
                <p class="fw-semibold m-0 text-truncate text-dark">
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
<div class="sidebar-overlay position-fixed top-0 start-0 w-100 h-100 d-lg-none" style="z-index: 1045; display: none; background-color: rgba(0,0,0,0.5);"></div>

<!-- Scripts para funcionamento do menu -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const hamburgerIcon = document.querySelector('.hamburger-icon');
        let menuIsOpen = false;

        // Função para alternar o estado do menu
        function toggleMenu(event) {
            // Prevenir comportamento padrão e propagação do evento
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            console.log("Admin menu toggle clicked - Current state: " + (menuIsOpen ? "open" : "closed"));
            
            if (!menuIsOpen) {
                // IMPORTANTE: Alterado de 'show-sidebar' para 'active' para corresponder ao menu do cliente
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
                
                menuIsOpen = true;
            } else {
                // Fechar menu
                hideSidebar();
            }
        }

        // Função para esconder menu
        function hideSidebar() {
            // IMPORTANTE: Alterado de 'show-sidebar' para 'active' para corresponder ao menu do cliente
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
            
            menuIsOpen = false;
        }

        // Toggle menu no mobile - múltiplos eventos para melhor resposta
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

        // Fechar ao clicar no overlay
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', function(event) {
                event.preventDefault();
                toggleMenu();
            });
        }
        
        // Fechar com tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && menuIsOpen) {
                toggleMenu();
            }
        });
    });
</script>

<!-- Estilos CSS para o hambúrguer animado que se transforma em X -->
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
    
    /* IMPORTANTE: Alterado de .sidebar-helpdesk.show-sidebar para .sidebar-helpdesk.active */
    .sidebar-helpdesk.active {
        z-index: 1046;
    }
    
    /* Classe para mostrar o menu móvel */
    .sidebar-helpdesk {
        left: -300px; /* Começar fora da tela */
        transition: all 0.3s ease-in-out;
        position: fixed;
        top: 0;
        bottom: 0;
        width: 300px;
        overflow-y: auto;
    }

    /* Quando ativado */
    .sidebar-helpdesk.active {
        left: 0; /* Mover para dentro da tela */
        box-shadow: 0 0 20px rgba(0,0,0,0.3);
    }
    
    /* Em dispositivos móveis, garantir que o botão está sempre visível */
    @media (max-width: 991.98px) {
        .hamburger-btn {
            position: fixed !important;
            z-index: 1060 !important;
        }
    }
</style>