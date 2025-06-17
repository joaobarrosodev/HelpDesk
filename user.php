<?php
session_start();  // Inicia a sessão

include('conflogin.php');
include('db.php');

// Consultar dados do usuário
$sql = "SELECT * FROM online_entity_extrafields WHERE email = :email";

// Preparar a consulta
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':email', $_SESSION['usuario_email']);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);    if (!$usuario) {
    echo "Utilizador não encontrado.";
    exit;
}

// Update ticket statistics query based on user role
if (isAdmin()) {
    // Admins see statistics for tickets from their entity
    $sql_tickets = "SELECT 
        COUNT(*) as total_tickets,
        SUM(CASE WHEN i.Status != 'Concluído' THEN 1 ELSE 0 END) as tickets_ativos,
        SUM(CASE WHEN i.Status = 'Concluído' THEN 1 ELSE 0 END) as tickets_concluidos,
        SUM(CASE WHEN i.Status = 'Em Análise' THEN 1 ELSE 0 END) as tickets_em_analise,
        AVG(TIMESTAMPDIFF(HOUR, i.CreationDate, i.dateu)) as tempo_medio_resposta,
        AVG(i.Tempo) as avg_tempo,
        SUM(i.Tempo) as total_tempo
    FROM 
        info_xdfree01_extrafields i
    INNER JOIN 
        online_entity_extrafields oee ON i.CreationUser = oee.email
    WHERE 
        oee.Entity_KeyId = :usuario_entity_id";
    
    $stmt_tickets = $pdo->prepare($sql_tickets);
    $stmt_tickets->bindParam(':usuario_entity_id', $usuario['Entity_KeyId']);
} else {
    // Common users see statistics only for their own tickets
    $sql_tickets = "SELECT 
        COUNT(*) as total_tickets,
        SUM(CASE WHEN i.Status != 'Concluído' THEN 1 ELSE 0 END) as tickets_ativos,
        SUM(CASE WHEN i.Status = 'Concluído' THEN 1 ELSE 0 END) as tickets_concluidos,
        SUM(CASE WHEN i.Status = 'Em Análise' THEN 1 ELSE 0 END) as tickets_em_analise,
        AVG(TIMESTAMPDIFF(HOUR, i.CreationDate, i.dateu)) as tempo_medio_resposta,
        AVG(i.Tempo) as avg_tempo,
        SUM(i.Tempo) as total_tempo
    FROM 
        info_xdfree01_extrafields i
    WHERE 
        i.CreationUser = :usuario_email";
    
    $stmt_tickets = $pdo->prepare($sql_tickets);
    $stmt_tickets->bindParam(':usuario_email', $_SESSION['usuario_email']);
}

$stmt_tickets->execute();
$estatisticas = $stmt_tickets->fetch(PDO::FETCH_ASSOC);

// Update overdue tickets query based on user role
if (isAdmin()) {
    // Admins see overdue tickets for their entity
    $sql_overdue = "SELECT COUNT(*) as overdue_tickets
    FROM info_xdfree01_extrafields i
    INNER JOIN 
        online_entity_extrafields oee ON i.CreationUser = oee.email
    WHERE oee.Entity_KeyId = :usuario_entity_id
    AND i.Status != 'Concluído'
    AND TIMESTAMPDIFF(HOUR, i.dateu, NOW()) > 48";
    
    $stmt_overdue = $pdo->prepare($sql_overdue);
    $stmt_overdue->bindParam(':usuario_entity_id', $usuario['Entity_KeyId']);
} else {
    // Common users see overdue tickets only for their tickets
    $sql_overdue = "SELECT COUNT(*) as overdue_tickets
    FROM info_xdfree01_extrafields i
    WHERE i.CreationUser = :usuario_email
    AND i.Status != 'Concluído'
    AND TIMESTAMPDIFF(HOUR, i.dateu, NOW()) > 48";
    
    $stmt_overdue = $pdo->prepare($sql_overdue);
    $stmt_overdue->bindParam(':usuario_email', $_SESSION['usuario_email']);
}

