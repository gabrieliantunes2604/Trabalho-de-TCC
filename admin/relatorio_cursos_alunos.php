<?php
session_start();
require '../includes/conexao.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

$sql = "
    SELECT c.id, c.titulo, c.preco,
           a.id AS aluno_id, a.nome AS aluno_nome, a.email AS aluno_email,
           m.status_pagamento, m.forma_pagamento, m.data_matricula
    FROM cursos c
    LEFT JOIN matriculas m ON m.curso_id = c.id
    LEFT JOIN alunos a ON a.id = m.aluno_id
    ORDER BY c.titulo, a.nome
";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$porCurso = [];
foreach ($rows as $r) {
    $cid = $r['id'];
    if (!isset($porCurso[$cid])) {
        $porCurso[$cid] = [
            'titulo' => $r['titulo'],
            'preco' => $r['preco'],
            'alunos' => []
        ];
    }
    if (!empty($r['aluno_id'])) {
        $porCurso[$cid]['alunos'][] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Cursos e Alunos</title>
    <link rel="shortcut icon" href="../includes/uploads/logo.ico?v=1" type="image/x-icon">
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

        .curso-bloco {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
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
            <p class="text-muted small mb-0">Relatório de cursos e alunos matriculados</p>
        </div>
        <div class="text-end text-muted small">
            <div><strong>Emissão:</strong> <?php echo date('d/m/Y H:i'); ?></div>
        </div>
    </div>

    <?php if (!$porCurso): ?>
        <p class="text-muted">Nenhum curso cadastrado.</p>
    <?php else:
        foreach ($porCurso as $curso): ?>
            <div class="curso-bloco">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($curso['titulo']); ?></h5>
                        <span class="text-muted small"><?php echo count($curso['alunos']); ?> aluno(s) matriculado(s)</span>
                    </div>
                    <strong>R$ <?php echo number_format((float) $curso['preco'], 2, ',', '.'); ?></strong>
                </div>
                <?php if (empty($curso['alunos'])): ?>
                    <p class="text-muted small mb-0">Nenhum aluno matriculado neste curso.</p>
                <?php else: ?>
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>E-mail</th>
                                <th>Data</th>
                                <th>Forma</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($curso['alunos'] as $al): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($al['aluno_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($al['aluno_email']); ?></td>
                                    <td><?php echo !empty($al['data_matricula']) ? date('d/m/Y', strtotime($al['data_matricula'])) : '—'; ?>
                                    </td>
                                    <td class="text-uppercase"><?php echo htmlspecialchars($al['forma_pagamento'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($al['status_pagamento'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
</body>

</html>