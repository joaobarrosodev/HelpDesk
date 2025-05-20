<?php
session_start();
include('conflogin.php');
include('db.php');

// Admin access check
if (!isset($_SESSION['usuario_admin']) || !$_SESSION['usuario_admin']) {
    header('Location: login.php');
    exit;
}

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
    $sql_messages = "SELECT comments.id, comments.Message, comments.type, comments.Date as CommentTime, comments.user
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
<link href="../css/chat.css" rel="stylesheet">
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
                <h1 class="chat-title">Ticket de <?php echo $ticket['CreationUser']; ?></h1>
                <p class="text-muted mb-0"><?php echo $ticket['Name']; ?></p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-<?php echo getStatusColor($ticket['Status']); ?>">
                    <?php echo $ticket['Status']; ?>
                </span>    
                <span class="badge bg-<?php echo getPriorityColor($ticket['Priority']); ?>"><?php echo $ticket['Priority']; ?></span>
            </div>
        </div>
        
        <!-- Admin controls section -->
        <div class="admin-controls">
            <h5>Informações Administrativas</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Atribuído a:</label>
                        <div class="form-control bg-light"><?php echo !empty($ticket['atribuido_a']) ? $ticket['atribuido_a'] : 'Não atribuído'; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tempo despendido:</label>
                        <div class="form-control bg-light"><?php echo !empty($ticket['Time']) ? $ticket['Time'] : 'Não registrado'; ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Detalhes Internos:</label>
                        <div class="form-control bg-light" style="height: auto; min-height: 60px;"><?php echo !empty($ticket['Descr']) ? $ticket['Descr'] : 'Sem detalhes'; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Informações Extra:</label>
                        <div class="form-control bg-light" style="height: auto; min-height: 60px;"><?php echo !empty($ticket['info']) ? $ticket['info'] : 'Sem informações extras'; ?></div>
                    </div>
                </div>
            </div>
            <div class="flex-row d-flex" style="gap: 20px;">
                <div class="d-flex justify-content-end">
                    <a href="alterar_tickets.php?keyid=<?php echo $ticket['id']; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil-square me-1"></i> Editar Ticket
                    </a>
                </div>
                <?php if ($ticket['Status'] !== 'Concluído') { ?>
                    <button class="close-ticket-btn" onclick="fecharTicket(<?php echo $ticket['id']; ?>)">
                        <i class="bi bi-x-circle"></i> Fechar Ticket
                    </button>
                <?php } ?>
            </div>
        </div>
        
        <div class="chat-body" id="chatBody">
            <!-- Ticket information message at the top -->
            <div class="ticket-info">
                <h5><?php echo $ticket['Name']; ?></h5>
                <p><strong>Descrição:</strong> <?php echo $ticket['Description']; ?></p>
                <p><strong>Criado por:</strong> <?php echo $ticket['CreationUser']; ?></p>
                <p><strong>Criado em:</strong> <?php echo $ticket['CreationDate']; ?></p>                <?php if (!empty($ticket['image'])) { ?>
                <p><strong>Imagem:</strong>
                    <img src="<?php echo fixImagePath($ticket['image']); ?>" alt="Imagem do Ticket" class="message-image" onclick="showImage('<?php echo fixImagePath($ticket['image']); ?>')">
                </p>
                <?php } ?>
            </div>
            
            <!-- Messages -->
            <?php
            // Function to ensure image paths are correct
            function fixImagePath($path) {
                // If path is relative (doesn't start with http or /)
                if (!preg_match('/^(https?:\/\/|\/)/', $path)) {
                    return '../' . $path;
                }
                return $path;
            }
            
            if ($messages) {
                foreach ($messages as $message) {
                    $isUser = ($message['type'] == 1); // Type 1 = client, Type 0 = admin
                    $messageClass = $isUser ? 'message-user' : 'message-admin';
                    $timestamp = date('H:i', strtotime($message['CommentTime']));
                    
                    echo "<div class='message $messageClass' data-message-id='{$message['id']}'>";
                    echo "<p class='message-content'>" . nl2br(htmlspecialchars($message['Message'])) . "</p>";
                    echo "<div class='message-meta'>";
                    echo "<span class='message-user-info'>" . htmlspecialchars($message['user']) . "</span>";
                    echo "<span class='message-time'>" . $timestamp . "</span>";
                    echo "</div>";
                    echo "</div>";
                }
            }
            ?>
        </div>
        
        <div class="chat-footer">
            <?php if ($ticket['Status'] !== 'Concluído') { ?>
                <!-- Admin message form -->
                <form id="chatForm">
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
                <!-- Message for closed tickets -->
                <div class="d-flex justify-content-center align-items-center py-3">
                    <p class="text-muted m-0">Ticket fechado. Não é possível enviar novas mensagens.</p>
                </div>
            <?php } ?>
            
            <!-- Footer navigation -->
            <div class="d-flex justify-content-between mt-3">
                <a href="consultar_tickets.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar aos tickets
                </a>
                <?php if ($ticket['Status'] !== 'Concluído') { ?>
                <button class="close-ticket-btn" onclick="fecharTicket(<?php echo $ticket['id']; ?>)">
                    <i class="bi bi-x-circle"></i> Fechar Ticket
                </button>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/message-utils.js"></script>
    <script>
        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
                document.getElementById('sendButton').disabled = this.value.trim().length === 0;
            });
        }
        
        // Admin device ID
        let deviceId = localStorage.getItem('helpdesk_admin_device_id');
        if (!deviceId) {
            deviceId = 'admin_' + Math.random().toString(36).substring(2, 15);
            localStorage.setItem('helpdesk_admin_device_id', deviceId);
        }
        
        // Track last check time
        let lastCheckTime = '<?php echo !empty($messages) ? 
            date('Y-m-d H:i:s', strtotime($messages[count($messages) - 1]['CommentTime'])) : 
            date('Y-m-d H:i:s'); ?>';
        
        // Track processed message IDs
        const processedMessageIds = new Set();
        
        // Initialize chat
        let checkInterval;
        
        window.addEventListener('DOMContentLoaded', function() {
            console.log('Admin chat initialized, device ID: ' + deviceId);
            
            // Scroll to bottom initially
            scrollToBottom();
            
            // Track existing message IDs
            document.querySelectorAll('.message').forEach(msg => {
                const id = msg.getAttribute('data-message-id');
                if (id) {
                    processedMessageIds.add(id);
                    console.log('Added existing message ID to tracking: ' + id);
                }
            });
            
            // Set up message form
            setupMessageForm();
            
            // Start checking for new messages
            startMessageChecking();
        });
        
        // Scroll chat to bottom
        function scrollToBottom() {
            const chatBody = document.getElementById('chatBody');
            if (chatBody) {
                chatBody.scrollTop = chatBody.scrollHeight;
            }
        }
        
        // Start checking for messages
        function startMessageChecking() {
            if (checkInterval) {
                clearInterval(checkInterval);
            }
            checkInterval = setInterval(checkForNewMessages, 3000);
            console.log('Message checking started');
        }
        
        // Check for new messages
        function checkForNewMessages() {
            const ticketId = '<?php echo $ticket_id; ?>';
            const timestamp = new Date().getTime();
              console.log('Admin checking for new messages since: ' + lastCheckTime);
            
            fetch('../silent_check.php?ticketId=' + encodeURIComponent(ticketId) + 
                 '&lastCheck=' + encodeURIComponent(lastCheckTime) + 
                 '&deviceId=' + encodeURIComponent(deviceId) +
                 '&_=' + timestamp)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error checking messages: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('Error checking messages:', data.error);
                        return;
                    }
                    
                    console.log('Admin message check response:', data);
                    
                    if (data.hasNewMessages && data.messages && data.messages.length > 0) {
                        // Filter and add new messages
                        const newMessages = data.messages.filter(message => {
                            return !processedMessageIds.has(message.id.toString());
                        });
                        
                        if (newMessages.length > 0) {
                            console.log('New messages found:', newMessages.length);
                            addMessagesToChat(newMessages);
                            
                            // Update last check time to the latest message time
                            const latestMessage = newMessages.reduce((latest, msg) => {
                                return new Date(msg.CommentTime) > new Date(latest.CommentTime) ? msg : latest;
                            }, newMessages[0]);
                            
                            lastCheckTime = latestMessage.CommentTime;
                            console.log('Updated last check time to:', lastCheckTime);
                        }
                    }
                    
                    // Check if status has changed and potentially reload
                    if (data.currentStatus !== '<?php echo $ticket['Status']; ?>') {
                        console.log('Status changed, reloading page');
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error in message check:', error);
                });
        }
        
        // Add new messages to the chat
        function addMessagesToChat(messages) {
            const chatBody = document.getElementById('chatBody');
            let newMessagesAdded = false;
            
            messages.forEach(message => {
                // Mark as processed
                processedMessageIds.add(message.id.toString());
                
                // Determine message type and class (1=client, 0=admin)
                const type = parseInt(message.type);
                const isUser = (type === 1);
                const messageClass = isUser ? 'message-user' : 'message-admin';
                
                // Format time
                const timeDisplay = formatMessageTime(message.CommentTime);
                
                // Create message element
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${messageClass} new-message`;
                messageDiv.setAttribute('data-message-id', message.id);
                
                messageDiv.innerHTML = `
                    <p class="message-content">${message.Message.replace(/\n/g, '<br>')}</p>
                    <div class="message-meta">
                        <span class="message-user-info">${message.user}</span>
                        <span class="message-time">${timeDisplay}</span>
                    </div>
                `;
                
                chatBody.appendChild(messageDiv);
                newMessagesAdded = true;
            });
            
            // Scroll to bottom if new messages were added
            if (newMessagesAdded) {
                scrollToBottom();
            }
        }
        
        // Format message time for display
        function formatMessageTime(time) {
            try {
                const date = new Date(time);
                return date.toLocaleTimeString('pt-PT', {hour: '2-digit', minute: '2-digit'});
            } catch (e) {
                return time;
            }
        }
        
        // Set up message form
        function setupMessageForm() {
            const form = document.getElementById('chatForm');
            if (!form) return;
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const message = messageInput.value.trim();
                if (!message) return;
                
                // Show message preview
                const chatBody = document.getElementById('chatBody');
                const previewDiv = document.createElement('div');
                
                // Use 'message-admin' class for admin messages
                previewDiv.className = 'message message-admin new-message';
                previewDiv.innerHTML = `
                    <p class="message-content">${message.replace(/\n/g, '<br>')}</p>
                    <div class="message-meta">
                        <span class="message-user-info"><?php echo htmlspecialchars($_SESSION['usuario_email']); ?></span>
                        <span class="message-time">${formatMessageTime(new Date())}</span>
                    </div>
                `;
                
                chatBody.appendChild(previewDiv);
                scrollToBottom();
                
                // Save message and reset input
                const messageToSend = message;
                messageInput.value = '';
                messageInput.style.height = 'auto';
                document.getElementById('sendButton').disabled = true;
                  // Send message to server
                const formData = new FormData(this);
                formData.append('deviceId', deviceId); // Add device ID for tracking
                
                fetch('inserir_mensagem.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error sending message: ' + response.status);
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Failed to parse response as JSON:', text);
                            return { status: 'error', message: 'Invalid server response' };
                        }
                    });
                })
                .then(data => {
                    console.log('Admin message response:', data);
                    
                    if (data.status === 'success') {
                        // Mark this message as processed
                        if (data.messageId) {
                            previewDiv.setAttribute('data-message-id', data.messageId);
                            processedMessageIds.add(data.messageId.toString());
                        }
                        
                        // Update last check time if needed
                        if (data.messageTime && new Date(data.messageTime) > new Date(lastCheckTime)) {
                            lastCheckTime = data.messageTime;
                        }
                    } else {
                        throw new Error(data.message || 'Failed to send message');
                    }
                })
                .catch(error => {
                    console.error('Error sending admin message:', error);
                    
                    // Remove preview on error
                    previewDiv.remove();
                    
                    // Show error and restore input
                    alert('Erro ao enviar mensagem: ' + error.message);
                    messageInput.value = messageToSend;
                    messageInput.dispatchEvent(new Event('input'));
                });
            });
        }
        
        // Helper functions
        function fecharTicket(id) {
            if (confirm('Tem certeza de que deseja fechar este ticket?')) {
                window.location.href = 'fechar_ticket.php?id=' + id;
            }
        }
          function showImage(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }
        
        // Fix for relative image paths
        function fixImagePath(path) {
            if (!path) return '';
            // If path is relative (doesn't start with http or /)
            if (!path.match(/^(https?:\/\/|\/)/)) {
                return '../' + path;
            }
            return path;
        }
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (checkInterval) {
                clearInterval(checkInterval);
            }
        });
    </script>
</body>
</html>
