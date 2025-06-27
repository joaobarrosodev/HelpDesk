<?php
// Iniciar sessão primeiro
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Informações de depuração da sessão
error_log("admin/detalhes_ticket.php - ID da Sessão: " . session_id());
error_log("admin/detalhes_ticket.php - Dados da sessão: " . print_r($_SESSION, true));
error_log("admin/detalhes_ticket.php - Parâmetros GET: " . print_r($_GET, true));

// Tentar auto-iniciar o servidor WebSocket se necessário
include('../auto-start.php');

include('conflogin.php');
include('db.php');

// Get ticket ID from URL - use 'keyid' parameter which contains the actual ID
$ticketId = isset($_GET['keyid']) ? $_GET['keyid'] : '';

if (empty($ticketId)) {
    echo '<div class="alert alert-danger" role="alert">';
    echo 'Ticket não especificado.';
    echo '<br><a href="index.php" class="btn btn-primary mt-3">Voltar ao Painel</a>';
    echo '</div>';
    exit;
}

// Check if user can access this ticket using the keyid
if (!canAccessTicket($ticketId)) {
    header("Location: index.php?error=" . urlencode("Acesso negado. Não tem permissões para ver este ticket."));
    exit;
}

// Properly decode the ticket ID
$ticket_id = urldecode($ticketId);

// We need to check if keyid exists and proceed directly to the ticket query
if (isset($_GET['keyid'])) {
    $keyid = $_GET['keyid'];
    
    // Remover o símbolo '#' caso ele exista (se a base de dados não usa o '#')
    $keyid_sem_hash = str_replace('#', '', $keyid);
    
    // Log para depuração
    error_log("Buscando ticket com keyid: " . $keyid . " ou ID sem hash: " . $keyid_sem_hash);
    
    // Consultar os detalhes do ticket - query aprimorada para buscar por ambos os formatos
    $sql = "SELECT free.KeyId, free.id, free.Name, info.Description, info.Priority, info.Status,
            info.CreationUser, info.CreationDate, info.dateu, info.image, info.Atribuido as User, info.Entity,
            u.Name as atribuido_a, info.Tempo as Time, info.Relatorio as Descr, info.MensagensInternas as info
            FROM xdfree01 free
            LEFT JOIN info_xdfree01_extrafields info ON free.KeyId = info.XDFree01_KeyID
            LEFT JOIN users u ON info.Atribuido = u.id
            WHERE free.KeyId = :keyid OR free.KeyId = :keyid_with_hash OR free.id = :numeric_id";

    // Preparar a consulta
    $stmt = $pdo->prepare($sql);
    $keyid_with_hash = "#" . $keyid_sem_hash; // Garantir formato com #
    $numeric_id = intval($keyid_sem_hash);    // Tentar como número inteiro
    
    $stmt->bindParam(':keyid', $keyid);
    $stmt->bindParam(':keyid_with_hash', $keyid_with_hash);
    $stmt->bindParam(':numeric_id', $numeric_id);
    $stmt->execute();
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        // Log para depuração
        error_log("Ticket não encontrado para keyid: " . $keyid . " ou ID sem hash: " . $keyid_sem_hash);
        
        echo '<div class="alert alert-danger" role="alert">';
        echo 'Ticket ' . htmlspecialchars($keyid) . ' não encontrado. Verificar se o ID do ticket está correto.';
        echo '<br><a href="index.php" class="btn btn-primary mt-3">Voltar ao Painel</a>';
        echo '</div>';
        exit;
    }

    // Log de sucesso para depuração
    error_log("Ticket encontrado: KeyId=" . $ticket['KeyId'] . ", id=" . $ticket['id']);

    $ticket_id = $ticket['KeyId'];

    // Consultar todas as mensagens associadas ao ticket
    $sql_messages = "SELECT comments.Message, comments.type, comments.Date as CommentTime, comments.user
                    FROM comments_xdfree01_extrafields comments
                    WHERE comments.XDFree01_KeyID = :keyid
                    ORDER BY comments.Date ASC";

    $stmt_messages = $pdo->prepare($sql_messages);
    $stmt_messages->bindParam(':keyid', $ticket_id);
    $stmt_messages->execute();
    $messages = $stmt_messages->fetchAll(PDO::FETCH_ASSOC);
    
    // Consultar todos os utilizadores para o dropdown de atribuição
    $sql_users = "SELECT id, Name FROM users ORDER BY Name ASC";
    $stmt_users = $pdo->prepare($sql_users);
    $stmt_users->execute();
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} else {
    echo '<div class="alert alert-danger" role="alert">';
    echo 'Ticket não especificado.';
    echo '<br><a href="index.php" class="btn btn-primary mt-3">Voltar ao Painel</a>';
    echo '</div>';
    exit;
}

