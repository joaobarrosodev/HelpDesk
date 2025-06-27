<?php
session_start();
include('conflogin.php');
include('db.php');
include('verificar_tempo_disponivel.php');

// CORREÇÃO: Restringir acesso apenas para administradores
if (!isAdmin()) {
    header("Location: index.php?error=" . urlencode("Acesso negado. Esta página é apenas para administradores."));
    exit;
}

$entity = $_SESSION['usuario_id'] ?? '';

// Parâmetros de pesquisa - EXPANDED (same as admin but without creator filter)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$data_inicio_de = isset($_GET['data_inicio_de']) ? $_GET['data_inicio_de'] : '';
$data_inicio_ate = isset($_GET['data_inicio_ate']) ? $_GET['data_inicio_ate'] : '';
$tempo_restante_filter = isset($_GET['tempo_restante']) ? $_GET['tempo_restante'] : '';
$pack_horas_filter = isset($_GET['pack_horas']) ? $_GET['pack_horas'] : '';

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Processar solicitação de pack de horas
if ($_POST && isset($_POST['solicitar_pack'])) {
    $pack_horas = $_POST['pack_selecionado'];
    $preco = $_POST['preco'];
    $desconto = $_POST['desconto'];
    $empresa = $_POST['empresa'];
    $data_inicio_desejada = $_POST['data_inicio'] ?? '';
    
    // Configurar preços (valores finais já com desconto aplicado)
    $precos = [
        '5' => ['preco_original' => 175, 'preco_final' => 175, 'desconto' => 0],
        '10' => ['preco_original' => 350, 'preco_final' => 315, 'desconto' => 10],
        '20' => ['preco_original' => 700, 'preco_final' => 560, 'desconto' => 20]
    ];
    
    if (isset($precos[$pack_horas])) {
        $preco_original = $precos[$pack_horas]['preco_original'];
        $preco_final = $precos[$pack_horas]['preco_final'];
        $desconto_pct = $precos[$pack_horas]['desconto'];
        $total_minutos = $pack_horas * 60; // Converter horas para minutos
        
        // Preparar email com formatação melhorada e mais informações
        $to = "web@info-exe.com";
        $subject = "NOVA SOLICITAÇÃO - Pack de $pack_horas Horas - $empresa";
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Solicitação de Pack de Horas</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .email-container { max-width: 700px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; padding: 40px 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 32px; font-weight: 300; letter-spacing: 1px; }
                .header .subtitle { margin: 10px 0 0 0; opacity: 0.9; font-size: 16px; font-weight: 300; }
                .content { padding: 40px 30px; }
                .urgent-notice { background-color: #e74c3c; color: white; padding: 20px; margin: -40px -30px 30px -30px; text-align: center; }
                .urgent-notice h3 { margin: 0; font-size: 18px; font-weight: 600; }
                .section { margin-bottom: 35px; }
                .section-title { color: #2c3e50; font-size: 20px; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #ecf0f1; }
                .info-grid { display: table; width: 100%; border-collapse: collapse; }
                .info-row { display: table-row; }
                .info-label { display: table-cell; padding: 12px 20px 12px 0; font-weight: 600; color: #34495e; width: 40%; vertical-align: top; }
                .info-value { display: table-cell; padding: 12px 0; color: #2c3e50; vertical-align: top; }
                .highlight-box { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 25px; border-radius: 8px; text-align: center; margin: 25px 0; }
                .highlight-box h3 { margin: 0 0 10px 0; font-size: 28px; font-weight: 600; }
                .highlight-box p { margin: 0; font-size: 14px; opacity: 0.9; }
                .action-steps { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 25px; border-radius: 8px; margin: 25px 0; }
                .action-steps h4 { margin: 0 0 15px 0; color: #856404; font-size: 18px; }
                .action-steps ol { margin: 0; padding-left: 20px; }
                .action-steps li { margin-bottom: 8px; color: #6c5700; }
                .summary-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .summary-table th, .summary-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ecf0f1; }
                .summary-table th { background-color: #f8f9fa; font-weight: 600; color: #2c3e50; }
                .savings { color: #27ae60; font-weight: 600; }
                .footer { background-color: #34495e; color: #ecf0f1; padding: 25px 30px; text-align: center; font-size: 13px; line-height: 1.8; }
                .footer strong { color: white; }
                .client-id { background-color: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-family: 'Courier New', monospace; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>SOLICITAÇÃO DE PACK DE HORAS</h1>
                    <p class='subtitle'>Sistema de Gestão HelpDesk Info-Exe</p>
                </div>
                
                <div class='content'>
                    <div class='urgent-notice'>
                        <h3>AÇÃO REQUERIDA: Nova solicitação pendente de processamento</h3>
                    </div>
                    
                    <div class='section'>
                        <h2 class='section-title'>Informações do Cliente</h2>
                        <div class='info-grid'>
                            <div class='info-row'>
                                <div class='info-label'>Empresa:</div>
                                <div class='info-value'><strong>$empresa</strong></div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>ID do Cliente:</div>
                                <div class='info-value'><span class='client-id'>$entity</span></div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Data da Solicitação:</div>
                                <div class='info-value'>" . date('d \d\e F \d\e Y \à\s H:i') . "</div>
                            </div>" . 
                            ($data_inicio_desejada ? "
                            <div class='info-row'>
                                <div class='info-label'>Data de Início Pretendida:</div>
                                <div class='info-value'><strong>" . date('d \d\e F \d\e Y', strtotime($data_inicio_desejada)) . "</strong></div>
                            </div>" : "") . "
                        </div>
                    </div>
                    
                    <div class='section'>
                        <h2 class='section-title'>Detalhes do Pack Solicitado</h2>
                        <table class='summary-table'>
                            <tr>
                                <th>Descrição</th>
                                <th>Valor</th>
                            </tr>
                            <tr>
                                <td>Pack Solicitado</td>
                                <td><strong>$pack_horas horas</strong> ($total_minutos minutos)</td>
                            </tr>
                            <tr>
                                <td>Preço de Tabela</td>
                                <td>€" . number_format($preco_original, 2, ',', '.') . "</td>
                            </tr>" . 
                            ($desconto_pct > 0 ? "
                            <tr>
                                <td>Desconto Aplicado</td>
                                <td class='savings'>$desconto_pct%</td>
                            </tr>
                            <tr>
                                <td>Valor da Poupança</td>
                                <td class='savings'>€" . number_format($preco_original - $preco_final, 2, ',', '.') . "</td>
                            </tr>" : "") . "
                            <tr style='border-top: 2px solid #2c3e50;'>
                                <td><strong>Valor Final</strong></td>
                                <td><strong>€" . number_format($preco_final, 2, ',', '.') . "</strong></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class='highlight-box'>
                        <h3>€" . number_format($preco_final, 2, ',', '.') . "</h3>
                        <p>Valor total a faturar ao cliente</p>
                    </div>
                    
                    <div class='action-steps'>
                        <h4>Procedimentos a Seguir</h4>
                        <ol>
                            <li><strong>Contacto Inicial:</strong> Contactar o cliente no prazo de 24 horas</li>
                            <li><strong>Confirmação:</strong> Validar detalhes do pack e data de início pretendida</li>
                            <li><strong>Proposta:</strong> Enviar proposta comercial formal</li>
                            <li><strong>Processamento:</strong> Após aprovação, processar pagamento</li>
                            <li><strong>Ativação:</strong> Ativar o pacote e notificar a equipa técnica</li>
                        </ol>
                    </div>
                    
                    <div class='section'>
                        <h2 class='section-title'>Análise Financeira</h2>
                        <div class='info-grid'>
                            <div class='info-row'>
                                <div class='info-label'>Total de Minutos:</div>
                                <div class='info-value'>$total_minutos minutos</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Custo por Hora:</div>
                                <div class='info-value'>€" . number_format($preco_final / $pack_horas, 2, ',', '.') . "</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class='footer'>
                    <p><strong>Email automático gerado pelo Sistema HelpDesk Info-Exe</strong></p>
                    <p>
                        Suporte Técnico: suporte@info-exe.com | Departamento Comercial: web@info-exe.com<br>
                        Website: www.info-exe.com | Este email requer ação dentro de 24 horas
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: HelpDesk Info-Exe <noreply@info-exe.com>" . "\r\n";
        $headers .= "Reply-To: web@info-exe.com" . "\r\n";
        $headers .= "X-Priority: 1" . "\r\n"; // Alta prioridade
        
        // Tentar enviar email
        if (@mail($to, $subject, $message, $headers)) {
            $mensagem_sucesso = "Solicitação enviada com sucesso! Receberá contacto em 24h através do email web@info-exe.com.";
            $mostrar_preview = true; // Flag para mostrar preview
        } else {
            // Se falhar o envio, mostrar mensagem de confirmação mesmo assim
            $mensagem_sucesso = "Solicitação registada! Entraremos em contacto em 24h através do email web@info-exe.com ou telefone.";
            $mostrar_preview = true; // Flag para mostrar preview mesmo se falhar
            
            // Log da solicitação para arquivo com mais detalhes
            $log_data = date('Y-m-d H:i:s') . " | PACK: $pack_horas h ($total_minutos min) | EMPRESA: $empresa | ID: $entity | PREÇO: €$preco_final | INÍCIO: $data_inicio_desejada\n";
            @file_put_contents('logs/solicitacoes_packs.log', $log_data, FILE_APPEND | LOCK_EX);
        }
        
        // Guardar dados para o preview
        $preview_data = [
            'pack' => $pack_horas,
            'empresa' => $empresa,
            'entity' => $entity,
            'data_inicio' => $data_inicio_desejada
        ];
    }
}

// Inicializar variáveis para evitar erros
$contratos = [];
$todos_tickets = [];
$tickets_com_contrato = [];
$tickets_sem_contrato = [];
$tempoTotalComprado = 0;
$tempoTotalGasto = 0;
$tempoRestante = 0;
$contratosExcedidos = 0;
$nome_empresa = 'N/A';
$total_records = 0;
$total_pages = 0;

try {
    // Update contract status before displaying
    atualizarStatusContratos($entity, $pdo);
    
    // Get company name
    $sql_empresa = "SELECT name FROM entities WHERE KeyId = ?";
    $stmt_empresa = $pdo->prepare($sql_empresa);
    $stmt_empresa->execute([$entity]);
    $empresa_info = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
    $nome_empresa = $empresa_info ? $empresa_info['name'] : "Cliente ID: $entity";
    
    // Construir query com filtros EXPANDIDOS (adapted from admin version)
    $where_conditions = ["oee.Entity_KeyId = (
        SELECT Entity_KeyId 
        FROM online_entity_extrafields 
        WHERE email = :admin_email
    )"];
    $params = [':admin_email' => $_SESSION['usuario_email']];
    
    if (!empty($search)) {
        $search_conditions = [];
        $search_conditions[] = "e.name LIKE :search";
        $search_conditions[] = "e.ContactEmail LIKE :search";
        $search_conditions[] = "e.MobilePhone1 LIKE :search";
        $search_conditions[] = "e.Phone1 LIKE :search";
        $search_conditions[] = "x2Extra.Status LIKE :search";
        $search_conditions[] = "x2Extra.XDfree02_KeyId LIKE :search"; // Add contract ID search
        
        $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
        $params['search'] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "x2Extra.Status = :status";
        $params['status'] = $status_filter;
    }
    
    // Date range filter
    if (!empty($data_inicio_de)) {
        $where_conditions[] = "x2Extra.StartDate >= :data_inicio_de";
        $params['data_inicio_de'] = $data_inicio_de . ' 00:00:00';
    }
    
    if (!empty($data_inicio_ate)) {
        $where_conditions[] = "x2Extra.StartDate <= :data_inicio_ate";
        $params['data_inicio_ate'] = $data_inicio_ate . ' 23:59:59';
    }
    
    // Pack hours filter - filter by total hours ranges
    if (!empty($pack_horas_filter)) {
        switch ($pack_horas_filter) {
            case '5':
                $where_conditions[] = "x2Extra.TotalHours >= 240 AND x2Extra.TotalHours <= 360"; // 4-6 hours range
                break;
            case '10':
                $where_conditions[] = "x2Extra.TotalHours >= 540 AND x2Extra.TotalHours <= 660"; // 9-11 hours range
                break;
            case '20':
                $where_conditions[] = "x2Extra.TotalHours >= 1140 AND x2Extra.TotalHours <= 1260"; // 19-21 hours range
                break;
        }
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Count total records
    $sql_count = "SELECT COUNT(*) as total 
                  FROM info_xdfree02_extrafields x2Extra
                  LEFT JOIN entities e ON e.KeyId = x2Extra.Entity
                  LEFT JOIN online_entity_extrafields oee ON x2Extra.Entity = oee.Entity_KeyId
                  $where_clause";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Main query with filtering
    $sql = "SELECT 
                x2Extra.XDfree02_KeyId,
                x2Extra.*,
                e.name as CompanyName,
                e.ContactEmail as EntityEmail,
                e.MobilePhone1 as EntityMobilePhone,
                e.Phone1 as EntityPhone,
                oee.email as CreationUserEmail,
                oee.Name as CreationUserName
            FROM info_xdfree02_extrafields x2Extra
            LEFT JOIN entities e ON e.KeyId = x2Extra.Entity
            LEFT JOIN online_entity_extrafields oee ON x2Extra.Entity = oee.Entity_KeyId
            $where_clause 
            ORDER BY x2Extra.id DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter by remaining time after fetching (since this requires calculation)
    if (!empty($tempo_restante_filter) && !empty($contratos)) {
        $filtered_contratos = [];
        foreach ($contratos as $contrato) {
            $total_minutes = (int)($contrato['TotalHours'] ?? 0);
            $used_minutes = (int)($contrato['SpentHours'] ?? 0);
            $remaining_minutes = $total_minutes - $used_minutes;
            
            $include = false;
            switch ($tempo_restante_filter) {
                case 'disponivel':
                    $include = $remaining_minutes > 0;
                    break;
                case 'excedido':
                    $include = $remaining_minutes < 0;
                    break;
                case 'esgotado':
                    $include = $remaining_minutes <= 0;
                    break;
                case 'critico':
                    $include = $remaining_minutes > 0 && $remaining_minutes <= 60; // Less than 1 hour
                    break;
            }
            
            if ($include) {
                $filtered_contratos[] = $contrato;
            }
        }
        $contratos = $filtered_contratos;
        
        // Recalculate totals for filtered results
        $total_records = count($contratos);
        $total_pages = ceil($total_records / $records_per_page);
        
        // Apply pagination to filtered results
        $contratos = array_slice($contratos, $offset, $records_per_page);
    }
    
    // Get status options for filter
    $sql_status = "SELECT DISTINCT x2Extra.Status 
                   FROM info_xdfree02_extrafields x2Extra
                   LEFT JOIN online_entity_extrafields oee ON x2Extra.Entity = oee.Entity_KeyId
                   WHERE oee.Entity_KeyId = (
                       SELECT Entity_KeyId 
                       FROM online_entity_extrafields 
                       WHERE email = :admin_email
                   ) AND x2Extra.Status IS NOT NULL AND x2Extra.Status != ''";
    $stmt_status = $pdo->prepare($sql_status);
    $stmt_status->execute([':admin_email' => $_SESSION['usuario_email']]);
    $status_options = $stmt_status->fetchAll(PDO::FETCH_COLUMN);
    
    // Debug log
    error_log("=== MEUS CONTRATOS QUERY DEBUG ===");
    error_log("User role: " . (isAdmin() ? 'ADMIN' : 'USER'));
    error_log("User email: " . $_SESSION['usuario_email']);
    error_log("Entity ID: " . $entity);
    error_log("Contracts found: " . count($contratos));
    
    // Debug individual contracts
    foreach ($contratos as $idx => $contrato) {
        error_log("Contract $idx: ID=" . ($contrato['XDfree02_KeyId'] ?? 'NULL') . 
                  ", Entity=" . ($contrato['Entity'] ?? 'NULL') . 
                  ", TotalHours=" . ($contrato['TotalHours'] ?? 'NULL') . 
                  ", Company=" . ($contrato['CompanyName'] ?? 'NULL'));
    }
    
    // If no contracts found for admin, let's debug entity relationships
    if (isAdmin() && empty($contratos)) {
        // Debug: Find admin's entity
        $debug_sql = "SELECT Entity_KeyId, Name FROM online_entity_extrafields WHERE email = ?";
        $debug_stmt = $pdo->prepare($debug_sql);
        $debug_stmt->execute([$_SESSION['usuario_email']]);
        $admin_entity_info = $debug_stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Admin entity info: " . print_r($admin_entity_info, true));

        // Debug: Find all contracts for this entity
        if ($admin_entity_info) {
            $debug_contracts_sql = "SELECT COUNT(*) as total FROM info_xdfree02_extrafields WHERE Entity = ?";
            $debug_contracts_stmt = $pdo->prepare($debug_contracts_sql);
            $debug_contracts_stmt->execute([$admin_entity_info['Entity_KeyId']]);
            $contracts_count = $debug_contracts_stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Total contracts for admin's entity {$admin_entity_info['Entity_KeyId']}: " . $contracts_count['total']);
        }
    }
    
    // Calculate totals - FIXED: Correctly separate active vs. historical contracts
    $tempoTotalComprado = 0;
    $tempoTotalGasto = 0;
    $tempoRestante = 0;
    $contratosAtivos = [];
    $contratosExcedidos = 0;
    $contracts_processed = []; // Array para evitar processar o mesmo contrato duas vezes
    
    foreach ($contratos as $contrato) {
        $contract_id = $contrato['XDfree02_KeyId'] ?? '';
        
        // CORREÇÃO: Evitar processar o mesmo contrato duas vezes nos totais
        if (empty($contract_id) || in_array($contract_id, $contracts_processed)) {
            continue;
        }
        $contracts_processed[] = $contract_id;
        
        $total_minutes = (int)($contrato['TotalHours'] ?? 0);
        $used_minutes = (int)($contrato['SpentHours'] ?? 0);
        $remaining_minutes = $total_minutes - $used_minutes;
        $status = strtolower($contrato['Status'] ?? '');
        
        $contrato['restanteMinutos'] = $remaining_minutes;
        $contrato['excedido'] = $used_minutes > $total_minutes;
        
        // FIXED: Separate calculations for TOTAL values vs. AVAILABLE values
        // Add to total purchased for historical tracking
        $tempoTotalComprado += $total_minutes;
        
        // Add to total spent for historical tracking
        $tempoTotalGasto += $used_minutes;
        
        // FIXED: Only add to remaining time if contract is active (Em Utilização or Por Começar)
        // and has positive remaining time
        if ($remaining_minutes > 0 && 
            ($status === 'em utilização' || $status === 'por começar' || $status === 'regularizado')) {
            $tempoRestante += $remaining_minutes;
            $contratosAtivos[] = $contrato; // Track active contracts
        }
        
        if ($contrato['excedido']) {
            $contratosExcedidos++;
        }
    }
    
    // Debug log for calculations
    error_log("=== TOTAIS FINAIS CORRIGIDOS ===");
    error_log("Contratos únicos processados: " . count($contracts_processed));
    error_log("Tempo Total Comprado (histórico): {$tempoTotalComprado}min");
    error_log("Tempo Total Gasto (histórico): {$tempoTotalGasto}min");
    error_log("Tempo Restante (apenas contratos ativos): {$tempoRestante}min");
    error_log("Contratos Ativos: " . count($contratosAtivos));
    error_log("Contratos Excedidos: {$contratosExcedidos}");
    
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar contratos: " . $e->getMessage();
    error_log("Database error: " . $e->getMessage());
    $contratos = [];
    $status_options = [];
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>
    <?php include('menu.php'); ?>
    <div class="content">
        <div class="container-fluid p-4">
            
            <!-- Cabeçalho -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="mb-3 display-5">Contratos da Empresa</h1>
                    <p class="text-muted">
                        Lista de todos os contratos da sua empresa.
                        <?php echo htmlspecialchars($nome_empresa); ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packsHorasModal">
                        <i class="bi bi-plus-lg me-1"></i> Solicitar Contrato
                    </button>
                </div>
            </div>
            
            <?php if(isset($erro_db)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($erro_db); ?>
            </div>
            <?php endif; ?>
            
            <?php if(isset($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo htmlspecialchars($mensagem_sucesso); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Filtros EXPANDIDOS (adapted from admin) -->
            <div class="bg-white p-3 rounded border mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Pesquisar</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nome, email, telefone, ID contrato (SP-002)...">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">Todos os status</option>
                            <?php foreach ($status_options as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" 
                                    <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="pack_horas" class="form-label">Pack de Horas</label>
                        <select class="form-control" id="pack_horas" name="pack_horas">
                            <option value="">Todos os packs</option>
                            <option value="5" <?php echo $pack_horas_filter === '5' ? 'selected' : ''; ?>>5 Horas</option>
                            <option value="10" <?php echo $pack_horas_filter === '10' ? 'selected' : ''; ?>>10 Horas</option>
                            <option value="20" <?php echo $pack_horas_filter === '20' ? 'selected' : ''; ?>>20 Horas</option>
                            </select>
                    </div>
                    <div class="col-md-2">
                        <label for="tempo_restante" class="form-label">Tempo Restante</label>
                        <select class="form-control" id="tempo_restante" name="tempo_restante">
                            <option value="">Todos</option>
                            <option value="disponivel" <?php echo $tempo_restante_filter === 'disponivel' ? 'selected' : ''; ?>>Com tempo disponível</option>
                            <option value="critico" <?php echo $tempo_restante_filter === 'critico' ? 'selected' : ''; ?>>Crítico (&lt;1h)</option>
                            <option value="esgotado" <?php echo $tempo_restante_filter === 'esgotado' ? 'selected' : ''; ?>>Esgotado</option>
                            <option value="excedido" <?php echo $tempo_restante_filter === 'excedido' ? 'selected' : ''; ?>>Excedido</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data de Início</label>
                        <div class="d-flex gap-1">
                            <input type="date" class="form-control" name="data_inicio_de" 
                                   value="<?php echo htmlspecialchars($data_inicio_de); ?>" 
                                   placeholder="De">
                            <input type="date" class="form-control" name="data_inicio_ate" 
                                   value="<?php echo htmlspecialchars($data_inicio_ate); ?>" 
                                   placeholder="Até">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i> Pesquisar
                            </button>
                            <a href="meus_contratos.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Limpar
                            </a>
                            
                            <!-- Show active filters -->
                            <?php
                            $active_filters = [];
                            if (!empty($search)) $active_filters[] = "Pesquisa: " . htmlspecialchars($search);
                            if (!empty($status_filter)) $active_filters[] = "Status: " . htmlspecialchars($status_filter);
                            if (!empty($pack_horas_filter)) $active_filters[] = "Pack: " . htmlspecialchars($pack_horas_filter) . "h";
                            if (!empty($tempo_restante_filter)) $active_filters[] = "Tempo: " . htmlspecialchars($tempo_restante_filter);
                            if (!empty($data_inicio_de)) $active_filters[] = "De: " . htmlspecialchars($data_inicio_de);
                            if (!empty($data_inicio_ate)) $active_filters[] = "Até: " . htmlspecialchars($data_inicio_ate);
                            
                            if (!empty($active_filters)):
                            ?>
                            <div class="ms-auto">
                                <small class="text-muted">Filtros ativos: <?php echo implode(' | ', $active_filters); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
           
            
            <!-- Tabela de Contratos -->
            <div class="bg-white rounded border">
                <?php if (!empty($contratos)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pack de Horas</th>
                                <th>Cliente</th>
                                <th>Data Início</th>
                                <th>Status</th>
                                <th>Tempo Utilizado</th>
                                <th>Tempo Restante</th>
                                <th>Valor</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contracts_displayed = [];
                            foreach ($contratos as $contrato): 
                                $contract_id = $contrato['XDfree02_KeyId'] ?? '';
                                
                                // Skip if already displayed
                                if (empty($contract_id) || in_array($contract_id, $contracts_displayed)) {
                                    continue;
                                }
                                $contracts_displayed[] = $contract_id;
                                
                                $total_minutes = (int)($contrato['TotalHours'] ?? 0);
                                $used_minutes = (int)($contrato['SpentHours'] ?? 0);
                                $remaining_minutes = $total_minutes - $used_minutes;
                                $total_hours = $total_minutes / 60;
                                $percentage = $total_minutes > 0 ? round(($used_minutes / $total_minutes) * 100) : 0;
                                $excedido = $used_minutes > $total_minutes;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold">
                                        <?php 
                                        $display_hours = floor($total_hours);
                                        $display_minutes = $total_minutes % 60;
                                        echo $display_hours . "h";
                                        if ($display_minutes > 0) {
                                            echo " " . $display_minutes . "min";
                                        }
                                        ?>
                                    </div>
                                    <small class="text-muted">ID: <?php echo htmlspecialchars($contract_id); ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($contrato['CompanyName'] ?? 'N/A'); ?></div>
                                    <?php if (!empty($contrato['EntityEmail'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($contrato['EntityEmail']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $start_date = $contrato['StartDate'] ?? '';
                                    if (!empty($start_date) && $start_date !== '0000-00-00 00:00:00') {
                                        echo date('d/m/Y', strtotime($start_date));
                                    } else {
                                        echo '<span class="text-muted">A definir</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $status = $contrato['Status'] ?? '';
                                    $status_class = '';
                                    switch(strtolower($status)) {
                                        case 'em utilização': $status_class = 'bg-success'; break;
                                        case 'por começar': $status_class = 'bg-warning'; break;
                                        case 'concluido': $status_class = 'bg-info'; break;
                                        case 'excedido': $status_class = 'bg-danger'; break;
                                        case 'regularizado': $status_class = 'bg-primary'; break; // New status
                                        default: $status_class = 'bg-secondary';
                                    }
                                    if ($excedido && $status !== 'Concluído' && $status !== 'Regularizado') {
                                        $status_class = 'bg-danger';
                                        $status = 'Excedido';
                                    }
                                    echo !empty($status) ? "<span class='badge $status_class'>" . htmlspecialchars($status) . "</span>" : '<span class="badge bg-secondary">N/A</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($used_minutes > 0) {
                                        $used_h = floor($used_minutes / 60);
                                        $used_m = $used_minutes % 60;
                                        echo "<span class='fw-bold'>{$used_h}h {$used_m}min</span>";
                                        echo "<br><small class='text-muted'>$percentage% utilizado</small>";
                                    } else {
                                        echo '<span class="text-muted">0h</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($excedido) {
                                        $remaining_h = floor(abs($remaining_minutes) / 60);
                                        $remaining_m = abs($remaining_minutes) % 60;
                                        echo "<span class='fw-bold text-danger'>Excedido</span>";
                                        echo "<br><small class='text-danger'>{$remaining_h}h {$remaining_m}min em excesso</small>";
                                    } elseif ($remaining_minutes > 0) {
                                        $remaining_h = floor($remaining_minutes / 60);
                                        $remaining_m = $remaining_minutes % 60;
                                        echo "<span class='fw-bold text-success'>{$remaining_h}h {$remaining_m}min</span>";
                                        echo "<br><small class='text-muted'>Disponível</small>";
                                    } else {
                                        echo '<span class="text-muted">0h</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="fw-bold">€<?php echo number_format($contrato['TotalAmount'] ?? 0, 2, ',', '.'); ?></div>
                                    <small class="text-muted">
                                        €<?php echo $total_hours > 0 ? number_format(($contrato['TotalAmount'] ?? 0) / $total_hours, 2, ',', '.') : '0,00'; ?>/hora
                                    </small>
                                </td>
                                <td>
                                    <a href="detalhes_contrato.php?id=<?php echo htmlspecialchars($contract_id); ?>" class="btn btn-outline-primary btn-sm" title="Ver detalhes">
                                        <i class="bi bi-eye me-1"></i> Detalhes
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <div class="text-muted">
                        Mostrando <?php echo number_format($offset + 1); ?> a <?php echo number_format(min($offset + $records_per_page, $total_records)); ?> 
                        de <?php echo number_format($total_records); ?> registros
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Anterior</a>
                            </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Próximo</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-text text-muted" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 text-muted">Nenhum contrato encontrado</h5>
                    <p class="text-muted">
                        <?php if (!empty($active_filters)): ?>
                            Tente ajustar os filtros de pesquisa.
                        <?php else: ?>
                            Não há contratos para a sua empresa.
                        <?php endif; ?>
                    </p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packsHorasModal">
                        <i class="bi bi-plus-lg me-1"></i> Solicitar Primeiro Pack de Horas
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
         <!-- Summary Cards - só mostrar se não há filtros ativos -->
            <?php if (empty($active_filters) && !empty($contratos)): ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php 
                                $total_h = floor($tempoTotalComprado / 60);
                                $total_m = $tempoTotalComprado % 60;
                                echo "{$total_h}h";
                                echo $total_m > 0 ? " {$total_m}min" : "";
                                ?>
                            </h5>
                            <p class="card-text">Total Comprado</p>
                            <?php if (isset($_GET['debug'])): ?>
                            <small>Raw: <?php echo $tempoTotalComprado; ?>min</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php 
                                $used_h = floor($tempoTotalGasto / 60);
                                $used_m = $tempoTotalGasto % 60;
                                echo "{$used_h}h";
                                echo $used_m > 0 ? " {$used_m}min" : "";
                                ?>
                            </h5>
                            <p class="card-text">Total Utilizado</p>
                            <?php if (isset($_GET['debug'])): ?>
                            <small>Raw: <?php echo $tempoTotalGasto; ?>min</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php 
                                // FIXED: Display remaining time from active contracts only
                                $remaining_h = floor($tempoRestante / 60);
                                $remaining_m = $tempoRestante % 60;
                                echo "{$remaining_h}h";
                                echo $remaining_m > 0 ? " {$remaining_m}min" : "";
                                ?>
                            </h5>
                            <p class="card-text">Tempo Restante</p>
                            <?php if (isset($_GET['debug'])): ?>
                            <!-- Added more detailed debug info -->
                            <small>Raw: <?php echo $tempoRestante; ?>min (contratos ativos apenas)</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
    </div>
    
    <!-- Modal para Packs de Horas - SEMPRE DISPONÍVEL para administradores -->
    <div class="modal fade" id="packsHorasModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-clock-history me-2"></i>Solicitar Pack de Horas
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="pack-card" data-pack="5">
                                    <div class="pack-hours">5 Horas</div>
                                    <div class="pack-price">€175</div>
                                    <div class="text-muted">Sem desconto</div>
                                    <input type="radio" name="pack_selecionado" value="5" hidden>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="pack-card" data-pack="10">
                                    <div class="pack-hours">10 Horas</div>
                                    <div class="pack-discount">10% DESCONTO</div>
                                    <div class="pack-price">€315</div>
                                    <div class="pack-original-price">De €350</div>
                                    <input type="radio" name="pack_selecionado" value="10" hidden>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="pack-card" data-pack="20">
                                    <div class="pack-hours">20 Horas</div>
                                    <div class="pack-discount">20% DESCONTO</div>
                                    <div class="pack-price">€560</div>
                                    <div class="pack-original-price">De €700</div>
                                    <input type="radio" name="pack_selecionado" value="20" hidden>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="data_inicio" class="form-label">
                                    Data de Início Desejada
                                </label>
                                <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                                <small class="text-muted">Se não especificar, assumiremos início imediato.</small>
                            </div>
                        </div>
                        
                        <input type="hidden" name="empresa" value="<?php echo htmlspecialchars($nome_empresa); ?>">
                        <input type="hidden" name="preco" id="preco_hidden">
                        <input type="hidden" name="desconto" id="desconto_hidden">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="solicitar_pack" class="btn btn-primary" id="confirmarBtn" disabled>
                            <i class="bi bi-check-lg me-1"></i>Confirmar Solicitação
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
        :root {
            --bs-primary: #529ebe;
            --bs-primary-rgb: 82, 158, 190;
        }
        
        .btn-primary, .btn-outline-primary {
            --bs-btn-color: #529ebe;
            --bs-btn-border-color: #529ebe;
        }
        .btn-primary {
            background-color: #e7f3ff;
            border-color: #529ebe;
        }

        .btn-primary:hover {
            background-color: #4a8ba8;
            border-color: #4a8ba8;
        }
        
        .btn-outline-primary:hover {
            background-color: #529ebe;
            border-color: #529ebe;
        }
        
        .text-primary {
            color: #529ebe !important;
        }
        
        .badge.bg-info {
            background-color: #529ebe !important;
        }
        
        .pack-card {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .pack-card:hover {
            border-color: #529ebe;
            box-shadow: 0 4px 12px rgba(82, 158, 190, 0.15);
        }
        
        .pack-card.selected {
            border-color: #529ebe;
            background-color: #e7f3ff;
        }
        
        .pack-hours {
            font-size: 2rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 0.5rem;
        }
        
        .pack-price {
            font-size: 1.5rem;
            font-weight: 600;
            color: #529ebe;
            margin-bottom: 0.5rem;
        }
        
        .pack-discount {
            background-color: #198754;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.5rem;
        }
        
        .pack-original-price {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestão da seleção de packs
        document.querySelectorAll('.pack-card').forEach(function(card) {
            card.addEventListener('click', function() {
                // Remove seleção anterior
                document.querySelectorAll('.pack-card').forEach(function(c) {
                    c.classList.remove('selected');
                });
                
                // Adiciona seleção atual
                this.classList.add('selected');
                
                // Marca o radio button
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Atualiza campos ocultos
                const pack = this.dataset.pack;
                const precos = {
                    '5': {preco: 175, desconto: 0},
                    '10': {preco: 315, desconto: 10},
                    '20': {preco: 560, desconto: 20}
                };
                
                document.getElementById('preco_hidden').value = precos[pack].preco;
                document.getElementById('desconto_hidden').value = precos[pack].desconto;
                
                // Habilita botão confirmar
                document.getElementById('confirmarBtn').disabled = false;
            });
        });
        
        // Abrir preview se solicitação foi enviada
        <?php if (isset($mostrar_preview) && $mostrar_preview && isset($preview_data)): ?>
        setTimeout(function() {
            const previewUrl = 'preview-email-contratos.php?' + 
                'pack=<?php echo urlencode($preview_data['pack']); ?>' +
                '&empresa=<?php echo urlencode($preview_data['empresa']); ?>' +
                '&entity=<?php echo urlencode($preview_data['entity']); ?>' +
                '&data_inicio=<?php echo urlencode($preview_data['data_inicio']); ?>';
            
            window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
        }, 1500);
        <?php endif; ?>
    </script>
</body>
</html>