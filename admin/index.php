<?php
session_start();
include('conflogin.php');
include('db.php');

// Obter estatísticas de tickets
try {
    // Total de tickets abertos
    $sql_abertos = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status <> 'Concluído'";
    $stmt_abertos = $pdo->prepare($sql_abertos);
    $stmt_abertos->execute();
    $total_abertos = $stmt_abertos->fetch(PDO::FETCH_ASSOC)['total'];

    // Tickets atribuídos ao administrador atual
    $sql_atribuidos = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status <> 'Concluído' AND Atribuido = :admin_id";
    $stmt_atribuidos = $pdo->prepare($sql_atribuidos);
    $stmt_atribuidos->bindParam(':admin_id', $_SESSION['admin_id']);
    $stmt_atribuidos->execute();
    $total_atribuidos = $stmt_atribuidos->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de tickets concluídos
    $sql_fechados = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Status = 'Concluído'";
    $stmt_fechados = $pdo->prepare($sql_fechados);
    $stmt_fechados->execute();
    $total_fechados = $stmt_fechados->fetch(PDO::FETCH_ASSOC)['total'];

    // Tickets de alta prioridade
    $sql_alta = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE Priority = 'Alta' AND Status <> 'Concluído'";
    $stmt_alta = $pdo->prepare($sql_alta);
    $stmt_alta->execute();
    $total_alta = $stmt_alta->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    // Em caso de erro, definir valores predefinidos
    $total_abertos = 0;
    $total_atribuidos = 0;
    $total_fechados = 0;
    $total_alta = 0;
    
    $erro_db = "Erro ao carregar estatísticas: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <div class="container-fluid">
            <!-- Cabeçalho da página -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Painel de Administração</h2>
            </div>
            
            <?php if(isset($erro_db)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $erro_db; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
            <?php endif; ?>
            
            <!-- Cartões de estatísticas -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2 bg-opacity-10">
                                    <i class="bi bi-ticket-fill fs-3 "></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-xs text-uppercase fw-bold mb-1">Tickets Abertos</div>
                                    <div class="h3 mb-0 fw-bold"><?php echo $total_abertos; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2 bg-opacity-10">
                                    <i class="bi bi-person-check fs-3 text-info"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-xs text-uppercase fw-bold text-info mb-1">Atribuídos a Mim</div>
                                    <div class="h3 mb-0 fw-bold"><?php echo $total_atribuidos; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2  bg-opacity-10">
                                    <i class="bi bi-check-circle fs-3 text-success"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-xs text-uppercase fw-bold text-success mb-1">Tickets Concluídos</div>
                                    <div class="h3 mb-0 fw-bold"><?php echo $total_fechados; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2  bg-opacity-10">
                                    <i class="bi bi-exclamation-triangle fs-3 text-warning"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-xs text-uppercase fw-bold text-warning mb-1">Alta Prioridade</div>
                                    <div class="h3 mb-0 fw-bold"><?php echo $total_alta; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Acesso Rápido -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-light">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-lightning-fill me-2"></i>Acesso Rápido</h6>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-start border-primary border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-ticket-fill fs-1 text-primary"></i>
                                    </div>
                                    <h5 class="card-title">Tickets por Atribuir</h5>
                                    <p class="card-text small text-muted">Visualizar e atribuir novos tickets</p>
                                    <a href="consultar_tickets.php" class="btn btn-outline-primary mt-2 w-100">
                                        <i class="bi bi-arrow-right me-1"></i> Aceder
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-start border-success border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-person-check-fill fs-1 text-success"></i>
                                    </div>
                                    <h5 class="card-title">Os Meus Tickets</h5>
                                    <p class="card-text small text-muted">Gerir tickets atribuídos a si</p>
                                    <a href="tickets_atribuidos.php" class="btn btn-outline-success mt-2 w-100">
                                        <i class="bi bi-arrow-right me-1"></i> Aceder
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-start border-secondary border-3">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-archive-fill fs-1 text-secondary"></i>
                                    </div>
                                    <h5 class="card-title">Tickets Encerrados</h5>
                                    <p class="card-text small text-muted">Histórico de tickets concluídos</p>
                                    <a href="tickets_fechados.php" class="btn btn-outline-secondary mt-2 w-100">
                                        <i class="bi bi-arrow-right me-1"></i> Aceder
                                    </a>
                                </div>
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
