<!-- Sidebar -->
<div class="sidebar ">
    <div class="text-center mb-4">
        <a href="index.php"> <img src="img/logo.png" alt="Logo" class="img-fluid"></a>
    </div>
    <a href="index.php" id="home" class="btn-link text-light"><i class="bi bi-house"></i> Página Inicial</a>    
    <!-- Se o admin tiver o login feito, aparecem estes menus -->
    <?php if (isset($_SESSION['admin_id'])): ?>
        <a href="consultar_tickets.php" id="ticket" class="btn-link text-light"><i class="bi bi-ticket"></i> Tickets por Atribuir</a>
        <a href="tickets_atribuidos.php" id="historic" class="btn-link text-light"><i class="bi bi-ticket-detailed"></i> Tickets Atribuidos</a>

        <!-- Opção "Tickets Fechados" com tabulação -->
        <div class="submenu" style="margin-left: 30px;">
            <a href="consultar_tickets_fechados.php" id="closed-tickets" class="btn-link text-light"><i class="bi bi-ticket-fill"></i> Tickets Fechados</a>
        </div>
    <?php else: ?>
        <!-- Se não tiver o Login feito aparece apenas a opção para fazer login -->
        <a href="login.php" id="login">Login</a>
    <?php endif; ?>
</div>




 <!-- Barra Superior -->
    <div class="topbar">
        <div class="brand-name">
            <strong>Minha Empresa</strong>
        </div>
<?php
// Verifica se a sessão foi iniciada e se o email do usuário está presente
if (isset($_SESSION['admin_email']) && !empty($_SESSION['admin_email'])):
?>
    <div class="contact-info">
        <span>Bem-vindo 
            <a href="user.php" data-toggle="tooltip" title="Conta: <?php echo $_SESSION['admin_email']; ?>">
                <!-- Cria o círculo com a primeira letra do email -->
                <div class="user-avatar">
                    <?php 
                    $email = $_SESSION['admin_email'];
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