<?php
// WebSocket Diagnostic Tool
session_start();
include('conflogin.php');

// Only admin users can access this page
if (!isset($_SESSION['Grupo']) || $_SESSION['Grupo'] !== 'Admin') {
    header("Location: index.php");
    exit;
}

// Define constants
define('TEMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'temp');

// Check system environment
$diagnostics = array();

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
$diagnostics[] = array(
    'name' => 'WebSocket Server',
    'status' => $serverRunning ? 'OK' : 'Falha',
    'message' => $serverRunning ? 'O servidor está em execução na porta 8080' : 'O servidor não está em execução.',
    'class' => $serverRunning ? 'success' : 'danger'
);

// Check temp directory
$tempExists = file_exists(TEMP_DIR);
$tempWritable = $tempExists && is_writable(TEMP_DIR);
$diagnostics[] = array(
    'name' => 'Diretório Temporário',
    'status' => $tempWritable ? 'OK' : 'Problema',
    'message' => $tempWritable ? 'O diretório temporário existe e tem permissões corretas' : 
                                ($tempExists ? 'O diretório temporário existe mas não tem permissões de escrita' : 
                                             'O diretório temporário não existe'),
    'class' => $tempWritable ? 'success' : 'warning'
);

// Check PHP version
$phpVersion = phpversion();
$phpOk = version_compare($phpVersion, '7.4.0', '>=');
$diagnostics[] = array(
    'name' => 'Versão do PHP',
    'status' => $phpOk ? 'OK' : 'Alerta',
    'message' => 'PHP ' . $phpVersion . ($phpOk ? ' (compatível)' : ' (recomendado PHP 7.4 ou superior)'),
    'class' => $phpOk ? 'success' : 'warning'
);

// Check database connection
try {
    include('db.php');
    $dbTest = $pdo->query("SELECT 1");
    $dbOk = true;
    $dbMessage = 'Conexão com o banco de dados estabelecida com sucesso';
} catch (Exception $e) {
    $dbOk = false;
    $dbMessage = 'Erro na conexão com o banco de dados: ' . $e->getMessage();
}
$diagnostics[] = array(
    'name' => 'Conexão com Banco de Dados',
    'status' => $dbOk ? 'OK' : 'Falha',
    'message' => $dbMessage,
    'class' => $dbOk ? 'success' : 'danger'
);

// Check if WebSocket extension is loaded
$wsExtension = extension_loaded('sockets');
$diagnostics[] = array(
    'name' => 'Extensão de Sockets',
    'status' => $wsExtension ? 'OK' : 'Alerta',
    'message' => $wsExtension ? 'A extensão de sockets está instalada' : 'A extensão de sockets não está instalada, pode afetar o desempenho',
    'class' => $wsExtension ? 'success' : 'warning'
);

// Check message table
$messageTableExists = false;
$messageCount = 0;
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'comments_xdfree01_extrafields'");
    $messageTableExists = $tableCheck->rowCount() > 0;
    
    if ($messageTableExists) {
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM comments_xdfree01_extrafields");
        $messageCount = $countStmt->fetchColumn();
    }
} catch (Exception $e) {
    // Silently fail
}
$diagnostics[] = array(
    'name' => 'Tabela de Mensagens',
    'status' => $messageTableExists ? 'OK' : 'Falha',
    'message' => $messageTableExists ? 'A tabela de mensagens existe e contém ' . $messageCount . ' mensagens' : 'A tabela de mensagens não existe ou não está acessível',
    'class' => $messageTableExists ? 'success' : 'danger'
);

// Check any pending sync files
$syncFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . 'sync_*.txt');
$syncFileCount = is_array($syncFiles) ? count($syncFiles) : 0;
$diagnostics[] = array(
    'name' => 'Arquivos de Sincronização',
    'status' => 'Info',
    'message' => 'Existem ' . $syncFileCount . ' arquivos de sincronização pendentes',
    'class' => $syncFileCount > 10 ? 'warning' : 'info'
);

// Check if server autostart is enabled
$enableAutoStart = true; // Default value from auto-start.php
$diagnostics[] = array(
    'name' => 'Auto-Inicialização',
    'status' => $enableAutoStart ? 'Ativado' : 'Desativado',
    'message' => $enableAutoStart ? 'O servidor iniciará automaticamente quando necessário' : 'O servidor não iniciará automaticamente',
    'class' => $enableAutoStart ? 'success' : 'info'
);

