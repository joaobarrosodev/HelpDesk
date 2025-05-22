<?php
// test_closing_ticket.php - Test script to verify ticket closing functionality
include('db.php');

echo "===== TESTING TICKET CLOSING VALIDATION =====\n\n";

// 1. Verify tickets with required fields are properly recognized
echo "1. SQL Query for tickets requiring closure fields:\n";
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.Name as ticket_title,
            info_xdfree01_extrafields.Status as status
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        WHERE info_xdfree01_extrafields.Status = 'Concluído'
        AND (info_xdfree01_extrafields.Tempo IS NULL OR info_xdfree01_extrafields.Tempo = '' OR info_xdfree01_extrafields.Tempo = 0)";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($tickets) . " closed tickets without resolution time.\n";
foreach($tickets as $ticket) {
    echo "- Ticket " . $ticket['KeyId'] . ": " . $ticket['ticket_title'] . " (Status: " . $ticket['status'] . ")\n";
}
echo "\n";

// 2. Verify tickets with No Description are properly recognized
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.Name as ticket_title,
            info_xdfree01_extrafields.Status as status
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        WHERE info_xdfree01_extrafields.Status = 'Concluído'
        AND (info_xdfree01_extrafields.Relatorio IS NULL OR info_xdfree01_extrafields.Relatorio = '')";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "2. Found " . count($tickets) . " closed tickets without resolution description.\n";
foreach($tickets as $ticket) {
    echo "- Ticket " . $ticket['KeyId'] . ": " . $ticket['ticket_title'] . " (Status: " . $ticket['status'] . ")\n";
}
echo "\n";

// 3. Verify tickets with No Assigned User are properly recognized
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.Name as ticket_title,
            info_xdfree01_extrafields.Status as status
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        WHERE info_xdfree01_extrafields.Status = 'Concluído'
        AND (info_xdfree01_extrafields.Atribuido IS NULL OR info_xdfree01_extrafields.Atribuido = '')";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "3. Found " . count($tickets) . " closed tickets without an assigned user.\n";
foreach($tickets as $ticket) {
    echo "- Ticket " . $ticket['KeyId'] . ": " . $ticket['ticket_title'] . " (Status: " . $ticket['status'] . ")\n";
}
echo "\n";

// 4. Count tickets by status
$sql = "SELECT 
            info_xdfree01_extrafields.Status as status,
            COUNT(*) as count
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        GROUP BY info_xdfree01_extrafields.Status";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "4. Ticket counts by status:\n";
foreach($statusCounts as $status) {
    echo "- " . $status['status'] . ": " . $status['count'] . " tickets\n";
}
echo "\n";

// 5. Count tickets by assignee
$sql = "SELECT 
            info_xdfree01_extrafields.Atribuido as user_id,
            users.Name as user_name,
            COUNT(*) as count
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LEFT JOIN users ON info_xdfree01_extrafields.Atribuido = users.id
        GROUP BY info_xdfree01_extrafields.Atribuido
        ORDER BY count DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$userCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "5. Ticket counts by assignee:\n";
foreach($userCounts as $user) {
    $userName = !empty($user['user_name']) ? $user['user_name'] : 'Ninguém';
    echo "- " . $userName . " (ID: " . $user['user_id'] . "): " . $user['count'] . " tickets\n";
}

echo "\n===== TEST COMPLETE =====\n";
?>
