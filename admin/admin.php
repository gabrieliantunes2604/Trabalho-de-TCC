<?php
session_start();
require '../includes/conexao.php';
require_once '../includes/funcoes_config.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

// Captura as datas do filtro de calendário (se enviadas)
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// 1. Resumo de Alunos
$stmtTotal = $conn->query("SELECT COUNT(*) FROM alunos");
$totalAlunos = (int) $stmtTotal->fetchColumn();

// Conta alunos com débitos pendentes (Considera Cursos e E-books)
$sqlDebito = "
    SELECT COUNT(DISTINCT a.id) 
    FROM alunos a 
    WHERE 
        (SELECT COUNT(*) FROM matriculas m WHERE m.aluno_id = a.id AND (m.status_pagamento IS NULL OR LOWER(TRIM(m.status_pagamento)) NOT IN ('pago', 'aprovado', 'confirmado', 'cancelado'))) > 0
        OR
        (SELECT COUNT(*) FROM compras_ebooks ce WHERE ce.aluno_id = a.id AND (ce.status_pagamento IS NULL OR LOWER(TRIM(ce.status_pagamento)) NOT IN ('pago', 'aprovado', 'confirmado', 'cancelado'))) > 0
";
$stmtDebito = $conn->query($sqlDebito);
$alunosEmDebito = (int) $stmtDebito->fetchColumn();
$alunosPagantes = $totalAlunos - $alunosEmDebito;

// 2. Faturamento Total Acumulado (Geral)
$sqlFaturamento = "
    SELECT SUM(valor) AS total FROM (
        SELECT c.preco AS valor 
        FROM matriculas m 
        JOIN cursos c ON m.curso_id = c.id 
        WHERE LOWER(TRIM(m.status_pagamento)) IN ('pago', 'aprovado', 'confirmado')
        
        UNION ALL
        
        SELECT e.preco AS valor 
        FROM compras_ebooks ce 
        JOIN ebooks e ON ce.ebook_id = e.id 
        WHERE LOWER(TRIM(ce.status_pagamento)) IN ('pago', 'aprovado', 'confirmado')
    ) AS faturamento";

$stmtFat = $conn->query($sqlFaturamento);
$totalDinheiro = $stmtFat->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 3. Dados Reais do Gráfico: Faturamento dos últimos 6 meses
$sqlGrafico = "
    SELECT 
        DATE_FORMAT(data_registro, '%Y-%m') AS mes_ano,
        DATE_FORMAT(data_registro, '%b/%y') AS mes_label,
        SUM(valor) AS total_mes
    FROM (
        SELECT c.preco AS valor, COALESCE(m.data_matricula, NOW()) AS data_registro
        FROM matriculas m 
        JOIN cursos c ON m.curso_id = c.id 
        WHERE LOWER(TRIM(m.status_pagamento)) IN ('pago', 'aprovado', 'confirmado')

        UNION ALL

        SELECT e.preco AS valor, COALESCE(ce.data_compra, NOW()) AS data_registro
        FROM compras_ebooks ce 
        JOIN ebooks e ON ce.ebook_id = e.id 
        WHERE LOWER(TRIM(ce.status_pagamento)) IN ('pago', 'aprovado', 'confirmado')
    ) AS consolidados
    WHERE data_registro >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY mes_ano, mes_label
    ORDER BY mes_ano ASC
";
$stmtGrafico = $conn->query($sqlGrafico);
$dadosGrafico = $stmtGrafico->fetchAll(PDO::FETCH_ASSOC);

$graficoLabels = [];
$graficoValores = [];

foreach ($dadosGrafico as $linha) {
    $graficoLabels[] = $linha['mes_label'];
    $graficoValores[] = (float)$linha['total_mes'];
}

// 4. Consulta de Lançamentos (com Filtro de Data)
$whereFiltro = "";
$params = [];

if (!empty($data_inicio) && !empty($data_fim)) {
    $whereFiltro = " WHERE DATE(data_registro) BETWEEN :data_inicio AND :data_fim ";
    $params[':data_inicio'] = $data_inicio;
    $params[':data_fim'] = $data_fim;
}

