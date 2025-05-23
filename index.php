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

        // Contar tickets em aberto para o usuário atual
        $sql_tickets = "SELECT COUNT(*) FROM info_xdfree01_extrafields WHERE (status = 'Em Análise' OR status = 'Em Resolução' OR status = ' Aguarda Resposta') AND Entity = :usuario_id";
        $stmt_tickets = $pdo->prepare($sql_tickets);
        $stmt_tickets->bindParam(':usuario_id', $_SESSION['usuario_id']);
        $stmt_tickets->execute();
        $ticket_count = $stmt_tickets->fetchColumn();

        // Obter dados para o gráfico de categorias (baseado nos assuntos dos tickets)
        $sql_categorias = "SELECT User as categoria, COUNT(*) as total FROM info_xdfree01_extrafields 
                          WHERE Entity = :usuario_id 
                          GROUP BY User 
                          ORDER BY total DESC";
        $stmt_categorias = $pdo->prepare($sql_categorias);
        $stmt_categorias->bindParam(':usuario_id', $_SESSION['usuario_id']);
        $stmt_categorias->execute();
        $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
        
        $categoria_labels = [];
        $categoria_counts = [];
        
        foreach ($categorias as $categoria) {
            $categoria_labels[] = $categoria['categoria'];
            $categoria_counts[] = $categoria['total'];
        }
        
        // Se não houver dados, criar alguns valores padrão para evitar erros no gráfico
        if (empty($categoria_labels)) {
            $categoria_labels = ['E-mail', 'XD', 'Impressoras', 'Office'];
            $categoria_counts = [0, 0, 0, 0];
        }

        // Obter dados para o gráfico de prioridade
        $sql_prioridade = "SELECT Priority as prioridade, COUNT(*) as total FROM info_xdfree01_extrafields 
                          WHERE Entity = :usuario_id 
                          GROUP BY Priority 
                          ORDER BY FIELD(Priority, 'Alta', 'Normal', 'Baixa')";
        $stmt_prioridade = $pdo->prepare($sql_prioridade);
        $stmt_prioridade->bindParam(':usuario_id', $_SESSION['usuario_id']);
        $stmt_prioridade->execute();
        $prioridades = $stmt_prioridade->fetchAll(PDO::FETCH_ASSOC);
        
        // Arrays para armazenar os dados de prioridade
        $prioridade_labels = [];
        $prioridade_counts = [];
        $prioridade_total = 0;
        
        foreach ($prioridades as $prioridade) {
            $prioridade_labels[] = $prioridade['prioridade'];
            $prioridade_counts[] = $prioridade['total'];
            $prioridade_total += $prioridade['total'];
        }
        
        // Converter contagens para percentagens
        if ($prioridade_total > 0) {
            foreach ($prioridade_counts as &$count) {
                $count = round(($count / $prioridade_total) * 100);
            }
        }
        
        // Se não houver dados, criar alguns valores padrão
        if (empty($prioridade_labels)) {
            $prioridade_labels = ['Alta', 'Normal', 'Baixa'];
            $prioridade_counts = [0, 0, 0];
        }
        
        // Calcular o tempo médio de resposta baseado na coluna Tempo
        $sql_tempo_resposta = "SELECT AVG(Tempo) as media_tempo FROM info_xdfree01_extrafields 
                              WHERE Tempo IS NOT NULL AND Tempo > 0";
        $stmt_tempo = $pdo->prepare($sql_tempo_resposta);
        $stmt_tempo->execute();
        $tempo_result = $stmt_tempo->fetch(PDO::FETCH_ASSOC);
        
        // Formatar o tempo médio de resposta - sem arredondamento para cima
        if ($tempo_result && isset($tempo_result['media_tempo']) && !is_null($tempo_result['media_tempo'])) {
            $minutos_totais = (int)$tempo_result['media_tempo']; // Cast para int em vez de arredondar
            $horas = floor($minutos_totais / 60);
            $minutos = $minutos_totais % 60;
            
            if ($horas > 0) {
                $tempo_medio_resposta = $horas . ":" . str_pad($minutos, 2, '0', STR_PAD_LEFT);
            } else {
                $tempo_medio_resposta = $minutos;
            }
        } else {
            // Valor padrão caso não haja dados
            $tempo_medio_resposta = "0";
        }
        
        // Data for Avaliação dos Clientes
        // Normalmente, isso viria de uma tabela de avaliações de clientes
        // Como não temos essa tabela nos arquivos fornecidos, usaremos valores padrão
        $respostas_recebidas = 156;
        $positive_percentage = 72;
        $negative_percentage = 4;
        $neutral_percentage = 24;

        ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-column flex-lg-row">
            <div>
                <h1 class="mb-3 display-5">Bem Vindo, <span class="text-primary"><?php echo isset($_SESSION['Nome']) ? htmlspecialchars($_SESSION['Nome']) : 'Utilizador'; ?></span> </h1>
                <p class="text-muted mb-3 w-100">Aqui pode acompanhar os seus tickets, ver o estado de cada pedido de suporte, e consultar informações importantes em tempo real.</p>
            </div>
            <a href="abrir_ticket.php" class="btn btn-primary btn-primary">Abrir Novo Ticket</a>
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
               
                <div class="col-12 mt-3">
                    <div class="card dashboard-card">
                        <div class="flex-row d-flex card-body" style="gap: 10px;">
                            <p class="m-0">Tempo Médio de Resposta</p>
                            <p class="m-0 fw-bold text-primary"><?php echo $tempo_medio_resposta; ?> min</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Avaliação dos Clientes -->
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
                            '#4A90E2', // Medium blue
                            '#7ED321', // Medium green
                            '#F5A623', // Medium orange/yellow
                            '#D0021B', // Medium red
                            '#9013FE', // Medium purple
                            '#FF6B35', // Medium orange
                            '#50E3C2', // Medium teal
                            '#BD10E0'  // Medium magenta
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
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.parsed || 0;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    
                                    return `${label}: ${value} (${percentage}%)`;
                                }
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
                            '#000000', // All bars in black
                            '#000000',
                            '#000000'
                        ],
                        borderWidth: 0,
                        categoryPercentage: 1.0, // Use full category width
                        barPercentage: 0.4 // Make bars take 40% of category height, leaving 60% as spacing
                    }]
                },
                options: {
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
                },
                plugins: [{
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