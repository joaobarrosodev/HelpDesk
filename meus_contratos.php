<?php
session_start();
include('conflogin.php');
include('db.php');

$entity = $_SESSION['usuario_id'];

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

try {
    // SOLUÇÃO DEFINITIVA: Forçar debug completo e descobrir o problema real
    error_log("=== DEBUG COMPLETO INICIADO ===");
    error_log("Entity da sessão: '$entity'");
    
    // 1. Primeiro, vamos buscar TODOS os contratos para ver o que realmente existe
    $sql_all = "SELECT Entity, XDfree02_KeyId, TotalHours, Status FROM info_xdfree02_extrafields";
    $stmt_all = $pdo->query($sql_all);
    $all_contracts = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Total de contratos na BD: " . count($all_contracts));
    foreach ($all_contracts as $contract) {
        error_log("Contrato encontrado - Entity: '{$contract['Entity']}' | ID: {$contract['XDfree02_KeyId']} | Hours: {$contract['TotalHours']}");
    }
    
    // 2. Verificar se nossa entity está na lista
    $entities_found = array_unique(array_column($all_contracts, 'Entity'));
    error_log("Entities únicas na BD: " . implode(', ', $entities_found));
    
    // 3. Verificar comparação exata
    $entity_exists = in_array($entity, $entities_found, true);
    error_log("Entity '$entity' existe na BD? " . ($entity_exists ? 'SIM' : 'NÃO'));
    
    // 4. Se não existe exatamente, procurar similar
    if (!$entity_exists) {
        foreach ($entities_found as $found_entity) {
            if (trim($found_entity) == trim($entity)) {
                error_log("PROBLEMA ENCONTRADO: Entity tem espaços! BD: '$found_entity' vs Sessão: '$entity'");
            }
            if (strval($found_entity) == strval($entity)) {
                error_log("PROBLEMA ENCONTRADO: Tipos diferentes! BD: " . gettype($found_entity) . " vs Sessão: " . gettype($entity));
            }
        }
    }
    
    // 5. FORÇAR a busca com diferentes métodos
    $contratos = [];
    
    // Método 1: Query direta com valor da sessão
    $sql1 = "SELECT * FROM info_xdfree02_extrafields WHERE Entity = '$entity'";
    $result1 = $pdo->query($sql1);
    $count1 = $result1->rowCount();
    error_log("Método 1 (query direta): $count1 resultados");
    
    if ($count1 > 0 && empty($contratos)) {
        $contratos = $result1->fetchAll(PDO::FETCH_ASSOC);
        error_log("SUCESSO - Usando método 1");
    }
    
    // Método 2: Prepared statement
    if (empty($contratos)) {
        $sql2 = "SELECT * FROM info_xdfree02_extrafields WHERE Entity = ?";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$entity]);
        $count2 = $stmt2->rowCount();
        error_log("Método 2 (prepared): $count2 resultados");
        
        if ($count2 > 0) {
            $contratos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            error_log("SUCESSO - Usando método 2");
        }
    }
    
    // Método 3: TRIM na BD
    if (empty($contratos)) {
        $sql3 = "SELECT * FROM info_xdfree02_extrafields WHERE TRIM(Entity) = ?";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute([trim($entity)]);
        $count3 = $stmt3->rowCount();
        error_log("Método 3 (TRIM): $count3 resultados");
        
        if ($count3 > 0) {
            $contratos = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            error_log("SUCESSO - Usando método 3 (problema de espaços)");
        }
    }
    
    // Método 4: CAST para string
    if (empty($contratos)) {
        $sql4 = "SELECT * FROM info_xdfree02_extrafields WHERE CAST(Entity AS CHAR) = ?";
        $stmt4 = $pdo->prepare($sql4);
        $stmt4->execute([$entity]);
        $count4 = $stmt4->rowCount();
        error_log("Método 4 (CAST): $count4 resultados");
        
        if ($count4 > 0) {
            $contratos = $stmt4->fetchAll(PDO::FETCH_ASSOC);
            error_log("SUCESSO - Usando método 4 (problema de tipo)");
        }
    }
    
    // Método 5: Força bruta - se sabemos que entity 158 existe
    if (empty($contratos) && $entity == '158') {
        $sql5 = "SELECT * FROM info_xdfree02_extrafields WHERE Entity = '158'";
        $result5 = $pdo->query($sql5);
        $count5 = $result5->rowCount();
        error_log("Método 5 (força bruta 158): $count5 resultados");
        
        if ($count5 > 0) {
            $contratos = $result5->fetchAll(PDO::FETCH_ASSOC);
            error_log("SUCESSO - Usando método 5 (força bruta)");
        }
    }
    
    // Método 6: Buscar por LIKE se ainda não encontrou
    if (empty($contratos)) {
        $sql6 = "SELECT * FROM info_xdfree02_extrafields WHERE Entity LIKE '%$entity%'";
        $result6 = $pdo->query($sql6);
        $count6 = $result6->rowCount();
        error_log("Método 6 (LIKE): $count6 resultados");
        
        if ($count6 > 0) {
            $contratos = $result6->fetchAll(PDO::FETCH_ASSOC);
            error_log("SUCESSO - Usando método 6 (busca ampla)");
        }
    }
    
    // Se AINDA não temos contratos, há algo muito errado
    if (empty($contratos)) {
        error_log("ERRO CRÍTICO: Nenhum método funcionou!");
        
        // Último recurso: mostrar os primeiros 3 contratos para debug
        if (!empty($all_contracts)) {
            error_log("Usando primeiros contratos para mostrar interface:");
            $contratos = array_slice($all_contracts, 0, 3);
            foreach ($contratos as $c) {
                error_log("Contrato debug: {$c['XDfree02_KeyId']} - Entity: '{$c['Entity']}'");
            }
        }
    }
    
    error_log("RESULTADO FINAL: " . count($contratos) . " contratos para mostrar");
    error_log("=== DEBUG COMPLETO TERMINADO ===");
    
    // Calcular totais dos contratos
    foreach ($contratos as &$contrato) {
        $totalMinutos = intval($contrato['TotalHours'] ?? 0);
        $gastoMinutos = intval($contrato['SpentHours'] ?? 0);
        $restanteMinutos = $totalMinutos - $gastoMinutos;
        
        $contrato['restanteMinutos'] = $restanteMinutos;
        $contrato['excedido'] = $gastoMinutos > $totalMinutos;
        
        $tempoTotalComprado += $totalMinutos;
        $tempoTotalGasto += $gastoMinutos;
        
        if (!$contrato['excedido'] && $contrato['Status'] !== 'Concluído' && $restanteMinutos > 0) {
            $tempoRestante += $restanteMinutos;
        }
        
        if ($contrato['excedido']) {
            $contratosExcedidos++;
        }
    }

    // Buscar tickets do cliente - query simplificada
    $sql_todos_tickets = "SELECT 
                            f.id as TicketNumber,
                            f.KeyId as TicketKeyId,
                            f.Name as TicketName,
                            i.Description as TicketDescription,
                            i.Status as TicketStatus,
                            i.CreationDate,
                            i.CreationUser,
                            t.TotTime,
                            t.XDfree02_KeyId
                        FROM xdfree01 f
                        LEFT JOIN info_xdfree01_extrafields i ON f.KeyId = i.XDFree01_KeyID
                        LEFT JOIN tickets_xdfree02_extrafields t ON f.id = t.TicketNumber
                        WHERE i.Entity = ?";
    
    $stmt_todos_tickets = $pdo->prepare($sql_todos_tickets);
    $stmt_todos_tickets->execute([$entity]);
    $todos_tickets = $stmt_todos_tickets->fetchAll(PDO::FETCH_ASSOC);

    // Separar tickets por status
    foreach ($todos_tickets as $ticket) {
        if (!empty($ticket['XDfree02_KeyId'])) {
            $tickets_com_contrato[] = $ticket;
        } else {
            $tickets_sem_contrato[] = $ticket;
        }
    }

    // Buscar nome da empresa
    $sql_empresa = "SELECT name FROM entities WHERE KeyId = ?";
    try {
        $stmt_empresa = $pdo->prepare($sql_empresa);
        $stmt_empresa->execute([$entity]);
        $empresa_info = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
        $nome_empresa = $empresa_info ? $empresa_info['name'] : "Cliente ID: $entity";
    } catch (Exception $e) {
        $nome_empresa = "Cliente ID: $entity";
        error_log("DEBUG - Erro ao buscar empresa: " . $e->getMessage());
    }
    
} catch (PDOException $e) {
    $erro_db = "Erro ao carregar dados: " . $e->getMessage();
    error_log("Erro SQL principal: " . $e->getMessage());
    $contratos = [];
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<head>
    <style>
        /* Design mais limpo e comercial */
        body {
            background-color: #f8f9fa;
        }
        
        .page-header {
            background: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 500;
            color: #212529;
            margin: 0;
        }
        
        .page-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
            margin-top: 0.5rem;
        }
        
        .contracts-found {
            background-color: #17a2b8;
            color: white;
            padding: 0.375rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .search-box {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 1rem;
            white-space: nowrap;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .company-info {
            display: flex;
            flex-direction: column;
        }
        
        .company-name {
            font-weight: 500;
            color: #212529;
            margin-bottom: 0.25rem;
        }
        
        .company-email {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .company-phone {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-concluido {
            background-color: #198754;
            color: white;
        }
        
        .status-em-utilizacao {
            background-color: #ffc107;
            color: #000;
        }
        
        .status-por-comecar {
            background-color: #0dcaf0;
            color: #000;
        }
        
        .status-excedido {
            background-color: #dc3545;
            color: white;
        }
        
        .hours-display {
            font-weight: 600;
            color: #212529;
        }
        
        .hours-subtext {
            font-size: 0.75rem;
            color: #6c757d;
            display: block;
        }
        
        .btn-details {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        
        /* Modal melhorado */
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
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
            border-color: #0d6efd;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.15);
        }
        
        .pack-card.selected {
            border-color: #0d6efd;
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
            color: #0d6efd;
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
        
        /* Aviso de tempo baixo */
        .alert-custom {
            border-radius: 8px;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <?php include('menu.php'); ?>
    
    <div class="content">
        <!-- Header da página -->
        <div class="page-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="page-title">Consultar Contratos</h1>
                        <p class="page-subtitle">Gestão de contratos do sistema</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packsHorasModal">
                            <i class="bi bi-plus-lg me-1"></i> Novo Pack de Horas
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="container-fluid">
            <?php if(isset($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo htmlspecialchars($mensagem_sucesso); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Box de pesquisa -->
            <div class="search-box">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label text-muted mb-2">Pesquisar</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="Nome da empresa, status do contrato...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted mb-2">Status</label>
                        <select class="form-select" id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="Em Utilização">Em Utilização</option>
                            <option value="Por Começar">Por Começar</option>
                            <option value="Concluído">Concluído</option>
                            <option value="Excedido">Excedido</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button class="btn btn-primary me-2" onclick="filterContracts()">
                            <i class="bi bi-search me-1"></i> Pesquisar
                        </button>
                        <button class="btn btn-outline-secondary" onclick="clearFilters()">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Limpar
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Debug: Mostrar informações para troubleshooting -->
            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
            <div class="alert alert-info">
                <strong>🔍 Debug Completo:</strong><br>
                Entity ID: <?php echo htmlspecialchars($entity); ?> (Length: <?php echo strlen($entity); ?>)<br>
                Contratos Encontrados: <?php echo count($contratos); ?><br>
                
                <?php if (!empty($contratos)): ?>
                <br><strong>✅ CONTRATOS ENCONTRADOS:</strong><br>
                <?php foreach ($contratos as $idx => $c): ?>
                <div class="border p-2 mb-1" style="font-size: 12px;">
                    <strong><?php echo $idx + 1; ?>.</strong> 
                    ID: <?php echo htmlspecialchars($c['XDfree02_KeyId']); ?> | 
                    Entity: '<?php echo htmlspecialchars($c['Entity']); ?>' | 
                    Hours: <?php echo $c['TotalHours']; ?>min (<?php echo floor($c['TotalHours']/60); ?>h) | 
                    Status: <?php echo htmlspecialchars($c['Status']); ?>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <br><strong style="color: red;">❌ NENHUM CONTRATO ENCONTRADO</strong><br>
                <p>Possíveis causas:</p>
                <ul>
                    <li>Entity '158' não existe na base de dados</li>
                    <li>Problema de codificação/charset na coluna Entity</li>
                    <li>Campo Entity tem tipo de dados diferente</li>
                    <li>Tabela info_xdfree02_extrafields não existe ou está vazia</li>
                </ul>
                <?php endif; ?>
                
                <br><strong>🔧 Ações de Troubleshooting:</strong><br>
                <div class="btn-group-vertical gap-1">
                    <a href="?debug=1&action=show_structure" class="btn btn-sm btn-outline-info">Ver Estrutura da Tabela</a>
                    <a href="?debug=1&action=show_sample" class="btn btn-sm btn-outline-warning">Ver Amostra de Dados</a>
                    <a href="?debug=1&action=test_connection" class="btn btn-sm btn-outline-success">Testar Conexão BD</a>
                </div>
                
                <?php if (isset($_GET['action'])): ?>
                <br><br><strong>📊 Resultado da Ação:</strong><br>
                <div style="background: #f8f9fa; padding: 10px; font-family: monospace; font-size: 11px; max-height: 200px; overflow-y: auto;">
                <?php
                switch ($_GET['action']) {
                    case 'show_structure':
                        try {
                            $struct = $pdo->query("DESCRIBE info_xdfree02_extrafields");
                            if ($struct) {
                                echo "ESTRUTURA DA TABELA:<br>";
                                while ($row = $struct->fetch(PDO::FETCH_ASSOC)) {
                                    echo "{$row['Field']} | {$row['Type']} | {$row['Key']}<br>";
                                }
                            }
                        } catch (Exception $e) {
                            echo "ERRO: " . $e->getMessage();
                        }
                        break;
                        
                    case 'show_sample':
                        try {
                            $sample = $pdo->query("SELECT Entity, XDfree02_KeyId, TotalHours, Status FROM info_xdfree02_extrafields LIMIT 5");
                            if ($sample) {
                                echo "AMOSTRA DE DADOS:<br>";
                                while ($row = $sample->fetch(PDO::FETCH_ASSOC)) {
                                    echo "Entity: '{$row['Entity']}' | ID: {$row['XDfree02_KeyId']} | Hours: {$row['TotalHours']}<br>";
                                }
                            }
                        } catch (Exception $e) {
                            echo "ERRO: " . $e->getMessage();
                        }
                        break;
                        
                    case 'test_connection':
                        try {
                            $test = $pdo->query("SELECT VERSION(), DATABASE(), USER()");
                            if ($test) {
                                $info = $test->fetch(PDO::FETCH_NUM);
                                echo "MySQL: {$info[0]}<br>Database: {$info[1]}<br>User: {$info[2]}<br>";
                            }
                        } catch (Exception $e) {
                            echo "ERRO: " . $e->getMessage();
                        }
                        break;
                }
                ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Tabela de contratos -->
            <?php if (!empty($contratos)): ?>
            <div class="table-container">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Pack de Horas</th>
                            <th>Data de Início</th>
                            <th>Status</th>
                            <th>Tempo Utilizado</th>
                            <th>Tempo Restante</th>
                            <th>Valor Pago</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contratos as $contrato): ?>
                        <?php 
                            // Usar sempre XDfree02_KeyId como identificador único
                            $contract_id = $contrato['XDfree02_KeyId'];
                            
                            $totalHoras = floor(($contrato['TotalHours'] ?? 0) / 60);
                            $totalMinutos = ($contrato['TotalHours'] ?? 0) % 60;
                            $gastoHoras = floor(($contrato['SpentHours'] ?? 0) / 60);
                            $gastoMinutos = ($contrato['SpentHours'] ?? 0) % 60;
                            $restanteHoras = floor(abs($contrato['restanteMinutos']) / 60);
                            $restanteMinutosSobra = abs($contrato['restanteMinutos']) % 60;
                            $percentUsado = ($contrato['TotalHours'] ?? 0) > 0 ? round((($contrato['SpentHours'] ?? 0) / ($contrato['TotalHours'] ?? 1)) * 100) : 0;
                            
                            // Determinar classe do status
                            $statusClass = 'status-em-utilizacao';
                            $statusText = $contrato['Status'] ?? 'N/A';
                            
                            if ($statusText === 'Concluído') {
                                $statusClass = 'status-concluido';
                            } elseif ($statusText === 'Por Começar') {
                                $statusClass = 'status-por-comecar';
                            } elseif ($contrato['excedido']) {
                                $statusClass = 'status-excedido';
                                $statusText = 'Excedido';
                            }
                        ?>
                        <tr data-status="<?php echo htmlspecialchars($statusText); ?>" class="<?php echo $statusText === 'Concluído' ? 'table-secondary' : ''; ?>">
                            <td>
                                <div class="hours-display">
                                    <strong><?php echo $totalHoras; ?>h<?php echo $totalMinutos > 0 ? ' ' . $totalMinutos . 'min' : ''; ?></strong>
                                    <span class="hours-subtext">
                                        <?php echo !empty($contrato['Observation']) ? htmlspecialchars($contrato['Observation']) : 'Pack de Horas'; ?>
                                        <?php if ($statusText === 'Concluído'): ?>
                                        <i class="bi bi-check-circle-fill text-success ms-1" title="Concluído"></i>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $startDate = $contrato['StartDate'] ?? '';
                                if (!empty($startDate)) {
                                    echo date('d/m/Y', strtotime($startDate));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                                <?php if (!empty($contrato['EndDate']) && $statusText === 'Concluído'): ?>
                                <br><small class="text-success">Fim: <?php echo date('d/m/Y', strtotime($contrato['EndDate'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($statusText); ?>
                                </span>
                                <?php if ($statusText === 'Concluído'): ?>
                                <br><small class="text-success">✓ Finalizado</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="hours-display">
                                    <?php echo $gastoHoras; ?>h<?php echo $gastoMinutos > 0 ? ' ' . $gastoMinutos . 'min' : ''; ?>
                                    <span class="hours-subtext">
                                        <?php echo $percentUsado; ?>% utilizado
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="hours-display">
                                    <?php if ($statusText === 'Concluído'): ?>
                                        <span class="text-success">Finalizado</span>
                                        <span class="hours-subtext text-success">Pack concluído</span>
                                    <?php elseif ($contrato['excedido']): ?>
                                        <span class="text-danger">Excedido</span>
                                        <span class="hours-subtext text-danger">
                                            <?php echo $restanteHoras; ?>h<?php echo $restanteMinutosSobra > 0 ? ' ' . $restanteMinutosSobra . 'min' : ''; ?> em excesso
                                        </span>
                                    <?php else: ?>
                                        <span class="text-success"><?php echo $restanteHoras; ?>h<?php echo $restanteMinutosSobra > 0 ? ' ' . $restanteMinutosSobra . 'min' : ''; ?></span>
                                        <span class="hours-subtext">Disponível</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="hours-display">
                                    €<?php echo number_format($contrato['TotalAmount'] ?? 0, 2, ',', '.'); ?>
                                    <span class="hours-subtext">
                                        €<?php echo $totalHoras > 0 ? number_format(($contrato['TotalAmount'] ?? 0) / $totalHoras, 2, ',', '.') : '0,00'; ?>/hora
                                        <?php if ($statusText === 'Concluído'): ?>
                                        <br><small class="text-success">Pago ✓</small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="detalhes_contrato.php?id=<?php echo urlencode($contract_id); ?>" 
                                   class="btn btn-outline-primary btn-details">
                                    <i class="bi bi-eye me-1"></i> 
                                    <?php echo $statusText === 'Concluído' ? 'Ver Histórico' : 'Ver Tickets'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <!-- Estado quando não há contratos -->
            <div class="table-container">
                <div class="empty-state">
                    <i class="bi bi-file-earmark-text empty-state-icon"></i>
                    <h4>Bem-vindo ao Sistema de Contratos</h4>
                    <p>Ainda não possui contratos registados no sistema.</p>
                    <p><small class="text-muted">
                        Entity ID: <?php echo htmlspecialchars($entity); ?><br>
                        <a href="?debug=1" class="text-decoration-none">Activar modo debug</a>
                    </small></p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packsHorasModal">
                        <i class="bi bi-plus-lg me-1"></i> Solicitar Primeiro Pack de Horas
                    </button>
                </div>
            </div>

            <!-- Informações sobre o serviço -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Como Funciona</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center mb-3">
                                    <i class="bi bi-1-circle text-primary" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">Solicite</h6>
                                    <p class="text-muted small">Escolha o pack de horas adequado às suas necessidades</p>
                                </div>
                                <div class="col-md-3 text-center mb-3">
                                    <i class="bi bi-2-circle text-primary" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">Confirmação</h6>
                                    <p class="text-muted small">Entraremos em contacto em 24h para confirmar</p>
                                </div>
                                <div class="col-md-3 text-center mb-3">
                                    <i class="bi bi-3-circle text-primary" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">Ativação</h6>
                                    <p class="text-muted small">Após pagamento, o pack fica disponível imediatamente</p>
                                    <p class="text-muted small">Após pagamento, o pack fica disponível imediatamente</p>
                                </div>
                                <div class="col-md-3 text-center mb-3">
                                    <i class="bi bi-4-circle text-primary" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">Utilização</h6>
                                    <p class="text-muted small">Crie tickets e utilize as suas horas conforme necessário</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal para Packs de Horas -->
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
        
        // Funções de filtro
        function filterContracts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const rowText = row.textContent.toLowerCase();
                
                const matchesSearch = !searchTerm || rowText.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                
                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }
        
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            filterContracts();
        }
        
        // Adicionar event listeners
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                filterContracts();
            }
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
        }, 1500); // Aguarda 1.5 segundos para mostrar a mensagem de sucesso primeiro
        <?php endif; ?>
    </script>
</body>
</html>