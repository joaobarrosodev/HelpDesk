<?php
session_start();  // Inicia a sessão
include('conflogin.php');
include('db.php');  // Incluindo o arquivo de conexão com o banco de dados

// Processamento do formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_computador = $_POST['nome_computador'];
    $descricao_problema = $_POST['descricao_problema'];
    $prioridade = $_POST['prioridade'];
    $usuario_id = $_SESSION['usuario_id'];
    $entidade_codigo = $_SESSION['usuario_email'];
    $imagem = $_POST['imagem'] ?? ''; // Caminho da imagem recebida (com fallback)
    
    try {
        $stmt = $pdo->query("SELECT KeyId FROM xdfree01 ORDER BY id DESC LIMIT 1");
        $ultimo_keyid = $stmt->fetchColumn();

        if ($ultimo_keyid) {
            $ultimo_numero = (int) substr($ultimo_keyid, 1);
            $novo_numero = $ultimo_numero + 1;
        } else {
            $novo_numero = 1;
        }

        $novo_keyid = "#" . str_pad($novo_numero, 3, "0", STR_PAD_LEFT);
        $titulo = "Ticket " . $novo_keyid; 

        $stmt = $pdo->prepare("INSERT INTO xdfree01 (KeyId, Name) VALUES (:keyId, :name)");
        $stmt->bindParam(':keyId', $novo_keyid);
        $stmt->bindParam(':name', $titulo);
        $stmt->execute();

        $keyId = $novo_keyid;

        $stmt = $pdo->prepare("INSERT INTO info_xdfree01_extrafields (XDFree01_KeyID, Entity, User, Description, Priority, Status, Image, CreationUser, CreationDate, dateu) 
                               VALUES (:keyId, :entity, :user, :description, :priority, :status, :image, :creationuser, NOW(), NOW())");
        $stmt->bindParam(':keyId', $keyId);
        $stmt->bindParam(':entity', $usuario_id);
        $stmt->bindParam(':user', $nome_computador);
        $stmt->bindParam(':description', $descricao_problema);
        $stmt->bindParam(':priority', $prioridade);
        $status = "Em Análise";
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':image', $imagem); 
        $stmt->bindParam(':creationuser', $_SESSION['usuario_email']);

        $stmt->execute();

        // Set success flag and ticket ID in session instead of showing alert
        $_SESSION['ticket_created'] = true;
        $_SESSION['novo_keyid'] = $novo_keyid;

    } catch (PDOException $e) {
        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                Erro ao criar ticket: " . $e->getMessage() . "
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Fechar'></button>
              </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<head>
    <!-- Importando Dropzone.js para o Drag & Drop -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css">
</head>
<body>
    <?php include('menu.php'); ?>    <div class="content p-5">
            <h2 class="mb-3 display-5">Criar Ticket de Suporte</h2>
            <p class="text-muted">Preencher os campos abaixo para solicitar suporte técnico</p>
             
        <div class="card mt-4">
            <div class="p-4">
                <form action="abrir_ticket.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row">
                        <!-- Coluna Esquerda: Assunto e Descrição -->
                        <div class="col-lg-6">
                            <!-- Assunto -->
                            <div class="mb-4">
                                <label for="nome_computador" class="form-label fw-bold">
                                    Assunto:
                                </label>
                                <select class="form-select" id="nome_computador" name="nome_computador" required>
                                    <option value="" disabled selected>Selecionar um assunto</option>
                                    <option value="XD">XD</option>
                                    <option value="Sage">Sage</option>
                                    <option value="Office">Office</option>
                                    <option value="E-mail">Email</option>
                                    <option value="Site">Site</option>
                                    <option value="Computador">Computador</option>
                                    <option value="Impressoras">Impressoras</option>
                                    <option value="Outros">Outros</option>
                                </select>
                                <div class="form-text">Selecionar o assunto principal do seu problema</div>
                            </div>

                            <!-- Descrição do problema -->
                            <div class="mb-4">
                                <label for="descricao_problema" class="form-label fw-bold">
                                  Descrição do Problema:
                                </label>
                                <textarea class="form-control" id="descricao_problema" name="descricao_problema" 
                                          rows="10" placeholder="Fornecer detalhes sobre o problema..." required></textarea>
                                <div class="form-text">Quanto mais detalhes fornecer, mais rapidamente poderemos ajudar</div>
                            </div>
                        </div>
                        
                        <!-- Coluna Direita: Prioridade e Anexos -->
                        <div class="col-lg-6">
                            <!-- Prioridade -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-flag me-1"></i> Prioridade:
                                </label>
                                <input type="hidden" id="prioridade" name="prioridade" value="Baixa" required>
                                <div class="priority-selector">
                                    <div class="priority-item priority-low selected" data-value="Baixa" onclick="selectPriority(this, 'Baixa')">
                                        <i class="bi bi-flag priority-icon" style="color: #27ae60;"></i>
                                        <span>Baixa</span>
                                        <small class="d-block text-muted">Posso continuar a trabalhar</small>
                                    </div>
                                    <div class="priority-item priority-normal" data-value="Normal" onclick="selectPriority(this, 'Normal')">
                                        <i class="bi bi-flag-fill priority-icon" style="color: #f39c12;"></i>
                                        <span>Normal</span>
                                        <small class="d-block text-muted">Dificulta o meu trabalho</small>
                                    </div>
                                    <div class="priority-item priority-high" data-value="Alta" onclick="selectPriority(this, 'Alta')">
                                        <i class="bi bi-exclamation-triangle priority-icon" style="color: #e74c3c;"></i>
                                        <span>Alta</span>
                                        <small class="d-block text-muted">Não consigo trabalhar</small>
                                    </div>
                                </div>
                            </div>

                    <!-- Upload de Imagem (Drag & Drop) -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-image me-1"></i> Anexar Imagem do Problema:
                        </label>
                        <div id="dropzone" class="dropzone"></div>
                        <div class="form-text">Arrastar uma imagem ou clicar para selecionar (opcional)</div>
                        <input type="hidden" name="imagem" id="imagem_path">                    </div>

                    <!-- Botão de envio -->
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-success submit-btn">
                            <i class="bi bi-send me-2"></i>Criar Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Sucesso -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Ticket Criado com Sucesso!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">Ticket registado com sucesso!</h4>
                    <p class="lead">O seu número de ticket é: <strong id="ticketId"></strong></p>
                    <p>Um técnico irá analisar a sua solicitação em breve.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Inclusão do JS do Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Dropzone.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>
    <script>
        // Configuração do Dropzone para upload de imagens
        Dropzone.autoDiscover = false;
        var dropzone = new Dropzone("#dropzone", {
            url: "upload_imagem.php",  // Script para processar o upload
            maxFiles: 1,
            acceptedFiles: "image/*",
            addRemoveLinks: true,
            dictDefaultMessage: "<i class='bi bi-cloud-arrow-up' style='font-size: 2rem;'></i><br>Arrastar uma imagem ou clicar aqui",
            dictRemoveFile: "Remover",
            success: function(file, response) {
                // Parse the JSON response if needed
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch(e) {
                        console.error("Error parsing response:", e);
                        return;
                    }
                }
                
                // Check if upload was successful and update the hidden field
                if (response.success && response.caminho) {
                    document.getElementById("imagem_path").value = response.caminho;
                    console.log("Imagem carregada com sucesso:", response.caminho);
                } else {
                    console.error("Erro ao processar imagem:", response.erro || "Erro desconhecido");
                    // Show error notification
                    alert("Erro ao carregar imagem: " + (response.erro || "Erro desconhecido"));
                    // Remove the file preview
                    this.removeFile(file);
                }
            },
            error: function(file, errorMessage) {
                console.error("Dropzone error:", errorMessage);
                alert("Erro ao carregar imagem: " + errorMessage);
                this.removeFile(file);
            }
        });
        
        // Remove image from form when remove link is clicked
        dropzone.on("removedfile", function() {
            document.getElementById("imagem_path").value = "";
        });

        // Função para selecionar prioridade
        function selectPriority(element, value) {
            // Remove a classe 'selected' de todos os elementos
            document.querySelectorAll('.priority-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Adiciona a classe 'selected' ao elemento clicado
            element.classList.add('selected');
            
            // Atualiza o valor do input oculto
            document.getElementById('prioridade').value = value;
        }

        // Validação do formulário
        (function () {
            'use strict'
            
            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            var forms = document.querySelectorAll('.needs-validation')
            
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        
                        form.classList.add('was-validated')
                    }, false)
                })
        })()

        // Verifica se um ticket foi criado e exibe o modal
        document.addEventListener('DOMContentLoaded', function() {
            <?php if(isset($_SESSION['ticket_created']) && $_SESSION['ticket_created']): ?>
                var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                document.getElementById('ticketId').textContent = "<?php echo $_SESSION['novo_keyid']; ?>";
                successModal.show();
                
                // Redirecionar após fechar o modal
                document.getElementById('successModal').addEventListener('hidden.bs.modal', function () {
                    window.location.href = 'index.php';
                });
                
                <?php 
                // Limpa as variáveis de sessão após uso
                unset($_SESSION['ticket_created']);
                unset($_SESSION['novo_keyid']);
                ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
