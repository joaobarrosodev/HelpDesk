<?php
session_start();
include('conflogin.php');
include('db.php');
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('header.php'); ?>
    <div class="content">
        <h2>Movimentações de Conta Corrente</h2>
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
        $stmt->execute();

        // Verificando se encontrou algum registro
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$transactions) {
            echo "Sem movimentações registradas.";
            exit;
        }

        // Inicializar as variáveis de soma
        $totalMovD = 0;
        $totalMovC = 0;
        $total = 0;
        $totalDueValue = 0;

        // Exibindo os dados em uma tabela HTML
        echo "<table class='table table-striped'>
                <tr>
                    <th>Data de Documento</th>
                    <th>Data de Vencimento</th>
                    <th>Série</th>
                    <th>Número do Documento</th>
                    <th>Descrição</th>
                    <th>Movimento Débito</th>
                    <th>Movimento Crédito</th>
                    <th>Total</th>
                    <th>Desconto Financeiro</th>
                    <th>Valor em Dívida</th>
                    <th>Download Documento</th>
                </tr>";

        // Exibindo os dados da transação
        foreach ($transactions as $transaction) {
            // Atualizar os totais
            $totalMovD += $transaction['MovD'];
            $totalMovC += $transaction['MovC'];
            $total += $transaction['Total'];
            $totalDueValue += $transaction['DueValue'];

            echo "<tr>
        <td>" . htmlspecialchars($transaction['RegisterDate']) . "</td>
        <td>" . htmlspecialchars($transaction['ExpirationDate']) . "</td>
        <td>" . htmlspecialchars($transaction['SerieId']) . "</td>
        <td>" . htmlspecialchars($transaction['DocNumber']) . "</td>
        <td>" . htmlspecialchars($transaction['Description']) . "</td>
        <td>" . number_format($transaction['MovD'], 2, ',', '.') . "</td>
        <td>" . number_format($transaction['MovC'], 2, ',', '.') . "</td>
        <td>" . number_format($transaction['Total'], 2, ',', '.') . "</td>
        <td>" . number_format($transaction['FinancialDiscount'], 2, ',', '.') . "</td>
        <td>" . number_format($transaction['DueValue'], 2, ',', '.') . "</td>";

        // Mapeamento de descrições para siglas
        $mapa_descricoes = [
            "Fatura" => "FAC",
            "Recibo Cliente" => "REC",
            "Nota de Crédito" => "NC"
        ];

        // Converter descrição para sigla
        $sigla = isset($mapa_descricoes[$transaction['Description']]) ? $mapa_descricoes[$transaction['Description']] : "DOC";

        // Construir nome do arquivo
        $nome_arquivo = "{$sigla}_{$transaction['SerieId']}-{$transaction['DocNumber']}_1.pdf";

        // Caminho do arquivo (ajuste conforme necessário)
        $pasta_arquivos = "docs/";
        $caminho_completo = $pasta_arquivos . $nome_arquivo;

        // Verifica se o arquivo existe e gera o link
        if (file_exists($caminho_completo)) {
        echo "<td class='text-center'><a href='$caminho_completo' target='_blank'><i class='bi bi-cloud-arrow-down-fill fa-2x'></i>
        </a></td>";
        } else {
        echo "<td class='text-center'>Documento não encontrado</td>";
        }

        echo "</tr>";
        }

        // Exibir linha de totais
        echo "<tr>
                <td colspan='5' class='text-right font-wb'>Totais:</td>
                <td>" . number_format($totalMovD, 2, ',', '.') . "</td>
                <td>" . number_format($totalMovC, 2, ',', '.') . "</td>
                <td>" . number_format($total, 2, ',', '.') . "</td>
                <td colspan='1'></td>
                <td>" . number_format($totalDueValue, 2, ',', '.') . "</td>
                <td></td>
              </tr>";

        // Fim da tabela HTML
        echo "</table>";
        echo "<div class='card mb-3 bg-light' style='border-left: 5px solid blue;'>";
        echo "<div class='card-body'>";
        echo "<h4>Dados para Pagamento</h4>";
        echo "<p class='card-text text-muted'>Banco: Crédito Agrícola</p>";
        echo "<p class='card-text text-muted'>BIC SWITFT: CCCMPTPL</p>";
        echo "<p class='card-text text-muted'>IBAN: PT50 0045 1405 4029 4772 6307 5</p>";
                echo "</div>";
        echo "</div>";
        // Fechando a conexão com o banco de dados
        $pdo = null;
        ?>

    </div>
    <!-- Scripts do Bootstrap e JQuery -->
    <script src="script/script.js"></script>    
</body>
</html>
