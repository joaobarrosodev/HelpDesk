<?php
// Include database logger if it exists
if (file_exists(__DIR__ . '/db-log.php')) {
    require_once __DIR__ . '/db-log.php';
}

// Conexão com a base de dados
$host = 'infocloud.ddns.net'; // Endereço do banco de dados
$dbname = 'infoxd'; // Nome do banco de dados
$username = 'infoadmin'; // Nome de usuário do MySQL
$password = '/*2025IE+'; // Senha do MySQL
$port = '3306';  // Porta do MySQL

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    // Log to error log
    error_log('Database Connection Error: ' . $e->getMessage());
    die("Erro ao conectar: " . $e->getMessage());
}

/**
 * Standardize ticket ID format for consistent use across the system
 * @param mixed $ticketId The ticket ID in any accepted format
 * @return string The standardized ticket ID with # prefix
 */
function standardizeTicketId($ticketId) {
    // Remove any # prefix if present
    $cleanId = ltrim($ticketId, '#');
    
    // Check if this is a numeric ID reference
    if (is_numeric($cleanId)) {
        // If it's a short numeric value (likely a DB ID), fetch the proper KeyId
        if (strlen($cleanId) < 4) {
            global $pdo;
            
            // Only attempt database lookup if $pdo is available
            if (isset($pdo) && $pdo instanceof PDO) {
                try {
                    $stmt = $pdo->prepare("SELECT KeyId FROM xdfree01 WHERE id = :id");
                    $stmt->bindParam(':id', $cleanId, PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        return $result['KeyId']; // Return the proper KeyId format
                    }
                } catch (PDOException $e) {
                    error_log("Error fetching KeyId: " . $e->getMessage());
                }
            }
        }
        
        // If we couldn't find a KeyId or it's a longer number, format it properly
        return '#' . str_pad($cleanId, 3, '0', STR_PAD_LEFT);
    }
    
    // Add # prefix back if it was removed
    return '#' . $cleanId;
}

/**
 * Generate a proper URL for a ticket based on whether it's admin or client side
 * @param string $ticketId The ticket ID
 * @param bool $adminArea Whether the link is for admin area
 * @return string The proper URL for the ticket
 */
function generateTicketUrl($ticketId, $adminArea = false) {
    $standardId = standardizeTicketId($ticketId);
    
    if ($adminArea) {
        return "detalhes_ticket.php?keyid=" . urlencode($standardId);
    } else {
        return "../detalhes_ticket.php?keyid=" . urlencode($standardId);
    }
}
?>