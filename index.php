<?php
session_start();
include('conflogin.php');
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>

    <?php include('menu.php'); ?>
    <div class="content">
        <h2 class="text-center">Bem-vindo à página principal, <?php echo $_SESSION['Nome']; ?>!</h2>
        <p class="text-center">Conteúdo exclusivo para clientes.</p>

        <?php
        include('db.php');

        // Consultar Conta Corrente em Aberto
        $sql = "SELECT * FROM entities WHERE keyid = :keyid";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':keyid', $_SESSION['usuario_id']);
        $stmt->execute();
        $cc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cc) {
            echo "Ticket não encontrado.";
            exit;
        }

        echo "<p style='font-weight: bold;'> Informações Gerais da Empresa:</p>";
        $valor = $cc['Balance'];
        $valor_formatado = number_format(abs($valor), 2, ',', '.');
        // Corrigindo a lógica das cores:
        if ($valor_formatado > 0) {
            $cor = 'bg-danger'; // Se houver dívida, fica vermelho
        } else {
            $cor = 'bg-success'; // Se houver crédito, fica verde
        }

        // Formatar o número corretamente, SEM o sinal negativo na exibição
        

        // Consultar número de tickets abertos
        $sql_tickets = "SELECT COUNT(*) FROM info_xdfree01_extrafields WHERE (status = 'Em Análise' OR status = 'Em Resolução' OR status = 'Aguarda Resposta Cliente') AND Entity = :usuario_id";
        $stmt_tickets = $pdo->prepare($sql_tickets);
        $stmt_tickets->bindParam(':usuario_id', $_SESSION['usuario_id']);
        $stmt_tickets->execute();
        $ticket_count = $stmt_tickets->fetchColumn();
        ?>

        <!-- Exibir Conta Corrente e Tickets na mesma linha -->
        <div class="row">
            <!-- Conta Corrente -->
            <div class='col-md-2'>
                <a href='cc.php' style='text-decoration: none; color: inherit;'>
                    <div class='alert <?php echo $cor; ?> text-white p-3' role='alert' style='border-radius: 10px;'>
                        <div class='d-flex align-items-center'>
                            <i class='bi bi-bank' style='font-size: 20px; margin-right: 10px;'></i>
                            <h6 class='m-0' style='font-size: 14px; font-weight: bold;'>Conta Corrente</h6>
                        </div>
                        <p class='text-left' style='font-size: 24px; font-weight: bold;'>€ <?php echo $valor_formatado; ?></p>
                    </div>
                </a>
            </div>

            <!-- Tickets Abertos -->
            <div class='col-md-2'>
                <a href='consultar_tickets.php' style='text-decoration: none; color: inherit;'>
                    <div class='alert <?php echo ($ticket_count > 0 ? "bg-danger" : "bg-success"); ?> text-white p-3' role='alert' style='border-radius: 10px;'>
                        <div class='d-flex align-items-center'>
                            <i class='bi bi-ticket' style='font-size: 20px; margin-right: 10px;'></i>
                            <h6 class='m-0' style='font-size: 14px; font-weight: bold;'>Tickets Abertos</h6>
                        </div>
                        <p class='text-center' style='font-size: 24px; font-weight: bold;'><?php echo $ticket_count; ?> Tickets</p>
                    </div>
                </a>
            </div>
        </div>

    </div>  

    <!-- Scripts do Bootstrap e JQuery -->
    <script src="script/script.js"></script>    
</body>
</html>