// Função para determinar a cor da prioridade
function getPriorityColor($priority)
{
    switch (strtolower($priority)) {
        case 'alta':
            return 'danger';
        case 'normal':
            return 'warning';
        case 'baixa':
            return 'success';
        case 'média':
        case 'media':
            return 'warning';
        default:
            return 'info';
    }
}

// Função para determinar a cor do status
function getStatusColor($status)
{
    switch (strtolower(trim($status))) {
        case 'concluído':
            return 'success';
        case 'em análise':
            return 'info';
        case 'pendente':
            return 'warning';
        case 'em resolução':
            return 'warning';
        case 'aguarda resposta':
            return 'secondary';
        default:
            return 'primary';
    }
}

// Block TinyMCE before any HTML output
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <?php include('head.php'); ?>
    <link href="../css/chat.css" rel="stylesheet">
    <style>
        .message.new-message {
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }


        .admin-controls {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }

        .admin-controls h5 {
            margin-top: 0;
            color: #495057;
            margin-bottom: 15px;
        }

        .admin-controls .form-group {
            margin-bottom: 15px;
        }

        .admin-controls-header {
            transition: all 0.3s ease;
            padding: 8px 0;
            border-radius: 4px;
        }

        .admin-controls-header:hover {
            background-color: #f0f0f0;
            padding-left: 8px;
        }

        .toggle-icon {
            transition: transform 0.3s ease;
        }

        .collapsed .toggle-icon {
            transform: rotate(-90deg);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .message-image {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
            max-width: 300px;
            max-height: 300px;
            object-fit: contain;
        }
        
        .message-image:hover {
            transform: scale(1.03);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        #modalImage {
            max-height: calc(80vh - 120px);
            object-fit: contain;
        }
        
        .modal-lg {
            max-width: 900px;
        }

        .chat-input-container {
            position: relative;
            display: flex;
            align-items: flex-end;
        }

        .file-upload-button {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-upload-button:hover {
            color: #0d6efd;
        }

        .file-upload-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .message-image {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            max-width: 100%;
            height: auto;
            margin: 10px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .message-image:hover {
            transform: scale(1.02);
        }

        .ticket-closed-info {
            margin-top: 20px;
        }

        .resolution-summary {
            margin-top: 20px;
            animation: fadeIn 0.5s ease-in-out;
        }

        .resolution-summary .card {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .resolution-summary .card-header {
            border-radius: 8px 8px 0 0;
            font-weight: 500;
        }

        .resolution-summary .card-body {
            padding: 1.5rem;
        }

        .resolution-summary .card-text {
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 0;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .time-control-container {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background-color: #f8f9fa;
        }

        .time-display {
            text-align: center;
            margin-bottom: 15px;
        }

        .time-display .badge {
            font-size: 1.1rem;
            padding: 8px 16px;
        }

        .time-buttons {
            justify-content: center;
        }

        .time-buttons .btn {
            min-width: 80px;
        }

        /* Estilos para notificações */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 250px;
            max-width: 400px;
            width: auto;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s, transform 0.3s;
        }

        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .notification.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .notification.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .notification.info {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .notification.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
    </style>
</head>

<!-- Modal para Exibir Imagem -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Imagem do Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid" alt="Imagem do Ticket">
            </div>
            <div class="modal-footer">
                <a id="downloadImageLink" href="#" class="btn btn-primary" download>Descarregar</a>
                <a id="openImageLink" href="#" target="_blank" class="btn btn-secondary">Abrir em Nova Aba</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Débito -->
<div class="modal fade" id="timeDebtModal" tabindex="-1" aria-labelledby="timeDebtModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="timeDebtModalLabel"><i class="bi bi-exclamation-triangle me-2"></i>Tempo Insuficiente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tempo insuficiente nos contratos para fechar este ticket:</p>
                <div class="row mb-3">
                    <div class="col-6">
                        <strong>Tempo necessário:</strong>
                        <div id="modalTempoNecessario">--</div>
                    </div>
                    <div class="col-6">
                        <strong>Tempo disponível:</strong>
                        <div id="modalTempoDisponivel">--</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <strong>Tempo em falta:</strong>
                        <div id="modalTempoEmFalta" class="text-danger">--</div>
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Opção de débito disponível:</strong> 
                    <p class="mb-0">O tempo em falta será registrado como débito e descontado automaticamente quando um novo contrato for adquirido.</p>
                    <p class="mb-0 mt-2"><strong>Atenção:</strong> A criação de novos tickets ficará bloqueada até que o débito seja quitado.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmDebt">
                    <i class="bi bi-check-circle me-1"></i> Usar Débito e Fechar Ticket
                </button>
            </div>
        </div>
    </div>
</div>

<body>
    <?php include('menu.php'); ?>
    <div class="content chat-container">
       <div class="chat-header flex-column flex-sm-row">
            <div class="col-12 col-sm-6">
                <h1 class="chat-title">Ticket de <?php echo htmlspecialchars($ticket['CreationUser']); ?></h1>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($ticket['Name']); ?></p>
            </div>
            <div class="col-12 col-sm-6 d-flex align-items-center gap-2 justify-content-sm-end justify-content-start">
                <span class="badge bg-<?php echo getStatusColor($ticket['Status']); ?>">
                    <?php echo htmlspecialchars($ticket['Status']); ?>
                </span>
                <span class="badge bg-<?php echo getPriorityColor($ticket['Priority']); ?>">
                    <?php echo htmlspecialchars($ticket['Priority']); ?>
                </span>
            </div>
        </div>

        <!-- Secção de controlos de administrador -->
        <?php if ($ticket['Status'] !== 'Concluído'): ?>
        <div class="admin-controls">
            <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between admin-controls-header collapsed" 
                data-bs-toggle="collapse" 
                data-bs-target="#adminInfo" 
                aria-expanded="false" 
                aria-controls="adminInfo"
                style="cursor: pointer; text-decoration: none; color: inherit;">
                <h5 class="m-0">Informações Administrativas</h5>
            </a>
            <div class="collapse" id="adminInfo">
                <form id="adminUpdateForm">
                    <input type="hidden" name="keyid" value="<?php echo htmlspecialchars($ticket_id); ?>">
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <!-- Formulário de Atualização de Estado -->
                            <div class="form-group mb-3">
                                <label for="status" class="form-label">Estado</label>
                                <select id="status" name="status" class="form-select" data-original-value="<?php echo htmlspecialchars($ticket['Status']); ?>">
                                    <option value="Em Análise" <?php echo ($ticket['Status'] == 'Em Análise') ? 'selected' : ''; ?>>Em Análise</option>
                                    <option value="Em Resolução" <?php echo ($ticket['Status'] == 'Em Resolução') ? 'selected' : ''; ?>>Em Resolução</option>
                                    <option value="Aguarda Resposta" <?php echo ($ticket['Status'] == 'Aguarda Resposta') ? 'selected' : ''; ?>>Aguarda Resposta</option>
                                    <option value="Concluído" <?php echo ($ticket['Status'] == 'Concluído') ? 'selected' : ''; ?>>Concluído</option>
                                </select>
                            </div>

                            <!-- Formulário de Atualização de Utilizador Atribuído -->
                            <div class="form-group mb-3">
                                <label for="assigned_user" class="form-label">Atribuído a:</label>
                                <select id="assigned_user" name="assigned_user" class="form-select" data-original-value="<?php echo htmlspecialchars($ticket['User']); ?>">
                                    <option value="">Selecionar um responsável</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo ($ticket['User'] == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Formulário de Atualização de Tempo de Resolução -->
                            <div class="form-group mb-3">
                                <label for="resolution_time" class="form-label">Tempo de Resolução</label>
                                <input type="hidden" id="resolution_time" name="resolution_time" 
                                       value="<?php echo !empty($ticket['Time']) && $ticket['Time'] >= 15 ? htmlspecialchars($ticket['Time']) : '15'; ?>"
                                       data-original-value="<?php echo !empty($ticket['Time']) && $ticket['Time'] >= 15 ? htmlspecialchars($ticket['Time']) : '15'; ?>">
                                
                                <div class="time-control-container bg-whie">
                                    <div class="time-display">
                                        <span class="badge bg-primary" id="timeDisplay">
                                            <?php 
                                            $currentTime = !empty($ticket['Time']) && $ticket['Time'] >= 15 ? intval($ticket['Time']) : 15;
                                            $hours = floor($currentTime / 60);
                                            $minutes = $currentTime % 60;
                                            if ($hours > 0) {
                                                echo $hours . 'h';
                                                if ($minutes > 0) echo ' ' . $minutes . 'min';
                                            } else {
                                                echo $minutes . ' minuto' . ($minutes != 1 ? 's' : '');
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div class="time-buttons d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="adjustTime(-15)" id="removeTimeBtn">
                                            - 15min
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="adjustTime(15)">
                                            + 15min
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="adjustTime(30)">
                                            + 30min
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="adjustTime(60)">
                                            + 1h
                                        </button>
                                    </div>
                                </div>
                                
                                <small class="text-muted mt-2 d-block">Tempo mínimo: 15 minutos. Usar os botões para ajustar.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Formulário de Atualização de Descrição de Resolução -->
                            <div class="form-group mb-3">
                                <label for="resolution_description" class="form-label">Descrição da Resolução</label>
                                <textarea id="resolution_description" name="resolution_description" class="form-control" rows="3" 
                                          data-original-value="<?php echo htmlspecialchars($ticket['Descr'] ?? ''); ?>"><?php echo !empty($ticket['Descr']) ? htmlspecialchars($ticket['Descr']) : ''; ?></textarea>
                                <small class="text-muted">Descrever a solução aplicada para resolver o problema (visível ao cliente)</small>
                            </div>

                            <!-- Formulário de Atualização de Informação Extra -->
                            <div class="form-group mb-3">
                                <label for="extra_info" class="form-label">Informação Extra (Interna)</label>
                                <textarea id="extra_info" name="extra_info" class="form-control" rows="3" 
                                          data-original-value="<?php echo htmlspecialchars($ticket['info'] ?? ''); ?>"><?php echo !empty($ticket['info']) ? htmlspecialchars($ticket['info']) : ''; ?></textarea>
                                <small class="text-muted">Informações adicionais apenas para uso interno (não visível ao cliente)</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botões de Ação -->
                    <div class="d-flex justify-content-between mt-3 pt-3 border-top">                       
                        <div>
                            <button type="submit" class="btn btn-success" id="saveChangesBtn">
                                <i class="bi bi-save me-1"></i> Guardar Alterações
                            </button>
                            <button type="button" class="btn btn-secondary ms-2" onclick="cancelChanges()">
                                <i class="bi bi-x me-1"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="admin-controls">
            <div class="alert alert-info">
                <h5 class="mb-2"><i class="bi bi-lock-fill me-2"></i>Ticket Encerrado</h5>
                <p class="mb-0">Este ticket foi encerrado e não pode mais ser modificado. Todas as informações administrativas estão bloqueadas para preservar a integridade do registo.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="chat-body" id="chatBody">
            <!-- Mensagem de informação do ticket no topo -->
            <div class="ticket-info">
                <h5><?php echo htmlspecialchars($ticket['Name']); ?></h5>
                <p><strong>Descrição:</strong> <?php echo html_entity_decode(htmlspecialchars($ticket['Description']), ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Criado por:</strong> <?php echo htmlspecialchars($ticket['CreationUser']); ?></p>
                <p><strong>Criado em:</strong> <?php echo htmlspecialchars($ticket['CreationDate']); ?></p>
                <?php if (!empty($ticket['image'])) { 
                    // Fix image path for admin side
                    $imagePath = $ticket['image'];
                    if (!str_starts_with($imagePath, '../') && !str_starts_with($imagePath, 'http') && !str_starts_with($imagePath, '/')) {
                        $imagePath = '../' . $imagePath;
                    }
                ?>
                    <p><strong>Imagem:</strong>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Imagem do Ticket" class="message-image" 
                             onclick="showImage('<?php echo htmlspecialchars($imagePath); ?>')">
                    </p>
                <?php } ?>
            </div>

            <!-- Mensagens -->
            <?php
            if ($messages) {
                foreach ($messages as $message) {
                    $isUser = ($message['type'] == 1);
                    $messageClass = $isUser ? 'message-user' : 'message-admin';
                    $timestamp = date('H:i', strtotime($message['CommentTime']));

                    echo "<div class='message $messageClass'>";
                    echo "<p class='message-content'>" . nl2br(htmlspecialchars($message['Message'])) . "</p>";
                    echo "<div class='message-meta'>";
                    echo "<span class='message-user-info'>" . htmlspecialchars($message['user']) . "</span>";
                    echo "<span class='message-time'>" . $timestamp . "</span>";
                    echo "</div>";
                    echo "</div>";
                }
            } else {
                echo "<p class='text-muted text-center'>Nenhuma mensagem encontrada.</p>";
            }
            ?>
        </div>

        <!-- Formulário para Enviar Nova Mensagem -->
        <?php if ($ticket['Status'] !== 'Concluído') { ?>
            <form method="POST" action="inserir_mensagem.php" id="chatForm" enctype="multipart/form-data">
                <input type="hidden" name="keyid" value="<?php echo htmlspecialchars($ticket['KeyId']); ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($ticket['id']); ?>">
                <div class="chat-input-container">
                    <textarea name="message" class="chat-input" id="messageInput" placeholder="Escrever aqui a sua mensagem..." required></textarea>
                    <button type="submit" class="send-button" id="sendButton" disabled>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        <?php } else { ?>
            <div class="ticket-closed-info">
                <div class="d-flex justify-content-center align-items-center py-3 mb-3">
                    <p class="text-muted m-0"><i class="bi bi-lock-fill me-2"></i>Ticket encerrado. Não é possível enviar novas mensagens.</p>
                </div>
                
                <?php if (!empty($ticket['Descr'])) { ?>
                <div class="resolution-summary">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-check-circle-fill me-2"></i>Resolução do Ticket</h6>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title text-success">Descrição da Resolução:</h6>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($ticket['Descr'])); ?></p>
                            
                            <?php if (!empty($ticket['Time'])) { ?>
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    Tempo de resolução: 
                                    <strong>
                                        <?php 
                                        $hours = floor($ticket['Time'] / 60);
                                        $minutes = $ticket['Time'] % 60;
                                        if ($hours > 0) {
                                            echo $hours . 'h';
                                            if ($minutes > 0) echo ' ' . $minutes . 'min';
                                        } else {
                                            echo $minutes . ' minutos';
                                        }
                                        ?>
                                    </strong>
                                </small>
                            </div>
                            <?php } ?>
                            
                            <?php if (!empty($ticket['atribuido_a'])) { ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-person-fill me-1"></i>
                                    Resolvido por: <strong><?php echo htmlspecialchars($ticket['atribuido_a']); ?></strong>
                                </small>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        <?php } ?>

        <div class="d-flex justify-content-between mt-3">
            <a href="consultar_tickets.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar aos tickets
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
                
                // Enable/disable send button based on content
                document.getElementById('sendButton').disabled = this.value.trim().length === 0;
            });
        }

        // Function to adjust the resolution time
        function adjustTime(minutes) {
            const timeInput = document.getElementById('resolution_time');
            const timeDisplay = document.getElementById('timeDisplay');
            const removeTimeBtn = document.getElementById('removeTimeBtn');
            
            // Get current time in minutes
            let currentTime = parseInt(timeInput.value) || 15;
            
            // Calculate new time
            let newTime = currentTime + minutes;
            
            // Enforce minimum of 15 minutes
            if (newTime < 15) {
                newTime = 15;
            }
            
            // Update the input value
            timeInput.value = newTime;
            
            // Format for display
            let hours = Math.floor(newTime / 60);
            let mins = newTime % 60;
            let displayText = '';
            
            if (hours > 0) {
                displayText = hours + 'h';
                if (mins > 0) {
                    displayText += ' ' + mins + 'min';
                }
            } else {
                displayText = mins + ' minuto' + (mins != 1 ? 's' : '');
            }
            
            // Update the display
            timeDisplay.textContent = displayText;
            
            // Disable removeTimeBtn if at minimum time
            removeTimeBtn.disabled = (newTime <= 15);
        }

        // Function to show image in modal
        function showImage(imageUrl) {
            const modalImage = document.getElementById('modalImage');
            const downloadLink = document.getElementById('downloadImageLink');
            const openLink = document.getElementById('openImageLink');
            
            if (modalImage && downloadLink && openLink) {
                modalImage.src = imageUrl;
                downloadLink.href = imageUrl;
                openLink.href = imageUrl;
                
                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
            }
        }
        
        // Use polling instead of WebSocket
        let lastMessageTimestamp = '<?php echo !empty($messages) ? date('Y-m-d H:i:s', strtotime($messages[count($messages) - 1]['CommentTime'])) : date('Y-m-d H:i:s'); ?>';
        let deviceId = 'device_' + Math.random().toString(36).substring(2, 15);
        let syncCheckInterval = null;
        const processedMessageIds = new Set();
        
        // Simple polling function - no WebSocket
        function checkForNewMessages() {
            try {
                const timestamp = new Date().getTime();
                const ticketId = '<?php echo $ticket_id; ?>';
                const encodedTicketId = encodeURIComponent(ticketId);
                
                fetch('silent_sync.php?ticketId=' + encodedTicketId + 
                      '&deviceId=' + encodeURIComponent(deviceId) + 
                      '&lastCheck=' + encodeURIComponent(lastMessageTimestamp) + 
                      '&_=' + timestamp)
                    .then(response => {
                        if (!response.ok) {
                            return { hasNewMessages: false };
                        }
                        return response.json().catch(e => {
                            console.log("Error parsing JSON response:", e);
                            return { hasNewMessages: false };
                        });
                    })
                    .then(data => {
                        if (data && data.hasNewMessages && data.messages && data.messages.length > 0) {
                            const messagesToProcess = [];
                            
                            data.messages.forEach(message => {
                                const messageId = message.id || 
                                    `${message.user}-${message.CommentTime}-${message.Message?.substring(0, 20)}`;
                                    
                                if (!processedMessageIds.has(messageId)) {
                                    processedMessageIds.add(messageId);
                                    messagesToProcess.push(message);
                                }
                            });
                            
                            if (messagesToProcess.length > 0) {
                                processNewMessages(messagesToProcess);
                                
                                messagesToProcess.forEach(message => {
                                    if (message.CommentTime && message.CommentTime > lastMessageTimestamp) {
                                        lastMessageTimestamp = message.CommentTime;
                                    }
                                });
                            }
                        }
                    })
                    .catch(error => {
                        // Silent error handling
                    });
            } catch (error) {
                // Silent error handling
            }
        }
        
        // Process messages to add to the chat
        function processNewMessages(messages) {
            const chatBody = document.getElementById('chatBody');
            if (!chatBody) return;
            
            messages.forEach(message => {
                const isUser = (message.type == 1);
                const messageClass = isUser ? 'message-user' : 'message-admin';
                const timestamp = new Date(message.CommentTime).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
                
                const messageDiv = document.createElement('div');
                messageDiv.classList.add('message', messageClass);
                messageDiv.innerHTML = `
                    <p class="message-content">${message.Message}</p>
                    <div class="message-meta">
                        <span class="message-user-info">${message.user}</span>
                        <span class="message-time">${timestamp}</span>
                    </div>
                `;
                
                chatBody.appendChild(messageDiv);
                chatBody.scrollTop = chatBody.scrollHeight;
            });
        }
        
        // Form submission handling with debt time support
        document.addEventListener('DOMContentLoaded', function() {
            // Handle form submission
            const adminForm = document.getElementById('adminUpdateForm');
            let timeDebtModal;
            let formData;
            
            if (document.getElementById('timeDebtModal')) {
                timeDebtModal = new bootstrap.Modal(document.getElementById('timeDebtModal'));
                
                // Handle debt confirmation button
                document.getElementById('btnConfirmDebt').addEventListener('click', function() {
                    // Add debt flag to form data
                    formData.append('usar_debito', 'true');
                    
                    // Hide modal
                    timeDebtModal.hide();
                    
                    // Show loading state
                    const saveBtn = document.getElementById('saveChangesBtn');
                    const originalBtnText = saveBtn.innerHTML;
                    saveBtn.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i> A Guardar...';
                    saveBtn.disabled = true;
                    
                    // Submit with debt option enabled
                    submitFormWithData(formData, saveBtn, originalBtnText);
                });
            }
            
            if (adminForm) {
                adminForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Show loading state
                    const saveBtn = document.getElementById('saveChangesBtn');
                    const originalBtnText = saveBtn.innerHTML;
                    saveBtn.innerHTML = '<i class="bi bi-arrow-repeat spin me-1"></i> A Guardar...';
                    saveBtn.disabled = true;
                    
                    // Get form data
                    formData = new FormData(adminForm);
                    
                    // Check if trying to close ticket (status = Concluído)
                    const statusValue = formData.get('status');
                    
                    if (statusValue === 'Concluído') {
                        // First try without debt option to see if there's enough time
                        formData.append('usar_debito', 'false');
                    }
                    
                    // Submit the form
                    submitFormWithData(formData, saveBtn, originalBtnText);
                });
            }
            
            // Replace the submitFormWithData function with this improved version
            function submitFormWithData(formData, saveBtn, originalBtnText) {
                // Send request with explicit headers
                fetch('processar_alteracao.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Server error: ' + response.status);
                    }
                    return response.text();
                })
                .then(text => {
                    // First check if text is empty
                    if (!text || text.trim() === '') {
                        console.log('Empty response received');
                        showNotification('Resposta vazia recebida do servidor', 'error');
                        saveBtn.innerHTML = originalBtnText;
                        saveBtn.disabled = false;
                        return;
                    }
                    
                    console.log("Response received:", text);
                    
                    // Try to handle the response as JSON
                    let data;
                    try {
                        // Check if response is HTML instead of JSON
                        if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                            console.error('Received HTML response instead of JSON:');
                            console.error(text.substring(0, 500) + '...'); // Log part of the HTML
                            throw new Error('Server returned HTML page instead of JSON data');
                        }
                        
                        data = JSON.parse(text);
                        console.log("Parsed JSON data:", data);
                    } catch (e) {
                        console.error('Failed to parse response as JSON:', e);
                        console.log('Response received:', text.substring(0, 500) + '...');
                        showNotification('Erro ao processar resposta do servidor', 'error');
                        saveBtn.innerHTML = originalBtnText;
                        saveBtn.disabled = false;
                        return;
                    }
                    
                    // Check if we need to handle debt time scenario
                    if (data.success === false && data.podeUsarDebito === true && timeDebtModal) {
                        // Format time values for display
                        const formatTime = (minutes) => {
                            const h = Math.floor(minutes / 60);
                            const m = minutes % 60;
                            return (h > 0 ? h + 'h ' : '') + (m > 0 ? m + 'min' : (h === 0 ? '0min' : ''));
                        };
                        
                        // Update modal with time information
                        document.getElementById('modalTempoNecessario').textContent = formatTime(data.tempoNecessario);
                        document.getElementById('modalTempoDisponivel').textContent = formatTime(data.tempoDisponivel);
                        document.getElementById('modalTempoEmFalta').textContent = formatTime(data.tempoEmFalta);
                        
                        // Reset form button
                        saveBtn.innerHTML = originalBtnText;
                        saveBtn.disabled = false;
                        
                        // Show debt confirmation modal
                        timeDebtModal.show();
                        return;
                    }
                    
                    if (data.success) {
                        // Show success notification
                        showNotification('Alterações guardadas com sucesso!', 'success');
                        
                        // If ticket was closed, reload the page after a short delay
                        if (data.ticketFechado) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            // Reset form state
                            saveBtn.innerHTML = originalBtnText;
                            saveBtn.disabled = false;
                            
                            // Update original values to prevent unnecessary updates
                            updateOriginalValues();
                        }
                    } else {
                        // Show error notification
                        showNotification(data.message || 'Erro ao guardar alterações', 'error');
                        saveBtn.innerHTML = originalBtnText;
                        saveBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Erro ao processar pedido: ' + error.message, 'error');
                    saveBtn.innerHTML = originalBtnText;
                    saveBtn.disabled = false;
                });
            }
            
            // Function to update original values
            function updateOriginalValues() {
                const inputs = document.querySelectorAll('#adminUpdateForm [data-original-value]');
                inputs.forEach(input => {
                    input.setAttribute('data-original-value', input.value);
                });
            }
        });
        
        // Function to cancel changes
        function cancelChanges() {
            const inputs = document.querySelectorAll('#adminUpdateForm [data-original-value]');
            inputs.forEach(input => {
                const originalValue = input.getAttribute('data-original-value');
                if (input.tagName === 'SELECT') {
                    input.value = originalValue;
                } else if (input.tagName === 'TEXTAREA' || input.tagName === 'INPUT') {
                    input.value = originalValue;
                }
            });
            
            // Reset time display for resolution time
            const timeInput = document.getElementById('resolution_time');
            if (timeInput) {
                const originalTime = parseInt(timeInput.getAttribute('data-original-value')) || 15;
                adjustTime(originalTime - parseInt(timeInput.value));
            }
        }
        
        // Function to show notifications
        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} notification-toast`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.style.padding = '15px';
            notification.style.borderRadius = '5px';
            notification.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
            notification.style.animation = 'fadeIn 0.3s ease';
            
            // Add icon based on type
            const icon = document.createElement('i');
            icon.className = type === 'success' ? 'bi bi-check-circle me-2' : 'bi bi-exclamation-triangle me-2';
            notification.appendChild(icon);
            
            // Add message
            const textNode = document.createTextNode(message);
            notification.appendChild(textNode);
            
            // Add to body
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
        
        // Start polling on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Start polling for new messages every 2 seconds
            syncCheckInterval = setInterval(checkForNewMessages, 2000);
            
            // Initial check
            checkForNewMessages();
        });
    </script>
</body>
</html>