<?php
session_start();
require '../includes/conexao.php';

if (!isset($_GET['id'])) {
    die("ID do aluno não informado.");
}

$aluno_id = (int)$_GET['id'];

// 1. Dados do Aluno
$stmt = $conn->prepare("SELECT * FROM alunos WHERE id = :id");
$stmt->execute([':id' => $aluno_id]);
$aluno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aluno) {
    die("Aluno não encontrado.");
}

// 2. Cursos do Aluno
$sqlCursos = "
    SELECT c.titulo, c.preco, m.data_matricula, m.forma_pagamento, m.status_pagamento 
    FROM matriculas m
    JOIN cursos c ON m.curso_id = c.id
    WHERE m.aluno_id = :aluno_id
    ORDER BY m.data_matricula DESC
";
$stmtCursos = $conn->prepare($sqlCursos);
$stmtCursos->execute([':aluno_id' => $aluno_id]);
$cursos = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);

// 3. E-books do Aluno
$sqlEbooks = "
    SELECT e.titulo, ce.data_compra, ce.forma_pagamento, ce.status_pagamento 
    FROM compras_ebooks ce
    JOIN ebooks e ON ce.ebook_id = e.id
    WHERE ce.aluno_id = :aluno_id
    ORDER BY ce.data_compra DESC
";
$stmtEbooks = $conn->prepare($sqlEbooks);
$stmtEbooks->execute([':aluno_id' => $aluno_id]);
$ebooks = $stmtEbooks->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extrato do Aluno - <?php echo htmlspecialchars($aluno['nome']); ?></title>
    <link rel="shortcut icon" href="../uploads/logo.ico?v=1" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #fff; color: #2b3674; }
        .relatorio-header { border-bottom: 2px solid #111c44; padding-bottom: 15px; margin-bottom: 25px; }
        .card-info { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 25px; border: 1px solid #e9ecef; }
        .relatorio-footer { border-top: 1px solid #e9ecef; padding-top: 15px; margin-top: 40px; font-size: 0.85rem; color: #6c757d; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="p-4">

    <!-- Botões -->
    <div class="no-print d-flex justify-content-between align-items-center mb-4">
        <a href="admin_alunos.php" class="btn btn-outline-secondary">← Voltar para Gestão</a>
        <button onclick="window.print()" class="btn btn-primary fw-bold">🖨️ Imprimir Extrato</button>
    </div>

    <!-- Header -->
    <div class="relatorio-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-dark mb-0">Engenharia Academy</h2>
            <p class="text-muted small mb-0">Extrato Financeiro e de Matriculas do Aluno</p>
        </div>
        <div class="text-end text-muted small">
            <div><strong>Data:</strong> <?php echo date('d/m/Y H:i'); ?></div>
        </div>
    </div>

    <!-- Dados do Aluno -->
    <div class="card-info">
        <h5 class="fw-bold text-dark mb-3">Ficha do Aluno #<?php echo $aluno['id']; ?></h5>
        <div class="row">
            <div class="col-md-4"><strong>Nome:</strong> <?php echo htmlspecialchars($aluno['nome']); ?></div>
            <div class="col-md-4"><strong>E-mail:</strong> <?php echo htmlspecialchars($aluno['email']); ?></div>
            <div class="col-md-4"><strong>Telefone:</strong> <?php echo htmlspecialchars($aluno['telefone'] ?? 'Não informado'); ?></div>
        </div>
    </div>

    <!-- Cursos Matriculados -->
    <h5 class="fw-bold mb-3">Cursos Matriculados</h5>
    <table class="table table-bordered align-middle mb-4">
        <thead class="table-light">
            <tr>
                <th>CURSO</th>
                <th>DATA</th>
                <th>FORMA PGTO</th>
                <th class="text-center">STATUS</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($cursos)): ?>
                <?php foreach ($cursos as $c): 
                    $is_pago_c = in_array(strtolower($c['status_pagamento'] ?? ''), ['pago', 'aprovado', 'confirmado'], true);
                ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars($c['titulo']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($c['data_matricula'])); ?></td>
                        <td><?php echo ucfirst($c['forma_pagamento'] ?? 'N/A'); ?></td>
                        <td class="text-center">
                            <?php if ($is_pago_c): ?>
                                <span class="badge bg-success">Pago</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pendente</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-muted text-center">Nenhum curso registrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- E-books Adquiridos -->
    <h5 class="fw-bold mb-3">E-books Adquiridos</h5>
    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th>E-BOOK</th>
                <th>DATA</th>
                <th>FORMA PGTO</th>
                <th class="text-center">STATUS</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($ebooks)): ?>
                <?php foreach ($ebooks as $e): 
                    $is_pago_e = in_array(strtolower($e['status_pagamento'] ?? ''), ['pago', 'aprovado', 'confirmado'], true);
                ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars($e['titulo']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($e['data_compra'])); ?></td>
                        <td><?php echo ucfirst($e['forma_pagamento'] ?? 'N/A'); ?></td>
                        <td class="text-center">
                            <?php if ($is_pago_e): ?>
                                <span class="badge bg-success">Pago</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pendente</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-muted text-center">Nenhum e-book registrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Footer -->
    <div class="relatorio-footer d-flex justify-content-between align-items-center">
        <div>Engenharia Academy &copy; <?php echo date('Y'); ?></div>
        <div>Comprovante individual de pendências.</div>
    </div>

</body>
</html>