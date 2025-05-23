<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session information
error_log("detalhes_ticket.php - Session ID: " . session_id());
error_log("detalhes_ticket.php - Session data: " . print_r($_SESSION, true));
error_log("detalhes_ticket.php - GET parameters: " . print_r($_GET, true));

// Try to auto-start the WebSocket server if needed
include('auto-start.php');

// The session_start() call was removed from here to prevent "session already started" errors

include('conflogin.php');
include('db.php');

// Verificar se o 'KeyId' do ticket foi passado pela URL
if (isset($_GET['keyid'])) {
    $keyid = $_GET['keyid'];

    // Consultar os detalhes do ticket usando KeyId
    $sql = "SELECT free.KeyId, free.id, free.Name, info.Description, info.Priority, info.Status, 
            info.CreationUser, info.CreationDate, info.dateu, info.image, info.Atribuido as User, 
            u.Name as atribuido_a, info.Tempo as Time, info.Relatorio as Descr, info.MensagensInternas as info
            FROM xdfree01 free
            LEFT JOIN info_xdfree01_extrafields info ON free.KeyId = info.XDFree01_KeyID
            LEFT JOIN users u ON info.Atribuido = u.id
            WHERE free.KeyId = :keyid";  // Use KeyId for comparison

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
                     ORDER BY comments.Date ASC";  // Ordenar pela data

    $stmt_messages = $pdo->prepare($sql_messages);
    $stmt_messages->bindParam(':keyid', $ticket_id);
    $stmt_messages->execute();
    $messages = $stmt_messages->fetchAll(PDO::FETCH_ASSOC);

} else {
    echo "Ticket não especificado.";
    exit;
}

