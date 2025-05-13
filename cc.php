<?php
session_start();
include('conflogin.php');
include('db.php');
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>

<body>
    <?php include('menu.php'); ?>    <div class="content p-5">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/sortTable.js"></script>
    <script src="js/filter-functions.js"></script>
    <script src="js/script.js"></script>
                    <h1 class="mb-3 display-5">Extrato de Conta Corrente</h1>
                    <p class="fs-4">Aqui tem um pequena lista de compras que fez</p>

        <div class=" mt-4">
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                      <div class="row mb-4">                        <div class="col-md-6">
                            <div class="d-flex">
                                <div class="me-4">
                                    <p class="mb-1 fs-5"><strong>Banco:</strong></p>
                                    <p class="mb-1 fs-5"><strong>Bic Swift:</strong></p>
                                    <p class="mb-1 fs-5"><strong>IBAN:</strong></p>
                                </div>
                                <div>
                                    <p class="mb-1 fs-5">Crédito Agrícola</p>
                                    <p class="mb-1 fs-5">CCCMPTPL</p>
                                    <p class="mb-1 fs-5">PT50 0045 1405 4029 4772 6307 5</p>
                                </div>
                            </div>
                        </div>                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body py-3" id="pending-amount-section">
                                    <!-- The pending amount will be filled by JavaScript -->
                                    <p class="mb-1 fs-5">Tem neste momento:</p>
                                    <h2 class="text-danger mb-0" id="pending-amount">Calculando...</h2>
                                    <p class="text-muted mb-0 fs-5">pendentes</p>
                                </div>
                            </div>
                        </div></div><div class="d-flex justify-content-between align-items-center mb-3">                        <div>                            <!-- Filtro por data com popup -->                            <button class="btn btn-outline-primary" id="filterBtn" onclick="toggleFilterPopup()">
                                <i class="bi bi-funnel"></i> Filtros Avançados
                            </button><!-- Popup de filtro (inicialmente oculto) -->
                            <div id="filterPopup" class="filter-popup shadow p-3 bg-white rounded" style="display: none; position: absolute; z-index: 1000; min-width: 320px;">
                                <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-funnel"></i> Filtros Avançados</h6>
                                <div class="mb-3">
                                    <label for="start-date" class="form-label"><i class="bi bi-calendar-event"></i> De:</label>
                                    <input type="date" class="form-control" id="start-date">
                                </div>
                                <div class="mb-3">
                                    <label for="end-date" class="form-label"><i class="bi bi-calendar-event"></i> Até:</label>
                                    <input type="date" class="form-control" id="end-date">
                                </div>
                                <div class="mb-3">
                                    <label for="document-type" class="form-label"><i class="bi bi-file-text"></i> Tipo de Documento:</label>
                                    <select class="form-control" id="document-type">
                                        <option value="all">Todos</option>
                                        <option value="FAC">Faturas</option>
                                        <option value="REC">Recibos</option>
                                        <option value="NC">Notas de Crédito</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="value-range" class="form-label"><i class="bi bi-currency-euro"></i> Valor:</label>
                                    <div class="d-flex gap-2">
                                        <input type="number" class="form-control" id="min-value" placeholder="Min" step="0.01">
                                        <input type="number" class="form-control" id="max-value" placeholder="Max" step="0.01">
                                    </div>
                                </div>                                <div class="d-flex gap-2">
                                    <button onclick="filterTableByAll(); toggleFilterPopup();" class="btn btn-primary flex-grow-1">
                                        <i class="bi bi-funnel"></i> Aplicar Filtros
                                    </button>
                                    <button onclick="clearAllFilters(); toggleFilterPopup();" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>                        <div class="d-flex align-items-center">
                            <div id="filter-badge" class="badge bg-primary me-2 fs-6" style="display: none;">Filtros Ativos</div>
                            <span id="date-range-text" class="me-2">Carregando...</span>
                            <button class="btn btn-outline-danger" onclick="clearAllFilters()">
                                <i class="bi bi-x-circle"></i> Limpar
                            </button>
                        </div>
                    </div>
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
                <tbody>";// Totais já foram calculados anteriormente

        // Exibindo os dados da transação
        $delay = 0;
        foreach ($transactions as $transaction) {
            $delay += 100;
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
            $data_vencimento = date('d/m/Y', strtotime($transaction['ExpirationDate']));
              // Verificar se há valor em dívida
            $divida_class = $transaction['DueValue'] != 0 ? 'text-danger' : '';
              // Criar uma borda entre as linhas com estilo leve
            echo "<tr class='border-top border-bottom' style='animation-delay: {$delay}ms; background-color: #fff;'>
                <td>Fatura Nº " . $transaction['SerieId'] . "/" . $transaction['DocNumber'] . "</td>
                <td>" . $data_registro . "</td>
                <td>" . number_format($transaction['MovD'], 2, ',', '.') . "</td>
                <td>" . number_format($transaction['MovC'], 2, ',', '.') . "</td>
                <td class='text-danger'>-" . number_format(abs($transaction['Total']), 2, ',', '.') . "</td>
                <td class='{$divida_class}'>";
            
            // Mostra a dívida apenas se não for zero
            if ($transaction['DueValue'] != 0) {
                echo "-" . number_format(abs($transaction['DueValue']), 2, ',', '.');
            } else {
                echo "0,00";
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
        ?>    
        
        <script>
        // Update the pending amount with the calculated value
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('pending-amount').innerHTML = '<?php echo number_format(abs($totalDueValue), 2, ',', '.'); ?>€';
        });
        </script>
    </div>
      <!-- jQuery, Popper.js, and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/sortTable.js"></script>
    <script src="script/script.js"></script>
</body>
</html>
