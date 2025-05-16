<?php
session_start();
include('conflogin.php');
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>

    <?php include('menu.php'); ?>
    <div class="content p-5 content-area">        
        <?php
        include('db.php');

        // Consultar Conta Corrente em Aberto
        $sql = "SELECT * FROM entities WHERE keyid = :keyid";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':keyid', $_SESSION['usuario_id']);
        $stmt->execute();
        $cc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cc) {            echo "Ticket não encontrado.";
            exit;
        }


        $valor = $cc['Balance'];
        $valor_formatado = number_format(abs($valor), 2, ',', '.');
        // Corrigindo a lógica das cores:
        if ($valor_formatado > 0) {
            $cor = 'bg-danger'; // Se houver dívida, fica vermelho
        } else {
            $cor = 'bg-success'; // Se houver crédito, fica verde
        }

        $sql_tickets = "SELECT COUNT(*) FROM info_xdfree01_extrafields WHERE (status = 'Em Análise' OR status = 'Em Resolução' OR status = 'Aguarda Resposta Cliente') AND Entity = :usuario_id";
        $stmt_tickets = $pdo->prepare($sql_tickets);
        $stmt_tickets->bindParam(':usuario_id', $_SESSION['usuario_id']);
        $stmt_tickets->execute();
        $ticket_count = $stmt_tickets->fetchColumn();


        $categoria_labels = ['E-mail', 'XD', 'Impressoras', 'Office'];
        $categoria_counts = [12, 19, 3, 5];


      
        $prioridade_labels = ['Baixo', 'Médio', 'Alto'];
        $prioridade_counts = [71, 22, 7];
        
        // Data for Avaliação dos Clientes
        $respostas_recebidas = 156;
        $positive_percentage = 72;
        $negative_percentage = 4;
        $neutral_percentage = 24;

        // Data for Tempo Médio de Resposta
        $tempo_medio_resposta = "4:34";
        ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-3 display-5">Bem Vindo, <?php echo htmlspecialchars($_SESSION['Nome']); ?></h1>
                <p class="text-muted m-0 w-100">Aqui pode acompanhar todos os seus tickets, ver o estado de cada pedido de suporte, e consultar informações importantes em tempo real. Utilize esta área para monitorizar o progresso das suas solicitações e garantir um acompanhamento eficaz.</p>
            </div>
            <a href="ticket.php" class="btn btn-primary btn-primary"><i class="bi bi-plus-circle me-2"></i>Abrir Novo Ticket</a>
        </div>

        <!-- Dashboard Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card dashboard-card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Categoria dos Tickets</h5>
                        <div class="mt-auto chart-container">
                            <canvas id="categoriaTicketsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card dashboard-card h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Prioridade dos Tickets</h5>
                        <div class="mt-auto chart-container">
                            <canvas id="prioridadeTicketsChart"></canvas> 
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Avaliação dos Clientes</h5>
                        <p class="text-muted mb-2 card-subtitle">Respostas Recebidas: <strong><?php echo $respostas_recebidas; ?> Clientes</strong></p>

                        <div class="evaluation-item">
                            <div class="icon icon-positive"><i class="bi bi-hand-thumbs-up-fill"></i></div>
                            <div class="details">
                                <div class="label-percent"><span>Positive</span><span><strong><?php echo $positive_percentage; ?>%</strong></span></div>
                                <div class="progress" style="height: 6px;"><div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $positive_percentage; ?>%;" aria-valuenow="<?php echo $positive_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                            </div>
                        </div>
                        <div class="evaluation-item mt-2">
                            <div class="icon icon-negative"><i class="bi bi-hand-thumbs-down-fill"></i></div>
                            <div class="details">
                                <div class="label-percent"><span>Negative</span><span><strong><?php echo $negative_percentage; ?>%</strong></span></div>
                                <div class="progress" style="height: 6px;"><div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $negative_percentage; ?>%;" aria-valuenow="<?php echo $negative_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                            </div>
                        </div>
                        <div class="evaluation-item mt-2">
                            <div class="icon icon-neutral"><i class="bi bi-emoji-neutral-fill"></i></div>
                            <div class="details">
                                <div class="label-percent"><span>Neutral</span><span><strong><?php echo $neutral_percentage; ?>%</strong></span></div>
                                <div class="progress" style="height: 6px;"><div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $neutral_percentage; ?>%;" aria-valuenow="<?php echo $neutral_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                 <div class="card dashboard-card h-100">
                    <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                        <h5 class="card-title mb-3">Tempo Médio de Resposta</h5>
                        <p class="display-4 fw-bold m-0 tempo-medio-valor"><?php echo $tempo_medio_resposta; ?> <span class="tempo-medio-unidade">min</span></p>
                    </div>
                </div>
            </div>
        </div>
        </div> <!-- This closes the div.content -->

        <!-- ISSUE Section - SKIPPED FOR NOW as per user request -->
        <?php /*
        <div class="card mt-4">
            <div class="card-header issue-table-header d-flex justify-content-between align-items-center py-3">
        // ... (rest of the ISSUE section HTML commented out or removed)
        </div>
        */ ?>

    </div> <!-- This was an extra closing div, ensure it matches your layout or remove if not needed -->


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Categoria dos Tickets Chart (Doughnut)
            const categoriaCtx = document.getElementById('categoriaTicketsChart').getContext('2d');
            new Chart(categoriaCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($categoria_labels); ?>,
                    datasets: [{
                        label: 'Categoria dos Tickets',
                        data: <?php echo json_encode($categoria_counts); ?>,
                        backgroundColor: [
                            '#28a745', // E-mail (Green)
                            '#007bff', // XD (Blue)
                            '#ffc107', // Impressoras (Yellow)
                            '#dc3545'  // Office (Red)
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        }
                    }
                }
            });

            // Prioridade dos Tickets Chart (Horizontal Bar)
            const prioridadeCtx = document.getElementById('prioridadeTicketsChart').getContext('2d');
            new Chart(prioridadeCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($prioridade_labels); ?>,
                    datasets: [{
                        label: 'Prioridade',
                        data: <?php echo json_encode($prioridade_counts); ?>,
                        backgroundColor: [
                            '#28a745', // Baixo
                            '#ffc107', // Médio
                            '#dc3545'  // Alto
                        ],
                        borderColor: [
                            '#28a745',
                            '#ffc107',
                            '#dc3545'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + "%"
                                }
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false // Hide legend for this chart as per image
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>