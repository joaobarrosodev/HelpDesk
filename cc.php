<?php
session_start();
include('conflogin.php');
include('db.php');
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<link rel="stylesheet" href="css/filter.css">
<style>
    /* Standardize row heights in the table */
    #account-table tbody tr {
        height: 60px; /* Fixed height for all rows */
        vertical-align: middle;
    }
    
    #account-table td {
        padding-top: 10px;
        padding-bottom: 10px;
        white-space: nowrap;
    }
    
    /* Ensure consistent cell height for empty cells */
    #account-table td:empty::after {
        content: "-";
        color: #ccc;
    }
    
    /* Improve table appearance */
    #account-table {
        border-collapse: separate;
        border-spacing: 0;
    }
    
    /* Highlight on hover */
    #account-table tbody tr:hover {
        background-color: rgba(0,0,0,0.02) !important;
    }
</style>

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
                            <div class="input-group">d mb-3">Valor Pendente</h6>
                                <input type="number" class="form-control" id="min-value" placeholder="Mín" step="0.01">
                                <input type="number" class="form-control" id="max-value" placeholder="Máx" step="0.01">
                            </div>  <p class="text-muted mb-0">pendentes de pagamento</p>
                        </div>                        <div class="col-md-2 d-flex align-items-end">
                            <div class="d-flex gap-2 w-100">
                                <!-- Removed filter button as it's now automatic -->
                                <button type="button" class="btn btn-outline-danger" onclick="clearAllFilters()">
                                    <i class="bi bi-x-circle"></i> Limpar
                                </button> class="row g-3 mb-4">
                            </div>="col-md-2">
                        </div>abel for="document-type" class="form-label">Tipo de Documento:</label>
                        <!-- TODO: Show filter statistics -->ocument-type" name="document-type">
                        <div class="col-12 mt-2">l">Todos</option>
                            <div class="alert alert-info p-2 d-flex align-items-center" id="filter-results" style="display: none !important;">
                                <i class="bi bi-funnel-fill me-2"></i>
                                <span>Mostrando <strong id="filtered-count">0</strong> registros filtrados</span>
                            </div>ct>
                        </div>
                    </form>v class="col-md-3">
                            <label for="start-date" class="form-label">De:</label>
