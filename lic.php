<?php
session_start();

include('conflogin.php');

?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('header.php'); ?>
    <div class="content">
<h2>Licenças Existentes</h2>
<?php
include('db.php');

// Consulta SQL
$sql = "
    SELECT
        lee.LicType,
        lee.Version,
        lee.RenovationDate,
        lee.Obs,
        it1.KeyId AS Item1_KeyId,
        it1.Description AS Item1_Description,
        it1.NetPrice1 AS Item1_NetPrice1,
        it2.KeyId AS Item2_KeyId,
        it2.Description AS Item2_Description,
        it2.NetPrice1 AS Item2_NetPrice1,
        it3.KeyId AS Item3_KeyId,
        it3.Description AS Item3_Description,
        it3.NetPrice1 AS Item3_NetPrice1,
        et.KeyId AS Entity_KeyId,
        et.Name,
        et.Address,
        et.PostalCode,
        et.City,
        et.Vat
    FROM
        licenses_entity_extrafields lee
    LEFT JOIN 
        items it1 ON it1.KeyId = lee.Item    
    LEFT JOIN 
        items it2 ON it2.KeyId = lee.Item2
    LEFT JOIN 
        items it3 ON it3.KeyId = lee.Item3
    INNER JOIN
        entities et ON et.KeyId = lee.Entity_KeyId
    WHERE 
        lee.Entity_KeyId = :keyid";

// Preparar a consulta
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':keyid', $_SESSION['usuario_id']);
$stmt->execute();

// Verificando se encontrou algum registro
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$tickets) {
    echo "Sem licenças registadas.";
    exit;
}

// Exibindo os dados em uma tabela HTML
echo "<table class='table table-striped'>
        <tr>
            <th>Tipo de Licença</th>
            <th>Versão/Registo</th>
            <th>Data de Renovação</th>
            <th>Obs</th>
        </tr>";

foreach ($tickets as $ticket) {
    setlocale(LC_TIME, 'pt_PT.utf8');
    echo "<tr>
            <td>" . htmlspecialchars($ticket['LicType']) . "</td>
            <td>" . htmlspecialchars($ticket['Version']) . "</td>
            <td>" . strftime("%d %b", strtotime($ticket['RenovationDate'])) . "</td>
            <td>" . htmlspecialchars($ticket['Obs']) . "</td>
        </tr>";
}

// Fim da tabela HTML
echo "</table>";

// Fechando a conexão com o banco de dados
$pdo = null;
?>

</div>
    <!-- Scripts do Bootstrap e JQuery -->
    <script src="script/script.js"></script>    
</body>
</html>