$sqlLancamentos = "
    SELECT * FROM (
        SELECT 
            m.id AS lancamento_id,
            'curso' AS tipo_item,
            c.titulo AS item_titulo,
            a.nome AS aluno_nome,
            COALESCE(m.forma_pagamento, 'PIX') AS forma_pagamento,
            COALESCE(m.status_pagamento, 'pendente') AS status_pagamento,
            c.preco AS valor,
            COALESCE(m.data_matricula, NOW()) AS data_registro
        FROM matriculas m
        JOIN alunos a ON m.aluno_id = a.id
        JOIN cursos c ON m.curso_id = c.id

        UNION ALL

        SELECT 
            ce.id AS lancamento_id,
            'ebook' AS tipo_item,
            e.titulo AS item_titulo,
            a.nome AS aluno_nome,
            COALESCE(ce.forma_pagamento, 'PIX') AS forma_pagamento,
            COALESCE(ce.status_pagamento, 'pendente') AS status_pagamento,
            e.preco AS valor,
            COALESCE(ce.data_compra, NOW()) AS data_registro
        FROM compras_ebooks ce
        JOIN alunos a ON ce.aluno_id = a.id
        JOIN ebooks e ON ce.ebook_id = e.id
    ) AS unificado
    {$whereFiltro}
    ORDER BY data_registro DESC
";

$stmtLancamentos = $conn->prepare($sqlLancamentos);
$stmtLancamentos->execute($params);
$lancamentos = $stmtLancamentos->fetchAll(PDO::FETCH_ASSOC);

