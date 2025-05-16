<?php

        // Conexão com a base de dados
        $host = '127.0.0.1'; // Endereço do banco de dados
        $dbname = 'xd'; // Nome do banco de dados
        $username = 'root'; // Nome de usuário do MySQL
        $password = ''; // Senha do MySQL
        $port = '3306';  // Porta do MySQL

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;port=$port", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erro ao conectar: " . $e->getMessage());
        }

?>