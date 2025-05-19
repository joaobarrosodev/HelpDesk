<?php
session_start();  // Inicia a sessão

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
        case 'média':
            return 'warning';
        default:
            return 'info';
    }
}

// Função para determinar a cor do status
function getStatusColor($status) {
    switch(strtolower($status)) {
        case 'concluído':
            return 'success';
        case 'em análise':
            return 'info';
        case 'pendente':
            return 'warning';
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
                <span class="chat-status badge bg-<?php echo getPriorityColor($ticket['Priority']); ?>"><?php echo $ticket['Priority']; ?></span>
                <span class="chat-status badge bg-<?php echo getStatusColor($ticket['Status']); ?>">
                    <?php echo $ticket['Status']; ?>
                </span>
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
        function showImage(src) {
            document.getElementById('modalImage').src = src;
            var myModal = new bootstrap.Modal(document.getElementById('imageModal'));
            myModal.show();
        }
        
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
        
        // Scroll to bottom of chat on load
        window.onload = function() {
            const chatBody = document.getElementById('chatBody');
            if (chatBody) {
                chatBody.scrollTop = chatBody.scrollHeight;
            }
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
                        <span class="message-user-info"><?php echo $_SESSION['usuario_email'] ?? 'Você'; ?></span>
                        <span class="message-time">${new Date().toLocaleTimeString('pt-PT', {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                `;
                chatBody.appendChild(messageDiv);
                chatBody.scrollTop = chatBody.scrollHeight;
                
                // Reset textarea
                messageInput.value = '';
                messageInput.style.height = 'auto';
                document.getElementById('sendButton').disabled = true;
                
                // Usar AJAX para enviar a mensagem em vez de submit normal
                const formData = new FormData(this);
                
                fetch('inserir_mensagem.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro ao enviar mensagem');
                    }
                    return response.text();
                })
                .then(data => {
                    // Mensagem enviada com sucesso
                    console.log('Mensagem enviada com sucesso');
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Ocorreu um erro ao enviar a mensagem. Por favor, tente novamente.');
                });
            }
        });
        
        function fecharTicket(id) {
            if (confirm('Tem certeza de que deseja fechar este ticket?')) {
                window.location.href = 'fechar_ticket.php?id=' + id;
            }
        }
    </script>
</body>
</html>
