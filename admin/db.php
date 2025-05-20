<?php
    // Conexão com a base de dados
    $host = '127.0.0.1'; // Endereço do banco de dados
    $dbname = 'xd'; // Nome do banco de dados
    $username = 'root'; // Nome de usuário do MySQL
    $password = ''; // Senha do MySQL
    $port = '3306';  // Porta do MySQL

    try {
        // Set connection timeout options
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3, // 3 seconds timeout
            PDO::ATTR_PERSISTENT => false
        );
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;port=$port", $username, $password, $options);
        // Success message for debug only - comment out in production
        // echo "<span style='color:green;'>Database connection successful</span>";
        
    } catch (PDOException $e) {
        // Display user-friendly error message
        echo "<div class='alert alert-danger'>
                <strong>Database Error:</strong> Unable to connect to the database server.<br>
                Please verify the database server is running and try again later.<br>
                <small>Details: " . $e->getMessage() . "</small>
              </div>";
        
        // Log the error to a file (optional)
        $error_log = 'db_error_log.txt';
        $message = date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n";
        file_put_contents($error_log, $message, FILE_APPEND);
        
        // Don't die, just continue without DB functionality
    }
?>