$stmt_overdue->execute();
$overdue = $stmt_overdue->fetch(PDO::FETCH_ASSOC);

// Update average response time query based on user role
if (isAdmin()) {
    // Admins see average response time for their entity
    $sql_avg = "SELECT AVG(TIMESTAMPDIFF(HOUR, i.CreationDate, i.dateu)) as tempo_medio_fechados
    FROM info_xdfree01_extrafields i
    INNER JOIN 
        online_entity_extrafields oee ON i.CreationUser = oee.email
    WHERE oee.Entity_KeyId = :usuario_entity_id
    AND i.Status = 'Concluído'
    AND i.dateu IS NOT NULL";
    
    $stmt_avg = $pdo->prepare($sql_avg);
    $stmt_avg->bindParam(':usuario_entity_id', $usuario['Entity_KeyId']);
} else {
    // Common users see average response time only for their tickets
    $sql_avg = "SELECT AVG(TIMESTAMPDIFF(HOUR, i.CreationDate, i.dateu)) as tempo_medio_fechados
    FROM info_xdfree01_extrafields i
    WHERE i.CreationUser = :usuario_email
    AND i.Status = 'Concluído'
    AND i.dateu IS NOT NULL";
    
    $stmt_avg = $pdo->prepare($sql_avg);
    $stmt_avg->bindParam(':usuario_email', $_SESSION['usuario_email']);
}

$stmt_avg->execute();
$avg_response = $stmt_avg->fetch(PDO::FETCH_ASSOC);

// Consultar dados da empresa do usuário
try {
    // Usar a estrutura correta das tabelas para obter os dados da empresa
    $sql_empresa = "SELECT 
        oee.Entity_KeyId,
        e.Name AS nome,
        e.ContactEmail,
        e.Address as endereco,
        e.City as cidade,
        e.PostalCode as codigo_postal,
        e.Country as pais,
        e.Phone1 as telefone,
        e.WebSite as website,
        e.Vat as nif
    FROM 
        online_entity_extrafields oee
    INNER JOIN
        entities e ON e.KeyId = oee.Entity_KeyId
    WHERE 
        oee.email = :email";    
    $stmt_empresa = $pdo->prepare($sql_empresa);
    $stmt_empresa->bindParam(':email', $_SESSION['usuario_email']);
    $stmt_empresa->execute();
    $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
      // Adicionar debug condicional para desenvolvimento
    if (isset($_GET['debug']) && $_GET['debug'] == 1) {
        echo "<pre style='background:#f5f5f5;padding:15px;border:1px solid #ddd;'>";        echo "<strong>DEBUG INFORMAÇÃO:</strong><br>";
        echo "Email do utilizador: " . $_SESSION['usuario_email'] . "<br>";
        echo "Entity_KeyId: " . $usuario['Entity_KeyId'] . "<br><br>";
        
        echo "<strong>QUERY PRINCIPAL:</strong><br>";
        echo $sql_empresa . "<br><br>";
        
        echo "<strong>RESULTADO:</strong><br>";
        print_r($empresa);
        
        // Verificar todas as tabelas relevantes
        echo "<br><br><strong>VERIFICAÇÃO DE RELAÇÕES:</strong><br>";
        
        // Verificar se o email existe na tabela online_entity_extrafields
        $check1 = $pdo->prepare("SELECT COUNT(*) as count FROM online_entity_extrafields WHERE email = :email");
        $check1->bindParam(':email', $_SESSION['usuario_email']);
        $check1->execute();
        $check1_result = $check1->fetch(PDO::FETCH_ASSOC);
        echo "Email encontrado em online_entity_extrafields: " . ($check1_result['count'] > 0 ? "Sim" : "Não") . "<br>";
        
        // Verificar se o Entity_KeyId existe na tabela entities
        $check2 = $pdo->prepare("SELECT COUNT(*) as count FROM entities WHERE KeyId = :entity_id");
        $check2->bindParam(':entity_id', $usuario['Entity_KeyId']);
        $check2->execute();
        $check2_result = $check2->fetch(PDO::FETCH_ASSOC);
        echo "Entity_KeyId encontrado na tabela entities: " . ($check2_result['count'] > 0 ? "Sim" : "Não") . "<br>";
        
        echo "</pre>";    }
    
    // Caso não encontre dados da empresa, tentar uma abordagem alternativa
    if (!$empresa) {
        // Tentar obter dados diretamente pelo KeyId da entidade
        $alt_sql = "SELECT 
            KeyId,
            Name as nome,
            ContactEmail,
            Address as endereco,
            City as cidade,
            PostalCode as codigo_postal,
            Country as pais,
            Phone1 as telefone,
            WebSite as website,
            Vat as nif
        FROM 
            entities
        WHERE 
            KeyId = :entity_keyid";
            
        $alt_stmt = $pdo->prepare($alt_sql);
        $alt_stmt->bindParam(':entity_keyid', $usuario['Entity_KeyId']);
        $alt_stmt->execute();
        $empresa = $alt_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se ainda não encontrou dados, usar valores padrão
        if (!$empresa) {
            error_log("Não foi possível encontrar dados da empresa para o utilizador: " . $_SESSION['usuario_email']);
            
            $empresa = [
                'nome' => 'Empresa não encontrada',
                'endereco' => '',
                'cidade' => '',
                'codigo_postal' => '',
                'pais' => 'Portugal',
                'telefone' => '',
                'website' => '',
                'nif' => ''
            ];
        }
    }
} catch (PDOException $e) {
    // Log do erro
    error_log("Erro ao Procurar dados da empresa: " . $e->getMessage());
    
    // Valores padrão em caso de erro
    $empresa = [
        'nome' => 'Erro ao carregar dados da empresa',
        'endereco' => '',
        'cidade' => '',
        'codigo_postal' => '',
        'pais' => '',
        'telefone' => '',
        'website' => '',
        'nif' => ''
    ];
}

