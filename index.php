<?php
//index.php client side
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
        // Update ticket count query based on user role
        if (isAdmin()) {
            // Admins see statistics for tickets from their entity
            $admin_entity_sql = "SELECT Entity_KeyId FROM online_entity_extrafields WHERE email = :admin_email";
            $admin_stmt = $pdo->prepare($admin_entity_sql);
            $admin_stmt->bindParam(':admin_email', $_SESSION['usuario_email']);
            $admin_stmt->execute();
            $admin_entity = $admin_stmt->fetch(PDO::FETCH_ASSOC);
            
            $sql_tickets = "SELECT COUNT(*) FROM info_xdfree01_extrafields i
                           INNER JOIN online_entity_extrafields oee ON i.CreationUser = oee.email
                           WHERE (i.status = 'Em Análise' OR i.status = 'Em Resolução' OR i.status = ' Aguarda Resposta')
                           AND oee.Entity_KeyId = :usuario_entity_id";
            
            $stmt_tickets = $pdo->prepare($sql_tickets);
            $stmt_tickets->bindParam(':usuario_entity_id', $admin_entity['Entity_KeyId']);
        } else {
            // Common users see statistics only for their own tickets
            $sql_tickets = "SELECT COUNT(*) FROM info_xdfree01_extrafields i
                           WHERE (i.status = 'Em Análise' OR i.status = 'Em Resolução' OR i.status = ' Aguarda Resposta') 
                           AND i.CreationUser = :usuario_email";
            
            $stmt_tickets = $pdo->prepare($sql_tickets);
            $stmt_tickets->bindParam(':usuario_email', $_SESSION['usuario_email']);
        }

        $stmt_tickets->execute();
        $ticket_count = $stmt_tickets->fetchColumn();

        // Get total tickets count (all tickets, not just open ones)
        if (isAdmin()) {
            // Admins see total tickets from their entity
            $sql_total_tickets = "SELECT COUNT(*) FROM info_xdfree01_extrafields i
                                 INNER JOIN online_entity_extrafields oee ON i.CreationUser = oee.email
                                 WHERE oee.Entity_KeyId = :usuario_entity_id";
            
            $stmt_total_tickets = $pdo->prepare($sql_total_tickets);
            $stmt_total_tickets->bindParam(':usuario_entity_id', $admin_entity['Entity_KeyId']);
        } else {
            // Common users see only their own total tickets
            $sql_total_tickets = "SELECT COUNT(*) FROM info_xdfree01_extrafields i
                                 WHERE i.CreationUser = :usuario_email";
            
            $stmt_total_tickets = $pdo->prepare($sql_total_tickets);
            $stmt_total_tickets->bindParam(':usuario_email', $_SESSION['usuario_email']);
        }

        $stmt_total_tickets->execute();
        $total_tickets_count = $stmt_total_tickets->fetchColumn();

        // Obter dados para o gráfico de categorias (baseado nos assuntos dos tickets)
        // Update category chart data based on user role
        if (isAdmin()) {
            // Admins see categories from tickets in their entity
            $sql_categorias = "SELECT i.User as categoria, COUNT(*) as total FROM info_xdfree01_extrafields i
                              INNER JOIN online_entity_extrafields oee ON i.CreationUser = oee.email
                              WHERE i.User IS NOT NULL
                              AND oee.Entity_KeyId = :usuario_entity_id
                              GROUP BY i.User 
                              ORDER BY total DESC";
            
            $stmt_categorias = $pdo->prepare($sql_categorias);
            $stmt_categorias->bindParam(':usuario_entity_id', $admin_entity['Entity_KeyId']);
        } else {
            // Common users see categories only from their tickets
            $sql_categorias = "SELECT i.User as categoria, COUNT(*) as total FROM info_xdfree01_extrafields i
                              WHERE i.CreationUser = :usuario_email
                              AND i.User IS NOT NULL
                              GROUP BY i.User 
                              ORDER BY total DESC";
            
            $stmt_categorias = $pdo->prepare($sql_categorias);
            $stmt_categorias->bindParam(':usuario_email', $_SESSION['usuario_email']);
        }

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
        // Update priority chart data based on user role
        if (isAdmin()) {
            // Admins see priorities from tickets in their entity
            $sql_prioridade = "SELECT i.Priority as prioridade, COUNT(*) as total FROM info_xdfree01_extrafields i
                              INNER JOIN online_entity_extrafields oee ON i.CreationUser = oee.email
                              WHERE i.Priority IS NOT NULL
                              AND oee.Entity_KeyId = :usuario_entity_id
                              GROUP BY i.Priority 
                              ORDER BY FIELD(i.Priority, 'Alta', 'Normal', 'Baixa')";
            
            $stmt_prioridade = $pdo->prepare($sql_prioridade);
            $stmt_prioridade->bindParam(':usuario_entity_id', $admin_entity['Entity_KeyId']);
        } else {
            // Common users see priorities only from their tickets
            $sql_prioridade = "SELECT i.Priority as prioridade, COUNT(*) as total FROM info_xdfree01_extrafields i
                              WHERE i.CreationUser = :usuario_email
                              AND i.Priority IS NOT NULL
                              GROUP BY i.Priority 
                              ORDER BY FIELD(i.Priority, 'Alta', 'Normal', 'Baixa')";
            
            $stmt_prioridade = $pdo->prepare($sql_prioridade);
            $stmt_prioridade->bindParam(':usuario_email', $_SESSION['usuario_email']);
        }

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
        // Update average response time query based on user role
        if (isAdmin()) {
            // Admins see average time for tickets in their entity
            $sql_tempo_resposta = "SELECT AVG(i.Tempo) as media_tempo FROM info_xdfree01_extrafields i
                                  INNER JOIN online_entity_extrafields oee ON i.CreationUser = oee.email
                                  WHERE i.Tempo IS NOT NULL AND i.Tempo > 0 
                                  AND oee.Entity_KeyId = :usuario_entity_id";
            
            $stmt_tempo = $pdo->prepare($sql_tempo_resposta);
            $stmt_tempo->bindParam(':usuario_entity_id', $admin_entity['Entity_KeyId']);
        } else {
            // Common users see average time only for their tickets
            $sql_tempo_resposta = "SELECT AVG(i.Tempo) as media_tempo FROM info_xdfree01_extrafields i
                                  WHERE i.Tempo IS NOT NULL AND i.Tempo > 0 
                                  AND i.CreationUser = :usuario_email";
            
            $stmt_tempo = $pdo->prepare($sql_tempo_resposta);
            $stmt_tempo->bindParam(':usuario_email', $_SESSION['usuario_email']);
        }

        $stmt_tempo->execute();
        $tempo_result = $stmt_tempo->fetch(PDO::FETCH_ASSOC);
        
        // Formatar o tempo médio de resposta - sem arredondamento para cima
        if ($tempo_result && isset($tempo_result['media_tempo']) && !is_null($tempo_result['media_tempo'])) {
            $minutos_totais = (int)$tempo_result['media_tempo']; // Cast para int em vez de arredondar
            $horas = floor($minutos_totais / 60);
            $minutos = $minutos_totais % 60;
            
            if ($horas > 0) {
                $tempo_medio_resposta = $horas . ":" . str_pad($minutos, 2, '0', STR_PAD_LEFT);
                $tempo_unidade = "h";
            } else {
                $tempo_medio_resposta = $minutos;
                $tempo_unidade = "min";
            }
        } else {
            // Valor padrão caso não haja dados
            $tempo_medio_resposta = "0";
            $tempo_unidade = "min";
        }
        
        // Obter dados reais da avaliação dos clientes da tabela info_xdfree01_extrafields
        // Update customer review data based on user role
        try {
            if (isAdmin()) {
                // Admins see reviews from tickets in their entity
                $sql_total = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields i
                             INNER JOIN online_entity_extrafields oee ON i.CreationUser = oee.email
                             WHERE i.Review IS NOT NULL
                             AND oee.Entity_KeyId = :usuario_entity_id";
                
                $stmt_total = $pdo->prepare($sql_total);
                $stmt_total->bindParam(':usuario_entity_id', $admin_entity['Entity_KeyId']);
                $stmt_total->execute();
                $respostas_recebidas = $stmt_total->fetchColumn();
                
                $sql_avaliacoes = "SELECT 
                                    SUM(CASE WHEN i.Review = 1 THEN 1 ELSE 0 END) as positivas,
                                    SUM(CASE WHEN i.Review = 2 THEN 1 ELSE 0 END) as neutras,
                                    SUM(CASE WHEN i.Review = 3 THEN 1 ELSE 0 END) as negativas
                                   FROM info_xdfree01_extrafields i
                                   INNER JOIN online_entity_extrafields oee ON i.CreationUser = oee.email
                                   WHERE i.Review IS NOT NULL
                                   AND oee.Entity_KeyId = :usuario_entity_id";
                
                $stmt_avaliacoes = $pdo->prepare($sql_avaliacoes);
                $stmt_avaliacoes->bindParam(':usuario_entity_id', $admin_entity['Entity_KeyId']);
            } else {
                // Common users see reviews only from their tickets
                $sql_total = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields i
                             WHERE i.Review IS NOT NULL AND i.CreationUser = :usuario_email";
                
                $stmt_total = $pdo->prepare($sql_total);
                $stmt_total->bindParam(':usuario_email', $_SESSION['usuario_email']);
                $stmt_total->execute();
                $respostas_recebidas = $stmt_total->fetchColumn();
                
                $sql_avaliacoes = "SELECT 
                                    SUM(CASE WHEN i.Review = 1 THEN 1 ELSE 0 END) as positivas,
                                    SUM(CASE WHEN i.Review = 2 THEN 1 ELSE 0 END) as neutras,
                                    SUM(CASE WHEN i.Review = 3 THEN 1 ELSE 0 END) as negativas
                                   FROM info_xdfree01_extrafields i
                                   WHERE i.Review IS NOT NULL AND i.CreationUser = :usuario_email";
                
                $stmt_avaliacoes = $pdo->prepare($sql_avaliacoes);
                $stmt_avaliacoes->bindParam(':usuario_email', $_SESSION['usuario_email']);
            }

            $stmt_avaliacoes->execute();
            $avaliacoes = $stmt_avaliacoes->fetch(PDO::FETCH_ASSOC);
            
            // Calcular percentagens
            if ($respostas_recebidas > 0) {
                $positive_percentage = round(($avaliacoes['positivas'] / $respostas_recebidas) * 100);
                $neutral_percentage = round(($avaliacoes['neutras'] / $respostas_recebidas) * 100);
                $negative_percentage = round(($avaliacoes['negativas'] / $respostas_recebidas) * 100);
            } else {
                // Valores padrão caso não haja dados
                $positive_percentage = 0;
                $neutral_percentage = 0;
                $negative_percentage = 0;
            }
        } catch (PDOException $e) {
            // Em caso de erro, definir valores padrão
            $respostas_recebidas = 0;
            $positive_percentage = 0;
            $negative_percentage = 0;
            $neutral_percentage = 0;
            // Pode-se adicionar um log de erro aqui, se necessário
        }

        ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-column flex-lg-row">
            <div>
                <h1 class="mb-3 display-5">Bem Vindo, <span class="text-primary"><?php echo isset($_SESSION['Nome']) ? htmlspecialchars($_SESSION['Nome']) : 'Utilizador'; ?></span> </h1>
                <p class="text-muted mb-3 w-100">
                    <?php echo isAdmin() ? 'Painel de administração da empresa - Estatísticas e dados dos tickets da sua empresa' : 'Aqui pode acompanhar os seus tickets, ver o estado de cada pedido de suporte, e consultar informações importantes em tempo real.'; ?>
                </p>
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
                            <p class="m-0 fw-bold text-primary"><?php echo $tempo_medio_resposta; ?> <?php echo $tempo_unidade; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Avaliação dos Clientes -->
            <div class="col-xl-4 col-md-6 mb-4 flex-1">
                <div class="card dashboard-card h-100 ">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo isAdmin() ? 'Avaliação dos Clientes' : 'As Suas Avaliações'; ?>
                        </h5>
                        <div class="row w-100">
                            <div class="col-6 d-flex justify-content-center align-items-center flex-column mb-3">
                                <div class="text-center">
                                    <h4 class="text-success mb-0"><?php echo $positive_percentage; ?>%</h4>
                                    <small class="text-muted">Positivas</small>
                                </div>
                            </div>

                            <div class="col-6 d-flex justify-content-center align-items-center flex-row mb-3">
                                <div class="text-center">
                                    <h4 class="text-warning mb-0"><?php echo $neutral_percentage; ?>%</h4>
                                    <small class="text-muted">Neutras</small>
                                </div>
                            </div>

                            <div class="col-6 d-flex justify-content-center align-items-center flex-row mb-3">
                                <div class="text-center">
                                    <h4 class="text-danger mb-0"><?php echo $negative_percentage; ?>%</h4>
                                    <small class="text-muted">Negativas</small>
                                </div>
                            </div>

                            <div class="col-6 d-flex justify-content-center align-items-center flex-row mb-3">
                                <div class="text-center">
                                    <h4 class="text-info mb-0"><?php echo $total_tickets_count; ?></h4>
                                    <small class="text-muted">Total de Tickets</small>
                                </div>
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
                            '#36b9cc', // Teal
                            '#92033f', // Burgundy
                            '#2185a9', // Blue
                            '#00a89c', // Turquoise
                            '#f7941e', // Orange
                            '#e63c5f', // Red
                            '#0072b1', // Deep Blue
                            '#780c42'  // Dark Burgundy
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
                            '#5f5f5f', 
                            '#5f5f5f',
                            '#5f5f5f'
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