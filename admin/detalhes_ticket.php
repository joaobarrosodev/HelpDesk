<?php
// Try to auto-start the WebSocket server if needed
include('../auto-start.php');

include('conflogin.php');
include('db.php');

// Verificar se o 'KeyId' do ticket foi passado pela URL
if (isset($_GET['keyid'])) {
    $keyid = $_GET['keyid'];

    // Remover o símbolo '#' caso ele exista (se o banco não usa o '#')
    $keyid_sem_hash = str_replace('#', '', $keyid);
    
    // Consultar os detalhes do ticket
    $sql = "SELECT free.KeyId, free.id, free.Name, info.Description, info.Priority, info.Status,
            info.CreationUser, info.CreationDate, info.dateu, info.image, info.Atribuido as User,
            u.Name as atribuido_a, info.Tempo as Time, info.Relatorio as Descr, info.MensagensInternas as info
            FROM xdfree01 free
            LEFT JOIN info_xdfree01_extrafields info ON free.KeyId = info.XDFree01_KeyID
            LEFT JOIN users u ON info.Atribuido = u.id
            WHERE free.id = :keyid";

    // Preparar a consulta
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':keyid', $keyid);
    $stmt->execute();
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo "Ticket não encontrado.";
        exit;
    }

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
    
    // Consultar todos os usuários para o dropdown de atribuição
    $sql_users = "SELECT id, Name FROM users ORDER BY Name ASC";
    $stmt_users = $pdo->prepare($sql_users);
    $stmt_users->execute();
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} else {
    echo "Ticket não especificado.";
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
?>

<!DOCTYPE html>
<html lang="pt-pt">
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

    .close-ticket-btn {
        background-color: #dc3545;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 8px 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.3s;
    }

    .close-ticket-btn:hover {
        background-color: #c82333;
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
</style>

<!-- Modal para Exibir Imagem -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Imagem do Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid" alt="Imagem do Ticket">
            </div>
            <div class="modal-footer">
                <a id="downloadImageLink" href="#" class="btn btn-primary" download>Download</a>
                <a id="openImageLink" href="#" target="_blank" class="btn btn-secondary">Abrir em Nova Aba</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<body>
    <?php include('menu.php'); ?>

    <div class="content chat-container">
        <div class="chat-header">
            <div>
                <h1 class="chat-title">Ticket de <?php echo htmlspecialchars($ticket['CreationUser']); ?></h1>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($ticket['Name']); ?></p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-<?php echo getStatusColor($ticket['Status']); ?>">
                    <?php echo htmlspecialchars($ticket['Status']); ?>
                </span>
                <span class="badge bg-<?php echo getPriorityColor($ticket['Priority']); ?>">
                    <?php echo htmlspecialchars($ticket['Priority']); ?>
                </span>
            </div>
        </div>

        <!-- Admin controls section -->
        <div class="admin-controls">
            <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between admin-controls-header collapsed" 
                data-bs-toggle="collapse" 
                data-bs-target="#adminInfo" 
                aria-expanded="false" 
                aria-controls="adminInfo"
                style="cursor: pointer; text-decoration: none; color: inherit;">
                <h5 class="m-0">Informações Administrativas</h5>
                <i class="bi bi-chevron-down toggle-icon"></i>
            </a>
            <div class="collapse" id="adminInfo">
                <div class="row mt-3">
                    <div class="col-md-6">
                        <!-- Status Update Form -->
                        <div class="form-group mb-3">
                            <label for="status" class="form-label">Estado</label>
                            <div class="d-flex gap-2">
                                <select id="status" name="status" class="form-select" data-original-value="<?php echo htmlspecialchars($ticket['Status']); ?>">
                                    <option value="Em Análise" <?php echo ($ticket['Status'] == 'Em Análise') ? 'selected' : ''; ?>>Em Análise</option>
                                    <option value="Em Resolução" <?php echo ($ticket['Status'] == 'Em Resolução') ? 'selected' : ''; ?>>Em Resolução</option>
                                    <option value="Aguarda Resposta" <?php echo ($ticket['Status'] == 'Aguarda Resposta') ? 'selected' : ''; ?>>Aguarda Resposta</option>
                                    <option value="Concluído" <?php echo ($ticket['Status'] == 'Concluído') ? 'selected' : ''; ?>>Concluído</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateField('status')">
                                    <i class="bi bi-save"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Assigned User Update Form -->
                        <div class="form-group mb-3">
                            <label for="assigned_user" class="form-label">Atribuído a:</label>
                            <div class="d-flex gap-2">
                                <select id="assigned_user" name="assigned_user" class="form-select" data-original-value="<?php echo htmlspecialchars($ticket['User']); ?>">
                                    <option value="">Selecione um responsável</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo ($ticket['User'] == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateField('assigned_user')">
                                    <i class="bi bi-save"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Resolution Time Update Form -->
                        <div class="form-group mb-3">
                            <label for="resolution_time" class="form-label">Tempo de Resolução (minutos)</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="resolution_time" name="resolution_time" class="form-control" 
                                       min="1" step="1" placeholder="Ex: 90" 
                                       value="<?php echo !empty($ticket['Time']) ? htmlspecialchars($ticket['Time']) : ''; ?>"
                                       data-original-value="<?php echo !empty($ticket['Time']) ? htmlspecialchars($ticket['Time']) : ''; ?>">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="updateField('resolution_time')">
                                    <i class="bi bi-save"></i>
                                </button>
                            </div>
                            <small class="text-muted">Insira o tempo total em minutos (ex: 90 para 1 hora e 30 minutos)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <!-- Resolution Description Update Form -->
                        <div class="form-group mb-3">
                            <label for="resolution_description" class="form-label">Descrição da Resolução</label>
                            <div class="d-flex flex-column gap-2">
                                <textarea id="resolution_description" name="resolution_description" class="form-control" rows="3" 
                                          data-original-value="<?php echo htmlspecialchars($ticket['Descr'] ?? ''); ?>"><?php echo !empty($ticket['Descr']) ? htmlspecialchars($ticket['Descr']) : ''; ?></textarea>
                                <button type="button" class="btn btn-sm btn-outline-primary align-self-end" onclick="updateField('resolution_description')">
                                    <i class="bi bi-save"></i> Guardar
                                </button>
                            </div>
                            <small class="text-muted">Descreva a solução aplicada para resolver o problema (visível ao cliente)</small>
                        </div>

                        <!-- Extra Info Update Form -->
                        <div class="form-group mb-3">
                            <label for="extra_info" class="form-label">Informação Extra (Interna)</label>
                            <div class="d-flex flex-column gap-2">
                                <textarea id="extra_info" name="extra_info" class="form-control" rows="3" 
                                          data-original-value="<?php echo htmlspecialchars($ticket['info'] ?? ''); ?>"><?php echo !empty($ticket['info']) ? htmlspecialchars($ticket['info']) : ''; ?></textarea>
                                <button type="button" class="btn btn-sm btn-outline-primary align-self-end" onclick="updateField('extra_info')">
                                    <i class="bi bi-save"></i> Guardar
                                </button>
                            </div>
                            <small class="text-muted">Informações adicionais apenas para uso interno (não visível ao cliente)</small>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="d-flex justify-content-between mt-3 pt-3 border-top">
                    <?php if ($ticket['Status'] !== 'Concluído') { ?>
                        <button type="button" class="close-ticket-btn" onclick="fecharTicket(<?php echo $ticket['id']; ?>)">
                            <i class="bi bi-x-circle"></i> Fechar Ticket
                        </button>
                    <?php } else { ?>
                        <div></div>
                    <?php } ?>
                    
                    <button type="button" class="btn btn-secondary" onclick="resetAllFields()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Restaurar Valores
                    </button>
                </div>
            </div>
        </div>

        <div class="chat-body" id="chatBody">
            <!-- Ticket information message at the top -->
            <div class="ticket-info">
                <h5><?php echo htmlspecialchars($ticket['Name']); ?></h5>
                <p><strong>Descrição:</strong> <?php echo htmlspecialchars($ticket['Description']); ?></p>
                <p><strong>Criado por:</strong> <?php echo htmlspecialchars($ticket['CreationUser']); ?></p>
                <p><strong>Criado em:</strong> <?php echo htmlspecialchars($ticket['CreationDate']); ?></p>
                <?php if (!empty($ticket['image'])) { 
                    $imagePath = $ticket['image'];
                    $imagePath = str_replace('/HelpDesk', '', $imagePath);
                ?>
                    <p><strong>Imagem:</strong>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Imagem do Ticket" class="message-image" 
                             style="max-width: 300px; max-height: 300px; cursor: pointer;" 
                             onclick="showImage('<?php echo htmlspecialchars($imagePath); ?>')">
                    </p>
                <?php } ?>
            </div>

            <!-- Messages -->
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
                    <textarea name="message" class="chat-input" id="messageInput" placeholder="Escreva aqui a sua mensagem..." required></textarea>
                    <button type="submit" class="send-button" id="sendButton" disabled>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        <?php } else { ?>
            <div class="d-flex justify-content-center align-items-center py-3">
                <p class="text-muted m-0">Ticket fechado. Não é possível enviar novas mensagens.</p>
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

        // WebSocket connection variables
        let ws = null;
        const serverUrl = 'ws://' + window.location.hostname + ':8080';
        let wsConnected = false;
        let wsReconnectAttempts = 0;
        let wsLastErrorTime = 0;
        let lastMessageTimestamp = '<?php echo !empty($messages) ? date('Y-m-d H:i:s', strtotime($messages[count($messages) - 1]['CommentTime'])) : date('Y-m-d H:i:s'); ?>';
        let deviceId = generateDeviceId();
        let syncCheckInterval = null;
        const processedMessageIds = new Set();

        // Generate a unique device ID for this browser/device
        function generateDeviceId() {
            let id = localStorage.getItem('helpdesk_device_id');
            if (!id) {
                id = 'device_' + Math.random().toString(36).substring(2, 15) +
                    Math.random().toString(36).substring(2, 15);
                localStorage.setItem('helpdesk_device_id', id);
            }
            return id;
        }

        function initWebSocket() {
            const now = new Date().getTime();
            if (wsLastErrorTime > 0 && now - wsLastErrorTime < 5000) {
                setTimeout(initWebSocket, 5000);
                return;
            }

            try {
                if (ws) {
                    ws.onclose = null;
                    ws.close();
                }

                ws = new WebSocket(serverUrl);

                ws.onopen = function() {
                    wsConnected = true;
                    wsReconnectAttempts = 0;
                    const ticketId = '<?php echo $ticket_id; ?>';
                    subscribeToTicket(ticketId);

                    setInterval(function() {
                        if (ws && ws.readyState === WebSocket.OPEN) {
                            ws.send(JSON.stringify({
                                action: 'ping',
                                deviceId: deviceId,
                                timestamp: new Date().getTime()
                            }));
                        }
                    }, 30000);

                    console.log("WebSocket connection status: connected");
                };

                ws.onclose = function() {
                    wsConnected = false;
                    wsReconnectAttempts++;
                    const delay = Math.min(30000, wsReconnectAttempts * 2000);
                    setTimeout(initWebSocket, delay);
                    console.log("WebSocket connection status: disconnected");
                };

                ws.onerror = function(error) {
                    wsLastErrorTime = new Date().getTime();
                    wsConnected = false;
                    if (!syncCheckInterval) {
                        syncCheckInterval = setInterval(checkSyncFiles, 1000);
                    }
                    console.log("WebSocket connection error");
                };

                ws.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        if (data.action === 'newMessage' && data.ticketId === '<?php echo $ticket_id; ?>') {
                            if (data.message && !data.message.alreadySaved) {
                                saveMessageToDatabase(data.message, data.ticketId);
                            }
                            processNewMessages([data.message]);
                        }
                    } catch (e) {
                        console.error("Error processing WebSocket message:", e);
                    }
                };
            } catch (e) {
                wsLastErrorTime = new Date().getTime();
                if (!syncCheckInterval) {
                    syncCheckInterval = setInterval(checkSyncFiles, 1000);
                }
                console.log("WebSocket connection failed");
            }
        }

        function subscribeToTicket(ticketId) {
            if (!ws || ws.readyState !== WebSocket.OPEN) {
                console.log('Connection not ready, will retry subscription in 1 second');
                setTimeout(() => subscribeToTicket(ticketId), 1000);
                return;
            }
            
            console.log('Subscribing to ticket: ' + ticketId);
            try {
                ws.send(JSON.stringify({
                    action: 'subscribe',
                    ticketId: ticketId,
                    deviceId: deviceId
                }));
            } catch (e) {
                console.error('Error subscribing to ticket:', e);
            }
        }

        function sendWebSocketMessage(message, ticketId) {
            if (!ws || ws.readyState !== WebSocket.OPEN) {
                return false;
            }

            try {
                const messageObj = {
                    action: 'newMessage',
                    ticketId: ticketId,
                    message: message,
                    deviceId: deviceId
                };
                ws.send(JSON.stringify(messageObj));
                return true;
            } catch (e) {
                return false;
            }
        }

        function checkSyncFiles() {
            const timestamp = new Date().getTime();
            const ticketId = '<?php echo $ticket_id; ?>';
            const encodedTicketId = encodeURIComponent(ticketId);

            fetch('../silent_sync.php?ticketId=' + encodedTicketId +
                    '&deviceId=' + encodeURIComponent(deviceId) +
                    '&lastCheck=' + encodeURIComponent(lastMessageTimestamp) +
                    '&_=' + timestamp)
                .then(response => {
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data || data.error) {
                        return;
                    }

                    if (data.hasNewMessages && data.messages && data.messages.length > 0) {
                        const messagesToProcess = [];
                        data.messages.forEach(message => {
                            const messageId = message.messageId ||
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
                .catch(() => {
                    // Silent error handling
                });
        }

        function processNewMessages(messages) {
            if (!messages || messages.length === 0) {
                return;
            }

            const chatBody = document.getElementById('chatBody');
            let newMessagesAdded = false;

            messages.forEach(message => {
                if (!message || typeof message !== 'object') {
                    return;
                }

                const type = parseInt(message.type);
                const isUser = (type === 1);
                const messageClass = isUser ? 'message-user' : 'message-admin';
                const messageText = message.Message || '';
                const user = message.user || 'unknown';
                const time = message.CommentTime || new Date().toISOString();
                const messageKey = message.messageId || `${user}-${time}-${messageText.substring(0, 20)}`;

                if (document.querySelector(`[data-message-key="${messageKey}"]`)) {
                    return;
                }

                newMessagesAdded = true;
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${messageClass} new-message`;
                messageDiv.setAttribute('data-message-key', messageKey);

                let timeDisplay = formatMessageTime(time);

                messageDiv.innerHTML = `
                    <p class="message-content">${messageText.replace(/\n/g, '<br>')}</p>
                    <div class="message-meta">
                        <span class="message-user-info">${user}</span>
                        <span class="message-time">${timeDisplay}</span>
                    </div>
                `;

                chatBody.appendChild(messageDiv);
            });

            if (newMessagesAdded) {
                const isAtBottom = chatBody.scrollHeight - chatBody.clientHeight <= chatBody.scrollTop + 100;
                if (isAtBottom) {
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            }
        }

        function formatMessageTime(time) {
            try {
                const timeObj = new Date(time);
                if (isNaN(timeObj)) {
                    const parts = time.split(/[- :]/);
                    if (parts.length >= 6) {
                        const year = parseInt(parts[0]);
                        const month = parseInt(parts[1]) - 1;
                        const day = parseInt(parts[2]);
                        const hours = parseInt(parts[3]);
                        const minutes = parseInt(parts[4]);
                        const seconds = parseInt(parts[5]);

                        const hoursStr = hours.toString().padStart(2, '0');
                        const minutesStr = minutes.toString().padStart(2, '0');
                        return `${hoursStr}:${minutesStr}`;
                    } else {
                        return time;
                    }
                } else {
                    const hours = timeObj.getHours().toString().padStart(2, '0');
                    const minutes = timeObj.getMinutes().toString().padStart(2, '0');
                    return `${hours}:${minutes}`;
                }
            } catch (e) {
                console.error("Erro ao formatar hora:", e);
                return time;
            }
        }

        function saveMessageToDatabase(messageData, ticketId) {
            if (!messageData || !messageData.Message || !ticketId) {
                console.error("Invalid message data or ticket ID");
                return false;
            }

            const formData = new FormData();
            formData.append('keyid', ticketId);
            formData.append('id', '<?php echo $ticket['id']; ?>');
            formData.append('message', messageData.Message);
            formData.append('deviceId', deviceId);
            formData.append('ws_origin', '1');

            fetch('inserir_mensagem.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Error saving message: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Message saved to database:", data);
                    messageData.alreadySaved = true;
                })
                .catch(error => {
                    console.error("Failed to save message:", error);
                });
        }

        // Submit form with animation
        document.getElementById('chatForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = messageInput.value.trim();
            
            if (message) {
                // Add message with animation (preview)
                const chatBody = document.getElementById('chatBody');
                const messageDiv = document.createElement('div');
                messageDiv.className = 'message message-admin new-message';
                messageDiv.innerHTML = `
                    <p class="message-content">${message.replace(/\n/g, '<br>')}</p>
                    <div class="message-meta">
                        <span class="message-user-info"><?php echo $_SESSION['admin_email'] ?? 'Admin'; ?></span>
                        <span class="message-time">${new Date().toLocaleTimeString('pt-PT', {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                `;
                chatBody.appendChild(messageDiv);
                chatBody.scrollTop = chatBody.scrollHeight;

                // Reset textarea
                const messageToSend = message;
                messageInput.value = '';
                messageInput.style.height = 'auto';
                document.getElementById('sendButton').disabled = true;

                // Always use AJAX to ensure database persistence
                const formData = new FormData(this);
                
                // Ensure we're sending the right message
                if (!formData.get('message')) {
                    formData.set('message', messageToSend);
                }
                
                // Add device ID to the form data
                formData.append('deviceId', deviceId);

                // Direct form submission
                fetch('inserir_mensagem.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Erro ao enviar mensagem: ${response.status}`);
                        }
                        return response.text();
                    })
                    .then(data => {
                        console.log("Message saved successfully");
                        
                        // Force check for sync files to update any other clients
                        setTimeout(checkSyncFiles, 200);
                        
                        // Only try WebSocket after successful database save
                        if (wsConnected) {
                            const messageObj = {
                                Message: messageToSend,
                                user: '<?php echo $_SESSION['admin_email'] ?? "Admin"; ?>',
                                type: 2,
                                CommentTime: new Date().toISOString(),
                                deviceId: deviceId,
                                messageId: 'admin_msg_' + new Date().getTime(),
                                alreadySaved: true
                            };
                            sendWebSocketMessage(messageObj, '<?php echo $ticket_id; ?>');
                        }
                    })
                    .catch(error => {
                        console.error("Error saving message:", error);
                        
                        // Remove the preview message since it failed
                        if (messageDiv.parentNode) {
                            messageDiv.parentNode.removeChild(messageDiv);
                        }
                        
                        alert('Ocorreu um erro ao enviar a mensagem. Por favor, tente novamente.');
                        
                        // Restore the message so the admin doesn't have to retype it
                        messageInput.value = messageToSend;
                        messageInput.style.height = 'auto';
                        messageInput.style.height = (messageInput.scrollHeight) + 'px';
                        document.getElementById('sendButton').disabled = false;
                    });
            }
        });

        // Also handle button click explicitly
        document.getElementById('sendButton')?.addEventListener('click', function(e) {
            const form = document.getElementById('chatForm');
            if (form && messageInput.value.trim()) {
                form.dispatchEvent(new Event('submit', {
                    cancelable: true
                }));
            }
        });

        // Function to show image in modal
        function showImage(imageUrl) {
            const modalImage = document.getElementById('modalImage');
            const downloadLink = document.getElementById('downloadImageLink');
            const openLink = document.getElementById('openImageLink');

            if (modalImage) {
                // Make sure the image URL has the correct base path
                if (!imageUrl.startsWith('http') && !imageUrl.startsWith('/')) {
                    imageUrl = '/' + imageUrl;
                }

                modalImage.src = imageUrl;
                modalImage.style.maxWidth = '100%';
                modalImage.style.maxHeight = '80vh';

                // Set the download link
                if (downloadLink) {
                    downloadLink.href = imageUrl;
                    const filename = imageUrl.split('/').pop();
                    downloadLink.setAttribute('download', filename);
                }
                
                // Set the open link
                if (openLink) {
                    openLink.href = imageUrl;
                }
                
                new bootstrap.Modal(document.getElementById('imageModal')).show();
            }
        }

        // Function to update individual fields
        function updateField(fieldName) {
            const field = document.getElementById(fieldName);
            const originalValue = field.getAttribute('data-original-value') || '';
            const currentValue = field.value.trim();

            // Check if value has changed
            if (currentValue === originalValue) {
                alert('Nenhuma alteração foi feita neste campo.');
                return;
            }

            // Validate specific fields
            if (fieldName === 'resolution_time') {
                if (isNaN(currentValue) || currentValue <= 0) {
                    alert('O tempo de resolução deve ser um número positivo!');
                    return;
                }
            }

            // Show confirmation
            if (!confirm(`Tem certeza que deseja atualizar o campo "${getFieldLabel(fieldName)}"?`)) {
                return;
            }

            // Create form data for single field update
            const formData = new FormData();
            formData.append('keyid', '<?php echo htmlspecialchars($ticket_id); ?>');
            formData.append('field_name', fieldName);
            formData.append('field_value', currentValue);
            formData.append('single_field_update', '1');

            // Show loading state
            const button = field.parentNode.querySelector('button');
            const originalButtonHtml = button.innerHTML;
            button.innerHTML = '<i class="bi bi-arrow-repeat spin"></i>';
            button.disabled = true;

            // Send update request
            fetch('processar_alteracao.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the original value attribute
                    field.setAttribute('data-original-value', currentValue);
                    
                    // Show success message
                    showNotification('Campo atualizado com sucesso!', 'success');
                    
                    // If status was updated, refresh the page to update the badge
                    if (fieldName === 'status') {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    showNotification('Erro ao atualizar campo: ' + (data.message || 'Erro desconhecido'), 'error');
                    // Reset field to original value
                    field.value = originalValue;
                }
            })
            .catch(error => {
                console.error('Error updating field:', error);
                showNotification('Erro ao atualizar campo. Tente novamente.', 'error');
                // Reset field to original value
                field.value = originalValue;
            })
            .finally(() => {
                // Reset button state
                button.innerHTML = originalButtonHtml;
                button.disabled = false;
            });
        }

        // Function to get field label for display
        function getFieldLabel(fieldName) {
            const labels = {
                'status': 'Estado',
                'assigned_user': 'Responsável',
                'resolution_time': 'Tempo de Resolução',
                'resolution_description': 'Descrição da Resolução',
                'extra_info': 'Informação Extra'
            };
            return labels[fieldName] || fieldName;
        }

        // Function to reset all fields to original values
        function resetAllFields() {
            if (!confirm('Tem certeza que deseja restaurar todos os campos aos valores originais?')) {
                return;
            }

            const fields = ['status', 'assigned_user', 'resolution_time', 'resolution_description', 'extra_info'];
            fields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                const originalValue = field.getAttribute('data-original-value') || '';
                field.value = originalValue;
            });

            showNotification('Campos restaurados aos valores originais.', 'info');
        }

        // Function to show notifications
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.admin-notification');
            existingNotifications.forEach(n => n.remove());

            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show admin-notification`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            document.body.appendChild(notification);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        function fecharTicket(id) {
            if (confirm('Tem certeza que deseja fechar este ticket?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'processar_alteracao.php';
                form.style.display = 'none';

                const assignedUser = document.getElementById('assigned_user')?.value || '';
                const resolutionTime = document.getElementById('resolution_time').value;

                // Validate time
                if (isNaN(resolutionTime) || resolutionTime <= 0) {
                    alert('O tempo de resolução deve ser um número positivo!');
                    return;
                }

                const fields = {
                    'keyid': id,
                    'status': 'Concluído',
                    'assigned_user': assignedUser,
                    'resolution_time': resolutionTime,
                    'resolution_description': document.getElementById('resolution_description').value || 'Ticket fechado pelo administrador',
                    'extra_info': document.getElementById('extra_info').value || ''
                };

                // Create form fields
                for (const key in fields) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = fields[key];
                    form.appendChild(input);
                }

                // Add form to body and submit
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Initialize when page loads
        window.addEventListener('DOMContentLoaded', function() {
            // Create temp directory if it doesn't exist
            fetch('../create_temp_dir.php')
                .then(response => response.json())
                .catch(() => {});

            // Scroll chat to bottom
            const chatBody = document.getElementById('chatBody');
            if (chatBody) {
                chatBody.scrollTop = chatBody.scrollHeight;
            }

            // Try to connect to WebSocket
            initWebSocket();

            // Start sync file checking as a fallback
            syncCheckInterval = setInterval(checkSyncFiles, 1000);

            // Add existing message IDs to the set
            document.querySelectorAll('.message').forEach(msg => {
                const user = msg.querySelector('.message-user-info')?.textContent || 'unknown';
                const time = msg.querySelector('.message-time')?.textContent || '';
                const text = msg.querySelector('.message-content')?.textContent.substring(0, 20) || '';
                const compositeId = `${user}-${time}-${text}`;
                processedMessageIds.add(compositeId);
            });

            // Admin controls accordion
            const accordionHeader = document.querySelector('.admin-controls-header');
            const accordionContent = document.getElementById('adminInfo');

            if (accordionHeader && accordionContent) {
                if (!accordionContent.classList.contains('show')) {
                    accordionHeader.classList.add('collapsed');
                }

                accordionHeader.addEventListener('click', function(e) {
                    e.preventDefault();

                    if (accordionContent.classList.contains('show')) {
                        accordionContent.classList.remove('show');
                        accordionHeader.classList.add('collapsed');
                        accordionHeader.setAttribute('aria-expanded', 'false');
                    } else {
                        accordionContent.classList.add('show');
                        accordionHeader.classList.remove('collapsed');
                        accordionHeader.setAttribute('aria-expanded', 'true');
                    }

                    return false;
                });
            }

            // Handle cancel button - now just resets fields
            const cancelEditBtn = document.getElementById('cancelEditBtn');
            if (cancelEditBtn) {
                // The cancel button no longer exists in the new design
                // This section can be removed or repurposed
            }

            // Remove old form submission handling since we now use individual field updates
            const updateTicketForm = document.getElementById('updateTicketForm');
            if (updateTicketForm) {
                // The form no longer exists in the new design
                // This section can be removed
            }

            // File upload functionality
            const chatForm = document.getElementById('chatForm');
            if (chatForm) {
                const chatInputContainer = chatForm.querySelector('.chat-input-container');
                if (chatInputContainer) {
                    // Add file upload button
                    const fileUploadBtn = document.createElement('button');
                    fileUploadBtn.type = 'button';
                    fileUploadBtn.className = 'file-upload-button';
                    fileUploadBtn.innerHTML = '<i class="bi bi-image"></i>';
                    fileUploadBtn.title = 'Anexar imagem';

                    // Create hidden file input
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.id = 'fileInput';
                    fileInput.name = 'fileInput';
                    fileInput.accept = 'image/*';
                    fileInput.style.display = 'none';

                    // Add elements to form
                    chatInputContainer.insertBefore(fileUploadBtn, document.getElementById('sendButton'));
                    chatForm.appendChild(fileInput);

                    // Handle file upload button click
                    fileUploadBtn.addEventListener('click', function() {
                        fileInput.click();
                    });

                    // Handle file selection
                    fileInput.addEventListener('change', function() {
                        if (this.files && this.files[0]) {
                            const file = this.files[0];
                            
                            // Show loading indicator
                            fileUploadBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i>';
                            fileUploadBtn.disabled = true;

                            // Create FormData to upload file
                            const formData = new FormData();
                            formData.append('file', file);

                            // Upload file via AJAX
                            fetch('upload_imagem.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                // Reset button
                                fileUploadBtn.innerHTML = '<i class="bi bi-image"></i>';
                                fileUploadBtn.disabled = false;

                                if (data.success) {
                                    // Add image link to message input
                                    const messageInput = document.getElementById('messageInput');
                                    const imagePath = data.caminho;
                                    
                                    // Insert at cursor position or append
                                    const currentText = messageInput.value;
                                    const imageText = `[Imagem anexada](${imagePath})`;
                                    
                                    if (messageInput.selectionStart || messageInput.selectionStart === 0) {
                                        const startPos = messageInput.selectionStart;
                                        const endPos = messageInput.selectionEnd;
                                        
                                        messageInput.value = currentText.substring(0, startPos) + 
                                                           imageText + 
                                                           currentText.substring(endPos);
                                        
                                        // Set cursor position after inserted text
                                        messageInput.selectionStart = startPos + imageText.length;
                                        messageInput.selectionEnd = startPos + imageText.length;
                                    } else {
                                        messageInput.value += imageText;
                                    }
                                    
                                    // Trigger input event to resize textarea
                                    messageInput.dispatchEvent(new Event('input'));
                                    messageInput.focus();
                                } else {
                                    // Show error
                                    alert('Erro ao enviar imagem: ' + (data.erro || 'Erro desconhecido'));
                                }
                                
                                // Reset file input
                                fileInput.value = '';
                            })
                            .catch(error => {
                                console.error('Upload error:', error);
                                fileUploadBtn.innerHTML = '<i class="bi bi-image"></i>';
                                fileUploadBtn.disabled = false;
                                alert('Erro ao enviar imagem. Por favor, tente novamente.');
                                fileInput.value = '';
                            });
                        }
                    });
                }
            }

            // Update message processing to handle image markdown
            const processingMessage = function(message) {
                // Replace image markdown with actual image tags
                const imgRegex = /\[Imagem anexada\]\(([^)]+)\)/g;
                return message.replace(imgRegex, function(match, imagePath) {
                    return `<img src="${imagePath}" class="message-image" onclick="showImage('${imagePath}')" style="max-width: 200px; cursor: pointer;">`;
                });
            };

            // Update existing message processing function
            const originalProcessNewMessages = window.processNewMessages;
            if (typeof originalProcessNewMessages === 'function') {
                window.processNewMessages = function(messages) {
                    // Process image markdown in messages
                    messages.forEach(message => {
                        if (message && message.Message) {
                            message.Message = processingMessage(message.Message);
                        }
                    });
                    
                    // Call original function
                    return originalProcessNewMessages(messages);
                };
            }
        });
    </script>
</body>
</html>