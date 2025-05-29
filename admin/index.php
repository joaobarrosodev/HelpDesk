<?php
session_start();
include('conflogin.php');
include('db.php');

// Obter estatísticas de tickets
try {
    // Total de tickets no sistema
    $sql_total = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute();
    $total_tickets = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

    // Tickets atribuídos ao administrador atual
    $sql_atribuidos = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status <> 'Concluído' AND Atribuido = :admin_id";
    $stmt_atribuidos = $pdo->prepare($sql_atribuidos);
    $stmt_atribuidos->bindParam(':admin_id', $_SESSION['admin_id']);
    $stmt_atribuidos->execute();
    $total_atribuidos = $stmt_atribuidos->fetch(PDO::FETCH_ASSOC)['total'];

    // Tickets resolvidos esta semana
    $sql_semana = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status = 'Concluído' AND YEARWEEK(Updated, 1) = YEARWEEK(CURDATE(), 1)";
    $stmt_semana = $pdo->prepare($sql_semana);
    $stmt_semana->execute();
    $total_semana = $stmt_semana->fetch(PDO::FETCH_ASSOC)['total'];

    // Tempo médio de resolução (em dias)
    $sql_tempo = "SELECT AVG(DATEDIFF(Updated, Created)) as tempo_medio FROM info_xdfree01_extrafields WHERE Status = 'Concluído' AND Updated IS NOT NULL";
    $stmt_tempo = $pdo->prepare($sql_tempo);
    $stmt_tempo->execute();
    $tempo_medio = $stmt_tempo->fetch(PDO::FETCH_ASSOC)['tempo_medio'];
    $tempo_medio = $tempo_medio ? round($tempo_medio, 1) : 0;

    // Tickets críticos (alta prioridade abertos)
    $sql_criticos = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Priority = 'Alta' AND Status <> 'Concluído'";
    $stmt_criticos = $pdo->prepare($sql_criticos);
    $stmt_criticos->execute();
    $total_criticos = $stmt_criticos->fetch(PDO::FETCH_ASSOC)['total'];

    // Tickets aguardando aprovação
    $sql_aprovacao = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status = 'Aguardando Aprovação'";
    $stmt_aprovacao = $pdo->prepare($sql_aprovacao);
    $stmt_aprovacao->execute();
    $total_aprovacao = $stmt_aprovacao->fetch(PDO::FETCH_ASSOC)['total'];

    // Tickets atrasados (abertos há mais de 7 dias)
    $sql_atrasados = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status <> 'Concluído' AND DATEDIFF(CURDATE(), Created) > 7";
    $stmt_atrasados = $pdo->prepare($sql_atrasados);
    $stmt_atrasados->execute();
    $total_atrasados = $stmt_atrasados->fetch(PDO::FETCH_ASSOC)['total'];

    // Taxa de satisfação (baseada em feedback)
    $sql_satisfacao = "SELECT AVG(Rating) as media FROM info_xdfree01_extrafields WHERE Rating IS NOT NULL AND Rating > 0";
    $stmt_satisfacao = $pdo->prepare($sql_satisfacao);
    $stmt_satisfacao->execute();
    $satisfacao = $stmt_satisfacao->fetch(PDO::FETCH_ASSOC)['media'];
    $satisfacao = $satisfacao ? round($satisfacao, 1) : 0;

} catch (PDOException $e) {
    // Em caso de erro, definir valores predefinidos
    $total_tickets = 0;
    $total_atribuidos = 0;
    $total_semana = 0;
    $tempo_medio = 0;
    $total_criticos = 0;
    $total_aprovacao = 0;
    $total_atrasados = 0;
    $satisfacao = 0;
    
    $erro_db = "Erro ao carregar estatísticas: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('menu.php'); ?>
    <div class="content p-5 content-area">
        <div class="container-fluid">
            <!-- Cabeçalho da página -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Painel de Administração</h1>
            </div>
            
            <?php if(isset($erro_db)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $erro_db; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
            <?php endif; ?>
            
            <!-- Estatísticas Principais -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-light">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-graph-up me-2"></i>Visão Geral do Sistema</h6>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-primary border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-collection fs-1 text-primary"></i>
                                    </div>
                                    <h5 class="card-title">Total de Tickets</h5>
                                    <div class="h2 mb-2 fw-bold text-primary"><?php echo $total_tickets; ?></div>
                                    <p class="card-text small text-muted">Todos os tickets no sistema</p>
                                    <a href="consultar_tickets.php" class="btn btn-outline-primary btn-sm mt-2">
                                        <i class="bi bi-eye me-1"></i> Ver Todos
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-info border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-person-workspace fs-1 text-info"></i>
                                    </div>
                                    <h5 class="card-title">Meus Tickets</h5>
                                    <div class="h2 mb-2 fw-bold text-info"><?php echo $total_atribuidos; ?></div>
                                    <p class="card-text small text-muted">Atribuídos a mim (ativos)</p>
                                    <a href="tickets_atribuidos.php" class="btn btn-outline-info btn-sm mt-2">
                                        <i class="bi bi-eye me-1"></i> Gerir
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-success border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-calendar-week fs-1 text-success"></i>
                                    </div>
                                    <h5 class="card-title">Resolvidos Esta Semana</h5>
                                    <div class="h2 mb-2 fw-bold text-success"><?php echo $total_semana; ?></div>
                                    <p class="card-text small text-muted">Tickets finalizados nos últimos 7 dias</p>
                                    <a href="relatorios.php?periodo=semana" class="btn btn-outline-success btn-sm mt-2">
                                        <i class="bi bi-eye me-1"></i> Relatório
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-warning border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-stopwatch fs-1 text-warning"></i>
                                    </div>
                                    <h5 class="card-title">Tempo Médio</h5>
                                    <div class="h2 mb-2 fw-bold text-warning"><?php echo $tempo_medio; ?> dias</div>
                                    <p class="card-text small text-muted">Tempo médio de resolução</p>
                                    <a href="relatorios.php?tipo=performance" class="btn btn-outline-warning btn-sm mt-2">
                                        <i class="bi bi-eye me-1"></i> Análise
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estatísticas de Atenção -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-light">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-exclamation-triangle me-2"></i>Requer Atenção</h6>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-danger border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-fire fs-1 text-danger"></i>
                                    </div>
                                    <h5 class="card-title">Tickets Críticos</h5>
                                    <div class="h2 mb-2 fw-bold text-danger"><?php echo $total_criticos; ?></div>
                                    <p class="card-text small text-muted">Alta prioridade em aberto</p>
                                    <a href="consultar_tickets.php?prioridade=alta" class="btn btn-outline-danger btn-sm mt-2">
                                        <i class="bi bi-eye me-1"></i> Urgente
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-info border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-hourglass-split fs-1 text-info"></i>
                                    </div>
                                    <h5 class="card-title">Aguardam Aprovação</h5>
                                    <div class="h2 mb-2 fw-bold text-info"><?php echo $total_aprovacao; ?></div>
                                    <p class="card-text small text-muted">Pendentes de autorização</p>
                                    <a href="consultar_tickets.php?status=aprovacao" class="btn btn-outline-info btn-sm mt-2">
                                        <i class="bi bi-eye me-1"></i> Aprovar
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-secondary border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-clock-history fs-1 text-secondary"></i>
                                    </div>
                                    <h5 class="card-title">Tickets Atrasados</h5>
                                    <div class="h2 mb-2 fw-bold text-secondary"><?php echo $total_atrasados; ?></div>
                                    <p class="card-text small text-muted">Abertos há mais de 7 dias</p>
                                    <a href="consultar_tickets.php?status=atrasados" class="btn btn-outline-secondary btn-sm mt-2">
                                        <i class="bi bi-eye me-1"></i> Revisar
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card h-100 border-start border-dark border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-star-fill fs-1 text-dark"></i>
                                    </div>
                                    <h5 class="card-title">Satisfação</h5>
                                    <div class="h2 mb-2 fw-bold text-dark"><?php echo $satisfacao; ?>/5</div>
                                    <p class="card-text small text-muted">Avaliação média dos clientes</p>
                                    <a href="relatorios.php?tipo=satisfacao" class="btn btn-outline-dark btn-sm mt-2">
                                        <i class="bi bi-eye me-1"></i> Feedback
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumo e Performance -->
            <div class="row">
                <div class="col">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 bg-light">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-speedometer2 me-2"></i>Performance do Sistema</h6>
                        </div>
                        <div class="card-body">
                            <?php 
                            $tickets_ativos = $total_tickets - $total_semana;
                            $percentual_resolvidos = $total_tickets > 0 ? round(($total_semana / $total_tickets) * 100, 1) : 0;
                            $eficiencia = $tempo_medio > 0 ? round((7 / $tempo_medio) * 100, 1) : 100;
                            if($eficiencia > 100) $eficiencia = 100;
                            ?>
                            <div class="row text-center mb-4">
                                <div class="col-md-3">
                                    <div class="border-end">
                                        <div class="h4 fw-bold text-primary"><?php echo $total_tickets; ?></div>
                                        <small class="text-muted">Total Geral</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border-end">
                                        <div class="h4 fw-bold text-success"><?php echo $percentual_resolvidos; ?>%</div>
                                        <small class="text-muted">Resolvidos/Semana</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border-end">
                                        <div class="h4 fw-bold text-warning"><?php echo $eficiencia; ?>%</div>
                                        <small class="text-muted">Eficiência</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="h4 fw-bold text-info"><?php echo $satisfacao; ?>/5</div>
                                    <small class="text-muted">Satisfação</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Taxa de Resolução Semanal</h6>
                                    <div class="progress mb-3" style="height: 10px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentual_resolvidos; ?>%"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Eficiência Temporal</h6>
                                    <div class="progress mb-3" style="height: 10px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $eficiencia; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <p class="text-muted small mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Sistema processou <?php echo $total_semana; ?> tickets esta semana com tempo médio de <?php echo $tempo_medio; ?> dias
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- Botão para voltar ao topo -->
    <button class="back-to-top" id="backToTop" title="Voltar ao topo">
        <i class="bi bi-arrow-up"></i>
    </button>
    
    <script>
        // Script para o botão "voltar ao topo"
        const backToTopButton = document.getElementById('backToTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) { // Mostrar após descer 300px
                backToTopButton.style.display = 'block';
            } else {
                backToTopButton.style.display = 'none';
            }
        });
        
        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>
</html>
