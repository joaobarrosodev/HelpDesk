<!-- Add a hamburger menu for mobile and tablet view -->
<style>
    .hamburger-menu {
        display: none;
        cursor: pointer;
    }

    .hamburger-menu div {
        width: 25px;
        height: 3px;
        background-color: #fff;
        margin: 5px 0;
    }

    @media (max-width: 768px) {
        .sidebar {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 250px;
            height: 100%;
            background-color: #333;
            z-index: 1000;
            padding: 20px;
        }

        .sidebar.active {
            display: block;
        }

        .hamburger-menu {
            display: block;
        }
    }
</style>

<div class="hamburger-menu" onclick="toggleSidebar()">
    <div></div>
    <div></div>
    <div></div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('active');
    }
</script>

<!-- Sidebar -->
<div class="sidebar ">
    <div class="text-center mb-4">
        <a href="index.php"> <img src="img/logo.png" alt="Logo" class="img-fluid"></a>
    </div>
    <a href="index.php" id="home" class="btn-link text-light"><i class="bi bi-house"></i> Página Inicial</a>
    
    <!-- Se o utilizador tiver o login feito, aparecem estes menus -->
    <?php if (isset($_SESSION['usuario_id'])): ?>
        <a href="ticket.php" id="ticket" class="btn-link text-light"><i class="bi bi-ticket"></i> Novo Ticket</a>
        <a href="consultar_tickets.php" id="historic" class="btn-link text-light"><i class="bi bi-ticket-detailed"></i> Tickets em Aberto</a>

        <!-- Opção "Tickets Fechados" com tabulação -->
        <div class="submenu" style="margin-left: 30px;">
            <a href="consultar_tickets_fechados.php" id="closed-tickets" class="btn-link text-light"><i class="bi bi-ticket-fill"></i> Tickets Fechados</a>
        </div>

        <?php if ($_SESSION['Grupo'] == 'Admin'): ?>
            <!-- Somente Admin pode ver esses links -->
            <a href="lic.php" id="historic" class="btn-link text-light"><i class="bi bi-code-square"></i> Consultar Licenças</a>
            <a href="cc.php" id="historic" class="btn-link text-light"><i class="bi bi-bank"></i> Consultar Extrato Conta Corrente</a>
        <?php endif; ?>

    <?php else: ?>
        <!-- Se não tiver o Login feito aparece apenas a opção para fazer login -->
        <a href="login.php" id="login">Iniciar Sessão</a>
    <?php endif; ?>
</div>

 <!-- Barra Superior -->
    <div class="topbar">
        <div class="brand-name">
            <strong>Minha Empresa</strong>
        </div>
<?php
// Verifica se a sessão foi iniciada e se o email do usuário está presente
if (isset($_SESSION['usuario_email']) && !empty($_SESSION['usuario_email'])):
?>
    <div class="contact-info">
        <span>Bem-vindo 
            <a href="user.php" data-toggle="tooltip" title="Conta: <?php echo $_SESSION['usuario_email']; ?>">
                <!-- Cria o círculo com a primeira letra do email -->
                <div class="user-avatar">
                    <?php 
                    $email = $_SESSION['usuario_email'];
                    $primeira_letra = strtoupper($email[0]); // Pega a primeira letra do email e coloca em maiúscula
                    echo $primeira_letra;
                    ?>
                </div>
            </a>
        </span>

        <span> 
            <a href="logout.php">
                <!-- Cria o círculo com a palavra "Sair" -->
                <div class="user-logout">
                    <?php 
                    $primeira_letra = "Sair"; // Texto "Sair"
                    echo $primeira_letra;
                    ?>
                </div>
            </a>
        </span>
    </div>
<?php
endif; // Fim da verificação da sessão
?>

    </div>