// Run message database test if requested
$testResult = null;
if (isset($_POST['action']) && $_POST['action'] == 'test_db') {
    try {
        // Insert a test message
        $testTicket = '#001'; // Use a test ticket ID
        $testDate = date('Y-m-d H:i:s');
        $testMessage = 'Teste de diagnóstico WebSocket - ' . $testDate;
        $testUser = 'Sistema';
        
        $stmt = $pdo->prepare("INSERT INTO comments_xdfree01_extrafields 
                               (XDFree01_KeyID, Message, Date, user, type) 
                               VALUES (:keyid, :message, :date, :user, :type)");
        
        $stmt->bindParam(':keyid', $testTicket);
        $stmt->bindParam(':message', $testMessage);
        $stmt->bindParam(':date', $testDate);
        $stmt->bindParam(':user', $testUser);
        $type = 3; // Special type for system messages
        $stmt->bindParam(':type', $type);
        
        $result = $stmt->execute();
        
        if ($result) {
            // Now try to retrieve it
            $stmt = $pdo->prepare("SELECT id FROM comments_xdfree01_extrafields 
                                   WHERE XDFree01_KeyID = :keyid 
                                   AND Message = :message 
                                   AND Date = :date");
            
            $stmt->bindParam(':keyid', $testTicket);
            $stmt->bindParam(':message', $testMessage);
            $stmt->bindParam(':date', $testDate);
            $stmt->execute();
            
            $found = $stmt->rowCount() > 0;
            
            $testResult = [
                'success' => $found,
                'message' => $found ? 'Teste de banco de dados bem-sucedido: mensagem inserida e recuperada com sucesso.' : 
                                    'Falha no teste de banco de dados: a mensagem foi inserida mas não pôde ser recuperada.'
            ];
        } else {
            $testResult = [
                'success' => false,
                'message' => 'Falha no teste de banco de dados: não foi possível inserir a mensagem de teste.'
            ];
        }
    } catch (Exception $e) {
        $testResult = [
            'success' => false,
            'message' => 'Erro no teste de banco de dados: ' . $e->getMessage()
        ];
    }
}

// Clean up old files if requested
if (isset($_POST['action']) && $_POST['action'] == 'cleanup') {
    $cleaned = 0;
    
    // Clean up old sync files
    $syncFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . 'sync_*.txt');
    if (is_array($syncFiles)) {
        foreach ($syncFiles as $file) {
            @unlink($file);
            $cleaned++;
        }
    }
    
    // Clean up old message files
    $messageFiles = glob(TEMP_DIR . DIRECTORY_SEPARATOR . 'ws_message_*.json');
    if (is_array($messageFiles)) {
        foreach ($messageFiles as $file) {
            @unlink($file);
            $cleaned++;
        }
    }
    
    // Clean up old flag files
    $flagFile = TEMP_DIR . DIRECTORY_SEPARATOR . 'ws-server-starting.flag';
    if (file_exists($flagFile)) {
        @unlink($flagFile);
        $cleaned++;
    }
    
    $testResult = [
        'success' => true,
        'message' => 'Limpeza concluída: ' . $cleaned . ' arquivos removidos.'
    ];
}

?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<style>
    .diagnostic-item {
        border-left: 5px solid #eee;
        padding: 10px 15px;
        margin-bottom: 10px;
        border-radius: 4px;
    }
    .diagnostic-item.success { border-left-color: #28a745; }
    .diagnostic-item.warning { border-left-color: #ffc107; }
    .diagnostic-item.danger { border-left-color: #dc3545; }
    .diagnostic-item.info { border-left-color: #17a2b8; }
</style>
<body>
    <?php include('menu.php'); ?>
    
    <div class="content p-4">
        <h1 class="mb-4">Diagnóstico do Sistema de Chat</h1>
        
        <?php if ($testResult): ?>
        <div class="alert alert-<?php echo $testResult['success'] ? 'success' : 'danger'; ?> mb-4">
            <?php echo $testResult['message']; ?>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Status do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach($diagnostics as $item): ?>
                        <div class="diagnostic-item <?php echo $item['class']; ?>">
                            <h5 class="mb-1"><?php echo $item['name']; ?>: <span class="badge bg-<?php echo $item['class']; ?>"><?php echo $item['status']; ?></span></h5>
                            <p class="mb-0"><?php echo $item['message']; ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Ferramentas de Diagnóstico</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-3">
                            <input type="hidden" name="action" value="test_db">
                            <p>Testar a inserção e recuperação de mensagens no banco de dados.</p>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-database-check me-1"></i> Testar Banco de Dados
                            </button>
                        </form>
                        
                        <hr>
                        
                        <form method="post" onsubmit="return confirm('Esta operação removerá todos os arquivos de sincronização temporários. Continuar?');">
                            <input type="hidden" name="action" value="cleanup">
                            <p>Limpar arquivos temporários e de sincronização.</p>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-trash me-1"></i> Limpar Arquivos
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div>
                            <p>Gerenciar o servidor WebSocket.</p>
                            <a href="ws-server-manager.php" class="btn btn-info">
                                <i class="bi bi-gear me-1"></i> Gerenciar Servidor
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Informações do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Sistema Operacional:</strong><br> <?php echo PHP_OS; ?></p>
                        <p><strong>PHP:</strong><br> <?php echo phpversion(); ?></p>
                        <p><strong>Servidor Web:</strong><br> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                        <p><strong>WebSocket Port:</strong><br> 8080</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
