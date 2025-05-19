<?php
session_start();
include('conflogin.php');

// Only admin users can access this page
if (!isset($_SESSION['Grupo']) || $_SESSION['Grupo'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

// Handle actions
$status = '';
$serverRunning = false;

// Check if server is running
function isServerRunning() {
    $socket = @fsockopen('localhost', 8080, $errno, $errstr, 1);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}

$serverRunning = isServerRunning();

// Handle start/stop actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'start' && !$serverRunning) {
        $path = dirname(__FILE__);
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen('start /B "HelpDesk WS" cmd /c "cd /D ' . $path . ' && php ws-server.php > ws-server.log 2>&1"', 'r'));
        } else {
            exec('cd ' . $path . ' && nohup php ws-server.php > ws-server.log 2>&1 &');
        }
        $status = 'Solicitação para iniciar o servidor enviada. Por favor, aguarde alguns segundos e atualize a página.';
        sleep(2);
        $serverRunning = isServerRunning();
    } 
    elseif ($_POST['action'] == 'stop' && $serverRunning) {
        // This is a simple but not ideal way to stop the server on Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('taskkill /F /FI "WINDOWTITLE eq HelpDesk WS*" > nul 2>&1');
        } else {
            exec("pkill -f 'php ws-server.php'");
        }
        $status = 'Solicitação para parar o servidor enviada. Por favor, aguarde alguns segundos e atualize a página.';
        sleep(2);
        $serverRunning = isServerRunning();
    }
}

// Get log content
$logContent = '';
if (file_exists('ws-server.log')) {
    $logContent = file_get_contents('ws-server.log');
    // Limit log size for display
    if (strlen($logContent) > 10000) {
        $logContent = "...\n" . substr($logContent, -10000);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('menu.php'); ?>
    
    <div class="content p-4">
        <h1 class="mb-4">Gerenciador do Servidor WebSocket</h1>
        
        <?php if ($status): ?>
        <div class="alert alert-info"><?php echo $status; ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Status do Servidor</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <?php if ($serverRunning): ?>
                        <span class="badge bg-success p-2">Ativo</span>
                        <?php else: ?>
                        <span class="badge bg-danger p-2">Inativo</span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <form method="post" class="d-inline-block">
                            <?php if ($serverRunning): ?>
                            <input type="hidden" name="action" value="stop">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-stop-circle me-1"></i> Parar Servidor
                            </button>
                            <?php else: ?>
                            <input type="hidden" name="action" value="start">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-play-circle me-1"></i> Iniciar Servidor
                            </button>
                            <?php endif; ?>
                        </form>
                        
                        <a href="ws-server-manager.php" class="btn btn-secondary ms-2">
                            <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
                        </a>
                    </div>
                </div>
                
                <p class="mb-0">
                    <strong>Porta:</strong> 8080<br>
                    <strong>Endereço:</strong> ws://localhost:8080
                </p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Log do Servidor</h5>
                <a href="ws-server.log" download class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download me-1"></i> Baixar Log
                </a>
            </div>
            <div class="card-body">
                <pre class="bg-dark text-light p-3 rounded" style="max-height: 500px; overflow-y: auto;"><?php echo htmlspecialchars($logContent); ?></pre>
            </div>
        </div>
    </div>
    
    <script>
    // Auto-refresh the page every 30 seconds to update status
    setTimeout(function() {
        window.location.reload();
    }, 30000);
    </script>
</body>
</html>
