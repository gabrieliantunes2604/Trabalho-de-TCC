<?php
session_start();
require '../includes/conexao.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

$mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');
if ($mes < 1 || $mes > 12) {
    $mes = (int) date('n');
}
if ($ano < 2000 || $ano > 2100) {
    $ano = (int) date('Y');
}

$meses_pt = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];

$stmtC = $conn->prepare("
    SELECT m.data_matricula, a.nome AS aluno_nome, c.titulo, c.preco, m.forma_pagamento, m.status_pagamento
    FROM matriculas m
    JOIN alunos a ON a.id = m.aluno_id
    JOIN cursos c ON c.id = m.curso_id
    WHERE LOWER(IFNULL(m.status_pagamento, '')) IN ('pago', 'aprovado', 'confirmado')
      AND MONTH(m.data_matricula) = :mes AND YEAR(m.data_matricula) = :ano
    ORDER BY m.data_matricula
");
$stmtC->execute([':mes' => $mes, ':ano' => $ano]);
$cursos = $stmtC->fetchAll(PDO::FETCH_ASSOC);

$stmtE = $conn->prepare("
    SELECT COALESCE(ce.pago_em, ce.data_compra) AS data_ref, a.nome AS aluno_nome, e.titulo, e.preco, ce.forma_pagamento, ce.status_pagamento
    FROM compras_ebooks ce
    JOIN alunos a ON a.id = ce.aluno_id
    JOIN ebooks e ON e.id = ce.ebook_id
    WHERE LOWER(IFNULL(ce.status_pagamento, '')) IN ('pago', 'aprovado', 'confirmado')
      AND MONTH(COALESCE(ce.pago_em, ce.data_compra)) = :mes AND YEAR(COALESCE(ce.pago_em, ce.data_compra)) = :ano
    ORDER BY data_ref
");
$stmtE->execute([':mes' => $mes, ':ano' => $ano]);
$ebooks = $stmtE->fetchAll(PDO::FETCH_ASSOC);

$totalC = 0;
foreach ($cursos as $r) {
    $totalC += (float) $r['preco'];
}
$totalE = 0;
foreach ($ebooks as $r) {
    $totalE += (float) $r['preco'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório Financeiro Mensal</title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #fff;
            color: #2b3674;
        }

        .relatorio-header {
            border-bottom: 2px solid #111c44;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .card-resumo {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            background-color: #ffffff;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body class="p-4">
    <div class="no-print d-flex justify-content-between align-items-center mb-4">
        <a href="admin_configuracoes.php" class="btn btn-outline-secondary">← Voltar para Configurações</a>
        <button onclick="window.print()" class="btn btn-primary fw-bold">🖨️ Imprimir agora</button>
    </div>

    <div class="relatorio-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-dark mb-0">Engenharia Academy</h2>
            <p class="text-muted small mb-0">Relatório financeiro geral — <?php echo $meses_pt[$mes] . ' / ' . $ano; ?>
            </p>
        </div>
        <div class="text-end text-muted small">
            <div><strong>Emissão:</strong> <?php echo date('d/m/Y H:i'); ?></div>
            <div>Somente pagamentos confirmados</div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card-resumo">
                <div class="text-muted small">Cursos</div><strong class="fs-5">R$
                    <?php echo number_format($totalC, 2, ',', '.'); ?></strong>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-resumo">
                <div class="text-muted small">E-books</div><strong class="fs-5">R$
                    <?php echo number_format($totalE, 2, ',', '.'); ?></strong>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-resumo">
                <div class="text-muted small">Total do mês</div><strong class="fs-5 text-success">R$
                    <?php echo number_format($totalC + $totalE, 2, ',', '.'); ?></strong>
            </div>
        </div>
    </div>

    <h5 class="fw-bold mb-3">Cursos</h5>
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Data</th>
                <th>Aluno</th>
                <th>Curso</th>
                <th>Forma</th>
                <th class="text-end">Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$cursos): ?>
                <tr>
                    <td colspan="5" class="text-muted">Nenhuma venda de curso confirmada neste mês.</td>
                </tr>
            <?php else:
                foreach ($cursos as $r): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($r['data_matricula'])); ?></td>
                        <td><?php echo htmlspecialchars($r['aluno_nome']); ?></td>
                        <td><?php echo htmlspecialchars($r['titulo']); ?></td>
                        <td class="text-uppercase"><?php echo htmlspecialchars($r['forma_pagamento']); ?></td>
                        <td class="text-end">R$ <?php echo number_format((float) $r['preco'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
        </tbody>
    </table>

    <h5 class="fw-bold mb-3 mt-4">E-books</h5>
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Data</th>
                <th>Aluno</th>
                <th>E-book</th>
                <th>Forma</th>
                <th class="text-end">Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$ebooks): ?>
                <tr>
                    <td colspan="5" class="text-muted">Nenhuma venda de e-book confirmada neste mês.</td>
                </tr>
            <?php else:
                foreach ($ebooks as $r): ?>
                    <tr>
                        <td><?php echo !empty($r['data_ref']) ? date('d/m/Y', strtotime($r['data_ref'])) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($r['aluno_nome']); ?></td>
                        <td><?php echo htmlspecialchars($r['titulo']); ?></td>
                        <td class="text-uppercase"><?php echo htmlspecialchars($r['forma_pagamento']); ?></td>
                        <td class="text-end">R$ <?php echo number_format((float) $r['preco'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
        </tbody>
    </table>
</body>

</html>