<?php
session_start();
include('conflogin.php');

// Only admin users can access this page
if (!isset($_SESSION['Grupo']) || $_SESSION['Grupo'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

// Check system health
$healthResults = [];
$executedCheck = false;

if (isset($_GET['check']) || isset($_POST['check'])) {
    $executedCheck = true;
    
    // Run the health check script and get results
    require_once('ws-healthcheck.php');
    
    // Results will be collected by the ws-healthcheck.php script
    // and sent as a JSON response or displayed in CLI mode
}

// Get log content
$logContent = '';
if (file_exists('logs/health.log')) {
    $logContent = file_get_contents('logs/health.log');
    // Limit log size for display
    if (strlen($logContent) > 10000) {
        $logContent = "...\n" . substr($logContent, -10000);
    }
}

?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<style>
    .status-indicator {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        margin-right: 10px;
    }
    .status-ok { background-color: #28a745; }
    .status-warning { background-color: #ffc107; }
    .status-error { background-color: #dc3545; }
    
    .health-card {
        margin-bottom: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .health-card .card-header {
        border-radius: 8px 8px 0 0;
        font-weight: 500;
    }
    
    .log-container {
        max-height: 500px;
        overflow-y: auto;
        background-color: #f5f5f5;
        border-radius: 4px;
        padding: 10px;
        font-family: monospace;
        font-size: 0.9rem;
    }
    
    .refresh-container {
        position: absolute;
        right: 10px;
        top: 10px;
    }
</style>
<body>
    <?php include('menu.php'); ?>
    
    <div class="content p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Monitor do Sistema de Chat</h1>
            <div>
                <form method="post" class="d-inline">
                    <input type="hidden" name="check" value="1">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise me-1"></i> Verificar Agora
                    </button>
                </form>
                <a href="ws-server-manager.php" class="btn btn-secondary ms-2">
                    <i class="bi bi-gear me-1"></i> Gerenciar Servidor
                </a>
                <a href="ws-diagnostic.php" class="btn btn-info ms-2">
                    <i class="bi bi-search me-1"></i> Diagnóstico
                </a>
            </div>
        </div>
        
        <?php if (!$executedCheck): ?>
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle me-2"></i> Clique em "Verificar Agora" para executar uma verificação de saúde do sistema.
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Histórico de Verificações</h5>
                        <a href="logs/health.log" download class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download me-1"></i> Baixar Log
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="log-container">
                            <pre><?php echo htmlspecialchars($logContent); ?></pre>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card health-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Ações Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="ws-server-manager.php?action=start" class="btn btn-success">
                                <i class="bi bi-play-circle me-1"></i> Iniciar Servidor WebSocket
                            </a>
                            <a href="ws-server-manager.php?action=restart" class="btn btn-warning">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reiniciar Servidor
                            </a>
                            <button type="button" class="btn btn-secondary" id="clearTemp">
                                <i class="bi bi-trash me-1"></i> Limpar Arquivos Temporários
                            </button>
                            <a href="ws-server-manager.php?action=stop" class="btn btn-danger">
                                <i class="bi bi-stop-circle me-1"></i> Parar Servidor
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card health-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Configurações</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Porta WebSocket:</strong> 8080</p>
                        <p><strong>Auto-inicialização:</strong> Ativada</p>
                        <p><strong>Servidor WebSocket:</strong> ws://localhost:8080</p>
                        <p><strong>Diretório de arquivos temporários:</strong> ./temp</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Auto-refresh the status every 60 seconds
    setTimeout(function() {
        window.location.reload();
    }, 60000);
    
    // Handle clear temp files button
    document.getElementById('clearTemp').addEventListener('click', function() {
        if (confirm('Tem certeza de que deseja limpar todos os arquivos temporários?')) {
            fetch('ws-diagnostic.php?action=cleanup', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                alert('Limpeza concluída: ' + data.message);
                window.location.reload();
            })
            .catch(error => {
                alert('Erro: ' + error);
            });
        }
    });
    </script>
</body>
</html>
