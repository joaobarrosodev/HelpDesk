<?php
session_start();
include('conflogin.php');
include('db.php');

// Obter estatísticas de tickets
try {
    // First, let's check what columns exist in the table
    $sql_columns = "SHOW COLUMNS FROM info_xdfree01_extrafields";
    $stmt_columns = $pdo->prepare($sql_columns);
    $stmt_columns->execute();
    $columns = $stmt_columns->fetchAll(PDO::FETCH_COLUMN);
    
    // Total de tickets no sistema
    $sql_total = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute();
    $total_tickets = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

    // Get current user's assigned user ID for restricted users
    $current_user_id = null;
    if (isComum()) {
        // Get the user ID associated with this admin account
        $user_sql = "SELECT id FROM users WHERE email = :admin_email";
        $user_stmt = $pdo->prepare($user_sql);
        $user_stmt->bindParam(':admin_email', $_SESSION['admin_email']);
        $user_stmt->execute();
        $user_result = $user_stmt->fetch(PDO::FETCH_ASSOC);
        $current_user_id = $user_result['id'] ?? null;
    }

    // Tickets atribuídos (active tickets)
    if (isAdmin()) {
        // Full admins see all active tickets
        $sql_atribuidos = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status <> 'Concluído'";
        $stmt_atribuidos = $pdo->prepare($sql_atribuidos);
    } else {
        // Restricted users see only tickets assigned to them
        $sql_atribuidos = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields 
                          WHERE Status <> 'Concluído' AND Atribuido = :user_id";
        $stmt_atribuidos = $pdo->prepare($sql_atribuidos);
        $stmt_atribuidos->bindParam(':user_id', $current_user_id);
    }

    $stmt_atribuidos->execute();
    $total_atribuidos = $stmt_atribuidos->fetch(PDO::FETCH_ASSOC)['total'];

    // Tickets sem atribuição - only for admins
    if (isAdmin()) {
        $sql_sem_atribuicao = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields 
                              WHERE (Atribuido IS NULL OR Atribuido = '') AND Status <> 'Concluído'";
        $stmt_sem_atribuicao = $pdo->prepare($sql_sem_atribuicao);
        $stmt_sem_atribuicao->execute();
        $total_sem_atribuicao = $stmt_sem_atribuicao->fetch(PDO::FETCH_ASSOC)['total'];
    } else {
        $total_sem_atribuicao = 0;
    }

    // Tickets resolvidos esta semana - using a simple approach
    $sql_semana = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status = 'Concluído'";
    $stmt_semana = $pdo->prepare($sql_semana);
    $stmt_semana->execute();
    $total_semana = $stmt_semana->fetch(PDO::FETCH_ASSOC)['total'];

    // Tempo médio de resolução em minutos 
    if (in_array('Tempo', $columns)) {
        $sql_tempo = "SELECT AVG(Tempo) as tempo_medio FROM info_xdfree01_extrafields WHERE Tempo IS NOT NULL";
        $stmt_tempo = $pdo->prepare($sql_tempo);
        $stmt_tempo->execute();
        $tempo_medio = round($stmt_tempo->fetch(PDO::FETCH_ASSOC)['tempo_medio'] ?? 0);
    } else {
        $tempo_medio = 0; // Fallback se coluna não existir
    }

    // Total de contratos ativos - usando as tabelas corretas
    $sql_contratos = "SELECT COUNT(*) as total FROM xdfree02 x2 
                      LEFT JOIN info_xdfree02_extrafields i2 ON x2.KeyId = i2.XDFree02_KeyID 
                      WHERE i2.status = 'Em Utilização'";
    $stmt_contratos = $pdo->prepare($sql_contratos);
    $stmt_contratos->execute();
    $contratos_ativos = $stmt_contratos->fetch(PDO::FETCH_ASSOC)['total'];

    // Tickets críticos (check if Prioridade column exists)
    if (in_array('Prioridade', $columns)) {
        $sql_criticos = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Prioridade = 'Alta' AND Status <> 'Concluído'";
    } else {
        $sql_criticos = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status = 'Aberto'";
    }
    $stmt_criticos = $pdo->prepare($sql_criticos);
    $stmt_criticos->execute();
    $total_criticos = $stmt_criticos->fetch(PDO::FETCH_ASSOC)['total'];

    // Tickets aguardando aprovação
    $sql_aprovacao = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status = 'Pendente'";
    $stmt_aprovacao = $pdo->prepare($sql_aprovacao);
    $stmt_aprovacao->execute();
    $total_aprovacao = $stmt_aprovacao->fetch(PDO::FETCH_ASSOC)['total'];

    // Tickets atrasados - simplified approach
    $sql_atrasados = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Atribuido = Null OR Atribuido = ''";
    $stmt_atrasados = $pdo->prepare($sql_atrasados);
    $stmt_atrasados->execute();
    $total_atrasados = $stmt_atrasados->fetch(PDO::FETCH_ASSOC)['total'];

    // Taxa de satisfação baseada na coluna Review (1=Positivo, 2=Neutro, 3=Negativo)
    if (in_array('Review', $columns)) {
        $sql_review = "SELECT Review, COUNT(*) as count FROM info_xdfree01_extrafields WHERE Review IS NOT NULL AND Review IN (1,2,3) GROUP BY Review";
        $stmt_review = $pdo->prepare($sql_review);
        $stmt_review->execute();
        $reviews = $stmt_review->fetchAll(PDO::FETCH_ASSOC);
        
        $total_reviews = 0;
        $review_counts = [1 => 0, 2 => 0, 3 => 0]; // positive, neutral, negative
        
        foreach ($reviews as $review) {
            $review_counts[$review['Review']] = $review['count'];
            $total_reviews += $review['count'];
        }
        
        if ($total_reviews > 0) {
            // Determinar satisfação baseada na maioria
            $max_count = max($review_counts);
            $dominant_review = array_search($max_count, $review_counts);
            
            switch ($dominant_review) {
                case 1:
                    $satisfacao = "Positivo";
                    $satisfacao_cor = "success";
                    $satisfacao_icon = "bi-emoji-smile";
                    break;
                case 3:
                    $satisfacao = "Negativo";
                    $satisfacao_cor = "danger";
                    $satisfacao_icon = "bi-emoji-frown";
                    break;
                default:
                    $satisfacao = "Neutro";
                    $satisfacao_cor = "warning";
                    $satisfacao_icon = "bi-emoji-neutral";
                    break;
            }
        } else {
            $satisfacao = "Neutro";
            $satisfacao_cor = "secondary";
            $satisfacao_icon = "bi-emoji-neutral";
        }
    } else {
        // Fallback se coluna Review não existir
        $satisfacao = "Neutro";
        $satisfacao_cor = "secondary";
        $satisfacao_icon = "bi-emoji-neutral";
    }

} catch (PDOException $e) {
    // Em caso de erro, definir valores predefinidos
    $total_tickets = 0;
    $total_atribuidos = 0;
    $total_semana = 0;
    $tempo_medio = 0;
    $total_criticos = 0;
    $total_aprovacao = 0;
    $total_atrasados = 0;
    $contratos_ativos = 0;
    $satisfacao = "Neutro";
    $satisfacao_cor = "secondary";
    $satisfacao_icon = "bi-emoji-neutral";
    
    $erro_db = "Erro ao carregar estatísticas: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <div class="container-fluid p-4">
            <!-- Cabeçalho da página -->
            <div class="flex-grow-1">
                <h1 class="mb-3 display-5">
                    <?php 
                    if (isAdmin()) {  // CHANGED: was isFullAdmin()
                        echo 'Painel de Administração';
                    } else {
                        echo 'Painel de Suporte';
                    }
                    ?>
                </h1>
                <p class="">
                    <?php 
                    if (isAdmin()) {  // CHANGED: was isFullAdmin()
                        echo 'Gerir todos os tickets e utilizadores do sistema. Monitorizar o desempenho geral.';
                    } else {
                        echo 'Gerir os seus tickets atribuídos e acompanhar o progresso dos mesmos.';
                    }
                    ?>
                </p>
            </div>
            
            <?php if(isset($erro_db)): ?>
            <div class="alert alert-warning" role="alert">
                <?php echo htmlspecialchars($erro_db); ?>
            </div>
            <?php endif; ?>
            
            <!-- Estatísticas Principais -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="bg-white p-3 rounded d-flex h-100 border">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi bi-file-earmark-text text-info fs-4"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0"><?php echo number_format($contratos_ativos); ?></div>
                                <small class="text-muted">Contratos Ativos</small>
                                <?php if($contratos_ativos > 0): ?>
                                <div class="mt-2">
                                    <a href="consultar_contratos.php" class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-eye me-1"></i> Ver Todos
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="bg-white p-3 rounded d-flex h-100 border">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi bi-collection text-primary fs-4"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0"><?php echo number_format($total_tickets); ?></div>
                                <small class="text-muted">Total de Tickets</small>
                                <div class="mt-2">
                                    <a href="consultar_tickets.php" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye me-1"></i> Ver Todos
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="bg-white p-3 rounded d-flex h-100 border">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <i class="<?php echo htmlspecialchars($satisfacao_icon); ?> text-<?php echo htmlspecialchars($satisfacao_cor); ?> fs-4"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0 text-<?php echo htmlspecialchars($satisfacao_cor); ?>"><?php echo htmlspecialchars($satisfacao); ?></div>
                                <small class="text-muted">Satisfação</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="bg-white p-3 rounded d-flex h-100 border">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi bi-stopwatch text-warning fs-4"></i>
                            </div>
                            <div>
                                <div class="h4 mb-0"><?php echo number_format($tempo_medio); ?>min</div>
                                <small class="text-muted">Tempo Médio</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estados dos Tickets -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="bg-opacity-10 border border-danger border-2 p-3 rounded d-flex h-100" style="background-color: #f8d7da;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                            <div>
                                <div class="fw-bold text-dark"><?php echo number_format($total_criticos); ?></div>
                                <small class="text-muted">Alta Prioridade</small>
                                <?php if($total_criticos > 0): ?>
                                <div class="mt-1">
                                    <a href="consultar_tickets.php?prioridade=alta" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-eye me-1"></i> Ver
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="bg-secondary bg-opacity-10 border border-secondary border-2 p-3 rounded d-flex h-100">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-clock-history text-secondary me-2"></i>
                            <div>
                                <div class="fw-bold text-dark"><?php echo number_format($total_atrasados); ?></div>
                                <small class="text-muted">Atrasados</small>
                                <?php if($total_atrasados > 0): ?>
                                <div class="mt-1">
                                    <a href="consultar_tickets.php?status=atrasados" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-eye me-1"></i> Ver
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="bg-opacity-10 border border-warning border-2 p-3 rounded d-flex h-100" style="background-color: #fff3cd;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-clock text-warning me-2"></i>
                            <div>
                                <div class="fw-bold text-dark"><?php echo number_format($total_atribuidos); ?></div>
                                <small class="text-muted">Ativos</small>
                                <div class="mt-1">
                                    <a href="tickets_atribuidos.php" class="btn btn-outline-warning btn-sm">
                                        <i class="bi bi-eye me-1"></i> Gerir
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="bg-success bg-opacity-10 border border-success border-2 p-3 rounded d-flex h-100">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            <div>
                                <div class="fw-bold text-dark"><?php echo number_format($total_semana); ?></div>
                                <small class="text-muted">Resolvidos</small>
                                <div class="mt-1">
                                    <a href="tickets_fechados.php" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-eye me-1"></i> Ver
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resumo de Performance -->
            <div class="bg-white card p-3 rounded d-flex h-100">
                <?php 
                $percentual_resolvidos = $total_tickets > 0 ? round(($total_semana / $total_tickets) * 100, 1) : 0;
                ?>
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="h5"><?php echo number_format($percentual_resolvidos, 1); ?>%</div>
                        <small class="text-muted">Taxa Resolução</small>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentual_resolvidos; ?>%"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <?php 
                        $eficiencia_temporal = $tempo_medio <= 60 ? 100 : round((60 / $tempo_medio) * 100, 1);
                        ?>
                        <div class="h5"><?php echo number_format($eficiencia_temporal); ?>%</div>
                        <small class="text-muted">Eficiência Temporal</small>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $eficiencia_temporal; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Sistema processou <?php echo number_format($total_semana); ?> tickets com tempo médio de <?php echo number_format($tempo_medio); ?> minutos
                    </p>
                </div>
            </div>

            <!-- Tickets Recentes (5 últimos) - apenas para admins restritos -->
            <?php if (isComum()): ?>  <!-- CHANGED: was isRestrictedAdmin() -->
            <div class="bg-white card p-3 rounded d-flex h-100 mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Últimos Tickets Atribuídos</h5>
                    <a href="consultar_tickets.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye me-1"></i> Ver Todos
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Título</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th>Criado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets_recentes as $ticket): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['KeyId']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['titulo']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['prioridade']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['status']); ?></td>
                                <td><?php echo date("d-m-Y H:i", strtotime($ticket['criado'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
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
