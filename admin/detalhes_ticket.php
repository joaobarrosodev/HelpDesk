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
    
    // TODO:

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
    <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between admin-controls-header" 
       data-bs-toggle="collapse" 
       data-bs-target="#adminInfo" 
       aria-expanded="true" 
       aria-controls="adminInfo" 
       style="cursor: pointer; text-decoration: none; color: inherit;">
        <h5 class="m-0">Informações Administrativas</h5>
        <i class="bi bi-chevron-down toggle-icon"></i>
    </a>
    <div class="collapse show" id="adminInfo">
        <div class="row mt-3">
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
        <div class="flex-row d-flex">
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
                <a href="consultar_tickets.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar aos tickets
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
                
                // Enable/disable send button based on content
                document.getElementById('sendButton').disabled = this.value.trim().length === 0;
            });
        }
        
        // Initialize chat when page loads
        window.addEventListener('DOMContentLoaded', function() {
            // Scroll chat to bottom
            const chatBody = document.getElementById('chatBody');
            if (chatBody) {
                chatBody.scrollTop = chatBody.scrollHeight;
            }
            
            // Start sync file checking
            setInterval(checkForUpdates, 1000);const adminHeader = document.querySelector('.admin-controls-header');
    const adminInfoCollapse = document.getElementById('adminInfo');
    const toggleIcon = document.querySelector('.toggle-icon');
    
    // Verificar se o Bootstrap está carregado
    if (typeof bootstrap !== 'undefined') {
        // Usar collapse do Bootstrap
        const bsCollapse = new bootstrap.Collapse(adminInfoCollapse, {
            toggle: false
        });
        
        // Adicionar listener para o evento show.bs.collapse
        adminInfoCollapse.addEventListener('show.bs.collapse', function() {
            toggleIcon.style.transform = 'rotate(0deg)';
            adminHeader.setAttribute('aria-expanded', 'true');
            adminHeader.classList.remove('collapsed');
        });
        
        // Adicionar listener para o evento hide.bs.collapse
        adminInfoCollapse.addEventListener('hide.bs.collapse', function() {
            toggleIcon.style.transform = 'rotate(-90deg)';
            adminHeader.setAttribute('aria-expanded', 'false');
            adminHeader.classList.add('collapsed');
        });
    } else {
        console.warn('Bootstrap não foi carregado, usando fallback manual');
        
        // Implementação manual do comportamento de collapse
        adminHeader.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Toggle a classe show
            const isExpanded = adminInfoCollapse.classList.toggle('show');
            
            // Atualizar o atributo aria-expanded
            this.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            
            // Alternar a classe collapsed
            this.classList.toggle('collapsed', !isExpanded);
            
            // Girar o ícone
            if (toggleIcon) {
                toggleIcon.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(-90deg)';
            }
        });
    }
    
    // Inicializar o estado do ícone baseado no estado inicial do collapse
    if (toggleIcon && adminInfoCollapse) {
        if (!adminInfoCollapse.classList.contains('show')) {
            toggleIcon.style.transform = 'rotate(-90deg)';
            adminHeader.classList.add('collapsed');
        }
    }
});
        
        // Function to check for updates
        function checkForUpdates() {
            // Get the ticket ID directly from the keyid parameter in the URL
            var ticketId = <?php echo json_encode(isset($_GET['keyid']) ? trim($_GET['keyid']) : ''); ?>;
                        
            // Only proceed if we have a valid ticket ID
            if (!ticketId) {
                return;
            }
            
            // Use the actual ticket ID from PHP (more reliable)
            ticketId = '<?php echo $ticket_id; ?>';
            
            fetch('check_updates.php?ticketId=' + encodeURIComponent(ticketId) + '&_=' + new Date().getTime())
                .then(response => response.json())
                .then(data => {
                    if (data.hasUpdates) {
                        // Reload the page to show new messages
                        location.reload();
                    }
                })
                .catch(error => {
                    // Silent error handling
                    console.error('Error checking for updates:', error);
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
                        <span class="message-user-info"><?php echo $_SESSION['usuario_email'] ?? 'Admin'; ?></span>
                        <span class="message-time">${new Date().toLocaleTimeString('pt-PT', {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                `;
                chatBody.appendChild(messageDiv);
                chatBody.scrollTop = chatBody.scrollHeight;
                
                // Reset textarea
                messageInput.value = '';
                messageInput.style.height = 'auto';
                document.getElementById('sendButton').disabled = true;
                
                // Send via AJAX
                const formData = new FormData(this);
                
                fetch('inserir_mensagem.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Erro ao enviar mensagem: ${response.status}`);
                    }
                    return response.text();
                })
                .then(data => {
                    // Force check for sync files to update any other clients
                    setTimeout(checkForUpdates, 200);
                })
                .catch(error => {
                    // Remove the preview message since it failed
                    messageDiv.remove();
                    alert('Ocorreu um erro ao enviar a mensagem. Por favor, tente novamente.');
                });
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
        
        // Toggle para as Informações Administrativas
        document.addEventListener('DOMContentLoaded', function() {
            const adminHeader = document.querySelector('.admin-controls-header');
            
            // Certifique-se de que o Bootstrap está carregado
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap não foi carregado, usando fallback manual');
                
                // Implementação manual do comportamento de collapse
                adminHeader.addEventListener('click', function(e) {
                    e.preventDefault();
                    const adminInfoCollapse = document.getElementById('adminInfo');
                    
                    // Toggle a classe show
                    adminInfoCollapse.classList.toggle('show');
                    
                    // Atualizar o atributo aria-expanded
                    const isExpanded = adminInfoCollapse.classList.contains('show');
                    this.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                    
                    // Alternar a classe collapsed
                    this.classList.toggle('collapsed', !isExpanded);
                    
                    // Girar o ícone
                    const chevronIcon = this.querySelector('.toggle-icon');
                    if (chevronIcon) {
                        chevronIcon.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(-90deg)';
                    }
                });
            }
        });
    </script>
</body>
</html>