// Função para determinar a cor da prioridade
function getPriorityColor($priority) {
    switch(strtolower($priority)) {
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
function getStatusColor($status) {
    switch(strtolower(trim($status))) {
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
<link href="css/chat.css" rel="stylesheet">
<style>
    .message.new-message {
        animation: fadeIn 0.5s;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
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

    /* WebSocket status indicator */
    .ws-status {
        display: inline-flex;
        align-items: center;
        font-size: 0.8rem;
        padding: 2px 6px;
        border-radius: 12px;
        margin-left: 10px;
    }

    .ws-status-connected {
        background-color: #d4edda;
        color: #155724;
    }

    .ws-status-disconnected {
        background-color: #f8d7da;
        color: #721c24;
    }

    .ws-status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 5px;
    }

    .ws-status-connected .ws-status-indicator {
        background-color: #28a745;
    }

    .ws-status-disconnected .ws-status-indicator {
        background-color: #dc3545;
    }
</style>
<!-- Modal para Exibir Imagem -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imageModalLabel">Imagem do Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img id="modalImage" src="" class="img-fluid" alt="Imagem do Ticket">
      </div>
    </div>
  </div>
</div>
<body>
    <?php include('menu.php'); ?>
    
    <div class="content chat-container">
        <div class="chat-header">
            <div>
                <h1 class="chat-title">A falar com <?php echo !empty($ticket['atribuido_a']) ? $ticket['atribuido_a'] : 'Ninguém'; ?></h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span id="ticket-status" class="badge bg-<?php echo getStatusColor($ticket['Status']); ?>">
                    <?php echo $ticket['Status']; ?>
                </span>    
                <span class="badge bg-<?php echo getPriorityColor($ticket['Priority']); ?>"><?php echo $ticket['Priority']; ?></span>
            </div>
        </div>
        
        <div class="chat-body" id="chatBody">
            <!-- Ticket information message at the top -->
            <div class="ticket-info">
                <h5><?php echo $ticket['Name']; ?></h5>
                <p><strong>Descrição:</strong> <?php echo $ticket['Description']; ?></p>
                <p><strong>Criado por:</strong> <?php echo $ticket['CreationUser']; ?></p>
                <p><strong>Criado em:</strong> <?php echo $ticket['CreationDate']; ?></p>
                <?php if (!empty($ticket['image'])) { ?>
                <p><strong>Imagem:</strong>
                    <img src="<?php echo $ticket['image']; ?>" alt="Imagem do Ticket" class="message-image" style="max-width: 200px; cursor: pointer;" onclick="showImage('<?php echo $ticket['image']; ?>')">
                </p>
                <?php } ?>
                <?php if (!empty($ticket['Time'])) { ?>
                <?php } ?>
            </div>
            
            <!-- Messages -->
            <?php
            if ($messages) {
                foreach ($messages as $message) {
                    $isUser = ($message['type'] == 1);
                    $messageClass = $isUser ? 'message-user' : 'message-admin';
                    $userInitial = substr($message['user'], 0, 1);
                    $timestamp = date('H:i', strtotime($message['CommentTime']));
                    
                    echo "<div class='message $messageClass'>";
                    echo "<p class='message-content'>" . nl2br($message['Message']) . "</p>";
                    echo "<div class='message-meta'>";
                    echo "<span class='message-user-info'>" . $message['user'] . "</span>";
                    echo "<span class='message-time'>" . $timestamp . "</span>";
                    echo "</div>";
                    echo "</div>";
                }
            }
?>
        </div>
        
        <div class="chat-footer">
            <?php if ($ticket['Status'] !== 'Concluído') { ?>
                <!-- Formulário para Enviar Nova Mensagem -->
                <form method="POST" action="inserir_mensagem.php" id="chatForm">
                    <input type="hidden" name="keyid" value="<?php echo $ticket['KeyId']; ?>">
                    <input type="hidden" name="id" value="<?php echo $ticket['id']; ?>">
                    <div class="chat-input-container">
                        <textarea name="message" class="chat-input" id="messageInput" placeholder="Escreva aqui a sua mensagem..." required></textarea>
                        <button type="submit" class="send-button" id="sendButton" disabled>
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </form>
            <?php } else { ?>
                <!-- Caso o estado seja "Fechado", exibe uma mensagem informando -->
                <div class="d-flex justify-content-center align-items-center py-3">
                    <p class="text-muted m-0">Ticket fechado. Não é possível enviar novas mensagens.</p>
                </div>
            <?php } ?>
            
            <div class="d-flex justify-content-between mt-3">
                <a href="meus_tickets.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar aos meus tickets
                </a>
                <?php if ($ticket['Status'] !== 'Concluído' && isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']) { ?>
                    <button class="close-ticket-btn" onclick="fecharTicket(<?php echo $ticket['id']; ?>)">
                        <i class="bi bi-x-circle"></i> Fechar Ticket
                    </button>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Inclusão do JS do Bootstrap -->
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
        
        // WebSocket connection
        let ws = null;
        const serverUrl = 'ws://' + window.location.hostname + ':8080';
        let wsConnected = false;
        let wsReconnectAttempts = 0;
        let wsLastErrorTime = 0;

        // Simplified approach - only keep track of the last message timestamp
        // and use sync files for communication
        let lastMessageTimestamp = '<?php echo !empty($messages) ? date('Y-m-d H:i:s', strtotime($messages[count($messages)-1]['CommentTime'])) : date('Y-m-d H:i:s'); ?>';
        let deviceId = generateDeviceId(); // Generate unique device ID
        let syncCheckInterval = null;
        
        // Keep track of processed message IDs to avoid duplicates
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
        
        // Initialize WebSocket connection
        function initWebSocket() {
            // Don't try to reconnect too frequently
            const now = new Date().getTime();
            if (wsLastErrorTime > 0 && now - wsLastErrorTime < 5000) {
                setTimeout(initWebSocket, 5000);
                return;
            }
            
            try {
                // Close existing connection if any
                if (ws) {
                    ws.onclose = null; // Remove onclose handler to prevent reconnect loop
                    ws.close();
                }
                
                ws = new WebSocket(serverUrl);
                ws.onopen = function() {
                    wsConnected = true;
                    wsReconnectAttempts = 0;
                    
                    // Subscribe to this ticket
                    const ticketId = '<?php echo $ticket_id; ?>';
                    subscribeToTicket(ticketId);
                    
                    // Send a ping every 30 seconds to keep connection alive
                    setInterval(function() {
                        if (ws && ws.readyState === WebSocket.OPEN) {
                            ws.send(JSON.stringify({
                                action: 'ping',
                                deviceId: deviceId,
                                timestamp: new Date().getTime()
                            }));
                        }
                    }, 30000);

                    // Update WebSocket status indicator
                    updateWebSocketStatus(true);
                };
                
                ws.onclose = function() {
                    wsConnected = false;
                    
                    // Attempt to reconnect if not closing intentionally
                    wsReconnectAttempts++;
                    const delay = Math.min(30000, wsReconnectAttempts * 2000); // Exponential backoff
                    
                    setTimeout(initWebSocket, delay);

                    // Update WebSocket status indicator
                    updateWebSocketStatus(false);
                };
                
                ws.onerror = function(error) {
                    wsLastErrorTime = new Date().getTime();
                    wsConnected = false;
                    
                    // Fallback to sync file approach
                    if (!syncCheckInterval) {
                        syncCheckInterval = setInterval(checkSyncFiles, 1000);
                    }

                    // Update WebSocket status indicator
                    updateWebSocketStatus(false);
                };                ws.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        
                        // Handle different message types
                        if (data.action === 'newMessage' && data.ticketId === '<?php echo $ticket_id; ?>') {
                            // Process new message
                            if (data.message && !data.message.alreadySaved) {
                                // If message doesn't have alreadySaved flag, save it to database via AJAX
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
                
                // Fallback to sync file approach
                if (!syncCheckInterval) {
                    syncCheckInterval = setInterval(checkSyncFiles, 1000);
                }

                // Update WebSocket status indicator
                updateWebSocketStatus(false);
            }
        }
        
        // Subscribe to ticket updates
        function subscribeToTicket(ticketId) {
            if (!ws || ws.readyState !== WebSocket.OPEN) {
                console.log('Connection not ready, will retry subscription in 1 second');
                // Connection not ready yet, retry after a short delay
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
        
        // Send message via WebSocket
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
          // Check if the WebSocket server is healthy
        function checkWebSocketHealth() {
            fetch('ws-healthcheck.php?silent=1')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Health check failed');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'ok' && !wsConnected) {
                        // If the health check shows issues and we're not connected, 
                        // try to reconnect to the WebSocket server
                        setTimeout(initWebSocket, 1000);
                    }
                })
                .catch(() => {
                    // Silent error handling
                });
        }
        
        // Run health check every 30 seconds
        const healthCheckInterval = setInterval(checkWebSocketHealth, 30000);
        
        // Initialize chat when page loads
        window.addEventListener('DOMContentLoaded', function() {
            // Create temp directory if it doesn't exist
            fetch('create_temp_dir.php')
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
        });
        
        // Function to check for sync files
        function checkSyncFiles() {
            const timestamp = new Date().getTime();
            // Get the ticket ID directly from the original variable
            const ticketId = '<?php echo $ticket_id; ?>';
            
            // Encode the ticket ID properly for URL
            const encodedTicketId = encodeURIComponent(ticketId);
            
            fetch('silent_sync.php?ticketId=' + encodedTicketId + 
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
                    // Skip if null response or error
                    if (!data || data.error) {
                        return;
                    }
                    
                    if (data.hasNewMessages && data.messages && data.messages.length > 0) {
                        // Process new messages without logging
                        const messagesToProcess = [];
                            
                        // Filter out duplicate messages
                        data.messages.forEach(message => {
                            const messageId = message.messageId || 
                                `${message.user}-${time}-${message.Message?.substring(0, 20)}`;

                            if (!processedMessageIds.has(messageId)) {
                                processedMessageIds.add(messageId);
                                messagesToProcess.push(message);
                            }
                        });
                        
                        if (messagesToProcess.length > 0) {
                            // Process the unique messages
                            processNewMessages(messagesToProcess);
                                
                            // Update the last message timestamp if newer messages were found
                            messagesToProcess.forEach(message => {
                                if (message.CommentTime && message.CommentTime > lastMessageTimestamp) {
                                    lastMessageTimestamp = message.CommentTime;
                                }
                            });
                        }
                    }
                })
                .catch(() => {
                    // Silent error handling - no logging
                });
        }
        
        // Function to process new messages - modified for silent operation
        function processNewMessages(messages) {
            if (!messages || messages.length === 0) {
                return;
            }
            
            // Add new messages to the chat
            const chatBody = document.getElementById('chatBody');
            let newMessagesAdded = false;
            
            messages.forEach(message => {
                // Skip if message is invalid
                if (!message || typeof message !== 'object') {
                    return;
                }
                
                // Determine message type and class
                const type = parseInt(message.type);
                const isUser = (type === 1);
                const messageClass = isUser ? 'message-user' : 'message-admin';
                
                // Get message text with fallback
                const messageText = message.Message || '';
                
                // Create a unique key to identify this message
                const user = message.user || 'unknown';
                const time = message.CommentTime || new Date().toISOString();
                
                // Use messageId if available, otherwise create a composite key
                const messageKey = message.messageId || `${user}-${time}-${messageText.substring(0, 20)}`;
                
                // Skip if we already have this message in the DOM
                if (document.querySelector(`[data-message-key="${messageKey}"]`)) {
                    return;
                }
                
                newMessagesAdded = true;
                
                // Create message element
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${messageClass} new-message`;
                messageDiv.setAttribute('data-message-key', messageKey);
                
                // Format time display
                let timeDisplay = formatMessageTime(time);
                
                // Set content with safe fallbacks
                messageDiv.innerHTML = `
                    <p class="message-content">${messageText}</p>
                    <div class="message-meta">
                        <span class="message-user-info">${user}</span>
                        <span class="message-time">${timeDisplay}</span>
                    </div>
                `;
                
                // Add message to chat
                chatBody.appendChild(messageDiv);
            });
            
            // Scroll to bottom if we added new messages
            if (newMessagesAdded) {
                const isAtBottom = chatBody.scrollHeight - chatBody.clientHeight <= chatBody.scrollTop + 100;
                if (isAtBottom) {
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            }
        }
        
        // Helper function to format message time
        function formatMessageTime(time) {
            try {
                // Tenta criar um objeto Date com a string fornecida
                const timeObj = new Date(time);

                if (isNaN(timeObj)) {
                    // Se não é uma string de data válida, tenta analisar como um datetime do MySQL
                    const parts = time.split(/[- :]/);
                    if (parts.length >= 6) {
                        // Cria a data explicitamente no fuso horário local para evitar conversão automática
                        // O MySQL armazena em UTC e precisamos garantir que mostramos o horário correto
                        const year = parseInt(parts[0]);
                        const month = parseInt(parts[1]) - 1; // Meses em JS são 0-11
                        const day = parseInt(parts[2]);
                        const hours = parseInt(parts[3]);
                        const minutes = parseInt(parts[4]);
                        const seconds = parseInt(parts[5]);

                        // Formatar hora sem conversão de fuso horário
                        const hoursStr = hours.toString().padStart(2, '0');
                        const minutesStr = minutes.toString().padStart(2, '0');
                        return `${hoursStr}:${minutesStr}`;
                    } else {
                        return time;
                    }
                } else {
                    // Para datas criadas pelo JS no cliente (ex: new Date().toISOString())
                    // Extrair horas e minutos diretamente
                    const hours = timeObj.getHours().toString().padStart(2, '0');
                    const minutes = timeObj.getMinutes().toString().padStart(2, '0');

                    return `${hours}:${minutes}`;
                }
            } catch (e) {
                console.error("Erro ao formatar hora:", e);
                return time;
            }
        }
        
        // Function to save message to database via AJAX
        function saveMessageToDatabase(messageData, ticketId) {
            if (!messageData || !messageData.Message || !ticketId) {
                console.error("Invalid message data or ticket ID");
                return false;
            }
            
            // Create form data
            const formData = new FormData();
            formData.append('keyid', ticketId);
            formData.append('id', '<?php echo $ticket['id']; ?>');
            formData.append('message', messageData.Message);
            formData.append('deviceId', deviceId);
            formData.append('ws_origin', '1'); // Flag to indicate this came from WebSocket
            
            // Send to server
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
                // Mark as saved to prevent duplicate saves
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
                // Determine message class based on user type
                const isAdmin = <?php echo (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']) ? 'true' : 'false'; ?>;
                const messageClass = isAdmin ? 'message-admin' : 'message-user';
                
                // Create a unique message ID for tracking
                const tempMessageId = 'temp_' + new Date().getTime() + '_' + Math.random().toString(36).substring(7);
                const currentTime = new Date().toLocaleTimeString('pt-PT', {hour: '2-digit', minute:'2-digit'});
                
                // Add message with animation (preview)
                const chatBody = document.getElementById('chatBody');
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${messageClass} new-message temp-message`;
                messageDiv.setAttribute('data-message-key', tempMessageId);
                messageDiv.setAttribute('data-temp-message', 'true');
                messageDiv.innerHTML = `
                    <p class="message-content">${message.replace(/\n/g, '<br>')}</p>
                    <div class="message-meta">
                        <span class="message-user-info"><?php echo $_SESSION['usuario_email'] ?? 'Você'; ?></span>
                        <span class="message-time">${currentTime}</span>
                    </div>
                `;
                chatBody.appendChild(messageDiv);
                chatBody.scrollTop = chatBody.scrollHeight;
                
                // Add to processed messages immediately to prevent sync duplication
                const messageKey = `<?php echo $_SESSION['usuario_email'] ?? 'Você'; ?>-${currentTime}-${message.substring(0, 20)}`;
                processedMessageIds.add(messageKey);
                processedMessageIds.add(tempMessageId);
                
                // Store message details for cleanup
                const messageToSend = message;
                const messageUserKey = `<?php echo $_SESSION['usuario_email'] ?? 'Você'; ?>`;
                
                // Reset textarea
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
                    return response.json();
                })
                .then(data => {
                    console.log("Message saved successfully");
                    
                    // Don't remove the temp message immediately
                    // Instead, mark it for removal and let sync detect the real message
                    messageDiv.setAttribute('data-awaiting-sync', 'true');
                    
                    // Create final message object for WebSocket
                    if (wsConnected) {
                        const finalMessageObj = {
                            Message: messageToSend,
                            user: messageUserKey,
                            type: <?php echo (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']) ? '2' : '1'; ?>,
                            CommentTime: new Date().toISOString(),
                            deviceId: deviceId,
                            messageId: 'msg_' + new Date().getTime(),
                            alreadySaved: true
                        };

                        // Add final message key to processed set
                        const finalMessageKey = `${finalMessageObj.user}-${formatMessageTime(finalMessageObj.CommentTime)}-${finalMessageObj.Message.substring(0, 20)}`;
                        processedMessageIds.add(finalMessageKey);
                        processedMessageIds.add(finalMessageObj.messageId);

                        sendWebSocketMessage(finalMessageObj, '<?php echo $ticket_id; ?>');
                    }

                    // Force check for sync files to detect our own message
                    setTimeout(() => {
                        checkSyncFiles();
                        // Check again after a delay to ensure we caught the message
                        setTimeout(checkSyncFiles, 1000);
                    }, 500);
                    
                    // Safety fallback: remove temp message after 10 seconds if still there
                    setTimeout(() => {
                        if (messageDiv.parentNode && messageDiv.getAttribute('data-temp-message') === 'true') {
                            console.log("Safety cleanup: removing temp message");
                            messageDiv.parentNode.removeChild(messageDiv);
                        }
                    }, 10000);
                })
                .catch(error => {
                    console.error("Error saving message:", error);

                    // Remove the preview message since it failed
                    if (messageDiv.parentNode) {
                        messageDiv.parentNode.removeChild(messageDiv);
                    }

                    // Remove from processed messages since it failed
                    processedMessageIds.delete(messageKey);
                    processedMessageIds.delete(tempMessageId);

                    alert('Ocorreu um erro ao enviar a mensagem. Por favor, tente novamente.');

                    // Restore the message so the user doesn't have to retype it
                    messageInput.value = messageToSend;
                    messageInput.style.height = 'auto';
                    messageInput.style.height = (messageInput.scrollHeight) + 'px';
                    document.getElementById('sendButton').disabled = false;
                });
            }
        });
        
        // Function to process new messages - improved deduplication and temp message handling
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
                
                // Create multiple possible keys for this message
                const possibleKeys = [
                    message.messageId,
                    `${user}-${formatMessageTime(time)}-${messageText.substring(0, 20)}`,
                    `${user}-${time}-${messageText.substring(0, 20)}`,
                    `db_${message.messageId}`,
                    `msg_${message.messageId}`
                ];
                
                // Check if any of these keys already exist
                let messageExists = false;
                possibleKeys.forEach(key => {
                    if (key && processedMessageIds.has(key)) {
                        messageExists = true;
                    }
                });
                
                // Also check DOM for existing message (but not temp messages)
                if (!messageExists) {
                    possibleKeys.forEach(key => {
                        if (key) {
                            const existingElement = document.querySelector(`[data-message-key="${key}"]`);
                            if (existingElement && !existingElement.getAttribute('data-temp-message')) {
                                messageExists = true;
                            }
                        }
                    });
                }
                
                // Special handling: if this message matches a temp message from the same user,
                // remove the temp message and add the real one
                const tempMessageSelector = `[data-temp-message="true"][data-awaiting-sync="true"]`;
                const tempMessages = document.querySelectorAll(tempMessageSelector);
                
                tempMessages.forEach(tempMsg => {
                    const tempUser = tempMsg.querySelector('.message-user-info')?.textContent;
                    const tempTime = tempMsg.querySelector('.message-time')?.textContent;
                    const tempText = tempMsg.querySelector('.message-content')?.textContent;
                    
                    // Check if this real message matches the temp message
                    if (tempUser === user && tempText === messageText) {
                        console.log("Found matching temp message, removing it");
                        tempMsg.parentNode.removeChild(tempMsg);
                        messageExists = false; // Allow the real message to be added
                    }
                });
                
                if (messageExists) {
                    return;
                }
                
                // Add all possible keys to processed set
                possibleKeys.forEach(key => {
                    if (key) {
                        processedMessageIds.add(key);
                    }
                });
                
                newMessagesAdded = true;
                
                // Create message element
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${messageClass} new-message`;
                messageDiv.setAttribute('data-message-key', message.messageId || possibleKeys[1]);
                
                // Format time display
                let timeDisplay = formatMessageTime(time);
                
                messageDiv.innerHTML = `
                    <p class="message-content">${messageText}</p>
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

        // Initialize when page loads
        window.addEventListener('DOMContentLoaded', function() {
            // ...existing code...
            
            // Add existing message IDs to the set with multiple key formats
            document.querySelectorAll('.message').forEach(msg => {
                const user = msg.querySelector('.message-user-info')?.textContent || 'unknown';
                const time = msg.querySelector('.message-time')?.textContent || '';
                const text = msg.querySelector('.message-content')?.textContent.substring(0, 20) || '';
                
                // Add multiple possible key formats
                const keys = [
                    `${user}-${time}-${text}`,
                    `${user.trim()}-${time.trim()}-${text.trim()}`,
                    `${user}-${time}-${text.replace(/\s+/g, ' ')}`
                ];
                
                keys.forEach(key => {
                    if (key) {
                        processedMessageIds.add(key);
                    }
                });
            });
        });
        // Also handle button click explicitly (for mobile devices and certain browsers)
        document.getElementById('sendButton')?.addEventListener('click', function(e) {
            // If the form is valid, trigger submit event
            const form = document.getElementById('chatForm');
            if (form && messageInput.value.trim()) {
                form.dispatchEvent(new Event('submit', {cancelable: true}));
            }
        });

        // Function to show image in modal
        function showImage(imageUrl) {
            const modalImage = document.getElementById('modalImage');
            if (modalImage) {
                modalImage.src = imageUrl;
                new bootstrap.Modal(document.getElementById('imageModal')).show();
            }
        }

        // Function to update WebSocket connection status indicator
        function updateWebSocketStatus(isConnected) {
            // Simply update the connection status variable
            wsConnected = isConnected;
            console.log("WebSocket connection status: " + (isConnected ? "connected" : "disconnected"));
        }

        // Function to close a ticket
        function fecharTicket(id) {
            if (confirm('Tem certeza que deseja fechar este ticket?')) {
                window.location.href = 'admin/processar_alteracao.php?id=' + id + '&Status=Concluído';
            }
        }
    </script>

    <!-- Include the WebSocket client script -->
    <script src="js/websocket-client.js"></script>
</body>
</html>

