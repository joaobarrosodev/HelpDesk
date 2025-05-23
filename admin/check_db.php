<?php
// Temporary script to check the database structure
include('db.php');

// Check info_xdfree01_extrafields table
echo "===== info_xdfree01_extrafields TABLE STRUCTURE =====\n";
$sql = "DESCRIBE info_xdfree01_extrafields";
$stmt = $pdo->prepare($sql);
$stmt->execute();
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . "\n";
}

// Check a few sample tickets
echo "\n===== SAMPLE TICKETS =====\n";
$sql = "SELECT 
            xdfree01.KeyId, 
            xdfree01.Name as ticket_title, 
            info_xdfree01_extrafields.Atribuido as assigned_to, 
            info_xdfree01_extrafields.Priority as priority,
            info_xdfree01_extrafields.Status as status,
            info_xdfree01_extrafields.Tempo as resolution_time,
            info_xdfree01_extrafields.Relatorio as resolution_description
        FROM xdfree01 
        JOIN info_xdfree01_extrafields ON xdfree01.KeyId = info_xdfree01_extrafields.XDFree01_KeyID
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($tickets as $ticket) {
    echo "-----------------------------------\n";
    echo "Ticket ID: " . $ticket['KeyId'] . "\n";
    echo "Title: " . $ticket['ticket_title'] . "\n";
    echo "Assigned To: " . $ticket['assigned_to'] . "\n";
    echo "Priority: " . $ticket['priority'] . "\n";
    echo "Status: " . $ticket['status'] . "\n";
    echo "Resolution Time: " . $ticket['resolution_time'] . "\n";
    echo "Resolution Description: " . $ticket['resolution_description'] . "\n";
}

?>
