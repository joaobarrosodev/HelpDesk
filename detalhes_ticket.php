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

    .message-image {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s ease;
        max-width: 300px;
        max-height: 300px;
        object-fit: contain;
        cursor: pointer;
        margin: 10px 0;
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

        <div class="chat-body" id="chatBody">
            <!-- Ticket information message at the top -->
            <div class="ticket-info">
                <h5><?php echo htmlspecialchars($ticket['Name']); ?></h5>
                <p><strong>Descrição:</strong> <?php echo htmlspecialchars($ticket['Description']); ?></p>
                <p><strong>Criado por:</strong> <?php echo htmlspecialchars($ticket['CreationUser']); ?></p>
                <p><strong>Criado em:</strong> <?php echo htmlspecialchars($ticket['CreationDate']); ?></p>
                <?php if (!empty($ticket['image'])) { 
                    $imagePath = $ticket['image'];
                    // Fix the image path for client side display
                    if (strpos($imagePath, '../') === 0) {
                        // Remove ../ and make it relative to web root
                        $imagePath = str_replace('../', '', $imagePath);
                    }
                    // Ensure the path starts from the correct web directory
                    if (!str_starts_with($imagePath, 'http') && !str_starts_with($imagePath, '/')) {
                        $imagePath = $imagePath;
                    }
                ?>
                    <p><strong>Imagem:</strong>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Imagem do Ticket" class="message-image" 
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
        
        <!-- Message form or closed ticket info -->
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
            <div class="ticket-closed-info">
                <div class="d-flex justify-content-center align-items-center py-3 mb-3">
                    <p class="text-muted m-0"><i class="bi bi-lock-fill me-2"></i>Ticket fechado. Não é possível enviar novas mensagens.</p>
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
            <a href="meus_tickets.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Voltar aos meus tickets
            </a>
        </div>
    </div>

    <!-- Inclusão do JS do Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to show image in modal with proper path handling
        function showImage(imageUrl) {
            const modalImage = document.getElementById('modalImage');
            const downloadLink = document.getElementById('downloadImageLink');
            const openLink = document.getElementById('openImageLink');

            if (modalImage) {
                // Fix image URL path for client side
                let fixedImageUrl = imageUrl;
                
                // Remove any ../ from the path
                if (fixedImageUrl.includes('../')) {
                    fixedImageUrl = fixedImageUrl.replace(/\.\.\//g, '');
                }
                
                // If the path doesn't start with http or /, make it relative
                if (!fixedImageUrl.startsWith('http') && !fixedImageUrl.startsWith('/')) {
                    // Just use the path as is - it should be relative to the current directory
                    fixedImageUrl = fixedImageUrl;
                }

                modalImage.src = fixedImageUrl;
                modalImage.style.maxWidth = '100%';
                modalImage.style.maxHeight = '80vh';

                // Set the download link
                if (downloadLink) {
                    downloadLink.href = fixedImageUrl;
                    const filename = fixedImageUrl.split('/').pop();
                    downloadLink.setAttribute('download', filename);
                }
                
                // Set the open link
                if (openLink) {
                    openLink.href = fixedImageUrl;
                }
                
                new bootstrap.Modal(document.getElementById('imageModal')).show();
            }
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
        
        // Initialize when page loads
        window.addEventListener('DOMContentLoaded', function() {
            // Scroll chat to bottom
            const chatBody = document.getElementById('chatBody');
            if (chatBody) {
                chatBody.scrollTop = chatBody.scrollHeight;
            }
        });
    </script>
</body>
</html>

