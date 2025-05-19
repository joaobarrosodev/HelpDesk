<?php
// Try to auto-start the WebSocket server if needed
include('auto-start.php');

// The session_start() call was removed from here to prevent "session already started" errors

include('conflogin.php');
include('db.php');

// Verificar se o 'KeyId' do ticket foi passado pela URL
if (isset($_GET['keyid'])) {
    $keyid = $_GET['keyid'];

    // Remover o símbolo '#' caso ele exista (se o banco não usa o '#')
    $keyid_sem_hash = str_replace('#', '', $keyid);

    // Consultar os detalhes do ticket
    $sql = "SELECT free.KeyId, free.id, free.Name, info.Description, info.Priority, info.Status, 
            info.CreationUser, info.CreationDate, info.dateu, info.image, internal.User, 
            u.Name as atribuido_a, internal.Time, internal.Description as Descr, internal.info
            FROM xdfree01 free
            LEFT JOIN info_xdfree01_extrafields info ON free.KeyId = info.XDFree01_KeyID
            LEFT JOIN internal_xdfree01_extrafields internal on free.KeyId = internal.XDFree01_KeyID
            LEFT JOIN users u ON internal.User = u.id
            WHERE free.id = :keyid";  // Comparar sem o #

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
                <h1 class="chat-title">A falar com <?php echo !empty($ticket['atribuido_a']) ? $ticket['atribuido_a'] : 'Não atribuído'; ?></h1>
            </div>
            <div class="d-flex align-items-center gap-2">
            <span class="badge bg-<?php echo getStatusColor($ticket['Status']); ?>">
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
                    <img src="<?php echo $ticket['image']; ?>" alt="Imagem do Ticket" class="message-image" onclick="showImage('<?php echo $ticket['image']; ?>')">
                </p>
                <?php } ?>
                <?php if (!empty($ticket['Time'])) { ?>
                <p><strong>Tempo despendido:</strong> <?php echo $ticket['Time']; ?></p>
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
                console.log("Waiting before reconnect attempt...");
                setTimeout(initWebSocket, 5000);
                return;
            }
            
            try {
                // Close existing connection if any
                if (ws) {
                    ws.onclose = null; // Remove onclose handler to prevent reconnect loop
                    ws.close();
                }
                
                console.log("Connecting to WebSocket server at " + serverUrl);
                ws = new WebSocket(serverUrl);
                
                ws.onopen = function() {
                    console.log("WebSocket connection established");
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
                };
                
                ws.onclose = function() {
                    console.log("WebSocket connection closed");
                    wsConnected = false;
                    
                    // Attempt to reconnect if not closing intentionally
                    wsReconnectAttempts++;
                    const delay = Math.min(30000, wsReconnectAttempts * 2000); // Exponential backoff
                    
                    console.log(`Attempting to reconnect in ${delay/1000} seconds...`);
                    setTimeout(initWebSocket, delay);
                };
                
                ws.onerror = function(error) {
                    console.error("WebSocket error:", error);
                    wsLastErrorTime = new Date().getTime();
                    wsConnected = false;
                    
                    // Fallback to sync file approach
                    if (!syncCheckInterval) {
                        console.log("Starting sync file polling as fallback");
                        syncCheckInterval = setInterval(checkSyncFiles, 1000);
                    }
                };
                
                ws.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        console.log("WebSocket message received:", data);
                        
                        // Handle different message types
                        if (data.action === 'newMessage' && data.ticketId === '<?php echo $ticket_id; ?>') {
                            // Process new message
                            processNewMessages([data.message]);
                        } else if (data.action === 'pong') {
                            // Server ping response, connection is alive
                            console.log("Server pong received");
                        }
                    } catch (e) {
                        console.error("Error processing WebSocket message:", e);
                    }
                };
            } catch (e) {
                console.error("Error initializing WebSocket:", e);
                wsLastErrorTime = new Date().getTime();
                
                // Fallback to sync file approach
                if (!syncCheckInterval) {
                    console.log("Starting sync file polling as fallback");
                    syncCheckInterval = setInterval(checkSyncFiles, 1000);
                }
            }
        }
        
        // Subscribe to ticket updates
        function subscribeToTicket(ticketId) {
            if (!ws || ws.readyState !== WebSocket.OPEN) {
                console.log("Cannot subscribe: WebSocket not connected");
                return;
            }
            
            console.log("Subscribing to ticket:", ticketId);
            ws.send(JSON.stringify({
                action: 'subscribe',
                ticketId: ticketId,
                deviceId: deviceId
            }));
        }
        
        // Send message via WebSocket
        function sendWebSocketMessage(message, ticketId) {
            if (!ws || ws.readyState !== WebSocket.OPEN) {
                console.log("Cannot send message: WebSocket not connected");
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
                console.error("Error sending WebSocket message:", e);
                return false;
            }
        }
        
        // Initialize chat when page loads
        window.addEventListener('DOMContentLoaded', function() {
            // Create temp directory if it doesn't exist
            fetch('create_temp_dir.php')
                .then(response => response.json())
                .then(data => {
                    console.log("Temp directory check:", data);
                })
                .catch(error => {
                    console.error("Error checking temp directory:", error);
                });
                
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
            fetch('check_sync.php?ticketId=<?php echo $ticket_id; ?>&deviceId=' + encodeURIComponent(deviceId) + '&lastCheck=' + lastMessageTimestamp + '&_=' + timestamp)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Network response not ok: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Sync check result:", data);
                    
                    if (data.hasNewMessages && data.messages && data.messages.length > 0) {
                        // Process new messages
                        const messagesToProcess = [];
                        
                        // Filter out duplicate messages
                        data.messages.forEach(message => {
                            // Use messageId if available, otherwise create a composite key
                            const messageId = message.messageId || 
                                `${message.user}-${message.CommentTime}-${message.Message?.substring(0, 20)}`;
                            
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
                .catch(error => {
                    console.error("Error checking sync files:", error);
                });
        }
        
        // Function to process new messages
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
                
                console.log(`Adding new message: ${messageKey}`);
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
                const timeObj = new Date(time);
                if (isNaN(timeObj)) {
                    // If not a valid date string, try to parse it as a MySQL datetime
                    const parts = time.split(/[- :]/);
                    if (parts.length >= 6) {
                        const timeObj = new Date(parts[0], parts[1]-1, parts[2], parts[3], parts[4], parts[5]);
                        return timeObj.toLocaleTimeString('pt-PT', {hour: '2-digit', minute:'2-digit'});
                    } else {
                        return time;
                    }
                } else {
                    return timeObj.toLocaleTimeString('pt-PT', {hour: '2-digit', minute:'2-digit'});
                }
            } catch (e) {
                console.error("Error formatting time:", e);
                return time;
            }
        }
        
        // Submit form with animation
        document.getElementById('chatForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (message) {
                // Determine message class based on user type
                const isAdmin = <?php echo (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']) ? 'true' : 'false'; ?>;
                const messageClass = isAdmin ? 'message-admin' : 'message-user';
                
                // Add message with animation (preview)
                const chatBody = document.getElementById('chatBody');
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${messageClass} new-message`;
                messageDiv.innerHTML = `
                    <p class="message-content">${message.replace(/\n/g, '<br>')}</p>
                    <div class="message-meta">
                        <span class="message-user-info"><?php echo $_SESSION['usuario_email'] ?? 'Você'; ?></span>
                        <span class="message-time">${new Date().toLocaleTimeString('pt-PT', {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                `;
                chatBody.appendChild(messageDiv);
                chatBody.scrollTop = chatBody.scrollHeight;
                
                // Reset textarea
                const messageToSend = message; // Store message before resetting input
                messageInput.value = '';
                messageInput.style.height = 'auto';
                document.getElementById('sendButton').disabled = true;
                
                // Create message object
                const messageObj = {
                    Message: messageToSend,
                    user: '<?php echo $_SESSION['usuario_email'] ?? "Usuário"; ?>',
                    type: <?php echo (isset($_SESSION['usuario_admin']) && $_SESSION['usuario_admin']) ? '2' : '1'; ?>,
                    CommentTime: new Date().toISOString(),
                    deviceId: deviceId,
                    messageId: 'msg_' + new Date().getTime()
                };
                
                // Try to send via WebSocket first
                let wsSuccess = false;
                if (wsConnected) {
                    wsSuccess = sendWebSocketMessage(messageObj, '<?php echo $ticket_id; ?>');
                }
                
                // If WebSocket fails or not connected, use AJAX as fallback
                if (!wsSuccess) {
                    // Usar AJAX para enviar a mensagem em vez de submit normal
                    const formData = new FormData(this);
                    
                    // Ensure we're sending the right message (in case the form was cleared too early)
                    if (!formData.get('message')) {
                        formData.set('message', messageToSend);
                    }
                    
                    // Add device ID to the form data
                    formData.append('deviceId', deviceId);
                    
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
                        // Try to parse JSON, but handle if it's not JSON
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.log("Response wasn't JSON:", text);
                                return { status: 'success', message: text };
                            }
                        });
                    })
                    .then(data => {
                        console.log('Mensagem enviada com sucesso:', data);
                        
                        // Force check for sync files to update any other clients
                        setTimeout(checkSyncFiles, 200);
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        // Remove the preview message since it failed
                        if (messageDiv.parentNode) {
                            messageDiv.parentNode.removeChild(messageDiv);
                        }
                        alert('Ocorreu um erro ao enviar a mensagem. Por favor, tente novamente.');
                        // Restore the message so the user doesn't have to retype it
                        messageInput.value = messageToSend;
                        messageInput.style.height = 'auto';
                        messageInput.style.height = (messageInput.scrollHeight) + 'px';
                        document.getElementById('sendButton').disabled = false;
                    });
                }
            }
        });
        
        // Also handle button click explicitly (for mobile devices and certain browsers)
        document.getElementById('sendButton')?.addEventListener('click', function(e) {
            e.preventDefault();
            // Trigger the form submission
            const form = document.getElementById('chatForm');
            if (form) {
                // Create and dispatch a submit event
                const submitEvent = new Event('submit', {
                    bubbles: true,
                    cancelable: true,
                });
                form.dispatchEvent(submitEvent);
            }
        });
        
        function fecharTicket(id) {
            if (confirm('Tem certeza de que deseja fechar este ticket?')) {
                window.location.href = 'fechar_ticket.php?id=' + id;
            }
        }
        
        function showImage(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }
        
        // Cleanup when leaving the page
        window.addEventListener('beforeunload', function() {
            if (syncCheckInterval) clearInterval(syncCheckInterval);
            if (ws && wsConnected) {
                ws.onclose = null; // Prevent reconnection attempts
                ws.close();
            }
        });
    </script>
</body>
</html>