<?php                       <input type="date" class="form-control" id="start-date" name="start-date">
        // Consulta SQL </div>
        $sql = "        <div class="col-md-3">
        SELECT              <label for="end-date" class="form-label">Até:</label>
            cac.Id,         <input type="date" class="form-control" id="end-date" name="end-date">
            cac.RegisterDate, 
            cac.ExpirationDate, 
            cac.SerieId,<div class="col-md-2">
            cac.DocNumber,      el for="value-range" class="form-label">Valor:</label>
            cac.Description,<div class="input-group">
            (CASE               <input type="number" class="form-control" id="min-value" placeholder="Mín" step="0.01">
                WHEN Type = 'D' THEN (cac.Total * cac.CurrencyRate) trol" id="max-value" placeholder="Máx" step="0.01">
                ELSE 0      </div>
            END) AS MovD, div>                        <div class="col-md-2 d-flex align-items-end">
            (CASE           <div class="d-flex gap-2 w-100">
                WHEN Type = 'C' THEN (cac.Total * cac.CurrencyRate)now automatic -->
                ELSE 0          <button type="button" class="btn btn-outline-danger" onclick="clearAllFilters()">
            END) AS MovC,           <i class="bi bi-x-circle"></i> Limpar
            (CASE               </button>
                WHEN Type = 'D' THEN (- 1) * (cac.Total * cac.CurrencyRate)
                WHEN Type = 'C' THEN (cac.Total * cac.CurrencyRate) 
                ELSE 0  <!-- TODO: Show filter statistics -->
            END) AS Total,iv class="col-12 mt-2">
            rh.DiscountValue AS FinancialDiscount,t-info p-2 d-flex align-items-center" id="filter-results" style="display: none !important;">
            cac.Type,           <i class="bi bi-funnel-fill me-2"></i>
            cac.CurrencyId,     <span>Mostrando <strong id="filtered-count">0</strong> registros filtrados</span>
            cac.DocumentTypeId,iv>
            cac.CurrencyRate,>
            (CASE   </form>
                WHEN (xcdt.invoicetype = 'RE' OR xcdt.invoicetype = 'PF') AND cac.MovementType = 0 THEN 0
                WHEN cac.type = 'C' THEN (DocHeader.DueValue * DocHeader.CurrencyRate)
                WHEN cac.type = 'D' THEN (- 1) * (DocHeader.DueValue * DocHeader.CurrencyRate)
                ELSE 0.00
            END) AS DueValue,
            CASE d, 
                WHEN rh.Id IS NOT NULL THEN rh.CreationUserId ELSE DocHeader.CloseUserId 
            END AS CloseUserId, 
            cac.SyncStamp,
            DocHeader.Id,,      
            DocHeader.ExtraDocReference,
            DocHeader.TotalHoldingTaxes
        FROM    WHEN Type = 'D' THEN (cac.Total * cac.CurrencyRate) 
            CheckingAccountCustomers AS cac
        LEFT JOIN   MovD, 
            XConfigDocumentsTypes AS xcdt ON xcdt.Id = cac.DocumentTypeId 
        LEFT JOIN EN Type = 'C' THEN (cac.Total * cac.CurrencyRate)
            documentsheaders AS DocHeader ON DocHeader.DocumentTypeId = cac.DocumentTypeId  AND DocHeader.SerieId = cac.SerieId  AND DocHeader.Number = cac.DocNumber 
        LEFT JOINAS MovC,
            receiptsheaders AS rh ON rh.DocumentTypeId = cac.DocumentTypeId  AND rh.SerieId = cac.SerieId  AND rh.Number = cac.DocNumber  
        WHERE   WHEN Type = 'D' THEN (- 1) * (cac.Total * cac.CurrencyRate)
            cac.CustomerKeyId = :keyidcac.Total * cac.CurrencyRate) 
        ORDER BY cac.RegisterDate, DocHeader.creationDate, DocHeader.osdate, rh.creationDate, rh.osdate, cac.Id";
            END) AS Total,
        // Preparar e executar a consultaDiscount,
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':keyid', $_SESSION['usuario_id']);
        $stmt->execute();        // Verificando se encontrou algum registro
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            (CASE
        if (!$transactions) {voicetype = 'RE' OR xcdt.invoicetype = 'PF') AND cac.MovementType = 0 THEN 0
            echo "<div class='alert alert-info'>Sem movimentações registradas.</div>";
            exit;HEN cac.type = 'D' THEN (- 1) * (DocHeader.DueValue * DocHeader.CurrencyRate)
        }       ELSE 0.00
            END) AS DueValue,
        // Inicializar as variáveis de soma
        $totalMovD = 0;.Id IS NOT NULL THEN rh.CreationUserId ELSE DocHeader.CloseUserId 
        $totalMovC = 0;eUserId,
        $total = 0;cStamp,
        $totalDueValue = 0;
          // Calcular totais primeiroce,
        foreach ($transactions as $transaction) {
            $totalMovD += $transaction['MovD'];
            $totalMovC += $transaction['MovC'];
            $total += $transaction['Total'];
            $totalDueValue += $transaction['DueValue'];        }          // Exibindo os dados em uma tabela HTML com estilo Bootstrap moderno
        echo "<div class='table-responsive'>
                <table id='account-table' class='table'>
                <thead class='table-light'>
                  <tr style='height: 50px;'>
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
                <tbody>";
        // Totais já foram calculados anteriormente        // Exibindo os dados da transação
        foreach ($transactions as $transaction) {em movimentações registradas.</div>";
            // Mapeamento de descrições para siglas
            $mapa_descricoes = [
                "Fatura" => "FAC",
                "Recibo Cliente" => "REC",a
                "Nota de Crédito" => "NC"
            ];MovC = 0;
        $total = 0;
            // Converter descrição para sigla
            $sigla = isset($mapa_descricoes[$transaction['Description']]) ? $mapa_descricoes[$transaction['Description']] : "DOC";
            ach ($transactions as $transaction) {
            // Formatar a data de registro (criação)
            $data_registro = date('d/m/Y', strtotime($transaction['RegisterDate']));
            $total += $transaction['Total'];
            // Formatar a data de expiração (vencimento)       }          // Exibindo os dados em uma tabela HTML com estilo Bootstrap moderno
            $data_vencimento = date('d/m/Y', strtotime($transaction['ExpirationDate']));            // Verificar se há valor em dívida
            $divida_class = $transaction['DueValue'] != 0 ? 'text-danger' : '';            // Criar uma borda entre as linhas com estilo leve            // Mapear siglas para nomes completos de documentos
            $tiposDocumentos = [ble-light'>
                "FAC" => "Fatura",
                "REC" => "Recibo",' onclick='sortTable(0)'>Documento</th>
                "NC" => "Nota de Crédito",k='sortTable(1)'>Criação</th>
                "DOC" => "Documento"onclick='sortTable(2)'>Débito</th>
            ];      <th scope='col' onclick='sortTable(3)'>Crédito</th>
                    <th scope='col' onclick='sortTable(4)'>Total</th>
            // Obter o nome do tipo de documentotTable(5)'>Dívida</th>
            $tipoDocumento = isset($tiposDocumentos[$sigla]) ? $tiposDocumentos[$sigla] : "Documento";
                    <th scope='col' class='text-center'></th>
            echo "<tr class='border-top border-bottom' style='background-color: #fff; height: 60px;' data-doc-type='" . $sigla . "'>
                <td>" . $tipoDocumento . " Nº " . $transaction['SerieId'] . "/" . $transaction['DocNumber'] . "</td>
                <td>" . $data_registro . "</td>culados anteriormente        // Exibindo os dados da transação
                <td>" . number_format($transaction['MovD'], 2, ',', '.') . "</td>
                <td>" . number_format($transaction['MovC'], 2, ',', '.') . "</td>
                <td class='text-danger'>-" . number_format(abs($transaction['Total']), 2, ',', '.') . "€</td>
                <td class='{$divida_class}'>";
              // Mostra a dívida apenas se não for zero
            if ($transaction['DueValue'] != 0) {
                echo "-" . number_format(abs($transaction['DueValue']), 2, ',', '.') . "€";
            } else {
                echo "0,00€";rição para sigla
            }sigla = isset($mapa_descricoes[$transaction['Description']]) ? $mapa_descricoes[$transaction['Description']] : "DOC";
            
            echo "</td> a data de registro (criação)
                <td>" . $data_vencimento . "</td>";e($transaction['RegisterDate']));
            
            // Construir nome do arquivoção (vencimento)
            $nome_arquivo = "{$sigla}_{$transaction['SerieId']}-{$transaction['DocNumber']}_1.pdf"; // Verificar se há valor em dívida
            $divida_class = $transaction['DueValue'] != 0 ? 'text-danger' : '';            // Criar uma borda entre as linhas com estilo leve            // Mapear siglas para nomes completos de documentos
            // Caminho do arquivo
            $pasta_arquivos = "docs/";
            $caminho_completo = $pasta_arquivos . $nome_arquivo;            // Verifica se o arquivo existe e gera o link para download
            if (file_exists($caminho_completo)) {
                echo "<td class='text-center'><a href='$caminho_completo' target='_blank' class='btn btn-light'><i class='bi bi-download'></i></a></td>";
            } else {
                echo "<td class='text-center'>-</td>";
            }/ Obter o nome do tipo de documento
            $tipoDocumento = isset($tiposDocumentos[$sigla]) ? $tiposDocumentos[$sigla] : "Documento";
            echo "</tr>";
        }        // Fim da tabela HTMLp border-bottom' style='background-color: #fff;' data-doc-type='" . $sigla . "'>
        echo "</tbody>";$tipoDocumento . " Nº " . $transaction['SerieId'] . "/" . $transaction['DocNumber'] . "</td>
        echo "</table>";$data_registro . "</td>
        echo "</div>";. number_format($transaction['MovD'], 2, ',', '.') . "</td>
                <td>" . number_format($transaction['MovC'], 2, ',', '.') . "</td>
        // Fechando a div container, card, etc.mber_format(abs($transaction['Total']), 2, ',', '.') . "€</td>
        echo "</div>";ass='{$divida_class}'>";
        echo "</div>";a a dívida apenas se não for zero
        echo "</div>";        // Fechando a conexão com o banco de dados
        $pdo = null; "-" . number_format(abs($transaction['DueValue']), 2, ',', '.') . "€";
        ?>        <script>
        // Update the pending amount with the calculated value
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('pending-amount').innerHTML = '-<?php echo number_format(abs($totalDueValue), 2, ',', '.'); ?>€';
            echo "</td>
            // Implement automatic filtering</td>";
            
            // 1. Listen for Enter key on all filter inputs - already implemented
            const filterForm = document.getElementById('filterForm');nsaction['DocNumber']}_1.pdf";
            filterForm.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    filterTableByAll();arquivos . $nome_arquivo;            // Verifica se o arquivo existe e gera o link para download
                }ile_exists($caminho_completo)) {
            }); echo "<td class='text-center'><a href='$caminho_completo' target='_blank' class='btn btn-light'><i class='bi bi-download'></i></a></td>";
            } else {
            // 2. Listen for change events on select and date inputs - already implemented
            document.getElementById('document-type').addEventListener('change', filterTableByAll);
            document.getElementById('start-date').addEventListener('change', filterTableByAll);
            document.getElementById('end-date').addEventListener('change', filterTableByAll);
                 // Fim da tabela HTML
            // 3. Listen for input events on value fields with a small delay - already implemented
            const valueInputs = [
                document.getElementById('min-value'),
                document.getElementById('max-value')
            ];hando a div container, card, etc.
             "</div>";
            let valueTimeout = null;
            valueInputs.forEach(input => {a conexão com o banco de dados
                input.addEventListener('input', function() {
                    clearTimeout(valueTimeout);
                    valueTimeout = setTimeout(filterTableByAll, 500);
                });dEventListener('DOMContentLoaded', function() {
            });ument.getElementById('pending-amount').innerHTML = '-<?php echo number_format(abs($totalDueValue), 2, ',', '.'); ?>€';
            
            // Clear any date fields to ensure they're empty on page load
            document.getElementById('start-date').value = '';
            document.getElementById('end-date').value = ''; - already implemented
            const filterForm = document.getElementById('filterForm');
            const table = document.getElementById('account-table'); {
            if (table) {t.key === 'Enter') {
                const rows = table.getElementsByTagName('tr');
                for (let i = 1; i < rows.length; i++) {
                    rows[i].style.display = '';
                }
            }
            // 2. Listen for change events on select and date inputs - already implemented
            // Show initial count of all recordspe').addEventListener('change', filterTableByAll);
            const filterResults = document.getElementById('filter-results'); filterTableByAll);
            if (filterResults) {yId('end-date').addEventListener('change', filterTableByAll);
                const table = document.getElementById('account-table');
                if (table) { input events on value fields with a small delay - already implemented
                    const totalRows = table.getElementsByTagName('tr').length - 1; // Subtract header row
                    document.querySelector('#filter-results span').innerHTML = 
                        `Mostrando todos os <strong>${totalRows}</strong> registros`;
                    
                    filterResults.removeAttribute('style');
                    filterResults.style.display = 'flex';
                }Inputs.forEach(input => {
            }   input.addEventListener('input', function() {
        });         clearTimeout(valueTimeout);
                    valueTimeout = setTimeout(filterTableByAll, 500);
        // Função para limpar todos os filtros
        function clearAllFilters() {
            // Limpa os campos do formulário
            document.getElementById('filterForm').reset();ty on page load
            document.getElementById('start-date').value = '';
            // Explicitly clear date fields (in case reset doesn't work properly)
            document.getElementById('start-date').value = '';
            document.getElementById('end-date').value = '';table');
            if (table) {
            // Restaura a visualização de todas as linhas da tabela
            const table = document.getElementById('account-table');
            const rows = table.getElementsByTagName('tr');
                }
            for (let i = 1; i < rows.length; i++) {
                rows[i].style.display = '';
                rows[i].classList.remove('filtered-row');
            }onst filterResults = document.getElementById('filter-results');
            if (filterResults) {
            // Show all records count after clearing filtersnt-table');
            const totalRows = rows.length - 1; // Subtract header row
            const filterResults = document.getElementById('filter-results');h - 1; // Subtract header row
            document.querySelector('#filter-results span').innerHTML = rHTML = 
                `Mostrando todos os <strong>${totalRows}</strong> registros`;istros`;
                    
            filterResults.removeAttribute('style');style');
            filterResults.style.display = 'flex'; 'flex';
        }       }
            }
        // Função para filtrar a tabela por todos os critérios
        function filterTableByAll() {
            // Improved filtering implementation
            console.log('Filtering table...');
            // Limpa os campos do formulário
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            const documentType = document.getElementById('document-type').value;)
            const minValue = document.getElementById('min-value').value ? parseFloat(document.getElementById('min-value').value) : null;
            const maxValue = document.getElementById('max-value').value ? parseFloat(document.getElementById('max-value').value) : null;
            
            const table = document.getElementById('account-table');
            const rows = table.getElementsByTagName('tr');-table');
            const rows = table.getElementsByTagName('tr');
            // Prepara datas se filtro de data estiver ativo
            let startDateObj = null;.length; i++) {
            let endDateObj = null;lay = '';
            if (startDate && endDate) {                );
                startDateObj = new Date(startDate);
                endDateObj = new Date(endDate);
            }/ Show all records count after clearing filters
            const totalRows = rows.length - 1; // Subtract header row
            // Mapear os tipos de documentos para seus identificadores no texto
            const docTypeMappings = {filter-results span').innerHTML = 
                'FAC': 'Fatura', os <strong>${totalRows}</strong> registros`;
                'REC': 'Recibo',
                'NC': 'Nota de Crédito'te('style');
            };lterResults.style.display = 'flex';
            
        
            // Itera pelas linhas da tabela (começando em 1 para pular o cabeçalho)
            for (let i = 1; i < rows.length; i++) {
                let showRow = true; // Por padrão, mostra a linha
                ole.log('Filtering table...');
                // Filtra por data se as datas foram fornecidas
                if (startDateObj && endDateObj) {ById('start-date').value;
                    const dateCell = rows[i].getElementsByTagName('td')[1]; // Coluna de data de criação
                    if (dateCell) {cument.getElementById('document-type').value;
                        const dateParts = dateCell.textContent.trim().split('/');oat(document.getElementById('min-value').value) : null;
                        if (dateParts.length === 3) {'max-value').value ? parseFloat(document.getElementById('max-value').value) : null;
                            // Cria um objeto Date a partir da string de data (formato DD/MM/YYYY)
                            const rowDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
                            le.getElementsByTagName('tr');
                            // Verifica se a data está dentro do intervalo
                            if (!(rowDate >= startDateObj && rowDate <= endDateObj)) {
                                showRow = false;
                            }null;
                        } && endDate) {                
                    }DateObj = new Date(startDate);
                }                  // Filtra por tipo de documento
                if (showRow && documentType !== 'all') {
                    // Check the data attribute first (most reliable)
                    const rowDocType = rows[i].getAttribute('data-doc-type');to
                    cTypeMappings = {
                    if (rowDocType === documentType) {
                        // Data attribute matches, keep showing the row
                        showRow = true;
                    } else {
                        // Try fallback methods
                        const documentCell = rows[i].getElementsByTagName('td')[0]; // Coluna de documento
                        if (documentCell) { (começando em 1 para pular o cabeçalho)
                            // Get document text for debugging
                            const documentText = documentCell.textContent.trim();
                            console.log(`Filtering: ${documentType}, Row type: ${rowDocType}, Text: ${documentText}`);
                            r data se as datas foram fornecidas
                            // If data attribute didn't match, check document text
                            let isMatch = false;ElementsByTagName('td')[1]; // Coluna de data de criação
                            Cell) {
                            // Check if document text contains the type name'/');
                            if (documentType === 'FAC' && documentText.toLowerCase().includes('fatura')) {
                                isMatch = true;ate a partir da string de data (formato DD/MM/YYYY)
                            } else if (documentType === 'REC' && documentText.toLowerCase().includes('recibo')) {
                                isMatch = true;
                            } else if (documentType === 'NC' && documentText.toLowerCase().includes('nota')) {
                                isMatch = true;artDateObj && rowDate <= endDateObj)) {
                            }   showRow = false;
                            }
                            // Final check with download link if available
                            if (!isMatch) {
                                const downloadCell = rows[i].getElementsByTagName('td')[7]; // Last column with download link
                                if (downloadCell) {l') {
                                    const anchor = downloadCell.querySelector('a');
                                    if (anchor && anchor.getAttribute('href')) {
                                        const href = anchor.getAttribute('href');
                                        if (href.includes(`${documentType}_`)) {
                                            isMatch = true;wing the row
                                        }
                                    }
                                }llback methods
                            } documentCell = rows[i].getElementsByTagName('td')[0]; // Coluna de documento
                            documentCell) {
                            // If no match found, hide the row
                            if (!isMatch) {ext = documentCell.textContent.trim();
                                showRow = false;ng: ${documentType}, Row type: ${rowDocType}, Text: ${documentText}`);
                            }
                        } else {f data attribute didn't match, check document text
                            // No document cell found, hide the row
                            showRow = false;
                        }   // Check if document text contains the type name
                    }       if (documentType === 'FAC' && documentText.toLowerCase().includes('fatura')) {
                }               isMatch = true;
                            } else if (documentType === 'REC' && documentText.toLowerCase().includes('recibo')) {
                // Filtra por valoratch = true;
                if (showRow && (minValue !== null || maxValue !== null)) {xt.toLowerCase().includes('nota')) {
                    const valueCell = rows[i].getElementsByTagName('td')[4]; // Coluna de valor total
                    if (valueCell) {
                        // Extrai o valor numérico removendo formatação
                        const valueText = valueCell.textContent.trim().replace('-', '').replace('€', '').replace('.', '').replace(',', '.');
                        const rowValue = parseFloat(valueText);
                                const downloadCell = rows[i].getElementsByTagName('td')[7]; // Last column with download link
                        if (!isNaN(rowValue)) {l) {
                            // Verifica se o valor está dentro do intervaloor('a');
                            if (minValue !== null && rowValue < minValue) {')) {
                                showRow = false;ef = anchor.getAttribute('href');
                            }           if (href.includes(`${documentType}_`)) {
                            if (maxValue !== null && rowValue > maxValue) {
                                showRow = false;
                            }       }
                        }       }
                    }       }
                }           
                  // Aplica a visibilidade com base nos filtros
                rows[i].style.display = showRow ? '' : 'none';
                                showRow = false;
                // Aplica um efeito de destaque às linhas filtradas
                if (showRow) { {
                    rows[i].classList.add('filtered-row');e the row
                } else {    showRow = false;
                    rows[i].classList.remove('filtered-row');
                }   }
            }   }
                
            // Atualiza informações sobre os resultados filtrados
            updateFilteredResults();alue !== null || maxValue !== null)) {
        }           const valueCell = rows[i].getElementsByTagName('td')[4]; // Coluna de valor total
          // Função para atualizar as informações sobre os resultados filtrados
        function updateFilteredResults() {numérico removendo formatação
            // Conta quantas linhas estão visíveis (filtradas)t.trim().replace('-', '').replace('€', '').replace('.', '').replace(',', '.');
            const table = document.getElementById('account-table');
            const rows = table.getElementsByTagName('tr');
                        if (!isNaN(rowValue)) {
            let filteredCount = 0;ifica se o valor está dentro do intervalo
            let totalRows = 0; (minValue !== null && rowValue < minValue) {
                                showRow = false;
            // Verifica se algum filtro está ativo
            const documentType = document.getElementById('document-type').value;
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            const minValue = document.getElementById('min-value').value;
            const maxValue = document.getElementById('max-value').value;
                }
            const activeFilter = (documentType !== 'all' || (startDate && endDate) || minValue || maxValue);
                rows[i].style.display = showRow ? '' : 'none';
            // Conta as linhas visíveis (começando em 1 para pular o cabeçalho)
            for (let i = 1; i < rows.length; i++) {linhas filtradas
                totalRows++; {
                if (rows[i].style.display !== 'none') {');
                    filteredCount++;
                }   rows[i].classList.remove('filtered-row');
            }   }
            }
            // Atualiza o contador na UI
            const filterResultsDiv = document.getElementById('filter-results');
            updateFilteredResults();
            // Remove style attribute to clear any !important declarations
            filterResultsDiv.removeAttribute('style');e os resultados filtrados
            tion updateFilteredResults() {
            if (activeFilter) {nhas estão visíveis (filtradas)
                // Mostrando resultados filtrados 'account-table');
                document.querySelector('#filter-results span').innerHTML = 
                    `Mostrando <strong>${filteredCount}</strong> de ${totalRows} registros`;
                filteredCount = 0;
                filterResultsDiv.style.display = 'flex';
            } else {
                // Nenhum filtro aplicado - mostra o total de registros
                document.querySelector('#filter-results span').innerHTML = 
                    `Mostrando todos os <strong>${totalRows}</strong> registros`;
                t endDate = document.getElementById('end-date').value;
                filterResultsDiv.style.display = 'flex';n-value').value;
            }onst maxValue = document.getElementById('max-value').value;
        }   
    </script>    </div>eFilter = (documentType !== 'all' || (startDate && endDate) || minValue || maxValue);
      <!-- jQuery, Popper.js, and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>ho)
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/sortTable.js"></script>== 'none') {
</body>             filteredCount++;
</html>         }
            }
            
            // Atualiza o contador na UI
            const filterResultsDiv = document.getElementById('filter-results');
            
            // Remove style attribute to clear any !important declarations
            filterResultsDiv.removeAttribute('style');
            
            if (activeFilter) {
                // Mostrando resultados filtrados 
                document.querySelector('#filter-results span').innerHTML = 
                    `Mostrando <strong>${filteredCount}</strong> de ${totalRows} registros`;
                
                filterResultsDiv.style.display = 'flex';
            } else {
                // Nenhum filtro aplicado - mostra o total de registros
                document.querySelector('#filter-results span').innerHTML = 
                    `Mostrando todos os <strong>${totalRows}</strong> registros`;
                
                filterResultsDiv.style.display = 'flex';
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
