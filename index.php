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

        if (!$cc) {      
            echo "Ticket não encontrado.";
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

        <div class="d-flex justify-content-between align-items-center mb-4 flex-column flex-lg-row">            <div>
                <h1 class="mb-3 display-5">Bem Vindo, <span class="text-primary"><?php echo isset($_SESSION['Nome']) ? htmlspecialchars($_SESSION['Nome']) : 'Utilizador'; ?></span> </h1>
                <p class="text-muted mb-3 w-100">Aqui pode acompanhar os seus tickets, ver o estado de cada pedido de suporte, e consultar informações importantes em tempo real.</p>
            </div>
            <a href="ticket.php" class="btn btn-primary btn-primary">Abrir Novo Ticket</a>
        </div>

        <!-- Dashboard Cards -->
        <div class="row">
            <div class="col-xl-4 col-md-6 mb-4 flex-1">
                <div class="card dashboard-card">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Categoria dos Tickets</h5>
                        <div class="chart-container">
                            <canvas id="categoriaTicketsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6 mb-4 flex-1">
                
                <div class="col-12 mb-4">
                    <div class="card dashboard-card">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">Prioridade dos Tickets</h5>
                            <canvas id="prioridadeTicketsChart"></canvas> 
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="flex-row d-flex card-body" style="gap: 10px;">
                            <p class="m-0">Tempo Médio de Resposta</p>
                            <p class="m-0 fw-bold text-primary"><?php echo $tempo_medio_resposta; ?>min</p>
                        </div>
                    </div>
            
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6 mb-4 flex-1">
                <div class="card dashboard-card h-100 ">
                    <div class="card-body">
                        <h5 class="card-title">Avaliação dos Clientes</h5>
                        <div class="row w-100">
                            <div class="col-6 d-flex justify-content-center align-items-center flex-column mb-3">
                                <p class="w-100 text-muted">Respostas:</p>
                                <p class="w-100"><strong><?php echo $respostas_recebidas; ?> Clientes</strong></p>
                            </div>

                            <div class="col-6 d-flex justify-content-center align-items-center flex-row mb-3">
                                    <i class="bi bi-hand-thumbs-up-fill text-success fs-4"></i>
                                    <div class="flex-column w-100 justify-content-center align-items-center d-flex">
                                        <span class="text-muted">Positivo</span>
                                            <div class="progress w-75 mt-1"  style=" height: 6px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $positive_percentage; ?>%"></div>
                                            </div>       
                                    </div>
                                    <div>
                                        <span class="client-rating-number"><?php echo $positive_percentage; ?>%</span>
                                    </div>
                            </div>

                            <div class="col-6 d-flex justify-content-center align-items-center flex-row mb-3">
                                 <i class="bi bi-hand-thumbs-down-fill text-danger fs-4"></i>
                                <div class="flex-column w-100 justify-content-center align-items-center d-flex">
                                    <span class="text-muted">Negativo</span>
                                    <div class="progress w-75 mt-1"  style=" height: 6px;">
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $negative_percentage; ?>%"></div>
                                    </div>
                                </div>
                                <span class="client-rating-number"><?php echo $negative_percentage; ?>%</span>
                            </div>

                            <div class="col-6 d-flex justify-content-center align-items-center flex-row mb-3">
                                 <i class="bi bi-emoji-neutral-fill text-warning fs-4"></i>   
                                <div class="flex-column w-100 justify-content-center align-items-center d-flex">
                                    <span class="text-muted">Neutro</span>
                                    <div class="progress w-75 mt-1"  style=" height: 6px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $neutral_percentage; ?>%"></div>
                                   </div>
                                </div>
                                <span class="client-rating-number"><?php echo $neutral_percentage; ?>%</span>
                            </div>
                        </div>
                    </div>
        </div>
    </div> 

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
                            '#529ebe', // XD (Blue)
                            '#ffc107', // Impressoras (Yellow)
                            '#dc3545'  // Office (Red)
                        ],
                        borderColor: '#fff',
                        borderWidth: 5
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
            new Chart(prioridadeCtx, {                type: 'bar',
                data: {
                    labels: <?php echo json_encode($prioridade_labels); ?>,
                    datasets: [{
                        label: 'Prioridade',
                        data: <?php echo json_encode($prioridade_counts); ?>,                        backgroundColor: [
                            '#000000', // All bars in black
                            '#000000',
                            '#000000'
                        ],
                        borderWidth: 0,
                        categoryPercentage: 1.0, // Use full category width
                        barPercentage: 0.4 // Make bars take 40% of category height, leaving 60% as spacing
                    }]
                },                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            right: 30 // Add padding for percentage labels
                        }
                    },
                    barThickness: 18, // Keep medium bar thickness
                    maxBarThickness: 18,
                    minBarLength: 2,
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                display: false,
                            },
                            display: false // Hide x-axis
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                padding: 1, // Minimal padding
                                font: {
                                    size: 12 // Smaller font for labels
                                }
                            },
                            afterFit: function(scaleInstance) {
                                // Make the height smaller to reduce spacing
                                scaleInstance.height = 80;
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: false
                        },
                        datalabels: {
                            align: 'end',
                            anchor: 'end',
                            color: '#000',
                            font: {
                                weight: 'bold'
                            },
                            formatter: function(value, context) {
                                return value + '%';
                            }
                        }
                    }
                },                plugins: [{
                    id: 'compactBars',
                    beforeLayout: function(chart) {
                        // Force chart to be compact
                        chart.height = 100;
                    },
                    afterDraw: function(chart) {
                        const ctx = chart.ctx;
                        const yAxis = chart.scales.y;
                        const xAxis = chart.scales.x;
                        
                        // Draw percentage values
                        chart.data.datasets[0].data.forEach((dataValue, index) => {
                            const yPosition = yAxis.getPixelForTick(index);
                            const xPosition = xAxis.getPixelForValue(dataValue) + 10;
                            
                            ctx.fillStyle = "#000";
                            ctx.font = "bold 12px Arial";
                            ctx.textAlign = "left";
                            ctx.textBaseline = "middle";
                            ctx.fillText(dataValue + "%", xPosition, yPosition);
                        });
                    }
                }]
            });
        });
    </script>
</body>
</html>