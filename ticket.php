<?php
session_start();  // Inicia a sessão
include('conflogin.php');
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
            <p class="text-muted">Preencha os campos abaixo para solicitar suporte técnico</p>
             
        <div class="card mt-4">
            <div class="p-4">
                <form action="criar_ticket.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row">
                        <!-- Coluna Esquerda: Assunto e Descrição -->
                        <div class="col-lg-6">
                            <!-- Assunto -->
                            <div class="mb-4">
                                <label for="nome_computador" class="form-label fw-bold">
                                    Assunto:
                                </label>
                                <select class="form-select" id="nome_computador" name="nome_computador" required>
                                    <option value="" disabled selected>Selecione um assunto</option>
                                    <option value="xd">XD</option>
                                    <option value="sage">Sage</option>
                                    <option value="office">Office</option>
                                    <option value="email">Email</option>
                                    <option value="site">Site</option>
                                    <option value="computador">Computador</option>
                                    <option value="impressoras">Impressoras</option>
                                    <option value="outros">Outros</option>
                                </select>
                                <div class="form-text">Selecione o assunto principal do seu problema</div>
                            </div>

                            <!-- Descrição do problema -->
                            <div class="mb-4">
                                <label for="descricao_problema" class="form-label fw-bold">
                                  Descrição do Problema:
                                </label>                                
                                <textarea class="form-control" id="descricao_problema" name="descricao_problema" 
                                          rows="10" placeholder="Forneça detalhes sobre o problema..." required></textarea>
                                <div class="form-text">Quanto mais detalhes fornecer, mais rápido poderemos ajudar</div>
                            </div>
                        </div>
                        
                        <!-- Coluna Direita: Prioridade e Anexos -->
                        <div class="col-lg-6">
                            <!-- Prioridade -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-flag me-1"></i> Prioridade:
                                </label>
                                <input type="hidden" id="prioridade" name="prioridade" value="Normal" required><div class="priority-selector">
                            <div class="priority-item priority-low selected" data-value="Baixa" onclick="selectPriority(this, 'Baixa')">
                                <i class="bi bi-flag priority-icon" style="color: #27ae60;"></i>
                                <span>Baixa</span>
                                <small class="d-block text-muted">Posso continuar a trabalhar</small>
                            </div>
                            <div class="priority-item priority-normal " data-value="Normal" onclick="selectPriority(this, 'Normal')">
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
                        <div class="form-text">Arraste uma imagem ou clique para selecionar (opcional)</div>
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
            dictDefaultMessage: "<i class='bi bi-cloud-arrow-up' style='font-size: 2rem;'></i><br>Arraste uma imagem ou clique aqui",
            dictRemoveFile: "Remover",
            success: function(file, response) {
                document.getElementById("imagem_path").value = response.caminho; // Armazena o caminho no input oculto
            }
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
    </script>
</body>
</html>
