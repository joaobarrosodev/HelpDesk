<?php
session_start();
include('conflogin.php');
include('db.php');

// Restrict access to admin users only
requireAdmin();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: user.php?error=' . urlencode('Acesso inválido.'));
    exit;
}

// Get form data
$target_user_email = $_POST['entity_keyid'] ?? ''; // The email of the user being edited
$name = trim($_POST['name'] ?? '');
$new_email = trim($_POST['email'] ?? ''); // The potentially new email for the user
$grupo = $_POST['grupo'] ?? '';
$password = trim($_POST['password'] ?? '');

// Validate required fields
if (empty($target_user_email) || empty($name) || empty($new_email) || empty($grupo)) {
    header('Location: user.php?error=' . urlencode('Todos os campos obrigatórios devem ser preenchidos.'));
    exit;
}

// Validate email format
if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    header('Location: user.php?error=' . urlencode('Formato de email inválido.'));
    exit;
}

// Validate group
if (!in_array($grupo, ['Admin', 'Comum'])) {
    header('Location: user.php?error=' . urlencode('Grupo inválido.'));
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get current target user data to check if they belong to the same entity
    $check_user_sql = "SELECT Entity_KeyId, email, Name FROM online_entity_extrafields WHERE email = :target_user_email";
    $check_stmt = $pdo->prepare($check_user_sql);
    $check_stmt->bindParam(':target_user_email', $target_user_email);
    $check_stmt->execute();
    $target_user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target_user) {
        throw new Exception('Utilizador não encontrado.');
    }
    
    // Get admin user data to verify same entity
    $admin_entity_sql = "SELECT Entity_KeyId FROM online_entity_extrafields WHERE email = :admin_email";
    $admin_stmt = $pdo->prepare($admin_entity_sql);
    $admin_stmt->bindParam(':admin_email', $_SESSION['usuario_email']);
    $admin_stmt->execute();
    $admin_entity = $admin_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin_entity || $admin_entity['Entity_KeyId'] !== $target_user['Entity_KeyId']) {
        throw new Exception('Não tem permissão para editar este utilizador.');
    }
    
    // Check if new email is already in use by another user (only if email is being changed)
    if ($new_email !== $target_user['email']) {
        $email_check_sql = "SELECT email FROM online_entity_extrafields WHERE email = :new_email";
        $email_stmt = $pdo->prepare($email_check_sql);
        $email_stmt->bindParam(':new_email', $new_email);
        $email_stmt->execute();
        
        if ($email_stmt->fetch()) {
            throw new Exception('Este email já está em uso por outro utilizador.');
        }
    }
    
    // Prepare update query - UPDATE THE TARGET USER, NOT THE LOGGED-IN USER
    if (!empty($password)) {
        // Update with new password
        $update_sql = "UPDATE online_entity_extrafields 
                      SET Name = :name, email = :new_email, Grupo = :grupo, Password = :password 
                      WHERE email = :target_user_email";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->bindParam(':password', $password);
    } else {
        // Update without changing password
        $update_sql = "UPDATE online_entity_extrafields 
                      SET Name = :name, email = :new_email, Grupo = :grupo 
                      WHERE email = :target_user_email";
        $update_stmt = $pdo->prepare($update_sql);
    }
    
    $update_stmt->bindParam(':name', $name);
    $update_stmt->bindParam(':new_email', $new_email);
    $update_stmt->bindParam(':grupo', $grupo);
    $update_stmt->bindParam(':target_user_email', $target_user_email); // Use target user email for WHERE clause
    
    $update_result = $update_stmt->execute();
    
    if (!$update_result) {
        throw new Exception('Erro ao atualizar os dados do utilizador.');
    }
    
    // Check if any rows were affected
    if ($update_stmt->rowCount() === 0) {
        throw new Exception('Nenhuma alteração foi realizada ou utilizador não encontrado.');
    }
    
    // Log the admin action
    error_log("Admin {$_SESSION['usuario_email']} updated user {$target_user_email}: Name={$name}, Email={$new_email}, Group={$grupo}, Password=" . (!empty($password) ? 'CHANGED' : 'NOT_CHANGED'));
    
    // Commit transaction
    $pdo->commit();
    
    // Success message
    $success_msg = "Utilizador {$name} atualizado com sucesso.";
    if (!empty($password)) {
        $success_msg .= " A nova palavra-passe foi definida.";
    }
    
    header('Location: user.php?success=' . urlencode($success_msg));
    exit;
    
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollback();
    
    // Log error
    error_log("Error updating user: " . $e->getMessage());
    
    // Redirect with error
    header('Location: user.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
