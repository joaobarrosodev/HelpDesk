<?php
session_start();
include('conflogin.php');
?>
<!DOCTYPE html>
<html lang="pt-pt">
<?php include('head.php'); ?>
<body>

    <?php include('menu.php'); ?>
    <div class="content content-area">        
        <?php
        include('db.php');

        // Consultar Conta Corrente em Aberto
        $sql = "SELECT * FROM entities WHERE keyid = :keyid";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':keyid', $_SESSION['usuario_id']);
        $stmt->execute();
        $cc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cc) {            echo "Ticket não encontrado.";
            exit;
        }

        echo "<p style='font-weight: bold;'> Informações Gerais da Empresa:</p>";
        $valor = $cc['Balance'];
        $valor_formatado = number_format(abs($valor), 2, ',', '.');
        // Corrigindo a lógica das cores:
        if ($valor_formatado > 0) {
            $cor = 'bg-danger'; // Se houver dívida, fica vermelho
        } else {
            $cor = 'bg-success'; // Se houver crédito, fica verde
        }

        // Formatar o número corretamente, SEM o sinal negativo na exibição
        

        // Consultar número de tickets abertos
        $sql_tickets = "SELECT COUNT(*) FROM info_xdfree01_extrafields WHERE (status = 'Em Análise' OR status = 'Em Resolução' OR status = 'Aguarda Resposta Cliente') AND Entity = :usuario_id";
        $stmt_tickets = $pdo->prepare($sql_tickets);
        $stmt_tickets->bindParam(':usuario_id', $_SESSION['usuario_id']);
        $stmt_tickets->execute();
        $ticket_count = $stmt_tickets->fetchColumn();

        // Fetch data for "Categoria dos Tickets"
        // $sql_categorias = "SELECT categoria, COUNT(*) as count FROM info_xdfree01_extrafields WHERE Entity = :usuario_id GROUP BY categoria";
        // $stmt_categorias = $pdo->prepare($sql_categorias);
        // $stmt_categorias->bindParam(':usuario_id', $_SESSION['usuario_id']);
        // $stmt_categorias->execute();
        // $categorias_data = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
        // $categoria_labels = [];
        // $categoria_counts = [];
        // foreach ($categorias_data as $row) {
        //     $categoria_labels[] = $row['categoria']; // Assuming 'categoria' is the column name
        //     $categoria_counts[] = $row['count'];
        // }
        // Hardcoded data for Categoria dos Tickets
        $categoria_labels = ['E-mail', 'XD', 'Impressoras', 'Office'];
        $categoria_counts = [12, 19, 3, 5];


        // Fetch data for "Prioridade dos Tickets"
        // Assuming 'prioridade' is the column name in info_xdfree01_extrafields
        // $sql_prioridades = "SELECT prioridade, COUNT(*) as count FROM info_xdfree01_extrafields WHERE Entity = :usuario_id GROUP BY prioridade ORDER BY CASE prioridade WHEN 'Baixo' THEN 1 WHEN 'Médio' THEN 2 WHEN 'Alto' THEN 3 ELSE 4 END";
        // $stmt_prioridades = $pdo->prepare($sql_prioridades);
        // $stmt_prioridades->bindParam(':usuario_id', $_SESSION['usuario_id']);
        // $stmt_prioridades->execute();
        // $prioridades_data = $stmt_prioridades->fetchAll(PDO::FETCH_ASSOC);
        // $prioridade_labels = [];
        // $prioridade_counts = [];
        // foreach ($prioridades_data as $row) {
        //     $prioridade_labels[] = $row['prioridade'];
        //     $prioridade_counts[] = $row['count'];
        // }
        // Hardcoded data for Prioridade dos Tickets
        $prioridade_labels = ['Baixo', 'Médio', 'Alto'];
        $prioridade_counts = [71, 22, 7];
        
        // Fetch data for "ISSUE" table (recent 5 tickets for example)
        // Joined with xdfree01 to get user names for reporter and assignee if possible
        // Adjust column names like 'requestedby', 'assignedto', 'creationdate', 'lastupdate' as per your xdfree01 and info_xdfree01_extrafields table structure
        // $sql_issues = "SELECT 
        //                 t.id, 
        //                 t.assunto as summary, 
        //                 COALESCE(assignee_user.Nome, 'N/A') as assignee_name, 
        //                 COALESCE(reporter_user.Nome, 'N/A') as reporter_name, 
        //                 t.status, 
        //                 DATE_FORMAT(t.data_criacao, '%d/%m/%y') as created, 
        //                 DATE_FORMAT(t.data_atualizacao, '%d/%m/%y') as updated,
        //                 t.categoria as category_raw 
        //                FROM info_xdfree01_extrafields t
        //                LEFT JOIN xdfree01 reporter_user ON t.requestedby = reporter_user.keyid -- Assuming requestedby stores user ID
        //                LEFT JOIN xdfree01 assignee_user ON t.assignedto = assignee_user.keyid -- Assuming assignedto stores user ID
        //                WHERE t.Entity = :usuario_id 
        //                ORDER BY t.data_criacao DESC 
        //                LIMIT 5"; // Get 5 most recent tickets
        // $stmt_issues = $pdo->prepare($sql_issues);
        // $stmt_issues->bindParam(':usuario_id', $_SESSION['usuario_id']);
        // $stmt_issues->execute();
        // $issues = $stmt_issues->fetchAll(PDO::FETCH_ASSOC);

        ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="m-0">Bem Vindo, <?php echo htmlspecialchars($_SESSION['Nome']); ?></h4>
                <p class="text-muted m-0">Aqui pode acompanhar todos os seus tickets, ver o estado de cada pedido de suporte, e consultar informações importantes em tempo real. Utilize esta área para monitorizar o progresso das suas solicitações e garantir um acompanhamento eficaz.</p>
            </div>
            <a href="ticket.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Abrir Novo Ticket</a>
        </div>

        <!-- Dashboard Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title">Categoria dos Tickets</h5>
                        <canvas id="categoriaTicketsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title">Prioridade dos Tickets</h5>
                        <canvas id="prioridadeTicketsChart" height="175"></canvas> <!-- Adjusted height for bar chart -->
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title">Avaliação dos Clientes</h5>
                        <p>Respostas Recebidas: <strong>156 Clientes</strong></p>
                        <div class="d-flex align-items-center mb-1">
                            <span class="me-2" style="font-size: 1.5rem;">👍</span>
                            <div class="flex-grow-1">
                                <span>Positive</span>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 72%;" aria-valuenow="72" aria-valuemin="0" aria-valuemax="100">72%</div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-1">
                            <span class="me-2" style="font-size: 1.5rem;">👎</span>
                            <div class="flex-grow-1">
                                <span>Negative</span>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: 4%;" aria-valuenow="4" aria-valuemin="0" aria-valuemax="100">4%</div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="me-2" style="font-size: 1.5rem;">✋</span>
                            <div class="flex-grow-1">
                                <span>Neutral</span>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 24%;" aria-valuenow="24" aria-valuemin="0" aria-valuemax="100">24%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
             <div class="col-md-4">
                 <div class="card dashboard-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Tempo Médio de Resposta</h5>
                        <p class="display-4 fw-bold m-0" style="color: #434A54;">4:34 <span style="font-size: 1rem; color: #AAB8C2;">min</span></p>
                    </div>
                </div>
            </div>
            <!-- Existing Cards: Conta Corrente and Tickets Abertos - REMOVED -->
        </div>


        <!-- ISSUE Table -->
        <!-- 
        <div class="card dashboard-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="m-0">ISSUE</h5>
                <div>
                    <button class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-filter"></i> Filter</button>
                    <input type="text" class="form-control-sm d-inline-block" style="width: 200px;" value="Jan 01, 2025 - May 13, 2025" readonly> 
                    <a href="consultar_tickets.php" class="btn btn-sm btn-primary">View All <i class="bi bi-arrow-right-circle"></i></a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover m-0">
                        <thead>
                            <tr>
                                <th style="width: 5%;"><input type="checkbox" class="form-check-input"></th>
                                <th>Id Number</th>
                                <th>Summary</th>
                                <th>Assignee</th>
                                <th>Reporter</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Updated</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($issues)): ?>
                                <tr><td colspan="9" class="text-center">Nenhum ticket encontrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($issues as $issue): ?>
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input"></td>
                                        <td><?php echo htmlspecialchars($issue['id']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($issue['summary'], 0, 30)) . (strlen($issue['summary']) > 30 ? '...' : ''); ?></td>
                                        <td>
                                            
                                            <span class="user-avatar-sm me-2" style="background-color: #<?php echo substr(md5($issue['assignee_name']), 0, 6); ?>;">
                                                <?php echo strtoupper(substr($issue['assignee_name'], 0, 1)); ?>
                                            </span>
                                            <?php echo htmlspecialchars($issue['assignee_name']); ?>
                                        </td>
                                        <td>
                                            <span class="user-avatar-sm me-2" style="background-color: #<?php echo substr(md5($issue['reporter_name']), 0, 6); ?>;">
                                                <?php echo strtoupper(substr($issue['reporter_name'], 0, 1)); ?>
                                            </span>
                                            <?php echo htmlspecialchars($issue['reporter_name']); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_class = 'bg-secondary'; // Default
                                            if ($issue['status'] == 'Em Análise' || $issue['status'] == 'Em Resolução') $status_class = 'bg-warning text-dark';
                                            if ($issue['status'] == 'IN PROGRESS') $status_class = 'bg-warning text-dark'; // from screenshot
                                            if ($issue['status'] == 'Concluído' || $issue['status'] == 'Resolvido') $status_class = 'bg-success';
                                            if ($issue['status'] == 'SOLVED') $status_class = 'bg-success'; // from screenshot
                                            if ($issue['status'] == 'DECLINED') $status_class = 'bg-danger'; // from screenshot
                                            if ($issue['status'] == 'Fechado') $status_class = 'bg-danger';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($issue['status']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($issue['created']); ?></td>
                                        <td><?php echo htmlspecialchars($issue['updated']); ?></td>
                                        <td><a href="#" class="text-muted"><i class="bi bi-three-dots-vertical"></i></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        -->

    </div>  

    <!-- Scripts do Bootstrap e JQuery -->
    <script src="script/script.js"></script>    
    <script src="js/dashboard-charts.js"></script> <!-- Added dashboard charts script -->
    <script>
        // Pass PHP data to JavaScript for charts
        const categoriaLabels = <?php echo json_encode($categoria_labels); ?>;
        const categoriaCounts = <?php echo json_encode($categoria_counts); ?>;
        const prioridadeLabels = <?php echo json_encode($prioridade_labels); ?>;
        const prioridadeCounts = <?php echo json_encode($prioridade_counts); ?>;
    </script>
</body>
</html>
