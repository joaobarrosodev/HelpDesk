<?php

        // Conexão com a base de dados
        $host = '192.168.10.10'; // Endereço do banco de dados
        $dbname = 'info'; // Nome do banco de dados
        $username = 'root'; // Nome de usuário do MySQL
        $password = 'xd'; // Senha do MySQL
        $port = '3308';  // Porta do MySQL

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;port=$port", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erro ao conectar: " . $e->getMessage());
        }

?>