// Totalizador do período filtrado
$faturamentoFiltrado = 0;
foreach ($lancamentos as $item) {
    if (in_array(strtolower(trim($item['status_pagamento'])), ['pago', 'aprovado', 'confirmado'], true)) {
        $faturamentoFiltrado += $item['valor'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Engenharia Academy ERP</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js & html2pdf -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --sidebar-bg: #0b1437;
            --sidebar-width: 250px;
            --primary-purple: #4318ff;
            --text-gray: #a3aed0;
            --badge-green: #05cd99;
            --badge-orange: #ffb800;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7fe;
            color: #2b3674;
            margin: 0;
            overflow-x: hidden;
        }

        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--sidebar-bg);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 25px 15px;
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 0 10px 25px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand h4 {
            font-weight: 700;
            font-size: 1.2rem;
            margin: 0;
            color: #fff;
        }

        .sidebar-brand span.erp {
            color: var(--primary-purple);
            font-weight: 700;
            display: block;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0 0 0;
        }

        .sidebar-menu li { margin-bottom: 6px; }

        .sidebar-menu a {
            color: var(--text-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu li.active a {
            background-color: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-weight: 600;
            border-left: 4px solid var(--primary-purple);
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 15px;
        }

        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .link-ver-site { color: #ffb800 !important; }
        .link-sair { color: #ee5d50 !important; }

        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px 40px;
        }

        .card-custom {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            background: #fff;
            padding: 20px;
            margin-bottom: 24px;
        }

        .card-blue {
            background: linear-gradient(135deg, #4318FF 0%, #3911DB 100%);
            color: white;
        }

        .btn-primary-custom {
            background-color: #4318FF;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            padding: 8px 16px;
            color: #fff;
        }

        .btn-primary-custom:hover {
            background-color: #3311DB;
            color: #fff;
        }

        .card-tabela {
            background: #ffffff;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            border: none;
        }

        .table thead th {
            color: var(--text-gray);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 15px;
        }

        .table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            font-size: 0.9rem;
            border-bottom: 1px solid #f4f7fe;
        }

        .badge-pago {
            background-color: var(--badge-green);
            color: #fff;
            border-radius: 8px;
            padding: 6px 14px;
            font-weight: 700;
            font-size: 0.8rem;
            display: inline-block;
        }

        .btn-aprovar-rapido {
            background-color: var(--badge-orange);
            color: #fff;
            border-radius: 8px;
            padding: 6px 14px;
            font-weight: 700;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-block;
            transition: opacity 0.2s ease;
        }

        .btn-aprovar-rapido:hover {
            opacity: 0.85;
            color: #fff;
        }

        .badge-tipo {
            background: #e9ecef;
            color: #495057;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        footer,
        .footer {
            margin-left: 250px;
            width: calc(100% - 250px);
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div>
                <div class="sidebar-brand">
                    <h4>Engenharia Academy</h4>
                    <span class="erp">PAINEL ERP</span>
                </div>
                <ul class="sidebar-menu">
                    <li class="active"><a href="admin.php">📊 Dashboard</a></li>
                    <li><a href="admin_cursos.php">📚 Cursos</a></li>
                    <li><a href="admin_ebooks.php">📖 E-books</a></li>
                    <li><a href="admin_alunos.php">👥 Alunos</a></li>
                    <li><a href="admin_duvidas.php">💬 Fórum de Dúvidas</a></li>
                    <li><a href="admin_configuracoes.php">⚙️ Configurações</a></li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="../index.php" target="_blank" class="link-ver-site"><i class="bi bi-box-arrow-up-right"></i> Ver Site Público</a>
                <a href="../auth/logout.php" class="link-sair"><i class="bi bi-box-arrow-left"></i> Encerrar Sessão</a>
            </div>
        </aside>

        <!-- CONTEÚDO PRINCIPAL -->
        <main class="main-content">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'aprovado'): ?>
                <div class="alert alert-success alert-dismissible fade show fw-semibold" role="alert">
                    ✅ Matrícula/Compra aprovada com sucesso! O e-mail de confirmação foi disparado.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Cabeçalho Topo -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Painel Geral</h2>
                    <p class="text-muted small m-0">Acompanhe as matrículas e movimentações financeiras recentes.</p>
                </div>
            </div>

            <!-- PRIMEIRA LINHA: GRÁFICO E CARDS -->
            <div class="row mb-4">
                <!-- ÁREA DO GRÁFICO REAL -->
                <div class="col-lg-8">
                    <div class="card-custom h-100">
                        <h5 class="fw-bold mb-1">Fluxo de Matrículas Mensal</h5>
                        <p class="text-muted small mb-4">Performance financeira mensal consolidada (Dados Reais).</p>
                        <canvas id="meuGrafico" height="100"></canvas>
                    </div>
                </div>

                <!-- CARDS DE SALDO E RESUMO -->
                <div class="col-lg-4">
                    <div class="card-custom card-blue mb-4">
                        <p class="mb-1 opacity-75">Faturamento Total Real</p>
                        <h2 class="fw-bold mb-3">R$ <?php echo number_format($totalDinheiro, 2, ',', '.'); ?></h2>
                        <p class="mb-0 small">Apenas pagamentos consolidados</p>
                    </div>

                    <div class="card-custom">
                        <h6 class="fw-bold text-dark border-bottom pb-2">Resumo de Alunos</h6>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Total Matriculados</span>
                            <span class="fw-bold text-dark"><?php echo $totalAlunos; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-success small fw-semibold">Alunos Pagantes</span>
                            <span class="fw-bold text-success"><?php echo $alunosPagantes; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-danger small fw-semibold">Alunos em Débito</span>
                            <span class="fw-bold text-danger"><?php echo $alunosEmDebito; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABELA DE LANÇAMENTOS -->
            <div class="card-tabela" id="area-relatorio">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                    <div>
                        <h5 class="fw-bold m-0">Últimos Lançamentos (Matrículas & E-books)</h5>
                        <?php if (!empty($data_inicio) && !empty($data_fim)): ?>
                            <small class="text-primary fw-semibold">
                                Período Filtrado: <?php echo date('d/m/Y', strtotime($data_inicio)); ?> até <?php echo date('d/m/Y', strtotime($data_fim)); ?>
                                (Total Confirmado: R$ <?php echo number_format($faturamentoFiltrado, 2, ',', '.'); ?>)
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>DATA</th>
                                <th>DESCRIÇÃO / ALUNO</th>
                                <th>TIPO DA COMPRA</th>
                                <th>MEIO DE PAGAMENTO</th>
                                <th>STATUS DA COMPRA</th>
                                <th class="text-end">VALOR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($lancamentos)): ?>
                                <?php foreach ($lancamentos as $item): 
                                    $isPago = in_array(strtolower(trim($item['status_pagamento'])), ['pago', 'aprovado', 'confirmado'], true);
                                    $dataFmt = date('d M, Y - H:i', strtotime($item['data_registro']));
                                ?>
                                    <tr>
                                        <td class="text-secondary fw-medium"><?php echo $dataFmt; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['item_titulo']); ?></div>
                                            <div class="text-muted small">Aluno: <?php echo htmlspecialchars($item['aluno_nome']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge-tipo">
                                                <?php echo ($item['tipo_item'] === 'ebook') ? '📖 E-book' : '📚 Curso'; ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-uppercase text-secondary"><?php echo htmlspecialchars($item['forma_pagamento']); ?></td>
                                        <td>
                                            <?php if ($isPago): ?>
                                                <span class="badge-pago">Pago</span>
                                            <?php else: ?>
                                                <a href="aprovar_matricula.php?id=<?php echo $item['lancamento_id']; ?>&tipo=<?php echo $item['tipo_item']; ?>&ref=admin.php" 
                                                   class="btn-aprovar-rapido"
                                                   onclick="return confirm('Aprovar este pagamento agora?')">
                                                    ⏳ Aprovar
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            R$ <?php echo number_format($item['valor'], 2, ',', '.'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Nenhum lançamento registrado no período selecionado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FILTRO DE PERÍODO E BOTÃO DE EXPORTAÇÃO (ABAIXO DAS MATRÍCULAS) -->
            <div class="card-custom mt-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-funnel"></i> Filtrar Lançamentos e Exportar Relatório por Período</h6>
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Data Inicial</label>
                        <input type="date" name="data_inicio" class="form-control" value="<?php echo htmlspecialchars($data_inicio); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Data Final</label>
                        <input type="date" name="data_fim" class="form-control" value="<?php echo htmlspecialchars($data_fim); ?>" required>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary-custom w-100"><i class="bi bi-search"></i> Buscar</button>
                        <a href="admin.php" class="btn btn-outline-secondary">Limpar</a>
                        <button type="button" onclick="gerarPDF()" class="btn btn-success text-nowrap"><i class="bi bi-file-earmark-pdf"></i> Exportar PDF</button>
                    </div>
                </form>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Dados Reais do Banco vindos do PHP
        const labelsGrafico = <?php echo json_encode($graficoLabels); ?>;
        const valoresGrafico = <?php echo json_encode($graficoValores); ?>;

        // Renderização do Gráfico Real
        const ctx = document.getElementById('meuGrafico').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labelsGrafico.length > 0 ? labelsGrafico : ['Sem Dados'],
                datasets: [{
                    label: 'Faturamento R$',
                    data: valoresGrafico.length > 0 ? valoresGrafico : [0],
                    backgroundColor: '#4318FF',
                    borderRadius: 6,
                    barThickness: 28
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Faturamento: R$ ' + context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '#f0f0f0' },
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value;
                            }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });

        // Exportação de PDF com suporte à tabela e dados filtrados
        function gerarPDF() {
            const elemento = document.getElementById('area-relatorio');
            const dataIni = "<?php echo $data_inicio; ?>";
            const dataFim = "<?php echo $data_fim; ?>";
            
            let nomeArquivo = 'relatorio_lancamentos.pdf';
            if(dataIni && dataFim) {
                nomeArquivo = `relatorio_financeiro_${dataIni}_ate_${dataFim}.pdf`;
            }

            html2pdf().set({
                margin: 10, 
                filename: nomeArquivo,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
            }).from(elemento).save();
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>

</html>