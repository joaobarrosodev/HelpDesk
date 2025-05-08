<?php
session_start();
include('db.php'); // Conexão com o banco de dados
error_reporting(E_ALL);
ini_set('display_errors', 1);
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $keyid = $_POST['keyid'];
    $status = $_POST['status'];
    $user = $_SESSION['admin_id'];
    $description = $_POST['resolution_description'];
    $extra_info = $_POST['extra_info'];
    $resolution_time = $_POST['resolution_time'];

     // Validar e converter o tempo para formato DATETIME
    if (preg_match('/^([0-9]{1,2}):([0-5][0-9])$/', $resolution_time, $matches)) {
        $hours = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $minutes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $time_formatted = date('Y-m-d') . " $hours:$minutes:00";  // Adiciona a data de hoje
    } else {
        echo "<script>alert('Formato de tempo inválido! Use HH:MM'); window.history.back();</script>";
        exit;
    }

try {
        // Iniciar transação
        $pdo->beginTransaction();

        // Atualizar a tabela `internal_xdfree01_extrafields`
        $sql1 = "UPDATE internal_xdfree01_extrafields 
                 SET User = :user, Description = :description, 
                     Info = :extra_info, Time = :time, 
                 XDFree01_KeyID = :keyid";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->bindParam(':user', $user);
        $stmt1->bindParam(':description', $description);
        $stmt1->bindParam(':extra_info', $extra_info);
        $stmt1->bindParam(':time', $time_formatted);
        $stmt1->bindParam(':keyid', $keyid, PDO::PARAM_INT);
        $stmt1->execute();
        echo $sql1;
        // Atualizar a tabela `info_xdfree01_extrafields`
        $sql2 = "UPDATE info_xdfree01_extrafields 
                 SET Status = :status, dateu = NOW() 
                 WHERE XDFree01_KeyID = :keyid";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->bindParam(':status', $status);
        $stmt2->bindParam(':keyid', $keyid, PDO::PARAM_INT);
        $stmt2->execute();

        // Confirmar as alterações no banco de dados
        $pdo->commit();

        echo "<script>alert('Ticket atualizado com sucesso!'); window.location.href='meus_tickets.php';</script>";
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        $sql1_final = str_replace(
    [':user', ':description', ':extra_info', ':time', ':keyid'],
    [$user, $description, $extra_info, $time_formatted, $keyid],
    $sql1
);
echo "Final SQL1: " . $sql1_final . "<br>";
        echo "<script>alert('Erro ao atualizar o ticket: " . $e->getMessage() . "'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Acesso inválido!'); window.location.href='meus_tickets.php';</script>";
}
?>
