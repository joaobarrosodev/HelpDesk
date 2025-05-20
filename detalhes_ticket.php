<?php
session_start();
include('conflogin.php');
include('db.php');

// Verificar se o 'KeyId' do ticket foi passado pela URL
if (isset($_GET['keyid'])) {
    $keyid = $_GET['keyid'];

    // Consultar os detalhes do ticket
    $sql = "SELECT free.KeyId, free.id, free.Name, info.Description, info.Priority, info.Status, 
            info.CreationUser, info.CreationDate, info.dateu, info.image, internal.User, 
            u.Name as atribuido_a, internal.Time, internal.Description as Descr, internal.info
            FROM xdfree01 free
            LEFT JOIN info_xdfree01_extrafields info ON free.KeyId = info.XDFree01_KeyID
            LEFT JOIN internal_xdfree01_extrafields internal on free.KeyId = internal.XDFree01_KeyID
            LEFT JOIN users u ON internal.User = u.id
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
    $sql_messages = "SELECT comments.id, comments.Message, comments.type, comments.Date as CommentTime, comments.user
                     FROM comments_xdfree01_extrafields comments
                     WHERE comments.XDFree01_KeyID = :keyid
                     ORDER BY comments.Date ASC";

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
                <!-- Form for sending messages -->
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
            
            <div class="d-flex justify-content-between mt-3">
                <a href="meus_tickets.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar aos meus tickets
                </a>
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
                document.getElementById('sendButton').disabled = this.value.trim().length === 0;
            });
        }
        
        // Client deviceId - used to prevent duplicate messages
        let deviceId = localStorage.getItem('helpdesk_device_id');
        if (!deviceId) {
            deviceId = 'client_' + Math.random().toString(36).substring(2, 15);
            localStorage.setItem('helpdesk_device_id', deviceId);
        }
        
        // Track last check time for polling
        let lastCheckTime = '<?php echo !empty($messages) ? 
            date('Y-m-d H:i:s', strtotime($messages[count($messages) - 1]['CommentTime'])) : 
            date('Y-m-d H:i:s'); ?>';
        
        // Keep track of processed message IDs to avoid duplicates
        const processedMessageIds = new Set();
        
        // Initialize chat when page loads
        let checkInterval;
        
        window.addEventListener('DOMContentLoaded', function() {
            console.log('Chat initialized, device ID: ' + deviceId);
            
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
        
        // Start regular checking for new messages
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
              console.log('Checking for new messages since: ' + lastCheckTime);
            
            fetch('silent_check.php?ticketId=' + encodeURIComponent(ticketId) + 
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
                    
                    console.log('Message check response:', data);
                    
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
        
        // Format time for display
        function formatMessageTime(time) {
            try {
                const date = new Date(time);
                return date.toLocaleTimeString('pt-PT', {hour: '2-digit', minute: '2-digit'});
            } catch (e) {
                return time;
            }
        }
        
        // Set up the message form
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
                
                // Using 'message-user' class for client messages
                previewDiv.className = 'message message-user new-message';
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
                    console.log('Message sent successfully:', data);
                    
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
                    console.error('Error sending message:', error);
                    
                    // Remove preview on error
                    previewDiv.remove();
                    
                    // Show error and restore input
                    alert('Erro ao enviar mensagem: ' + error.message);
                    messageInput.value = messageToSend;
                    messageInput.dispatchEvent(new Event('input'));
                });
            });
        }
        
        // Functions for ticket actions
        function fecharTicket(id) {
            if (confirm('Tem certeza de que deseja fechar este ticket?')) {
                window.location.href = 'fechar_ticket.php?id=' + id;
            }
        }
        
        function showImage(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }
        
        // Clean up when leaving the page
        window.addEventListener('beforeunload', function() {
            if (checkInterval) {
                clearInterval(checkInterval);
            }
        });
    </script>
</body>
</html>
