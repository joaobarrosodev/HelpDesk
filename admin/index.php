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

    // Tickets atribuídos ao admin atual
    $sql_atribuidos = "SELECT COUNT(*) as total FROM info_xdfree01_extrafields WHERE AttUser = :admin_id AND Status <> 'Concluído'";
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
    // Em caso de erro, definir valores padrão
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
                <div class="d-flex align-items-center">
                    <span class="text-muted me-2">Hoje:</span>
                    <span class="badge bg-light text-dark"><?php echo date('d/m/Y'); ?></span>
                </div>
            </div>
            
            <?php if(isset($erro_db)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $erro_db; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Cartões de estatísticas -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2 rounded-circle bg-primary bg-opacity-10">
                                    <i class="bi bi-ticket-fill fs-3 text-primary"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-xs text-uppercase fw-bold text-primary mb-1">Tickets Abertos</div>
                                    <div class="h3 mb-0 fw-bold"><?php echo $total_abertos; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="p-2 rounded-circle bg-success bg-opacity-10">
                                    <i class="bi bi-person-check fs-3 text-success"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-xs text-uppercase fw-bold text-success mb-1">Atribuídos a Mim</div>
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
                                <div class="p-2 rounded-circle bg-info bg-opacity-10">
                                    <i class="bi bi-check-circle fs-3 text-info"></i>
                                </div>
                                <div class="ms-3">
                                    <div class="text-xs text-uppercase fw-bold text-info mb-1">Tickets Concluídos</div>
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
                                <div class="p-2 rounded-circle bg-warning bg-opacity-10">
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
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">Acesso Rápido</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <a href="consultar_tickets.php" class="btn btn-primary d-flex align-items-center justify-content-center w-100 p-3">
                                <i class="bi bi-ticket fs-4 me-2"></i>
                                <span>Tickets por Atribuir</span>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="tickets_atribuidos.php" class="btn btn-success d-flex align-items-center justify-content-center w-100 p-3">
                                <i class="bi bi-ticket-detailed fs-4 me-2"></i>
                                <span>Meus Tickets</span>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="consultar_tickets_fechados.php" class="btn btn-secondary d-flex align-items-center justify-content-center w-100 p-3">
                                <i class="bi bi-list-check fs-4 me-2"></i>
                                <span>Tickets Fechados</span>
                            </a>
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