// Get all users for admin - but only from the same entity/company
$all_users = [];
if (isAdmin()) {
    $sql_all_users = "SELECT 
        oee.Entity_KeyId,
        oee.Name,
        oee.email,
        oee.Password,
        oee.Grupo,
        e.Name as entity_name
    FROM 
        online_entity_extrafields oee
    INNER JOIN
        entities e ON e.KeyId = oee.Entity_KeyId
    WHERE 
        oee.Entity_KeyId = :current_user_entity_id
    ORDER BY oee.Name";
    
    $stmt_all_users = $pdo->prepare($sql_all_users);
    $stmt_all_users->bindParam(':current_user_entity_id', $usuario['Entity_KeyId']);
    $stmt_all_users->execute();
    $all_users = $stmt_all_users->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <div class="container-fluid p-4">
            <div class="row mb-4">
                <div class="col-12">                    
                    <h1 class="display-5 mb-0">
                        <?php echo isAdmin() ? 'Painel de Administração da Empresa' : 'O Meu Perfil'; ?>
                    </h1>
                    <p class="text-muted">
                        <?php echo isAdmin() ? 'Gira o sistema e veja estatísticas dos tickets da sua empresa' : 'Gira as suas informações pessoais e veja estatísticas dos seus tickets'; ?>
                    </p>
                    
                    <!-- Success/Error Messages -->
                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row mb-4">
                <!-- Coluna de Informações Pessoais -->
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-4">
                                <div class="avatar-circle bg-primary text-white me-3">
                                    <?php echo strtoupper(substr($usuario['Name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h5 class="card-title mb-0"><?php echo $usuario['Name']; ?></h5>
                                    <p class="text-muted mb-0">Cliente #<?php echo $usuario['Entity_KeyId']; ?></p>
                                </div>
                            </div>
                            
                            <h6 class="fw-bold mb-3">Informações de Contacto</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <span class="text-muted me-2"><i class="bi bi-envelope"></i></span>
                                    <span><?php echo $usuario['email']; ?></span>
                                </li>
                                <?php if (!empty($empresa['telefone'])): ?>
                                <li class="mb-2">
                                    <span class="text-muted me-2"><i class="bi bi-telephone"></i></span>
                                    <span><?php echo $empresa['telefone']; ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>                            <h6 class="fw-bold mb-3 mt-4">Empresa</h6>
                            <ul class="list-unstyled">
                                <?php if (isset($empresa['nome']) && !empty($empresa['nome']) && $empresa['nome'] != 'Empresa não encontrada'): ?>
                                <li class="mb-2">
                                    <span class="text-muted me-2"><i class="bi bi-building"></i></span>
                                    <span><?php echo $empresa['nome']; ?></span>
                                </li>
                                
                                <?php if (!empty($empresa['nif'])): ?>
                                <li class="mb-2">
                                    <span class="text-muted me-2"><i class="bi bi-credit-card"></i></span>
                                    <span>NIF: <?php echo $empresa['nif']; ?></span>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($empresa['endereco'])): ?>
                                <li class="mb-2">
                                    <span class="text-muted me-2"><i class="bi bi-geo-alt"></i></span>
                                    <span><?php echo $empresa['endereco']; ?></span>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($empresa['codigo_postal']) || !empty($empresa['cidade'])): ?>
                                <li class="mb-2">
                                    <span class="text-muted me-2"><i class="bi bi-pin-map"></i></span>
                                    <span>
                                    <?php 
                                        echo !empty($empresa['codigo_postal']) ? $empresa['codigo_postal'] : '';
                                        echo (!empty($empresa['codigo_postal']) && !empty($empresa['cidade'])) ? ', ' : '';
                                        echo !empty($empresa['cidade']) ? $empresa['cidade'] : '';
                                    ?>
                                    </span>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($empresa['pais']) && $empresa['pais'] != 'Portugal'): ?>
                                <li class="mb-2">
                                    <span class="text-muted me-2"><i class="bi bi-flag"></i></span>
                                    <span><?php echo $empresa['pais']; ?></span>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($empresa['website'])): ?>
                                <li class="mb-2">
                                    <span class="text-muted me-2"><i class="bi bi-globe"></i></span>
                                    <span><?php echo $empresa['website']; ?></span>
                                </li>
                                <?php endif; ?>
                                <?php else: ?>
                                <li class="mb-2">
                                    <span class="text-muted me-2"><i class="bi bi-exclamation-triangle"></i></span>
                                    <span>Erro ao carregar dados da empresa</span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Coluna de Estatísticas e Dados -->
                <div class="col-lg-8">
                    <!-- Estatísticas de Tickets -->                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <h2 class="display-4 mb-1 fw-bold text-primary"><?php echo $estatisticas['total_tickets'] ?? 0; ?></h2>
                                    <p class="text-muted mb-0">Total de Tickets</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <h2 class="display-4 mb-1 fw-bold text-warning"><?php echo $estatisticas['tickets_ativos'] ?? 0; ?></h2>
                                    <p class="text-muted mb-0">Tickets Ativos</p>
                                </div>
                            </div>
                        </div>                        <div class="col-md-3 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <?php
                                    // Utilizar o campo Tempo para média de tempo por ticket
                                    $avg_tempo = $estatisticas['avg_tempo'] ?? 0;
                                    if ($avg_tempo > 0) {
                                        $avg_horas = floor($avg_tempo / 60);
                                        $avg_minutos = $avg_tempo % 60;
                                        
                                        if ($avg_horas > 0) {
                                            $tempo_formatado = $avg_horas . "h" . ($avg_minutos > 0 ? " " . $avg_minutos . "m" : "");
                                        } else {
                                            $tempo_formatado = $avg_minutos . "m";
                                        }
                                    } else {
                                        $tempo_formatado = "0h";
                                    }
                                    ?>
                                    <h2 class="display-4 mb-1 fw-bold text-danger"><?php echo $tempo_formatado; ?></h2>
                                    <p class="text-muted mb-0">Tempo Médio</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card shadow-sm h-100">
                                <div class="card-body text-center">
                                    <?php
                                    // Utilizar o campo de soma total de Tempo
                                    $tempo_total = $estatisticas['total_tempo'] ?? 0;
                                    if ($tempo_total > 0) {
                                        if ($tempo_total >= 60) {
                                            // Se for 60 minutos ou mais, mostrar em horas e minutos
                                            $total_horas = floor($tempo_total / 60);
                                            $total_minutos = $tempo_total % 60;
                                            
                                            if ($total_horas > 0) {
                                                $total_formatado = $total_horas . "h" . ($total_minutos > 0 ? " " . $total_minutos . "m" : "");
                                            } else {
                                                $total_formatado = $total_minutos . "m";
                                            }
                                        } else {
                                            // Se for menos de 60 minutos, mostrar apenas em minutos
                                            $total_formatado = $tempo_total . "m";
                                        }
                                    } else {
                                        $total_formatado = "0m";
                                    }
                                    ?>
                                    <h2 class="display-4 mb-1 fw-bold text-success"><?php echo $total_formatado; ?></h2>
                                    <p class="text-muted mb-0">Tempo Total</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informações de Conta e Segurança -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Configurações da Conta</h5>
                        </div>
                        <div class="card-body">
                            <form id="accountSettingsForm" action="atualizar_dados.php" method="POST">
                                <input type="hidden" name="entity_keyid" value="<?php echo $usuario['Entity_KeyId']; ?>">
                                
                                <?php if (isCommonUser()): ?>
                                    <!-- Restricted form for common users - only password change -->
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label fw-bold">Nome Completo</label>
                                            <input type="text" class="form-control bg-light" id="name" name="name" value="<?php echo htmlspecialchars($usuario['Name']); ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label fw-bold">Email</label>
                                            <input type="email" class="form-control bg-light" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3">
                                            <label for="permissions" class="form-label fw-bold">Grupo/Permissões</label>
                                            <input type="text" class="form-control bg-light" id="permissions" name="grupo" value="<?php echo htmlspecialchars($usuario['Grupo']); ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label fw-bold">Nova Palavra-passe</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="password" name="password" placeholder="Digite a nova palavra-passe">
                                                <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">Deixe em branco para manter a palavra-passe atual</small>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info mt-3">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>Nota:</strong> Apenas pode alterar a sua palavra-passe. Para alterar outros dados, contacte um administrador.
                                    </div>
                                    
                                <?php else: ?>
                                    <!-- Full form for admins -->
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label fw-bold">Nome Completo</label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($usuario['Name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label fw-bold">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3">
                                            <label for="permissions" class="form-label fw-bold">Grupo/Permissões</label>
                                            <select class="form-select" id="permissions" name="grupo">
                                                <option value="Admin" <?php echo $usuario['Grupo'] === 'Admin' ? 'selected' : ''; ?>>Administrador</option>
                                                <option value="Comum" <?php echo $usuario['Grupo'] === 'Comum' ? 'selected' : ''; ?>>Utilizador Comum</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label fw-bold">Palavra-passe</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="password" name="password" value="<?php echo htmlspecialchars($usuario['Password']); ?>">
                                                <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">Deixe em branco para manter a palavra-passe atual</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">                                    
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo isCommonUser() ? 'Alterar Palavra-passe' : 'Guardar Alterações'; ?>
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary ms-2">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add user management section for admins -->
            <?php if (isAdmin()): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-transparent">
                            <h5 class="mb-0">Gestão de Utilizadores</h5>
                            <small class="text-muted">Lista de utilizadores da sua empresa: <?php echo htmlspecialchars($empresa['nome'] ?? 'N/A'); ?></small>
                        </div>
                        <div class="card-body">
                            <?php if (count($all_users) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>Empresa</th>
                                            <th>Grupo</th>
                                            <th>Password</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['Name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['entity_name']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $user['Grupo'] === 'Admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                                    <?php echo htmlspecialchars($user['Grupo']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="password-field" data-password="<?php echo htmlspecialchars($user['Password']); ?>">
                                                    ••••••••
                                                </span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="togglePassword(this)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editUser('<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['Name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['Grupo']); ?>')">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle me-2"></i>
                                Não foram encontrados outros utilizadores na sua empresa.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Editar Utilizador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editUserForm" action="update_user.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_entity_keyid" name="entity_keyid">
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label fw-bold">Nome Completo</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_grupo" class="form-label fw-bold">Grupo/Permissões</label>
                            <select class="form-select" id="edit_grupo" name="grupo" required>
                                <option value="Admin">Administrador</option>
                                <option value="Comum">Utilizador Comum</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label fw-bold">Nova Palavra-passe</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="edit_password" name="password" placeholder="Digite a nova palavra-passe">
                                <button type="button" class="btn btn-outline-secondary" id="toggleEditPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Deixe em branco para manter a palavra-passe atual</small>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Atenção:</strong> As alterações serão aplicadas imediatamente. O utilizador será notificado por email se a palavra-passe for alterada.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check me-2"></i>Guardar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Update icon
            this.innerHTML = type === 'password' ? 
                '<i class="bi bi-eye"></i>' : 
                '<i class="bi bi-eye-slash"></i>';
        });
        
        function togglePassword(button) {
            const passwordField = button.previousElementSibling;
            const isHidden = passwordField.textContent === '••••••••';
            
            if (isHidden) {
                passwordField.textContent = passwordField.getAttribute('data-password');
                button.innerHTML = '<i class="bi bi-eye-slash"></i>';
            } else {
                passwordField.textContent = '••••••••';
                button.innerHTML = '<i class="bi bi-eye"></i>';
            }
        }

        function editUser(userEmail, name, email, grupo) {
            // Populate the modal with user data
            document.getElementById('edit_entity_keyid').value = userEmail; // Use email as identifier
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_grupo').value = grupo;
            document.getElementById('edit_password').value = '';
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }

        // Toggle password visibility in edit modal
        document.addEventListener('DOMContentLoaded', function() {
            const toggleEditPasswordBtn = document.getElementById('toggleEditPassword');
            if (toggleEditPasswordBtn) {
                toggleEditPasswordBtn.addEventListener('click', function() {
                    const passwordInput = document.getElementById('edit_password');
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Update icon
                    this.innerHTML = type === 'password' ? 
                        '<i class="bi bi-eye"></i>' : 
                        '<i class="bi bi-eye-slash"></i>';
                });
            }
        });

        // Add form validation for common users
        <?php if (isCommonUser()): ?>
        document.getElementById('accountSettingsForm').addEventListener('submit', function(e) {
            const passwordField = document.getElementById('password');
            if (!passwordField.value.trim()) {
                e.preventDefault();
                alert('Por favor, introduza uma nova palavra-passe.');
                passwordField.focus();
            }
        });
        <?php endif; ?>
    </script>
    
    <style>
        /* Avatar circle style */
        .avatar-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }
        
        /* Card hover effect */
        .card {
            transition: transform 0.2s ease-in-out;
            border: none;
            border-radius: 10px;
        }
        
        .card-header {
            border-bottom: 1px solid rgba(0,0,0,.05);
        }
        
        .card-footer {
            border-top: 1px solid rgba(0,0,0,.05);
        }
        
        /* Stats cards */
        .display-4 {
            font-size: 2.5rem;
        }
        
        /* Improved form controls */
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13,110,253,.15);
        }
    </style>    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>    
</body>
</html>
