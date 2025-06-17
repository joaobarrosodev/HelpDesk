<?php
session_start();
include('conflogin.php');

// Verificar se foi passado um pack para preview
$pack_test = $_GET['pack'] ?? '10'; // Default 10 horas
$empresa_test = $_GET['empresa'] ?? 'Empresa Teste Lda';
$entity_test = $_GET['entity'] ?? '12345';
$data_inicio_test = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('+7 days'));

// Configurar preços para preview
$precos_test = [
    '5' => ['preco_original' => 175, 'preco_final' => 175, 'desconto' => 0],
    '10' => ['preco_original' => 350, 'preco_final' => 315, 'desconto' => 10],
    '20' => ['preco_original' => 700, 'preco_final' => 560, 'desconto' => 20]
];

if (!isset($precos_test[$pack_test])) {
    $pack_test = '10'; // Fallback
}

$preco_original_test = $precos_test[$pack_test]['preco_original'];
$preco_final_test = $precos_test[$pack_test]['preco_final'];
$desconto_pct_test = $precos_test[$pack_test]['desconto'];
$total_minutos_test = $pack_test * 60;

// Gerar o conteúdo do email igual ao que será enviado
$subject_preview = "NOVA SOLICITAÇÃO - Pack de $pack_test Horas - $empresa_test";
$message_preview = "
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
                        <div class='info-value'><strong>$empresa_test</strong></div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>ID do Cliente:</div>
                        <div class='info-value'><span class='client-id'>$entity_test</span></div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Data da Solicitação:</div>
                        <div class='info-value'>" . date('d \d\e F \d\e Y \à\s H:i') . "</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Data de Início Pretendida:</div>
                        <div class='info-value'><strong>" . date('d \d\e F \d\e Y', strtotime($data_inicio_test)) . "</strong></div>
                    </div>
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
                        <td><strong>$pack_test horas</strong> ($total_minutos_test minutos)</td>
                    </tr>
                    <tr>
                        <td>Preço de Tabela</td>
                        <td>€" . number_format($preco_original_test, 2, ',', '.') . "</td>
                    </tr>" . 
                    ($desconto_pct_test > 0 ? "
                    <tr>
                        <td>Desconto Aplicado</td>
                        <td class='savings'>$desconto_pct_test%</td>
                    </tr>
                    <tr>
                        <td>Valor da Poupança</td>
                        <td class='savings'>€" . number_format($preco_original_test - $preco_final_test, 2, ',', '.') . "</td>
                    </tr>" : "") . "
                    <tr style='border-top: 2px solid #2c3e50;'>
                        <td><strong>Valor Final</strong></td>
                        <td><strong>€" . number_format($preco_final_test, 2, ',', '.') . "</strong></td>
                    </tr>
                </table>
            </div>
            
            <div class='highlight-box'>
                <h3>€" . number_format($preco_final_test, 2, ',', '.') . "</h3>
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
                        <div class='info-value'>$total_minutos_test minutos</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Custo por Hora:</div>
                        <div class='info-value'>€" . number_format($preco_final_test / $pack_test, 2, ',', '.') . "</div>
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
?>

<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Email - Solicitação Pack de Horas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .preview-container { 
            background-color: #f8f9fa; 
            padding: 30px; 
            min-height: 100vh; 
        }
        .email-frame { 
            border: 3px solid #dee2e6; 
            border-radius: 10px; 
            background: white; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }
        .controls { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            margin-bottom: 30px; 
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="container-fluid">
            
            <!-- Controles de Preview -->
            <div class="controls">
                <?php if (isset($_GET['auto']) || (isset($_GET['pack']) && isset($_GET['empresa']) && isset($_GET['entity']))): ?>
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Preview Automático:</strong> Esta janela foi aberta automaticamente após a sua solicitação de pack de horas. 
                    Este é exatamente o email que foi enviado para web@info-exe.com.
                </div>
                <?php endif; ?>
                
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-0">
                            <i class="bi bi-envelope-open me-2"></i>
                            Preview do Email - Pack de Horas
                        </h2>
                        <p class="mb-0 text-muted">Visualização exata do email que será enviado para web@info-exe.com</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="meus_contratos.php" class="btn btn-secondary me-2" target="_parent">
                            <i class="bi bi-arrow-left me-1"></i>Voltar
                        </a>
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="bi bi-printer me-1"></i>Imprimir
                        </button>
                    </div>
                </div>
                
                <!-- Opções de Preview -->
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="btn-group" role="group">
                            <a href="?pack=5&empresa=<?php echo urlencode($empresa_test); ?>&entity=<?php echo $entity_test; ?>&data_inicio=<?php echo $data_inicio_test; ?>" 
                               class="btn btn-sm <?php echo $pack_test == '5' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                5 Horas
                            </a>
                            <a href="?pack=10&empresa=<?php echo urlencode($empresa_test); ?>&entity=<?php echo $entity_test; ?>&data_inicio=<?php echo $data_inicio_test; ?>" 
                               class="btn btn-sm <?php echo $pack_test == '10' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                10 Horas
                            </a>
                            <a href="?pack=20&empresa=<?php echo urlencode($empresa_test); ?>&entity=<?php echo $entity_test; ?>&data_inicio=<?php echo $data_inicio_test; ?>" 
                               class="btn btn-sm <?php echo $pack_test == '20' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                20 Horas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informações do Email -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">📧 Detalhes do Email</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Para:</strong> web@info-exe.com<br>
                                    <strong>De:</strong> HelpDesk Info-Exe &lt;noreply@info-exe.com&gt;<br>
                                    <strong>Assunto:</strong> <?php echo htmlspecialchars($subject_preview); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Prioridade:</strong> Alta 🚨<br>
                                    <strong>Formato:</strong> HTML<br>
                                    <strong>Tamanho:</strong> ~<?php echo round(strlen($message_preview) / 1024, 1); ?>KB
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview do Email -->
            <div class="row">
                <div class="col-12">
                    <div class="email-frame">
                        <?php echo $message_preview; ?>
                    </div>
                </div>
            </div>
            
            <!-- Informações Técnicas -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">🔧 Informações Técnicas</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Pack:</strong> <?php echo $pack_test; ?> horas<br>
                                    <strong>Minutos:</strong> <?php echo $total_minutos_test; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Preço Original:</strong> €<?php echo number_format($preco_original_test, 2, ',', '.'); ?><br>
                                    <strong>Preço Final:</strong> €<?php echo number_format($preco_final_test, 2, ',', '.'); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Desconto:</strong> <?php echo $desconto_pct_test; ?>%<br>
                                    <strong>Poupança:</strong> €<?php echo number_format($preco_original_test - $preco_final_test, 2, ',', '.'); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Preço/Hora:</strong> €<?php echo number_format($preco_final_test / $pack_test, 2, ',', '.'); ?><br>
                                    <strong>Preço/Min:</strong> €<?php echo number_format($preco_final_test / $total_minutos_test, 2, ',', '.'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</body>
</html>
        </div>
    </div>
</body>
</html>
