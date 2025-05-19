<?php
session_start();
include('conflogin.php');
include('db.php');
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<link rel="stylesheet" href="css/filter.css">

<body>
    <?php include('menu.php'); ?>    <div class="content p-5">    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/sortTable.js"></script>
    <script src="js/filter-functions.js"></script>
    <script src="js/auto-filter.js"></script>
    <script src="js/script.js"></script>
                    <h1 class="mb-3 display-5">Extrato de Conta Corrente</h1>
                    <p class="">Aqui tem um pequena lista de compras que fez</p>        <div class=" mt-4">
            <div class="card mb-4 shadow-sm">
                <div class="card-body"> 
                         <div class="row mb-4">                     
                       <div class="col-md-6">
                            <div class="card border-0">
                                <div class="card-body p-0">
                                    <h6 class="fw-bold mb-3">Informações Bancárias</h6>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-secondary me-2">Banco:</span>
                                        <span class="fw-medium">Crédito Agrícola</span>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-secondary me-2" >BIC/SWIFT:</span>
                                        <span class="fw-medium">CCCMPTPL</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="text-secondary me-2" >IBAN:</span>
                                        <span class="fw-medium" style="font-size: 0.95rem;">PT50 0045 1405 4029 4772 6307 5</span>
                                    </div>
                                </div>
                            </div>
                        </div>                        
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body py-3" id="pending-amount-section">
                                    <h6 class="fw-bold mb-3">Valor Pendente</h6>
                                    <p class="mb-1">Tem neste momento:</p>
                                    <h3 class="text-danger mb-0" id="pending-amount">Calculando...</h3>
                                    <p class="text-muted mb-0">pendentes de pagamento</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <!-- Formulário de Filtros -->
                    <form id="filterForm" class="row g-3 mb-4">
                        <div class="col-md-2">
                            <label for="document-type" class="form-label">Tipo de Documento:</label>
                            <select class="form-select" id="document-type" name="document-type">
                                <option value="all">Todos</option>
                                <option value="FAC">Faturas</option>
                                <option value="REC">Recibos</option>
                                <option value="NC">Notas de Crédito</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="start-date" class="form-label">De:</label>
                            <input type="date" class="form-control" id="start-date" name="start-date">
                        </div>
                        <div class="col-md-3">
                            <label for="end-date" class="form-label">Até:</label>
                            <input type="date" class="form-control" id="end-date" name="end-date">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="value-range" class="form-label">Valor:</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="min-value" placeholder="Mín" step="0.01">
                                <input type="number" class="form-control" id="max-value" placeholder="Máx" step="0.01">
                            </div>
                        </div>                        <div class="col-md-2 d-flex align-items-end">
                            <div class="d-flex gap-2 w-100">
                                <!-- Removed filter button as it's now automatic -->
                                <button type="button" class="btn btn-outline-danger" onclick="clearAllFilters()">
                                    <i class="bi bi-x-circle"></i> Limpar
                                </button>
                            </div>
                        </div>
                        <!-- TODO: Show filter statistics -->
                        <div class="col-12 mt-2">
                            <div class="alert alert-info p-2 d-flex align-items-center" id="filter-results" style="display: none !important;">
                                <i class="bi bi-funnel-fill me-2"></i>
                                <span>Mostrando <strong id="filtered-count">0</strong> registros filtrados</span>
                            </div>
                        </div>
                    </form>
           
