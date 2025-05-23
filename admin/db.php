<?php
// Include database logger if it exists
if (file_exists(__DIR__ . '/db-log.php')) {
    require_once __DIR__ . '/db-log.php';
}

// Conexão com a base de dados
$host = '127.0.0.1'; // Endereço do banco de dados
$dbname = 'xd'; // Nome do banco de dados
$username = 'root'; // Nome de usuário do MySQL
$password = ''; // Senha do MySQL
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
?>