<?php
session_start();
require '../includes/conexao.php';

// Busca a lista de alunos e conta débitos pelo status (PIX e cartão aguardam o admin)
$sql = "
    SELECT 
        a.id, 
        a.nome, 
        a.email, 
        a.telefone,
        (
            (SELECT COUNT(*) FROM matriculas m WHERE m.aluno_id = a.id AND LOWER(IFNULL(m.status_pagamento, '')) NOT IN ('pago', 'aprovado', 'confirmado', 'cancelado')) +
            (SELECT COUNT(*) FROM compras_ebooks ce WHERE ce.aluno_id = a.id AND LOWER(IFNULL(ce.status_pagamento, '')) NOT IN ('pago', 'aprovado', 'confirmado', 'cancelado'))
        ) AS total_pendencias
    FROM alunos a
    ORDER BY a.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cálculos para o Resumo de Alunos
$total_matriculados = count($alunos);
$alunos_pagantes = 0;
$alunos_em_debito = 0;

foreach ($alunos as $aluno) {
    if ($aluno['total_pendencias'] > 0) {
        $alunos_em_debito++;
    } else {
        $alunos_pagantes++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Geral de Alunos - Engenharia Academy</title>
    <link rel="shortcut icon" href="../auth/uploads/logo.ico?v=1" type="image/x-icon">
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

        .badge-em-dia {
            background-color: #05CD99;
            color: white;
            padding: 5px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .badge-debito {
            background-color: #f59e0b;
            color: white;
            padding: 5px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .card-resumo {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            background-color: #ffffff;
        }

        .relatorio-footer {
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
            margin-top: 40px;
            font-size: 0.85rem;
            color: #6c757d;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background-color: #fff !important;
            }

            .card-resumo {
                border: 1px solid #ccc !important;
            }
        }
    </style>
</head>

<body class="p-4">

    <!-- Botões de Ação (Escondidos na impressão) -->
    <div class="no-print d-flex justify-content-between align-items-center mb-4">
        <a href="admin_alunos.php" class="btn btn-outline-secondary">← Voltar para Gestão</a>
        <button onclick="window.print()" class="btn btn-primary fw-bold">🖨️ Imprimir agora</button>
    </div>

    <!-- Cabeçalho Oficial -->
    <div class="relatorio-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-dark mb-0">Engenharia Academy</h2>
            <p class="text-muted small mb-0">Relatório Gerencial de Alunos e Status Financeiro</p>
        </div>
        <div class="text-end text-muted small">
            <div><strong>Emissão:</strong> <?php echo date('d/m/Y H:i'); ?></div>
            <div><strong>Sistema:</strong> Painel Administrativo</div>
        </div>
    </div>

    <!-- Resumo de Alunos -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-5">
            <div class="card-resumo shadow-sm">
                <h5 class="fw-bold text-dark mb-3">Resumo de Alunos</h5>
                <hr class="my-2">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-secondary">Total Matriculados</span>
                    <strong class="fs-5 text-dark"><?php echo $total_matriculados; ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-success fw-semibold">Alunos Pagantes</span>
                    <strong class="fs-5 text-success"><?php echo $alunos_pagantes; ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-danger fw-semibold">Alunos em Débito</span>
                    <strong class="fs-5 text-danger"><?php echo $alunos_em_debito; ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Listagem Principal de Alunos -->
    <h5 class="fw-bold mb-3">Gestão de Alunos</h5>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th width="60">ID</th>
                    <th>NOME DO ALUNO</th>
                    <th>E-MAIL</th>
                    <th>TELEFONE</th>
                    <th class="text-center">STATUS FINANCEIRO</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($alunos)): ?>
                    <?php foreach ($alunos as $aluno): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo $aluno['id']; ?></td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($aluno['nome']); ?></td>
                            <td><?php echo htmlspecialchars($aluno['email']); ?></td>
                            <td><?php echo htmlspecialchars($aluno['telefone'] ?? '---'); ?></td>
                            <td class="text-center">
                                <?php if ($aluno['total_pendencias'] > 0): ?>
                                    <span class="badge-debito">Débito Pendente (<?php echo $aluno['total_pendencias']; ?>)</span>
                                <?php else: ?>
                                    <span class="badge-em-dia">Em dia</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">Nenhum aluno cadastrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Rodapé Institucional -->
    <div class="relatorio-footer d-flex justify-content-between align-items-center">
        <div>Engenharia Academy &copy; <?php echo date('Y'); ?> - Todos os direitos reservados.</div>
        <div>Documento gerado para controle interno de pagamentos.</div>
    </div>

</body>

</html>