<?php
        // Consulta SQL
        $sql = "
        SELECT 
            cac.Id, 
            cac.RegisterDate, 
            cac.ExpirationDate, 
            cac.SerieId,
            cac.DocNumber,      
            cac.Description,
            (CASE 
                WHEN Type = 'D' THEN (cac.Total * cac.CurrencyRate) 
                ELSE 0 
            END) AS MovD, 
            (CASE 
                WHEN Type = 'C' THEN (cac.Total * cac.CurrencyRate)
                ELSE 0 
            END) AS MovC,
            (CASE 
                WHEN Type = 'D' THEN (- 1) * (cac.Total * cac.CurrencyRate)
                WHEN Type = 'C' THEN (cac.Total * cac.CurrencyRate) 
                ELSE 0
            END) AS Total,
            rh.DiscountValue AS FinancialDiscount,
            cac.Type, 
            cac.CurrencyId, 
            cac.DocumentTypeId,
            cac.CurrencyRate,
            (CASE
                WHEN (xcdt.invoicetype = 'RE' OR xcdt.invoicetype = 'PF') AND cac.MovementType = 0 THEN 0
                WHEN cac.type = 'C' THEN (DocHeader.DueValue * DocHeader.CurrencyRate)
                WHEN cac.type = 'D' THEN (- 1) * (DocHeader.DueValue * DocHeader.CurrencyRate)
                ELSE 0.00
            END) AS DueValue,
            CASE 
                WHEN rh.Id IS NOT NULL THEN rh.CreationUserId ELSE DocHeader.CloseUserId 
            END AS CloseUserId,
            cac.SyncStamp,
            DocHeader.Id,
            DocHeader.ExtraDocReference,
            DocHeader.TotalHoldingTaxes
        FROM 
            CheckingAccountCustomers AS cac
        LEFT JOIN  
            XConfigDocumentsTypes AS xcdt ON xcdt.Id = cac.DocumentTypeId 
        LEFT JOIN 
            documentsheaders AS DocHeader ON DocHeader.DocumentTypeId = cac.DocumentTypeId  AND DocHeader.SerieId = cac.SerieId  AND DocHeader.Number = cac.DocNumber 
        LEFT JOIN
            receiptsheaders AS rh ON rh.DocumentTypeId = cac.DocumentTypeId  AND rh.SerieId = cac.SerieId  AND rh.Number = cac.DocNumber  
        WHERE
            cac.CustomerKeyId = :keyid
        ORDER BY cac.RegisterDate, DocHeader.creationDate, DocHeader.osdate, rh.creationDate, rh.osdate, cac.Id";

        // Preparar e executar a consulta
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':keyid', $_SESSION['usuario_id']);
        $stmt->execute();        // Verificando se encontrou algum registro
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$transactions) {
            echo "<div class='alert alert-info'>Sem movimentações registradas.</div>";
            exit;
        }

        // Inicializar as variáveis de soma
        $totalMovD = 0;
        $totalMovC = 0;
        $total = 0;
        $totalDueValue = 0;
          // Calcular totais primeiro
        foreach ($transactions as $transaction) {
            $totalMovD += $transaction['MovD'];
            $totalMovC += $transaction['MovC'];
            $total += $transaction['Total'];
            $totalDueValue += $transaction['DueValue'];        }          // Exibindo os dados em uma tabela HTML com estilo Bootstrap moderno
        echo "<div class='table-responsive'>
                <table id='account-table' class='table'>
                <thead class='table-light'>
                  <tr>
                    <th scope='col' onclick='sortTable(0)'>Documento</th>
                    <th scope='col' onclick='sortTable(1)'>Criação</th>
                    <th scope='col' onclick='sortTable(2)'>Débito</th>
                    <th scope='col' onclick='sortTable(3)'>Crédito</th>
                    <th scope='col' onclick='sortTable(4)'>Total</th>
                    <th scope='col' onclick='sortTable(5)'>Dívida</th>
                    <th scope='col' onclick='sortTable(6)'>Data de Vencimento</th>
                    <th scope='col' class='text-center'></th>
                  </tr>
                </thead>
                <tbody>";// Totais já foram calculados anteriormente        // Exibindo os dados da transação
        foreach ($transactions as $transaction) {
            // Mapeamento de descrições para siglas
            $mapa_descricoes = [
                "Fatura" => "FAC",
                "Recibo Cliente" => "REC",
                "Nota de Crédito" => "NC"
            ];

            // Converter descrição para sigla
            $sigla = isset($mapa_descricoes[$transaction['Description']]) ? $mapa_descricoes[$transaction['Description']] : "DOC";
            
            // Formatar a data de registro (criação)
            $data_registro = date('d/m/Y', strtotime($transaction['RegisterDate']));
            
            // Formatar a data de expiração (vencimento)
            $data_vencimento = date('d/m/Y', strtotime($transaction['ExpirationDate']));            // Verificar se há valor em dívida
            $divida_class = $transaction['DueValue'] != 0 ? 'text-danger' : '';            // Criar uma borda entre as linhas com estilo leve            // Mapear siglas para nomes completos de documentos
            $tiposDocumentos = [
                "FAC" => "Fatura",
                "REC" => "Recibo",
                "NC" => "Nota de Crédito",
                "DOC" => "Documento"
            ];
            
            // Obter o nome do tipo de documento
            $tipoDocumento = isset($tiposDocumentos[$sigla]) ? $tiposDocumentos[$sigla] : "Documento";
            
            echo "<tr class='border-top border-bottom' style='background-color: #fff;' data-doc-type='" . $sigla . "'>
                <td>" . $tipoDocumento . " Nº " . $transaction['SerieId'] . "/" . $transaction['DocNumber'] . "</td>
                <td>" . $data_registro . "</td>
                <td>" . number_format($transaction['MovD'], 2, ',', '.') . "</td>
                <td>" . number_format($transaction['MovC'], 2, ',', '.') . "</td>
                <td class='text-danger'>-" . number_format(abs($transaction['Total']), 2, ',', '.') . "€</td>
                <td class='{$divida_class}'>";
              // Mostra a dívida apenas se não for zero
            if ($transaction['DueValue'] != 0) {
                echo "-" . number_format(abs($transaction['DueValue']), 2, ',', '.') . "€";
            } else {
                echo "0,00€";
            }
            
            echo "</td>
                <td>" . $data_vencimento . "</td>";

            // Construir nome do arquivo
            $nome_arquivo = "{$sigla}_{$transaction['SerieId']}-{$transaction['DocNumber']}_1.pdf";

            // Caminho do arquivo
            $pasta_arquivos = "docs/";
            $caminho_completo = $pasta_arquivos . $nome_arquivo;            // Verifica se o arquivo existe e gera o link para download
            if (file_exists($caminho_completo)) {
                echo "<td class='text-center'><a href='$caminho_completo' target='_blank' class='btn btn-light'><i class='bi bi-download'></i></a></td>";
            } else {
                echo "<td class='text-center'>-</td>";
            }

            echo "</tr>";
        }        // Fim da tabela HTML
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
                
        // Fechando a div container, card, etc.
        echo "</div>";
        echo "</div>";
        echo "</div>";        // Fechando a conexão com o banco de dados
        $pdo = null;
        ?>        <script>
        // Update the pending amount with the calculated value
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('pending-amount').innerHTML = '-<?php echo number_format(abs($totalDueValue), 2, ',', '.'); ?>€';
            
            // Configura a data padrão inicial como primeiro dia do mês atual
            const today = new Date();
            const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            
            // Formata as datas no formato YYYY-MM-DD para os inputs
            const formatDateForInput = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            // Define os valores iniciais para os campos de data
            document.getElementById('start-date').value = formatDateForInput(firstDayOfMonth);
            document.getElementById('end-date').value = formatDateForInput(today);
            
            // TODO: Add event listeners for automatic filtering
            
            // 1. Listen for Enter key on all filter inputs
            const filterForm = document.getElementById('filterForm');
            filterForm.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    filterTableByAll();
                }
            });
            
            // 2. Listen for change events on select and date inputs
            document.getElementById('document-type').addEventListener('change', filterTableByAll);
            document.getElementById('start-date').addEventListener('change', filterTableByAll);
            document.getElementById('end-date').addEventListener('change', filterTableByAll);
            
            // 3. Listen for input events on value fields with a small delay
            const valueInputs = [
                document.getElementById('min-value'),
                document.getElementById('max-value')
            ];
            
            let valueTimeout = null;
            valueInputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(valueTimeout);
                    valueTimeout = setTimeout(filterTableByAll, 500);
                });
            });
        });
        
        // Função para limpar todos os filtros
        function clearAllFilters() {
            // Limpa os campos do formulário
            document.getElementById('filterForm').reset();
              // Restaura a visualização de todas as linhas da tabela
            const table = document.getElementById('account-table');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                rows[i].style.display = '';
                rows[i].classList.remove('filtered-row');
            }
         
            // Configura a data padrão inicial como primeiro dia do mês atual
            const today = new Date();
            const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            
            // Formata as datas no formato YYYY-MM-DD para os inputs
            const formatDateForInput = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
              // Define os valores iniciais para os campos de data            
            document.getElementById('start-date').value = formatDateForInput(firstDayOfMonth);
            document.getElementById('end-date').value = formatDateForInput(today);
            
            // Esconde a área de resultados filtrados
            document.getElementById('filter-results').style.display = 'none';
        }
          // Função para filtrar a tabela por todos os critérios
        function filterTableByAll() {
            // TODO: Improved filtering implementation with Enter key and auto-filter
            console.log('Filtering table...');
            
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            const documentType = document.getElementById('document-type').value;
            const minValue = document.getElementById('min-value').value ? parseFloat(document.getElementById('min-value').value) : null;
            const maxValue = document.getElementById('max-value').value ? parseFloat(document.getElementById('max-value').value) : null;
            
            const table = document.getElementById('account-table');
            const rows = table.getElementsByTagName('tr');
            
            // Prepara datas se filtro de data estiver ativo
            let startDateObj = null;
            let endDateObj = null;
            if (startDate && endDate) {                
                startDateObj = new Date(startDate);
                endDateObj = new Date(endDate);
            }
            
            // Mapear os tipos de documentos para seus identificadores no texto
            const docTypeMappings = {
                'FAC': 'Fatura',
                'REC': 'Recibo',
                'NC': 'Nota de Crédito'
            };
            
      
            // Itera pelas linhas da tabela (começando em 1 para pular o cabeçalho)
            for (let i = 1; i < rows.length; i++) {
                let showRow = true; // Por padrão, mostra a linha
                
                // Filtra por data se as datas foram fornecidas
                if (startDateObj && endDateObj) {
                    const dateCell = rows[i].getElementsByTagName('td')[1]; // Coluna de data de criação
                    if (dateCell) {
                        const dateParts = dateCell.textContent.trim().split('/');
                        if (dateParts.length === 3) {
                            // Cria um objeto Date a partir da string de data (formato DD/MM/YYYY)
                            const rowDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
                            
                            // Verifica se a data está dentro do intervalo
                            if (!(rowDate >= startDateObj && rowDate <= endDateObj)) {
                                showRow = false;
                            }
                        }
                    }
                }                  // Filtra por tipo de documento
                if (showRow && documentType !== 'all') {
                    // Check the data attribute first (most reliable)
                    const rowDocType = rows[i].getAttribute('data-doc-type');
                    
                    if (rowDocType === documentType) {
                        // Data attribute matches, keep showing the row
                        showRow = true;
                    } else {
                        // Try fallback methods
                        const documentCell = rows[i].getElementsByTagName('td')[0]; // Coluna de documento
                        if (documentCell) {
                            // Get document text for debugging
                            const documentText = documentCell.textContent.trim();
                            console.log(`Filtering: ${documentType}, Row type: ${rowDocType}, Text: ${documentText}`);
                            
                            // If data attribute didn't match, check document text
                            let isMatch = false;
                            
                            // Check if document text contains the type name
                            if (documentType === 'FAC' && documentText.toLowerCase().includes('fatura')) {
                                isMatch = true;
                            } else if (documentType === 'REC' && documentText.toLowerCase().includes('recibo')) {
                                isMatch = true;
                            } else if (documentType === 'NC' && documentText.toLowerCase().includes('nota')) {
                                isMatch = true;
                            }
                            
                            // Final check with download link if available
                            if (!isMatch) {
                                const downloadCell = rows[i].getElementsByTagName('td')[7]; // Last column with download link
                                if (downloadCell) {
                                    const anchor = downloadCell.querySelector('a');
                                    if (anchor && anchor.getAttribute('href')) {
                                        const href = anchor.getAttribute('href');
                                        if (href.includes(`${documentType}_`)) {
                                            isMatch = true;
                                        }
                                    }
                                }
                            }
                            
                            // If no match found, hide the row
                            if (!isMatch) {
                                showRow = false;
                            }
                        } else {
                            // No document cell found, hide the row
                            showRow = false;
                        }
                    }
                }
                
                // Filtra por valor
                if (showRow && (minValue !== null || maxValue !== null)) {
                    const valueCell = rows[i].getElementsByTagName('td')[4]; // Coluna de valor total
                    if (valueCell) {
                        // Extrai o valor numérico removendo formatação
                        const valueText = valueCell.textContent.trim().replace('-', '').replace('€', '').replace('.', '').replace(',', '.');
                        const rowValue = parseFloat(valueText);
                        
                        if (!isNaN(rowValue)) {
                            // Verifica se o valor está dentro do intervalo
                            if (minValue !== null && rowValue < minValue) {
                                showRow = false;
                            }
                            if (maxValue !== null && rowValue > maxValue) {
                                showRow = false;
                            }
                        }
                    }
                }
                  // Aplica a visibilidade com base nos filtros
                rows[i].style.display = showRow ? '' : 'none';
                
                // Aplica um efeito de destaque às linhas filtradas
                if (showRow) {
                    rows[i].classList.add('filtered-row');
                } else {
                    rows[i].classList.remove('filtered-row');
                }
            }
            
            // TODO: Mostra informações sobre os resultados filtrados
            updateFilteredResults();
        }
          // Função para atualizar as informações sobre os resultados filtrados
        function updateFilteredResults() {
            // Conta quantas linhas estão visíveis (filtradas)
            const table = document.getElementById('account-table');
            const rows = table.getElementsByTagName('tr');
            
            let filteredCount = 0;
            let totalRows = 0;
            let activeFilter = false;
            
            // Verifica se algum filtro está ativo
            const documentType = document.getElementById('document-type').value;
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            const minValue = document.getElementById('min-value').value;
            const maxValue = document.getElementById('max-value').value;
            
            if (documentType !== 'all' || (startDate && endDate) || minValue || maxValue) {
                activeFilter = true;
            }
            
            // Conta as linhas visíveis (começando em 1 para pular o cabeçalho)
            for (let i = 1; i < rows.length; i++) {
                totalRows++;
                if (rows[i].style.display !== 'none') {
                    filteredCount++;
                }
            }
            
            console.log(`Filtered results: ${filteredCount} of ${totalRows} rows, active filter: ${activeFilter}`);
            
            // Atualiza o contador na UI
            const filterResultsDiv = document.getElementById('filter-results');
            const filteredCountSpan = document.getElementById('filtered-count');
            
            if (activeFilter) {
                // Mostrando resultados filtrados
                filteredCountSpan.textContent = filteredCount;
                filterResultsDiv.style.display = 'flex';
            } else {
                // Nenhum filtro aplicado
                filterResultsDiv.style.display = 'none';
            }
        }
    </script>    </div>
      <!-- jQuery, Popper.js, and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/sortTable.js"></script>
</body>
</html>
