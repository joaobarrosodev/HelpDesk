<?php
session_start();  // Inicia a sessão

include('db.php');

// Verificar se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obter os dados do formulário
    $email = $_POST['email'];
    $senha = $_POST['password'];
    
    // Verificar se a conexão PDO foi estabelecida
    if (isset($pdo)) {
        try {
            // Check if Grupo column exists in online_userbe_extrafields table
            $stmt = $pdo->prepare("SHOW COLUMNS FROM online_userbe_extrafields LIKE 'Grupo'");
            $stmt->execute();
            $grupoColumnExists = $stmt->rowCount() > 0;
            
            // Prepare the SQL query - Grupo is in online_userbe_extrafields, not users!
            if ($grupoColumnExists) {
                $sql = "SELECT ou.UserBE_Id, ou.Email, ou.Password, ou.Grupo, u.Name
                        FROM online_userbe_extrafields ou
                        LEFT JOIN users u ON u.id = ou.UserBE_Id 
                        WHERE ou.Email = :email";
            } else {
                $sql = "SELECT ou.UserBE_Id, ou.Email, ou.Password, u.Name
                        FROM online_userbe_extrafields ou
                        LEFT JOIN users u ON u.id = ou.UserBE_Id 
                        WHERE ou.Email = :email";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            // Verificar se o utilizador foi encontrado
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (
                $usuario && (
                    $senha == $usuario['Password'] // WARNING: Use password_verify() if passwords are hashed!
                )
            ) {
                $_SESSION['admin_id'] = $usuario['UserBE_Id'];
                $_SESSION['admin_email'] = $usuario['Email'];
                $_SESSION['admin_nome'] = $usuario['Name'] ?? 'Administrador';

                // Robust group assignment
                $grupoSessao = 'Comum'; // default
                if ($grupoColumnExists && isset($usuario['Grupo']) && !empty($usuario['Grupo'])) {
                    // Normalize the database value - remove spaces and convert to lowercase for comparison
                    $dbGrupo = strtolower(trim($usuario['Grupo']));
                    
                    // Debug logging
                    error_log("Database Grupo value (normalized): '" . $dbGrupo . "' (original: '" . $usuario['Grupo'] . "')");
                    
                    // Check for admin group in various formats
                    if (in_array($dbGrupo, ['admin', 'administrador', 'administrator'])) {
                        $grupoSessao = 'Admin';
                    } elseif (in_array($dbGrupo, ['comum', 'common', 'user'])) {
                        $grupoSessao = 'Comum';
                    } else {
                        // If the value doesn't match known groups, check if it contains 'admin'
                        if (stripos($dbGrupo, 'admin') !== false) {
                            $grupoSessao = 'Admin';
                        } else {
                            error_log("Unknown database group '" . $dbGrupo . "', defaulting to Comum");
                        }
                    }
                } else {
                    error_log("No Grupo column or empty value, defaulting to Comum. Column exists: " . ($grupoColumnExists ? 'Yes' : 'No'));
                }
                
                $_SESSION['Grupo'] = $grupoSessao;
                error_log("FINAL - User login - Email: " . $usuario['Email'] . ", Database Grupo: " . ($usuario['Grupo'] ?? 'NULL') . ", Assigned Group: " . $_SESSION['Grupo']);
                $_SESSION['last_activity'] = time();
                
                // Redirecionar para a página principal
                header("Location: index.php");
                exit;
            } else {
                // Se a autenticação falhar, redirecionar de volta com erro
                header("Location: login.php?error=invalid");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Database error during login: " . $e->getMessage());
            header("Location: login.php?error=db_error");
            exit;
        }
    } else {
        header("Location: login.php?error=db_error");
        exit;
    }
} else {
    // Se não foi POST, redirecionar para login
    header("Location: login.php");
    exit;
}
?>